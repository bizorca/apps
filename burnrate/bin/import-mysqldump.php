<?php
/**
 * One-time import of the old burnrate.bizorca.com MySQL database (a mysqldump)
 * into the shared tools database.
 *
 *   php burnrate/bin/import-mysqldump.php /path/to/burnrate.sql [--dry-run]
 *
 * How:
 *   1. The dump is loaded into staging tables named brimp_<table> inside the
 *      target database (the Cloudways database user cannot create a scratch
 *      database). They are always dropped at the end.
 *   2. One transaction copies staging into br_ tables:
 *      - Owners are matched into the shared users table by email. An existing
 *        tools account is reused; otherwise one is created with the old bcrypt
 *        hash. Owners created by Bizorca SSO have an empty password, so they
 *        get an unusable hash and sign in via "Forgot your password?".
 *      - Each owner gets a br_owners row (tier, rounds, dates). Nobody gets
 *        IsAdmin: the original's admin test was MemberLevel >= the
 *        admin_member_level setting, which the import reports instead.
 *      - Players keep their PlayerID. The per-player tables Bank{id}, RE{id},
 *        Stocks{id}, StockData{id}, Businesses{id}, REMarketing{id} become rows
 *        in br_bank etc. with PlayerID set, keeping their own ids so every
 *        RecordID/StockID reference stays valid. Those ids were per-player
 *        sequences in the original; if two players' ids would collide in one
 *        shared table the import stops rather than guess (production had one
 *        player).
 *      - GameLog keeps its ids. site_settings values (minus Stripe keys, which
 *        belong in .env.php) overwrite the seeded defaults. The investment
 *        catalog is compared with migration 002 and any extra rows are added.
 *   3. Staging is dropped, whatever happened.
 *
 * Refuses to run if br_owners or br_players already has rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/burnrate.sql [--dry-run]\n");
    exit(1);
}

define('BR_ROOT', dirname(__DIR__));
require BR_ROOT . '/includes/config.php';

$db = tl_db();

foreach (['owners', 'players', 'bank', 'gamelog'] as $t) {
    if ((int) $db->query("SELECT COUNT(*) FROM br_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "br_{$t} already has rows. This import is for an install with no Burn Rate players yet; stopping.\n");
        exit(1);
    }
}

$sql = (string) file_get_contents($src);

// Every table in the dump: the fixed ones plus the per-player Bank1, RE1, ...
preg_match_all('/CREATE TABLE `([A-Za-z0-9_]+)`/', $sql, $m);
$dumped = $m[1];
if (!$dumped) {
    fwrite(STDERR, "No CREATE TABLE statements in {$src}; is it a mysqldump?\n");
    exit(1);
}

// Staged name per dumped table. The dump has both site_settings and a legacy
// SiteSettings, which are the same name to a case-insensitive MySQL (macOS),
// so a later case-twin gets a numbered suffix.
$stage = [];
$taken = [];
foreach ($dumped as $t) {
    $name = 'brimp_' . $t;
    $i = 2;
    while (isset($taken[strtolower($name)])) {
        $name = 'brimp_' . $t . '_' . $i++;
    }
    $taken[strtolower($name)] = true;
    $stage[$t] = $name;
}

function drop_staging(PDO $db, array $stage): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($stage as $name) {
        $db->exec("DROP TABLE IF EXISTS `{$name}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ── 1. stage ───────────────────────────────────────────────────────────────
$names = implode('|', array_map(fn($t) => preg_quote($t, '/'), $dumped));
$sql = preg_replace_callback('/`(' . $names . ')`/', fn($m) => '`' . $stage[$m[1]] . '`', $sql);

drop_staging($db, $stage);
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
    $body = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
    if ($body === '' || preg_match('/^(LOCK TABLES|UNLOCK TABLES|SET @@GLOBAL|SET @@SESSION\.SQL_LOG_BIN|SET @MYSQLDUMP|mysqldump:)/i', $body)) {
        continue;
    }
    $db->exec($body);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$has = fn(string $t): bool => in_array($t, $dumped, true);
$rows = fn(string $sql): array => $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$failed = false;
try {
    foreach (['Owners', 'Players'] as $t) {
        if (!$has($t)) {
            throw new RuntimeException("The dump has no {$t} table.");
        }
    }

    $db->beginTransaction();

    // ── owners -> shared users + br_owners ─────────────────────────────────
    $adminLevel = 65536;
    if ($has('site_settings')) {
        $v = $db->query("SELECT setting_value FROM {$stage['site_settings']} WHERE setting_key = 'admin_member_level'")->fetchColumn();
        if ($v !== false && (int) $v > 0) {
            $adminLevel = (int) $v;
        }
    }

    $ownerMap = [];
    $noPw = 0;
    $find = $db->prepare('SELECT id FROM users WHERE email = ?');
    foreach ($rows("SELECT * FROM {$stage['Owners']} ORDER BY OwnerID") as $o) {
        $email = strtolower(trim((string) $o['Email']));
        if ($email === '') {
            echo "  owner {$o['OwnerID']} skipped: no email\n";
            continue;
        }
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            echo "  owner {$o['OwnerID']} -> existing tools account {$id}\n";
        } else {
            $hash = (string) $o['Password'];
            $usable = str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2');
            if (!$usable) {
                // SSO-created (empty) or a legacy MD5+salt hash: unusable either way.
                $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
                $noPw++;
            }
            $name = trim($o['FirstName'] . ' ' . $o['LastName']);
            if ($name === '') {
                $name = ucfirst((string) strtok($email, '@'));
            }
            $db->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, $hash, $name, $o['SignUpDate'] ?? date('Y-m-d H:i:s')]);
            $id = $db->lastInsertId();
            echo "  owner {$o['OwnerID']} -> new tools account {$id}" . ($usable ? '' : ' (no usable password: Forgot your password?)') . "\n";
        }
        $ownerMap[(int) $o['OwnerID']] = (int) $id;

        $db->prepare('INSERT INTO br_owners (OwnerID, MemberLevel, RoundsCompleted, SignUpDate, LastLoginDate, IsAdmin) VALUES (?, ?, ?, ?, ?, 0)')
           ->execute([(int) $id, (int) $o['MemberLevel'], (int) ($o['RoundsCompleted'] ?? 0), $o['SignUpDate'], $o['LastLoginDate']]);
        if ((int) $o['MemberLevel'] >= $adminLevel) {
            echo "    note: tier {$o['MemberLevel']} made this owner an admin in the original; set br_owners.IsAdmin by hand if that was intended\n";
        }
    }

    // ── players ────────────────────────────────────────────────────────────
    $playerCols = array_column($rows('SHOW COLUMNS FROM br_players'), 'Field');
    $players = $rows("SELECT * FROM {$stage['Players']} ORDER BY PlayerID");
    foreach ($players as $p) {
        if (!isset($ownerMap[(int) $p['OwnerID']])) {
            throw new RuntimeException("Player {$p['PlayerID']} belongs to unknown owner {$p['OwnerID']}.");
        }
        $p['OwnerID'] = $ownerMap[(int) $p['OwnerID']];
        $data = array_intersect_key($p, array_flip($playerCols));
        $db->prepare('INSERT INTO br_players (`' . implode('`, `', array_keys($data)) . '`) VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')
           ->execute(array_values($data));
    }
    echo '  br_players: ' . count($players) . "\n";

    // ── per-player tables -> shared tables ─────────────────────────────────
    $perPlayer = [
        'Bank' => ['br_bank', 'BankID'], 'RE' => ['br_re', 'REID'], 'Stocks' => ['br_stocks', 'StockID'],
        'StockData' => ['br_stockdata', 'StockDataID'], 'Businesses' => ['br_businesses', 'BusinessID'],
        'REMarketing' => ['br_remarketing', 'REMarketingID'],
    ];
    foreach ($perPlayer as $prefix => [$target, $pk]) {
        $cols = array_column($rows("SHOW COLUMNS FROM {$target}"), 'Field');
        $seen = [];
        $n = 0;
        foreach ($players as $p) {
            $src = $prefix . $p['PlayerID'];
            if (!$has($src)) {
                continue;
            }
            foreach ($rows("SELECT * FROM {$stage[$src]} ORDER BY {$pk}") as $r) {
                if (isset($seen[(int) $r[$pk]])) {
                    throw new RuntimeException("{$target}.{$pk} {$r[$pk]} exists for two players; this import keeps ids and cannot merge them.");
                }
                $seen[(int) $r[$pk]] = true;
                $r['PlayerID'] = (int) $p['PlayerID'];
                $data = array_intersect_key($r, array_flip($cols));
                $db->prepare("INSERT INTO {$target} (`" . implode('`, `', array_keys($data)) . '`) VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')
                   ->execute(array_values($data));
                $n++;
            }
        }
        echo "  {$target}: {$n}\n";
    }

    // ── game log ───────────────────────────────────────────────────────────
    if ($has('GameLog')) {
        $cols = array_column($rows('SHOW COLUMNS FROM br_gamelog'), 'Field');
        $known = array_flip(array_column($players, 'PlayerID'));
        $n = 0;
        foreach ($rows("SELECT * FROM {$stage['GameLog']} ORDER BY GameLogID") as $r) {
            if (!isset($known[$r['PlayerID']])) {
                continue;   // an orphan left by a deleted player in the original
            }
            $data = array_intersect_key($r, array_flip($cols));
            $db->prepare('INSERT INTO br_gamelog (`' . implode('`, `', array_keys($data)) . '`) VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')
               ->execute(array_values($data));
            $n++;
        }
        echo "  br_gamelog: {$n}\n";
    }

    // ── settings ───────────────────────────────────────────────────────────
    if ($has('site_settings')) {
        foreach ($rows("SELECT setting_key, setting_value FROM {$stage['site_settings']}") as $s) {
            if (str_starts_with($s['setting_key'], 'stripe_') && $s['setting_key'] !== 'stripe_enabled') {
                if ($s['setting_value'] !== '') {
                    echo "  note: {$s['setting_key']} had a value; Stripe keys belong in .env.php (BR_STRIPE_*), not carried over\n";
                }
                continue;
            }
            if ($s['setting_key'] === 'admin_member_level') {
                continue;   // admin is no longer a tier
            }
            $db->prepare('INSERT INTO br_site_settings (setting_key, setting_value) VALUES (?, ?) AS new
                          ON DUPLICATE KEY UPDATE setting_value = new.setting_value')
               ->execute([$s['setting_key'], $s['setting_value']]);
        }
        echo "  br_site_settings: copied\n";
    }

    // ── investment catalog: must match migration 002, add anything extra ──
    if ($has('BadInvestments')) {
        $cols = array_column($rows('SHOW COLUMNS FROM br_badinvestments'), 'Field');
        $extra = 0;
        $diff = 0;
        foreach ($rows("SELECT * FROM {$stage['BadInvestments']} ORDER BY InvestmentID") as $r) {
            $mine = $db->prepare('SELECT * FROM br_badinvestments WHERE InvestmentID = ?');
            $mine->execute([$r['InvestmentID']]);
            $row = $mine->fetch(PDO::FETCH_ASSOC);
            $data = array_intersect_key($r, array_flip($cols));
            if (!$row) {
                $db->prepare('INSERT INTO br_badinvestments (`' . implode('`, `', array_keys($data)) . '`) VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')
                   ->execute(array_values($data));
                $extra++;
            } elseif (array_map('strval', array_intersect_key($row, $data)) != array_map('strval', $data)) {
                $diff++;
            }
        }
        echo "  br_badinvestments: matches the seed" . ($extra ? ", {$extra} extra added" : '') . ($diff ? ", {$diff} differ from the seed (seed kept)" : '') . "\n";
    }

    if ($dry) {
        $db->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $db->commit();
        echo "Imported. {$noPw} new account(s) have no usable password and must use \"Forgot your password?\".\n";
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Rolled back: ' . $e->getMessage() . "\n");
    $failed = true;
} finally {
    drop_staging($db, $stage);
    echo "  staging tables dropped\n";
}

exit($failed ? 1 : 0);
