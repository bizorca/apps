<?php
/**
 * One-time import of Pod's tables from the old shared login.bizorca.com MySQL
 * database (a mysqldump of Pod's tables plus `users`) into the shared tools
 * database.
 *
 *   php pod/bin/import-mysqldump.php /path/to/pod.sql [--dry-run]
 *
 * How:
 *   1. Every table in the dump is loaded as pdimp_<table> inside the target
 *      database (the Cloudways database user cannot create a scratch database),
 *      foreign keys stripped, and always dropped at the end.
 *   2. One transaction copies staging into pd_ tables:
 *      - `users` there is the whole Bizorca SSO user base, not Pod's. A person
 *        is imported only if they used Pod: signed in to it (Pod's SSO callback
 *        is the only thing that sets users.sso_id), own any Pod row, or carry
 *        Pod-only state (staff, disabled, bio, photo). Everyone else is skipped.
 *      - matched into the shared users table by email; an existing tools
 *        account is reused, otherwise one is created with the old bcrypt hash
 *        (login.bizorca.com was the identity store, so it is the real
 *        password). A hash that is not a password hash becomes unusable and the
 *        person signs in via "Forgot your password?".
 *      - each imported person gets pd_profiles: the old is_admin (the login
 *        server's admin flag, which was Pod's admin test) becomes the Pod-only
 *        admin flag, never the site-wide users.is_admin.
 *      - every other row keeps its id; user columns are remapped. Notification
 *        links become app paths.
 *   3. Staging is dropped, whatever happened.
 *
 * Clocks: the old MySQL ran in America/Chicago, so its DATETIME columns are
 * Chicago wall-clock times; they are converted (TIMESTAMP targets to UTC, the
 * one DATETIME target to Pacific). Event start/end times are kept exactly as
 * typed. TIMESTAMP columns are copied in UTC both ways and need nothing.
 *
 * Refuses if any pd_ table has data, except migration 002's seed categories
 * (replaced by the dump's) and untouched profiles of people who opened Pod
 * before the import (kept, and filled in from the dump).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/pod.sql [--dry-run]\n");
    exit(1);
}

define('PD_ROOT', dirname(__DIR__));
require PD_ROOT . '/includes/config.php';

// The old server's MySQL time_zone (SELECT @@time_zone on SiteGround).
define('OLD_TZ', getenv('POD_IMPORT_OLD_TZ') ?: 'America/Chicago');

$db = tl_db();                       // session time_zone is UTC
$db->exec("SET time_zone = '+00:00'");

// Copy order (parents first) => columns holding a user id.
$copyPlan = [
    'courses'              => [],
    'lessons'              => [],
    'lesson_progress'      => ['user_id'],
    'forum_categories'     => [],
    'forum_posts'          => ['user_id'],
    'forum_replies'        => ['user_id'],
    'forum_reactions'      => ['user_id'],
    'forum_category_reads' => ['user_id'],
    'tickets'              => ['user_id', 'assigned_to'],
    'ticket_messages'      => ['user_id'],
    'events'               => [],
    'event_rsvps'          => ['user_id'],
    'notifications'        => ['user_id'],
    'user_notes'           => ['user_id', 'admin_id'],
];
// Columns the old schema had no foreign key on: a row pointing at a parent
// that is gone is skipped (and counted), since pd_ tables enforce the key.
// Live example: a reaction could be stored on a post id that never existed.
$parents = [
    'forum_reactions'      => ['post_id' => 'forum_posts'],
    'forum_category_reads' => ['category_id' => 'forum_categories'],
];
// Wall-clock values typed by an admin: never converted.
$keepClock = ['events.starts_at', 'events.ends_at'];

// ── 0. refuse if Pod already has data ─────────────────────────────────────
$seedSlugs = ['general', 'business-coaching', 'tech-and-tools', 'wins'];
foreach (array_keys($copyPlan) as $t) {
    if ($t === 'forum_categories') {
        $slugs = $db->query('SELECT slug FROM pd_forum_categories')->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff($slugs, $seedSlugs)) {
            fwrite(STDERR, "pd_forum_categories has categories beyond the seed. This import is for an install with no Pod data yet; stopping.\n");
            exit(1);
        }
        continue;
    }
    if ((int) $db->query("SELECT COUNT(*) FROM pd_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "pd_{$t} already has rows. This import is for an install with no Pod data yet; stopping.\n");
        exit(1);
    }
}
$touched = (int) $db->query(
    "SELECT COUNT(*) FROM pd_profiles
      WHERE is_admin = 1 OR is_staff = 1 OR is_active = 0
         OR COALESCE(bio, '') <> '' OR COALESCE(avatar_url, '') <> ''"
)->fetchColumn();
if ($touched > 0) {
    fwrite(STDERR, "pd_profiles has {$touched} profile(s) someone has already changed; stopping.\n");
    exit(1);
}

// ── 1. stage ──────────────────────────────────────────────────────────────
$sql = (string) file_get_contents($src);
preg_match_all('/^CREATE TABLE `([A-Za-z0-9_]+)`/m', $sql, $m);
$tables = array_values(array_unique($m[1]));
foreach (array_merge(['users'], array_keys($copyPlan)) as $need) {
    if (!in_array($need, $tables, true)) {
        fwrite(STDERR, "The dump has no `{$need}` table. Dump Pod's tables plus users.\n");
        exit(1);
    }
}

function drop_staging(PDO $db, array $tables): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $db->exec("DROP TABLE IF EXISTS `pdimp_{$t}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// Rename every dumped table, so the staged copy can never touch a real one
// (the dump has a `users` table, and so does the target). Foreign keys go:
// staging only needs the rows, and user_app_admins points at sso_apps.
$sql = preg_replace('/`(' . implode('|', array_map('preg_quote', $tables)) . ')`/', '`pdimp_$1`', $sql);
$sql = preg_replace('/^\s*CONSTRAINT `[^`]+` FOREIGN KEY[^\n]*\n/m', '', $sql);
$sql = preg_replace('/,(\s*\n\)\s*ENGINE)/', '$1', $sql);

$failed = false;
try {
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
    $db->exec("SET time_zone = '+00:00'");   // the dump's trailer restores the old zone

    // Column types, staged and target: which values are Chicago wall-clock
    // DATETIMEs, and what each target column expects.
    $types = [];
    foreach ($db->query(
        "SELECT table_name AS t, column_name AS c, data_type AS d FROM information_schema.columns
          WHERE table_schema = DATABASE() AND (table_name LIKE 'pdimp\\_%' OR table_name LIKE 'pd\\_%' OR table_name = 'users')"
    )->fetchAll() as $c) {
        $types[$c['t']][$c['c']] = strtolower($c['d']);
    }

    $chicago = new DateTimeZone(OLD_TZ);
    $utc     = new DateTimeZone('UTC');
    $pacific = new DateTimeZone(PD_TIMEZONE);
    // An old DATETIME value, ready for a column of $targetType.
    $clock = static function (?string $v, string $targetType) use ($chicago, $utc, $pacific): ?string {
        if ($v === null || $v === '' || str_starts_with($v, '0000')) {
            return $v;
        }
        $d = new DateTime($v, $chicago);
        return $d->setTimezone($targetType === 'timestamp' ? $utc : $pacific)->format('Y-m-d H:i:s');
    };

    // Who used Pod.
    $owners = [];
    foreach ($copyPlan as $t => $cols) {
        foreach ($cols as $c) {
            foreach ($db->query("SELECT DISTINCT `{$c}` FROM pdimp_{$t} WHERE `{$c}` IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $owners[(int) $id] = true;
            }
        }
    }

    // ── 2. copy ───────────────────────────────────────────────────────────
    $db->beginTransaction();

    $userMap  = [];
    $find     = $db->prepare('SELECT id FROM users WHERE email = ?');
    $skipped  = 0;
    $created  = 0;
    $reused   = 0;
    $noPw     = 0;
    $profile  = $db->prepare(
        'INSERT INTO pd_profiles (user_id, is_admin, is_staff, is_active, bio, avatar_url, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?) AS new
         ON DUPLICATE KEY UPDATE is_admin = new.is_admin, is_staff = new.is_staff, is_active = new.is_active,
                                 bio = new.bio, avatar_url = new.avatar_url, created_at = new.created_at'
    );
    foreach ($db->query('SELECT * FROM pdimp_users ORDER BY id')->fetchAll() as $u) {
        $usedPod = $u['sso_id'] !== null || isset($owners[(int) $u['id']])
            || (int) $u['is_staff'] === 1 || (int) $u['is_active'] === 0
            || trim((string) $u['bio']) !== '' || trim((string) $u['avatar_url']) !== '';
        if (!$usedPod) {
            $skipped++;
            continue;
        }

        $email   = strtolower(trim($u['email']));
        $created_at = $clock($u['created_at'], 'timestamp');
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            $reused++;
            echo "  user {$u['id']} -> existing tools account {$id}\n";
        } else {
            $hash = (string) $u['password_hash'];
            $usable = password_get_info($hash)['algo'] !== null;
            if (!$usable) {
                // Not a password hash: nothing will ever match it.
                $hash = '!' . bin2hex(random_bytes(16));
                $noPw++;
            }
            $name = trim(trim((string) $u['first_name']) . ' ' . trim((string) $u['last_name']));
            $db->prepare('INSERT INTO users (email, password_hash, name, email_verified_at, created_at) VALUES (?, ?, ?, ?, ?)')
               ->execute([$email, $hash, $name !== '' ? $name : strtok($email, '@'), $clock($u['email_verified_at'], 'timestamp'), $created_at]);
            $id = $db->lastInsertId();
            $created++;
            echo "  user {$u['id']} -> new tools account {$id}" . ($usable ? '' : ' (no usable password: "Forgot your password?")') . "\n";
        }
        $userMap[(int) $u['id']] = (int) $id;
        $profile->execute([(int) $id, (int) $u['is_admin'], (int) $u['is_staff'], (int) $u['is_active'],
                           $u['bio'], $u['avatar_url'], $created_at]);
        $flags = array_keys(array_filter(['pod admin' => $u['is_admin'], 'staff' => $u['is_staff'], 'disabled' => !$u['is_active']]));
        if ($flags) {
            echo '    ' . implode(', ', $flags) . "\n";
        }
    }
    echo "  users: {$created} created, {$reused} matched to existing accounts, {$skipped} skipped (never used Pod)"
       . ($noPw ? ", {$noPw} need a password reset" : '') . "\n";

    // The seed categories make way for the dump's own rows and ids.
    $db->exec('DELETE FROM pd_forum_categories');

    $dumpedUsers = array_flip(array_map('intval', $db->query('SELECT id FROM pdimp_users')->fetchAll(PDO::FETCH_COLUMN)));
    foreach ($copyPlan as $t => $userCols) {
        $rows   = $db->query("SELECT * FROM pdimp_{$t} ORDER BY 1")->fetchAll();
        $orphan = 0;
        foreach ($parents[$t] ?? [] as $col => $parent) {
            $ids = array_flip(array_map('intval', $db->query("SELECT id FROM pdimp_{$parent}")->fetchAll(PDO::FETCH_COLUMN)));
            $rows = array_filter($rows, function ($r) use ($col, $ids, &$orphan) {
                if (isset($ids[(int) $r[$col]])) {
                    return true;
                }
                $orphan++;
                return false;
            });
        }
        // A user deleted from login.bizorca.com: their rows go with them, as
        // the foreign keys here would have done.
        $rows = array_values(array_filter($rows, function ($r) use ($userCols, $dumpedUsers, &$orphan) {
            foreach ($userCols as $c) {
                if ($r[$c] !== null && !isset($dumpedUsers[(int) $r[$c]]) && $c !== 'assigned_to' && $c !== 'admin_id') {
                    $orphan++;
                    return false;
                }
            }
            return true;
        }));
        if ($rows) {
            // Only columns Pod still has (and every one it has must be there).
            $cols = array_values(array_intersect(array_keys($rows[0]), array_keys($types["pd_{$t}"])));
            $ins  = $db->prepare(sprintf('INSERT INTO pd_%s (%s) VALUES (%s)', $t,
                implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
                implode(', ', array_fill(0, count($cols), '?'))));
            foreach ($rows as $r) {
                foreach ($userCols as $c) {
                    if (($c === 'assigned_to' || $c === 'admin_id') && $r[$c] !== null && !isset($dumpedUsers[(int) $r[$c]])) {
                        $r[$c] = null;   // that person is gone: ON DELETE SET NULL
                    }
                    if ($r[$c] !== null) {
                        $r[$c] = $userMap[(int) $r[$c]] ?? throw new RuntimeException("{$t}.{$c}: user {$r[$c]} was not imported");
                    }
                }
                $vals = [];
                foreach ($cols as $c) {
                    $v = $r[$c];
                    if (($types["pdimp_{$t}"][$c] ?? '') === 'datetime' && !in_array("{$t}.{$c}", $keepClock, true)) {
                        $v = $clock($v, $types["pd_{$t}"][$c]);
                    }
                    if ($v === null && in_array($c, ['created_at', 'updated_at', 'completed_at'], true)) {
                        $v = gmdate('Y-m-d H:i:s');   // NULL-able there, NOT NULL here
                    }
                    if ($t === 'notifications' && $c === 'url') {
                        // https://pod.bizorca.com/forum/post/5 -> /forum/post/5
                        $v = preg_replace('~^https?://[^/]+~i', '', (string) $v);
                    }
                    $vals[] = $v;
                }
                $ins->execute($vals);
            }
        }
        echo "  pd_{$t}: " . count($rows) . ($orphan ? " ({$orphan} orphaned row(s) skipped)" : '') . "\n";
    }

    if ($dry) {
        $db->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $db->commit();
        // Carry each table's id counter over, so an id the old Pod had used
        // (a deleted post, a failed insert) is never handed out again and an
        // old link cannot land on someone else's new post. ALTER TABLE commits
        // implicitly, hence after the transaction.
        $db->exec('SET SESSION information_schema_stats_expiry = 0');
        $next = $db->prepare('SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        foreach (array_keys($copyPlan) as $t) {
            $next->execute(["pdimp_{$t}"]);
            $n = (int) $next->fetchColumn();
            if ($n > 1) {
                $db->exec("ALTER TABLE pd_{$t} AUTO_INCREMENT = {$n}");
            }
        }
        echo "Imported.\n";
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Rolled back: ' . $e->getMessage() . "\n");
    $failed = true;
} finally {
    drop_staging($db, $tables);
}
exit($failed ? 1 : 0);
