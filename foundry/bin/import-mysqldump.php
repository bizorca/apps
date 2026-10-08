<?php
/**
 * One-time import of the old foundry.bizorca.com data (its consulting_* tables,
 * which lived inside the login.bizorca.com database) into the shared tools
 * database.
 *
 *   php foundry/bin/import-mysqldump.php /path/to/foundry.sql [--dry-run]
 *
 * The dump must hold the eleven consulting_* tables (mysqldump them by name;
 * the rest of that database belongs to login.bizorca.com).
 *
 * How:
 *   1. The dump is loaded into staging tables named fdimp_<table> inside the
 *      target database (the Cloudways database user cannot create a scratch
 *      database), and they are dropped again whatever happens.
 *   2. One transaction copies staging into fd_ tables:
 *      - consulting_users are matched into the shared users table by email.
 *        They were SSO mirrors with no password, so a new account gets an
 *        unusable one and its owner uses Forgot your password.
 *      - consulting_users.is_admin mirrored login.bizorca.com's admin flag. It
 *        becomes an fd_admins row, never the site-wide users.is_admin.
 *      - everything else keeps its ids; user columns are remapped.
 *      - an application whose dropdown answers are not real choices (a bot
 *        posting "Select...", like the only live row) is skipped, unless an
 *        engagement was made from it.
 *   3. Staging tables are dropped.
 *
 * Refuses to run if any fd_ table already has rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/foundry.sql [--dry-run]\n");
    exit(1);
}

define('FD_ROOT', dirname(__DIR__));
require FD_ROOT . '/includes/config.php';

use Bizorca\Consulting\Models\Application;

$db = tl_db();

// Parents before children, so foreign keys hold at every insert.
$tables = ['applications', 'engagements', 'onboarding_steps', 'scope_items', 'boards', 'columns',
           'cards', 'card_steps', 'card_comments', 'change_requests'];
$staged = array_merge(['users'], $tables);

foreach (array_merge($tables, ['admins']) as $t) {
    if ((int) $db->query("SELECT COUNT(*) FROM fd_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "fd_{$t} already has rows. This import is for an empty install; stopping.\n");
        exit(1);
    }
}

function drop_staging(PDO $db, array $staged): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($staged as $t) {
        $db->exec("DROP TABLE IF EXISTS `fdimp_{$t}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ── 1. stage ───────────────────────────────────────────────────────────────
$sql = (string) file_get_contents($src);
// Rename every reference, including REFERENCES clauses, so the staged copy is
// self-contained and never touches a real table.
$sql = preg_replace('/`consulting_(' . implode('|', $staged) . ')`/', '`fdimp_$1`', $sql);

drop_staging($db, $staged);
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
    $body = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
    if ($body === '' || preg_match('/^(LOCK TABLES|UNLOCK TABLES|SET @@GLOBAL|SET @@SESSION\.SQL_LOG_BIN|SET @MYSQLDUMP)/i', $body)) {
        continue;
    }
    $db->exec($body);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$exit = 0;
try {
    foreach ($staged as $t) {
        $db->query("SELECT 1 FROM `fdimp_{$t}` LIMIT 1");   // every table must have been in the dump
    }

    // ── 2. copy ────────────────────────────────────────────────────────────
    $db->beginTransaction();

    $userMap   = [];
    $noPass    = 0;
    $find      = $db->prepare('SELECT id FROM users WHERE email = ?');
    foreach ($db->query('SELECT * FROM fdimp_users ORDER BY id')->fetchAll() as $u) {
        $email = strtolower(trim((string) $u['email']));
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            echo "  user {$u['id']} -> existing tools account {$id}\n";
        } else {
            $name = trim($u['first_name'] . ' ' . $u['last_name']);
            $db->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                          $name !== '' ? $name : ucfirst((string) strtok($email, '@')), $u['created_at']]);
            $id = $db->lastInsertId();
            $noPass++;
            echo "  user {$u['id']} -> new tools account {$id} (no password yet: Forgot your password)\n";
        }
        $userMap[(int) $u['id']] = (int) $id;
        if ((int) $u['is_admin'] === 1) {
            $db->prepare('INSERT IGNORE INTO fd_admins (user_id) VALUES (?)')->execute([(int) $id]);
            echo "    Foundry admin (fd_admins), not a site admin\n";
        }
    }

    $mapUser = function (int|string|null $v) use ($userMap): ?int {
        if ($v === null) {
            return null;
        }
        return $userMap[(int) $v] ?? throw new RuntimeException("unknown consulting_users id {$v}");
    };
    $userCols = ['user_id', 'reviewed_by', 'client_id', 'completed_by', 'created_by', 'submitted_by'];

    // Applications made into engagements are always kept.
    $used = array_flip(array_map('intval', $db->query('SELECT application_id FROM fdimp_engagements')->fetchAll(PDO::FETCH_COLUMN)));

    foreach ($tables as $t) {
        $rows = $db->query("SELECT * FROM fdimp_{$t} ORDER BY id")->fetchAll();
        $kept = 0;
        $skip = 0;
        if ($rows) {
            $cols = array_keys($rows[0]);
            $ins  = $db->prepare(sprintf('INSERT INTO fd_%s (%s) VALUES (%s)', $t,
                implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
                implode(', ', array_fill(0, count($cols), '?'))));
            foreach ($rows as $r) {
                if ($t === 'applications' && !isset($used[(int) $r['id']])) {
                    foreach (Application::OPTIONS as $field => $allowed) {
                        if (!in_array($r[$field], $allowed, true)) {
                            echo "  application {$r['id']} skipped: {$field} is " . json_encode($r[$field]) . " (not a real choice; spam?)\n";
                            $skip++;
                            continue 2;
                        }
                    }
                }
                foreach ($userCols as $c) {
                    if (array_key_exists($c, $r)) {
                        $r[$c] = $mapUser($r[$c]);
                    }
                }
                $ins->execute(array_values($r));
                $kept++;
            }
        }
        echo "  fd_{$t}: {$kept}" . ($skip ? " ({$skip} skipped)" : '') . "\n";
    }

    if ($dry) {
        $db->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $db->commit();
        echo "Imported." . ($noPass ? " {$noPass} new account(s) have no password and must use Forgot your password." : '') . "\n";
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Rolled back: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    drop_staging($db, $staged);
    echo "  staging tables dropped\n";
}
exit($exit);
