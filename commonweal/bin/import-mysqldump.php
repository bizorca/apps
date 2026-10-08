<?php
/**
 * One-time import of the old commonweal.app database into the shared tools
 * database.
 *
 *   php commonweal/bin/import-mysqldump.php /path/to/commonweal.sql [--admin-email=you@example.com] [--dry-run]
 *
 * --admin-email: the original identified its admin by the ADMIN_EMAIL constant
 * in its .env.php, not by a column. Pass that address so the person keeps
 * Commonweal admin (a cw_members role, never the site-wide users.is_admin).
 * Anyone whose users.role was already 'admin' keeps it too.
 *
 * How:
 *   1. The dump is loaded into staging tables cwimp_<table> inside the target
 *      database (the Cloudways database user cannot create a scratch
 *      database); they are dropped again whatever happens.
 *   2. One transaction:
 *      - users are matched into the shared users table by email. A new account
 *        keeps its bcrypt hash, so people sign in with their old password; an
 *        existing tools account keeps its own password.
 *      - each person's role, coordinator approval and why-volunteer text become
 *        a cw_members row.
 *      - every other table keeps its ids; user columns are remapped.
 *   3. Staging tables are dropped.
 *
 * Refuses to run if any cw_ table already has rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
$adminEmail = '';
foreach ($argv as $a) {
    if (str_starts_with($a, '--admin-email=')) {
        $adminEmail = strtolower(trim(substr($a, 14)));
    }
}
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/commonweal.sql [--admin-email=...] [--dry-run]\n");
    exit(1);
}

define('CW_ROOT', dirname(__DIR__));
require CW_ROOT . '/includes/config.php';

$db = tl_db();

// Parents before children, so foreign keys hold at every insert.
$tables = ['businesses', 'cases', 'case_notes', 'structure_assessments', 'deal_models', 'documents', 'equity_ledger'];
$staged = array_merge(['users'], $tables);

foreach (array_merge($tables, ['members']) as $t) {
    if ((int) $db->query("SELECT COUNT(*) FROM cw_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "cw_{$t} already has rows. This import is for an empty install; stopping.\n");
        exit(1);
    }
}

function drop_staging(PDO $db, array $staged): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($staged as $t) {
        $db->exec("DROP TABLE IF EXISTS `cwimp_{$t}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ── 1. stage ───────────────────────────────────────────────────────────────
$sql = (string) file_get_contents($src);
// Rename every reference, including REFERENCES clauses, so the staged copy is
// self-contained and never touches a real table (`users` especially).
$sql = preg_replace('/`(' . implode('|', $staged) . ')`/', '`cwimp_$1`', $sql);

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
        $db->query("SELECT 1 FROM `cwimp_{$t}` LIMIT 1");   // every table must have been in the dump
    }

    // ── 2. copy ────────────────────────────────────────────────────────────
    $db->beginTransaction();

    $userMap = [];
    $created = 0;
    $find    = $db->prepare('SELECT id FROM users WHERE email = ?');
    $member  = $db->prepare('INSERT INTO cw_members (user_id, role, coordinator_approved, coordinator_why, created_at) VALUES (?, ?, ?, ?, ?)');
    foreach ($db->query('SELECT * FROM cwimp_users ORDER BY id')->fetchAll() as $u) {
        $email = strtolower(trim((string) $u['email']));
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            echo "  user {$u['id']} -> existing tools account {$id} (keeps its own password)\n";
        } else {
            $hash = (string) $u['password_hash'];
            if (!str_starts_with($hash, '$2y$') && !str_starts_with($hash, '$argon2')) {
                $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);   // unusable: Forgot your password
            }
            $db->prepare('INSERT INTO users (email, password_hash, name, email_verified_at, created_at) VALUES (?, ?, ?, ?, ?)')
               ->execute([$email, $hash, trim((string) $u['name']) !== '' ? trim((string) $u['name']) : ucfirst((string) strtok($email, '@')),
                          $u['email_verified_at'], $u['created_at']]);
            $id = $db->lastInsertId();
            $created++;
            echo "  user {$u['id']} -> new tools account {$id}\n";
        }
        $role = in_array($u['role'], ['client', 'coordinator', 'admin'], true) ? $u['role'] : 'client';
        if ($adminEmail !== '' && $email === $adminEmail) {
            $role = 'admin';
            echo "    Commonweal admin (cw_members.role), not a site admin\n";
        }
        $member->execute([(int) $id, $role, (int) $u['coordinator_approved'], $u['coordinator_why'] ?? null, $u['created_at']]);
        $userMap[(int) $u['id']] = (int) $id;
    }

    $mapUser = function (int|string|null $v) use ($userMap): ?int {
        if ($v === null) {
            return null;
        }
        return $userMap[(int) $v] ?? throw new RuntimeException("unknown users id {$v}");
    };

    foreach ($tables as $t) {
        $rows = $db->query("SELECT * FROM cwimp_{$t} ORDER BY id")->fetchAll();
        if ($rows) {
            $cols = array_keys($rows[0]);
            $ins  = $db->prepare(sprintf('INSERT INTO cw_%s (%s) VALUES (%s)', $t,
                implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
                implode(', ', array_fill(0, count($cols), '?'))));
            foreach ($rows as $r) {
                foreach (['user_id', 'coordinator_id'] as $c) {
                    if (array_key_exists($c, $r)) {
                        $r[$c] = $mapUser($r[$c]);
                    }
                }
                $ins->execute(array_values($r));
            }
        }
        echo "  cw_{$t}: " . count($rows) . "\n";
    }

    // Round-trip check on the money and modeling columns: what went in must read back the same.
    foreach (['deal_models' => 'business_valuation,seller_note_amount,seller_note_rate,cdfi_loan_amount,cdfi_loan_rate,member_count,outputs',
              'equity_ledger' => 'capital_account,patronage_basis,acquisition_method',
              'documents' => 'doc_type,rendered_html'] as $t => $cols) {
        $a = $db->query("SELECT id, {$cols} FROM cwimp_{$t} ORDER BY id")->fetchAll();
        $b = $db->query("SELECT id, {$cols} FROM cw_{$t} ORDER BY id")->fetchAll();
        if ($a != $b) {
            throw new RuntimeException("cw_{$t} does not match the dump after copying");
        }
    }
    echo "  round-trip check: deal models, equity ledger and documents match the dump\n";

    if ($dry) {
        $db->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $db->commit();
        echo "Imported. {$created} new tools account(s).\n";
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
