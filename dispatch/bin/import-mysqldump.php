<?php
/**
 * One-time import of the old dispatch.bizorca.com MySQL database (a mysqldump)
 * into the shared tools database.
 *
 *   php dispatch/bin/import-mysqldump.php /path/to/dispatch.sql [--dry-run]
 *
 * How:
 *   1. The dump is loaded into staging tables named dpimp_<table> inside the
 *      target database (the Cloudways database user cannot create a scratch
 *      database), and they are always dropped at the end.
 *   2. One transaction copies staging into dp_ tables:
 *      - users are matched into the shared users table by email; an existing
 *        tools account is reused, otherwise one is created carrying the old
 *        bcrypt hash. Accounts first created through Bizorca SSO were given a
 *        random password by the old app, so their hash is unusable either way:
 *        those people sign in via "Forgot your password?".
 *      - a user is imported only if their email was verified or they own any
 *        row. An unverified account that owns nothing (a spam signup) is
 *        skipped and listed.
 *      - each imported user gets a dp_profiles row with their old role and
 *        digest settings. The old users.is_admin only mirrored the SSO
 *        server's admin flag and is not carried into the site-wide is_admin.
 *      - every other row keeps its id; user columns are remapped.
 *   3. Staging is dropped, whatever happened.
 *
 * Uploaded documents are files, not rows: copy storage/uploads/documents/* to
 * private_html/data/dispatch/documents/ first. This script lists any document
 * whose file is missing there.
 *
 * Refuses to run if any dp_ table already has rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/dispatch.sql [--dry-run]\n");
    exit(1);
}

define('DP_ROOT', dirname(__DIR__));
require DP_ROOT . '/includes/config.php';

$db = tl_db();

$tables = ['users', 'venues', 'venue_submissions', 'campaigns', 'campaign_venues', 'action_items',
           'flyer_locations', 'notifications', 'documents', 'rate_limits'];

// Copy order (parents first) and which columns hold a user id.
$copyPlan = [
    'venues'            => ['suggested_by_user_id', 'created_by'],
    'venue_submissions' => ['user_id', 'reviewed_by'],
    'campaigns'         => ['user_id'],
    'campaign_venues'   => [],
    'action_items'      => ['user_id', 'assigned_to_user_id'],
    'flyer_locations'   => ['user_id'],
    'notifications'     => ['user_id'],
    'documents'         => ['user_id'],
];

foreach (array_merge(['profiles'], array_keys($copyPlan)) as $t) {
    if ((int) $db->query("SELECT COUNT(*) FROM dp_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "dp_{$t} already has rows. This import is for an install with no Dispatch data yet; stopping.\n");
        exit(1);
    }
}

function drop_staging(PDO $db, array $tables): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $db->exec("DROP TABLE IF EXISTS `dpimp_{$t}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ── 1. stage ───────────────────────────────────────────────────────────────
$sql = (string) file_get_contents($src);
// Rename every reference to a dumped table, REFERENCES clauses included, so the
// staged copy is self-contained and never touches a real table.
$sql = preg_replace('/`(' . implode('|', $tables) . ')`/', '`dpimp_$1`', $sql);

drop_staging($db, $tables);
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
    $body = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
    // mysqldump's session and GTID housekeeping is not wanted here.
    if ($body === '' || preg_match('/^(LOCK TABLES|UNLOCK TABLES|SET @@GLOBAL|SET @@SESSION\.SQL_LOG_BIN|SET @MYSQLDUMP|mysqldump:)/i', $body)) {
        continue;
    }
    $db->exec($body);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$failed = false;
try {
    foreach ($tables as $t) {
        $db->query("SELECT 1 FROM `dpimp_{$t}` LIMIT 1");   // every table must have been in the dump
    }

    // Who owns anything: user ids referenced by any copied column.
    $owners = [];
    foreach ($copyPlan as $t => $cols) {
        foreach ($cols as $c) {
            foreach ($db->query("SELECT DISTINCT `{$c}` FROM dpimp_{$t} WHERE `{$c}` IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $owners[(int) $id] = true;
            }
        }
    }

    // ── 2. copy ────────────────────────────────────────────────────────────
    $db->beginTransaction();

    $userMap = [];
    $find    = $db->prepare('SELECT id FROM users WHERE email = ?');
    $noPw    = 0;
    foreach ($db->query('SELECT * FROM dpimp_users ORDER BY id')->fetchAll() as $u) {
        if ($u['email_verified_at'] === null && !isset($owners[(int) $u['id']])) {
            echo "  user {$u['id']} skipped: never verified and owns nothing (spam signup?)\n";
            continue;
        }
        $email = strtolower(trim($u['email']));
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            echo "  user {$u['id']} -> existing tools account {$id}\n";
        } else {
            $db->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, $u['password'], $u['name'], $u['created_at']]);
            $id = $db->lastInsertId();
            $sso = $u['sso_id'] !== null ? ' (SSO account: may need "Forgot your password?")' : '';
            if ($sso !== '') $noPw++;
            echo "  user {$u['id']} -> new tools account {$id}{$sso}\n";
        }
        $userMap[(int) $u['id']] = (int) $id;
        $db->prepare('INSERT INTO dp_profiles (user_id, role, notification_preference, remind_days_before, created_at) VALUES (?, ?, ?, ?, ?)')
           ->execute([(int) $id, $u['role'], $u['notification_preference'] ?? 'daily', (int) $u['remind_days_before'], $u['created_at']]);
        echo "    Dispatch role: {$u['role']}\n";
    }

    foreach ($copyPlan as $t => $userCols) {
        $rows = $db->query("SELECT * FROM dpimp_{$t} ORDER BY id")->fetchAll();
        if ($rows) {
            $cols = array_keys($rows[0]);
            $ins  = $db->prepare(sprintf('INSERT INTO dp_%s (%s) VALUES (%s)', $t,
                implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
                implode(', ', array_fill(0, count($cols), '?'))));
            foreach ($rows as $r) {
                foreach ($userCols as $c) {
                    if ($r[$c] !== null) {
                        $r[$c] = $userMap[(int) $r[$c]] ?? throw new RuntimeException("{$t}.{$c}: user {$r[$c]} was not imported");
                    }
                }
                $ins->execute(array_values($r));
            }
        }
        echo "  dp_{$t}: " . count($rows) . "\n";
    }

    // Files the document rows point at.
    foreach ($db->query("SELECT id, file_path FROM dp_documents WHERE file_path IS NOT NULL")->fetchAll() as $d) {
        $f = DP_UPLOADS . '/' . basename($d['file_path']);
        echo is_file($f) ? "  document {$d['id']}: file present\n" : "  document {$d['id']}: FILE MISSING, copy {$d['file_path']} to " . DP_UPLOADS . "/\n";
    }

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
exit($failed ? 1 : 0);
