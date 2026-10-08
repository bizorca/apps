<?php
/**
 * One-time import of the old astrology.bizorca.com MySQL database (a mysqldump
 * of all its tables) into the shared tools database.
 *
 *   php astrology/bin/import-mysqldump.php /path/to/astrology.sql [--dry-run]
 *
 * How:
 *   1. The dump is loaded into staging tables named asimp_<table> inside the
 *      target database (the Cloudways database user cannot create a scratch
 *      database), and they are always dropped afterwards.
 *   2. One transaction copies staging into as_ tables:
 *      - users are matched into the shared users table by email. An existing
 *        tools account is reused untouched; otherwise one is created carrying
 *        the old bcrypt hash, so people sign in with their existing password.
 *        An account with no hash (SSO-only) gets an unusable one and must use
 *        "Forgot your password?".
 *      - primary_profile_id and timezone, which lived on the old users row,
 *        become an as_members row. The original's admin (ADMIN_EMAIL below)
 *        becomes an as_admins row, never the site-wide users.is_admin.
 *      - profiles, readings, forecasts, starseed results, offer leads and
 *        meditation history keep their ids; user ids are remapped. Anonymous
 *        profiles (user_id NULL) come across as they are.
 *      - meditations and app settings were seeded by migrations/002 from this
 *        same database; the import checks the meditations match by id and slug
 *        so meditation history can never attach to the wrong one.
 *      - API tokens come across only if unexpired (90-day tokens; the iOS app
 *        signs in again otherwise). subscriptions and password_resets do not
 *        come across: there is no billing, and resets live on /account.
 *   3. Staging tables are dropped, whatever happened.
 *
 * Refuses to run if any as_ table that holds people's data already has rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// The original decided "admin" by this address (its .env ADMIN_EMAIL).
const ORIGINAL_ADMIN_EMAIL = 'jassen@bizorca.com';

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/astrology.sql [--dry-run]\n");
    exit(1);
}

define('AS_ROOT', dirname(__DIR__));
define('AS_API', true);   // no session in the CLI
require AS_ROOT . '/includes/config.php';

$db = getDB();   // Chicago session clock, as the live site ran (see getDB())

$tables = ['users', 'astrology_profiles', 'readings', 'forecasts', 'user_forecasts', 'meditations',
           'user_meditations', 'weekly_forecasts', 'starseed_results', 'api_tokens', 'offer_interest',
           'app_settings', 'subscriptions', 'password_resets', 'schema_migrations'];
$people = ['members', 'admins', 'profiles', 'readings', 'forecasts', 'user_forecasts', 'user_meditations',
           'weekly_forecasts', 'starseed_results', 'api_tokens', 'offer_interest'];

foreach ($people as $t) {
    if ((int) $db->query("SELECT COUNT(*) FROM as_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "as_{$t} already has rows. This import is for an install with no Astrology data yet; stopping.\n");
        exit(1);
    }
}

function drop_staging(PDO $db, array $tables): void {
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $db->exec("DROP TABLE IF EXISTS `asimp_{$t}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ── 1. stage ───────────────────────────────────────────────────────────────
$sql = (string) file_get_contents($src);
// Rename every reference to a dumped table, REFERENCES clauses included, so the
// staged copy is self-contained and never touches a real table.
$sql = preg_replace('/`(' . implode('|', $tables) . ')`/', '`asimp_$1`', $sql);

drop_staging($db, $tables);
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
    $body = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
    // mysqldump's lock, log and GTID housekeeping is not wanted here.
    if ($body === '' || preg_match('/^(LOCK TABLES|UNLOCK TABLES|SET @@GLOBAL|SET @@SESSION\.SQL_LOG_BIN|SET @MYSQLDUMP)/i', $body)) {
        continue;
    }
    $db->exec($body);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
// The dump's header set the session to UTC for loading; TIMESTAMPs are absolute
// either way, but DATETIME comparisons (token expiry) need the Chicago clock back.
$db->exec("SET time_zone = '" . (new DateTime('now', new DateTimeZone(AS_DB_TIMEZONE)))->format('P') . "'");

$exit = 0;
try {
    foreach (['users', 'astrology_profiles', 'readings', 'meditations', 'starseed_results'] as $t) {
        $db->query("SELECT 1 FROM `asimp_{$t}` LIMIT 1");   // the core tables must have been in the dump
    }
    $has = fn(string $t): bool => (bool) $db->query("SHOW TABLES LIKE 'asimp_{$t}'")->fetchColumn();

    if ($has('subscriptions') && (int) $db->query('SELECT COUNT(*) FROM asimp_subscriptions')->fetchColumn() > 0) {
        echo "  NOTE: the dump has subscription rows; they are not imported (no billing on the platform)\n";
    }

    // ── 2. copy ────────────────────────────────────────────────────────────
    $db->beginTransaction();

    $userMap   = [];
    $noPass    = 0;
    $find      = $db->prepare('SELECT id FROM users WHERE email = ?');
    foreach ($db->query('SELECT * FROM asimp_users ORDER BY id')->fetchAll() as $u) {
        $email = strtolower(trim((string) $u['email']));
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            echo "  user {$u['id']} -> existing tools account {$id}\n";
        } else {
            $hash = (string) $u['password_hash'];
            $note = '';
            if ($hash === '') {
                $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);   // unusable: nobody knows it
                $noPass++;
                $note = ' (no password yet: use Forgot your password)';
            }
            $db->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, $hash, mb_substr((string) $u['name'], 0, 100), $u['created_at'] ?? date('Y-m-d H:i:s')]);
            $id = $db->lastInsertId();
            echo "  user {$u['id']} -> new tools account {$id}{$note}\n";
        }
        $id = (int) $id;
        $userMap[(int) $u['id']] = $id;

        $db->prepare('INSERT INTO as_members (user_id, primary_profile_id, timezone, created_at) VALUES (?, ?, ?, ?)')
           ->execute([$id, $u['primary_profile_id'] ?: null, $u['timezone'] ?: 'America/New_York', $u['created_at'] ?? date('Y-m-d H:i:s')]);

        if ($email === ORIGINAL_ADMIN_EMAIL) {
            $db->prepare('INSERT IGNORE INTO as_admins (user_id) VALUES (?)')->execute([$id]);
            echo "    Astrology admin (as_admins), not a site admin\n";
        }
    }

    $remap = function (?int $old) use ($userMap): ?int {
        if ($old === null) return null;
        return $userMap[$old] ?? throw new RuntimeException("unknown user {$old}");
    };

    $copy = function (string $from, string $to, ?callable $map = null, string $where = '') use ($db): int {
        $rows = $db->query("SELECT * FROM asimp_{$from} {$where} ORDER BY id")->fetchAll();
        if (!$rows) return 0;
        $cols = array_keys($rows[0]);
        $ins  = $db->prepare(sprintf('INSERT INTO %s (%s) VALUES (%s)', $to,
            implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
            implode(', ', array_fill(0, count($cols), '?'))));
        foreach ($rows as $r) {
            $ins->execute(array_values($map ? $map($r) : $r));
        }
        return count($rows);
    };
    $withUser = function (array $r) use ($remap): array {
        $r['user_id'] = $remap($r['user_id'] === null ? null : (int) $r['user_id']);   // in place: column order must not change
        return $r;
    };

    // Meditations were seeded by migrations/002: prove the staged ones are the same.
    $mismatch = (int) $db->query(
        "SELECT COUNT(*) FROM asimp_meditations s
           LEFT JOIN as_meditations m ON m.id = s.id AND CAST(m.slug AS BINARY) <=> CAST(s.slug AS BINARY)
          WHERE m.id IS NULL"
    )->fetchColumn();
    if ($mismatch > 0) {
        throw new RuntimeException("as_meditations: {$mismatch} staged meditation(s) do not match the seeded ones by id and slug");
    }
    echo "  as_meditations: already seeded, matches the dump\n";

    echo '  as_profiles: '         . $copy('astrology_profiles', 'as_profiles', $withUser) . "\n";
    echo '  as_readings: '         . $copy('readings', 'as_readings', $withUser) . "\n";
    echo '  as_forecasts: '        . ($has('forecasts') ? $copy('forecasts', 'as_forecasts') : 0) . "\n";
    echo '  as_user_forecasts: '   . ($has('user_forecasts') ? $copy('user_forecasts', 'as_user_forecasts', $withUser) : 0) . "\n";
    echo '  as_user_meditations: ' . ($has('user_meditations') ? $copy('user_meditations', 'as_user_meditations', $withUser) : 0) . "\n";
    echo '  as_weekly_forecasts: ' . ($has('weekly_forecasts') ? $copy('weekly_forecasts', 'as_weekly_forecasts') : 0) . "\n";
    echo '  as_starseed_results: ' . $copy('starseed_results', 'as_starseed_results', $withUser) . "\n";
    echo '  as_offer_interest: '   . ($has('offer_interest') ? $copy('offer_interest', 'as_offer_interest', $withUser) : 0) . "\n";
    if ($has('api_tokens')) {
        $live    = $copy('api_tokens', 'as_api_tokens', $withUser, 'WHERE expires_at > NOW()');
        $expired = (int) $db->query('SELECT COUNT(*) FROM asimp_api_tokens WHERE expires_at <= NOW()')->fetchColumn();
        echo "  as_api_tokens: {$live} (skipped {$expired} expired)\n";
    }

    // Primary profiles must point at a profile that came across and belongs to the person.
    $bad = (int) $db->query(
        "SELECT COUNT(*) FROM as_members m LEFT JOIN as_profiles p ON p.id = m.primary_profile_id AND p.user_id = m.user_id
          WHERE m.primary_profile_id IS NOT NULL AND p.id IS NULL"
    )->fetchColumn();
    if ($bad > 0) {
        throw new RuntimeException("{$bad} as_members row(s) point at a primary profile that did not come across");
    }

    if ($dry) {
        $db->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $db->commit();
        echo "Imported. {$noPass} new account(s) need \"Forgot your password?\" for their first sign-in.\n";
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
