<?php
/**
 * One-time import of the old lattice.bizorca.com MySQL database (a mysqldump
 * of its 11 tables) into the shared tools database.
 *
 *   php lattice/bin/import-mysqldump.php /path/to/lattice.sql [--dry-run]
 *
 * How:
 *   1. The dump is loaded into staging tables named ltimp_<table> inside the
 *      target database (the Cloudways database user cannot create a scratch
 *      database, so staging lives beside the real tables and is always dropped).
 *   2. One transaction copies staging into lt_ tables:
 *      - users are matched into the shared users table by email; an existing
 *        tools account is reused, otherwise one is created carrying the old
 *        bcrypt hash, so people sign in with their existing Lattice password.
 *      - Lattice's users.is_admin becomes an lt_admins row (course admin),
 *        never the site-wide users.is_admin.
 *      - content (courses through answers) keeps its ids. If lt_courses is
 *        empty it is copied; if the content migration already seeded it, every
 *        staged content id must already exist with the same text, or the import
 *        stops rather than attach history to the wrong question.
 *      - enrollments, attempts and responses keep their ids, user ids remapped.
 *   3. Staging tables are dropped, whatever happened.
 *
 * Refuses to run if any lt_ table that holds people's data has rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/lattice.sql [--dry-run]\n");
    exit(1);
}

define('LT_ROOT', dirname(__DIR__));
require LT_ROOT . '/includes/config.php';

$db = tl_db();

$tables  = ['users', 'courses', 'units', 'challenges', 'lessons', 'question_slots', 'question_variants',
            'answers', 'enrollments', 'challenge_attempts', 'question_responses'];
$content = ['courses', 'units', 'challenges', 'lessons', 'question_slots', 'question_variants', 'answers'];
$people  = ['enrollments', 'challenge_attempts', 'question_responses', 'admins'];

foreach ($people as $t) {
    if ((int)$db->query("SELECT COUNT(*) FROM lt_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "lt_{$t} already has rows. This import is for an install with no Lattice users yet; stopping.\n");
        exit(1);
    }
}

function drop_staging(PDO $db, array $tables): void {
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $db->exec("DROP TABLE IF EXISTS `ltimp_{$t}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ── 1. stage ───────────────────────────────────────────────────────────────
$sql = (string)file_get_contents($src);
// Rename every reference to a dumped table, including REFERENCES clauses, so
// the staged copy is self-contained and never touches a real table.
$sql = preg_replace('/`(' . implode('|', $tables) . ')`/', '`ltimp_$1`', $sql);

drop_staging($db, $tables);
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
    $body = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
    // mysqldump's session and GTID housekeeping is not wanted here.
    if ($body === '' || preg_match('/^(LOCK TABLES|UNLOCK TABLES|SET @@GLOBAL|SET @@SESSION\.SQL_LOG_BIN|SET @MYSQLDUMP)/i', $body)) {
        continue;
    }
    $db->exec($body);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

try {
    foreach ($tables as $t) {
        $db->query("SELECT 1 FROM `ltimp_{$t}` LIMIT 1");   // all eleven must have been in the dump
    }

    // ── 2. copy ────────────────────────────────────────────────────────────
    $db->beginTransaction();

    $userMap = [];
    $find    = $db->prepare('SELECT id FROM users WHERE email = ?');
    foreach ($db->query('SELECT * FROM ltimp_users ORDER BY id')->fetchAll() as $u) {
        $email = strtolower(trim($u['email']));
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            echo "  user {$u['id']} -> existing tools account {$id}\n";
        } else {
            $db->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, $u['password_hash'], $u['name'], $u['created_at'] ?? date('Y-m-d H:i:s')]);
            $id = $db->lastInsertId();
            echo "  user {$u['id']} -> new tools account {$id}\n";
        }
        $userMap[(int)$u['id']] = (int)$id;
        if ((int)$u['is_admin'] === 1) {
            $db->prepare('INSERT IGNORE INTO lt_admins (user_id) VALUES (?)')->execute([(int)$id]);
            echo "    Lattice course admin (lt_admins), not a site admin\n";
        }
    }

    $copy = function (string $t, ?callable $map = null) use ($db): int {
        $rows = $db->query("SELECT * FROM ltimp_{$t} ORDER BY id")->fetchAll();
        if (!$rows) return 0;
        $cols = array_keys($rows[0]);
        $ins  = $db->prepare(sprintf('INSERT INTO lt_%s (%s) VALUES (%s)', $t,
            implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
            implode(', ', array_fill(0, count($cols), '?'))));
        foreach ($rows as $r) {
            $ins->execute(array_values($map ? $map($r) : $r));
        }
        return count($rows);
    };

    if ((int)$db->query('SELECT COUNT(*) FROM lt_courses')->fetchColumn() === 0) {
        foreach ($content as $t) {
            echo "  lt_{$t}: " . $copy($t) . " (content copied)\n";
        }
    } else {
        // Seeded by migrations/002: prove the staged content is the same content.
        $check = [
            'courses' => 'title', 'units' => 'title', 'challenges' => 'title', 'lessons' => 'title',
            'question_slots' => 'lesson_id', 'question_variants' => 'question_text', 'answers' => 'answer_text',
        ];
        foreach ($check as $t => $col) {
            $missing = (int)$db->query(
                "SELECT COUNT(*) FROM ltimp_{$t} s LEFT JOIN lt_{$t} l ON l.id = s.id AND CAST(l.{$col} AS BINARY) <=> CAST(s.{$col} AS BINARY)
                  WHERE l.id IS NULL"
            )->fetchColumn();
            if ($missing > 0) {
                throw new RuntimeException("lt_{$t}: {$missing} staged row(s) do not match the seeded content by id and {$col}");
            }
            echo "  lt_{$t}: already seeded, matches the dump\n";
        }
    }

    $remapUser = function (array $r) use ($userMap): array {
        $r['user_id'] = $userMap[(int)$r['user_id']] ?? throw new RuntimeException("unknown user {$r['user_id']}");
        return $r;
    };
    echo '  lt_enrollments: '        . $copy('enrollments', $remapUser) . "\n";
    echo '  lt_challenge_attempts: ' . $copy('challenge_attempts', $remapUser) . "\n";
    echo '  lt_question_responses: ' . $copy('question_responses') . "\n";

    if ($dry) {
        $db->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $db->commit();
        echo "Imported.\n";
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'Rolled back: ' . $e->getMessage() . "\n");
    $failed = true;
} finally {
    drop_staging($db, $tables);
}
exit(empty($failed) ? 0 : 1);
