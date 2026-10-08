<?php
/**
 * One-time import of the old timebank.bizorca.com MySQL database (a mysqldump)
 * into the shared tools database.
 *
 *   php timebank/bin/import-mysqldump.php /path/to/timebank.sql [--dry-run]
 *
 * How:
 *   1. The dump is loaded into staging tables named tmimp_<table> inside the
 *      target database (the Cloudways database user cannot create a scratch
 *      database), and they are always dropped afterwards.
 *   2. One transaction copies staging into the tm_ tables, keeping every id, so
 *      every ledger reference (provider, receiver, recorded_by...) stays valid:
 *      - each member row becomes a membership tied to a shared tools account,
 *        matched by email. An existing account is reused; otherwise one is
 *        created carrying the member's bcrypt hash, so people sign in with
 *        their existing TimeBank password. One person in several communities
 *        gets one account with several memberships.
 *      - placeholder admins at @timebank.bizorca.com that own nothing (the
 *        install seed's "TimeBank Admin") are skipped.
 *      - columns the port dropped (login columns, unused payment-key columns)
 *        are left behind; password_resets is not copied.
 *   3. Round-trip check, inside the same transaction: every staged table and
 *      its tm_ copy must have the same rows, and the same values in every
 *      shared column (checksummed), so hours and balances arrive to the cent.
 *      Any difference rolls the whole import back.
 *
 * Refuses to run if tm_tenants or tm_members already has rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/timebank.sql [--dry-run]\n");
    exit(1);
}

define('TM_ROOT', dirname(__DIR__));
require TM_ROOT . '/includes/config.php';

$db = tl_db();
$db->exec('SET SESSION group_concat_max_len = 1073741824');

// Parents before children, so foreign keys hold at every insert.
$tables = ['tenants', 'members', 'groups', 'group_members', 'categories', 'offers', 'transactions',
           'transaction_participants', 'messages', 'message_recipients', 'announcements', 'endorsements',
           'donations', 'email_templates', 'notifications', 'password_resets'];
$copyTables = array_values(array_diff($tables, ['password_resets']));

foreach (['tenants', 'members'] as $t) {
    if ((int) $db->query("SELECT COUNT(*) FROM tm_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "tm_{$t} already has rows. This import is for an install with no TimeBank data yet; stopping.\n");
        exit(1);
    }
}

function drop_staging(PDO $db, array $tables): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $db->exec("DROP TABLE IF EXISTS `tmimp_{$t}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function columns(PDO $db, string $table): array
{
    return array_column($db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(), 'Field');
}

// ── 1. stage ───────────────────────────────────────────────────────────────
$sql = (string) file_get_contents($src);
// Rename every reference to a dumped table (CREATE, INSERT, REFERENCES) so the
// staged copy is self-contained; constraint names get a prefix so they cannot
// collide with the real tm_ tables' constraints.
$sql = preg_replace('/`(' . implode('|', $tables) . ')`/', '`tmimp_$1`', $sql);
$sql = preg_replace('/CONSTRAINT `/', 'CONSTRAINT `tmimp_', $sql);

drop_staging($db, $tables);
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
    foreach ($copyTables as $t) {
        $db->query("SELECT 1 FROM `tmimp_{$t}` LIMIT 1");   // every table must be in the dump
    }

    // ── 2. copy ────────────────────────────────────────────────────────────
    $db->beginTransaction();

    // Members that own something somewhere; a placeholder that owns nothing is skipped.
    $owns = function (int $memberId) use ($db): bool {
        $checks = [
            'SELECT 1 FROM tmimp_transactions WHERE provider_id = ? OR receiver_id = ? OR recorded_by = ? LIMIT 1' => 3,
            'SELECT 1 FROM tmimp_offers WHERE member_id = ? LIMIT 1' => 1,
            'SELECT 1 FROM tmimp_messages WHERE sender_id = ? LIMIT 1' => 1,
            'SELECT 1 FROM tmimp_message_recipients WHERE recipient_id = ? LIMIT 1' => 1,
            'SELECT 1 FROM tmimp_groups WHERE created_by = ? LIMIT 1' => 1,
            'SELECT 1 FROM tmimp_group_members WHERE member_id = ? LIMIT 1' => 1,
            'SELECT 1 FROM tmimp_announcements WHERE author_id = ? LIMIT 1' => 1,
            'SELECT 1 FROM tmimp_endorsements WHERE from_member_id = ? OR to_member_id = ? LIMIT 1' => 2,
            'SELECT 1 FROM tmimp_donations WHERE member_id = ? LIMIT 1' => 1,
            'SELECT 1 FROM tmimp_transaction_participants WHERE member_id = ? LIMIT 1' => 1,
            'SELECT 1 FROM tmimp_members WHERE household_head_id = ? OR guardian_id = ? LIMIT 1' => 2,
        ];
        foreach ($checks as $q => $n) {
            $s = $db->prepare($q);
            $s->execute(array_fill(0, $n, $memberId));
            if ($s->fetchColumn()) {
                return true;
            }
        }
        return false;
    };

    // tenants
    $tenantCols = array_values(array_intersect(columns($db, 'tmimp_tenants'), columns($db, 'tm_tenants')));
    $colList    = implode(', ', array_map(fn($c) => "`{$c}`", $tenantCols));
    $n = $db->exec("INSERT INTO tm_tenants ({$colList}) SELECT {$colList} FROM tmimp_tenants ORDER BY id");
    echo "  tm_tenants: {$n}\n";

    // members -> shared users + memberships
    $find    = $db->prepare('SELECT id FROM users WHERE email = ?');
    $memberCols = array_values(array_intersect(columns($db, 'tmimp_members'), columns($db, 'tm_members')));
    $skipped = [];
    $created = $reused = 0;
    $members = $db->query('SELECT * FROM tmimp_members ORDER BY id')->fetchAll();
    foreach ($members as $m) {
        $email = strtolower(trim((string) $m['email']));
        if (str_ends_with($email, '@timebank.bizorca.com') && !$owns((int) $m['id'])) {
            $skipped[] = (int) $m['id'];
            echo "  member {$m['id']} skipped: placeholder at @timebank.bizorca.com that owns nothing\n";
            continue;
        }

        $find->execute([$email]);
        $userId = $find->fetchColumn();
        if ($userId) {
            $reused++;
            echo "  member {$m['id']} (tenant {$m['tenant_id']}) -> existing tools account {$userId}\n";
        } else {
            $name = trim((string) ($m['display_name'] ?: trim($m['first_name'] . ' ' . $m['last_name'])));
            $db->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, (string) $m['password_hash'] !== '' ? $m['password_hash'] : password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                          $name !== '' ? $name : ucfirst(strtok($email, '@')), $m['created_at']]);
            $userId = $db->lastInsertId();
            $created++;
            echo "  member {$m['id']} (tenant {$m['tenant_id']}) -> new tools account {$userId}\n";
        }

        $row = [];
        foreach ($memberCols as $c) {
            $row[$c] = in_array($c, ['household_head_id', 'guardian_id'], true) ? null : $m[$c];   // second pass below
        }
        $row['email']   = $email;
        $row['user_id'] = (int) $userId;
        $cols = array_keys($row);
        $db->prepare(sprintf('INSERT INTO tm_members (%s) VALUES (%s)',
            implode(', ', array_map(fn($c) => "`{$c}`", $cols)), implode(', ', array_fill(0, count($cols), '?'))))
           ->execute(array_values($row));
    }
    // Self-references, now that every member exists.
    foreach ($members as $m) {
        if (in_array((int) $m['id'], $skipped, true)) {
            continue;
        }
        if ($m['household_head_id'] !== null || $m['guardian_id'] !== null) {
            $db->prepare('UPDATE tm_members SET household_head_id = ?, guardian_id = ? WHERE id = ?')
               ->execute([$m['household_head_id'], $m['guardian_id'], $m['id']]);
        }
    }
    echo '  tm_members: ' . (count($members) - count($skipped)) . " ({$created} new accounts, {$reused} existing)\n";

    // everything else, ids kept, shared columns only
    foreach (array_diff($copyTables, ['tenants', 'members']) as $t) {
        $cols    = array_values(array_intersect(columns($db, "tmimp_{$t}"), columns($db, "tm_{$t}")));
        $colList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
        $n = $db->exec("INSERT INTO tm_{$t} ({$colList}) SELECT {$colList} FROM tmimp_{$t}");
        echo "  tm_{$t}: {$n}\n";
    }

    // ── 3. round-trip check ───────────────────────────────────────────────
    $checksum = function (string $table, array $cols, string $where = '') use ($db): array {
        $expr = 'CONCAT_WS(0x1F, ' . implode(', ', array_map(fn($c) => "IFNULL(CAST(`{$c}` AS CHAR), '\\\\N')", $cols)) . ')';
        $order = in_array('id', $cols, true) ? 'id' : $cols[0] . ', ' . $cols[1];
        $row = $db->query("SELECT COUNT(*) AS n, SHA2(IFNULL(GROUP_CONCAT({$expr} ORDER BY {$order} SEPARATOR 0x1E), ''), 256) AS h FROM `{$table}` {$where}")->fetch();
        return [(int) $row['n'], (string) $row['h']];
    };
    $skipWhere = $skipped ? 'WHERE id NOT IN (' . implode(',', $skipped) . ')' : '';
    foreach ($copyTables as $t) {
        $cols = array_values(array_intersect(columns($db, "tmimp_{$t}"), columns($db, "tm_{$t}")));
        if ($t === 'members') {
            $cols = array_values(array_diff($cols, ['email']));   // lower-cased on the way in
        }
        $a = $checksum("tmimp_{$t}", $cols, $t === 'members' ? $skipWhere : '');
        $b = $checksum("tm_{$t}", $cols);
        if ($a !== $b) {
            throw new RuntimeException("round-trip check failed for {$t}: staged {$a[0]} rows / {$a[1]}, imported {$b[0]} rows / {$b[1]}");
        }
    }
    echo "  round-trip check: every table matches its staged copy, every value\n";

    if ($dry) {
        $db->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $db->commit();
        echo "Imported. {$created} new tools account(s); any created for a member with no password must use \"Forgot your password?\".\n";
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Rolled back: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    drop_staging($db, $tables);
    echo "  staging tables dropped\n";
}
exit($exit);
