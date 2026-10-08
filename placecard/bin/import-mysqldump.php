<?php
/**
 * One-time import of the old placecard.bizorca.com MySQL database (a mysqldump
 * of cities, restaurants, users, events, rsvps) into the shared tools database.
 *
 *   php placecard/bin/import-mysqldump.php /path/to/placecard.sql [--dry-run]
 *
 * How:
 *   1. The dump is staged as pcimp_<table> inside the target database (the
 *      Cloudways database user cannot create a scratch database). Only its
 *      DROP/CREATE TABLE and INSERT statements run: mysqldump's SET SQL_MODE /
 *      TIME_ZONE preamble would otherwise change this connection's settings,
 *      and its foreign-key constraints are stripped because constraint names
 *      (events_ibfk_1...) are unique per database and could collide.
 *   2. One transaction copies staging into pc_ tables:
 *      - people are matched into the shared users table by email; an existing
 *        tools account is reused, otherwise one is created carrying the old
 *        bcrypt hash, so people sign in with their existing Placecard password
 *        (a row with no usable hash gets an unusable one and must use
 *        "Forgot your password?"). Their Placecard fields become pc_profiles,
 *        with public_id = their original UUID, so the ids the API returns are
 *        the same strings as before.
 *      - cities and restaurants are seeded by migrations/002; every staged row
 *        must already exist with the same text, or the import stops.
 *      - events and RSVPs keep their UUIDs, with user ids remapped.
 *   3. Staging tables are dropped, whatever happened.
 *
 * Refuses to run if pc_profiles, pc_events or pc_rsvps already has rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$src = $argv[1] ?? '';
$dry = in_array('--dry-run', $argv, true);
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php import-mysqldump.php /path/to/placecard.sql [--dry-run]\n");
    exit(1);
}

define('PC_ROOT', dirname(__DIR__));
require PC_ROOT . '/includes/config.php';

$db     = tl_db();
$tables = ['cities', 'restaurants', 'users', 'events', 'rsvps'];

foreach (['profiles', 'events', 'rsvps'] as $t) {
    if ((int) $db->query("SELECT COUNT(*) FROM pc_{$t}")->fetchColumn() > 0) {
        fwrite(STDERR, "pc_{$t} already has rows. This import is for an install with no Placecard members yet; stopping.\n");
        exit(1);
    }
}

function drop_staging(PDO $db, array $tables): void
{
    foreach ($tables as $t) {
        $db->exec("DROP TABLE IF EXISTS `pcimp_{$t}`");
    }
}

// ── 1. stage ───────────────────────────────────────────────────────────────
$sql = (string) file_get_contents($src);
$sql = preg_replace('/`(' . implode('|', $tables) . ')`/', '`pcimp_$1`', $sql);
// Strip FK constraints (and the comma that preceded the first of them).
$sql = preg_replace('/,\s*\n\s*CONSTRAINT `[^`]+` FOREIGN KEY[^\n]*?(?=,?\n)/', '', $sql);

drop_staging($db, $tables);
try {
    foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
        $body = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
        $body = trim(preg_replace('#^/\*![0-9]+ .*?\*/$#m', '', $body));
        if ($body === '' || !preg_match('/^(DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO) `pcimp_/i', $body)) {
            continue;
        }
        $db->exec($body);
    }
    foreach ($tables as $t) {
        $db->query("SELECT 1 FROM `pcimp_{$t}` LIMIT 1");   // all five must have been in the dump
    }

    // ── 2. copy ────────────────────────────────────────────────────────────
    // Reference data: must match the seed exactly (byte comparison).
    $check = [
        'cities'      => ['name', 'country', 'emoji', 'tagline'],
        'restaurants' => ['name', 'city_id', 'cuisine', 'neighborhood', 'description', 'price_range', 'is_active'],
    ];
    foreach ($check as $t => $cols) {
        $on = implode(' AND ', array_map(fn($c) => "CAST(p.{$c} AS BINARY) <=> CAST(s.{$c} AS BINARY)", $cols));
        $missing = (int) $db->query(
            "SELECT COUNT(*) FROM pcimp_{$t} s LEFT JOIN pc_{$t} p ON p.id = s.id AND {$on} WHERE p.id IS NULL"
        )->fetchColumn();
        if ($missing > 0) {
            throw new RuntimeException("{$missing} staged {$t} row(s) differ from the seeded pc_{$t}; refusing to attach events to different reference data.");
        }
        echo "  pc_{$t}: already seeded, matches the dump\n";
    }

    $db->beginTransaction();

    $userMap  = [];
    $noPass   = 0;
    $find     = $db->prepare('SELECT id FROM users WHERE email = ?');
    $profile  = $db->prepare(
        'INSERT INTO pc_profiles (user_id, public_id, first_name, last_name, age, bio, interests, dining_preferences,
                                  show_exact_age, show_last_name, is_profile_complete, member_since, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($db->query('SELECT * FROM pcimp_users ORDER BY created_at, id')->fetchAll() as $u) {
        $email = strtolower(trim($u['email']));
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id) {
            echo "  user " . substr($u['id'], 0, 8) . " -> existing tools account {$id}\n";
        } else {
            $hash = (string) $u['password_hash'];
            if (!str_starts_with($hash, '$2')) {
                // Nothing to verify against: an unusable hash; they reset by email.
                $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                $noPass++;
            }
            $name = trim($u['first_name'] . ' ' . ($u['last_name'] ?? ''));
            $db->prepare('INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, ?)')
               ->execute([$email, $hash, $name, $u['created_at']]);
            $id = $db->lastInsertId();
            echo "  user " . substr($u['id'], 0, 8) . " -> new tools account {$id}\n";
        }
        $userMap[$u['id']] = (int) $id;
        $profile->execute([
            (int) $id, $u['id'], $u['first_name'], $u['last_name'], $u['age'], $u['bio'],
            $u['interests'], $u['dining_preferences'],
            (int) $u['show_exact_age'], (int) $u['show_last_name'], (int) $u['is_profile_complete'],
            $u['member_since'], $u['created_at'],
        ]);
    }
    echo "  pc_profiles: " . count($userMap) . "\n";

    $n = 0;
    $ins = $db->prepare(
        'INSERT INTO pc_events (id, title, restaurant_id, city_id, host_user_id, event_date, max_attendees, notes, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($db->query('SELECT * FROM pcimp_events ORDER BY created_at, id')->fetchAll() as $e) {
        $host = $userMap[$e['host_user_id']] ?? throw new RuntimeException("event {$e['id']}: unknown host {$e['host_user_id']}");
        $ins->execute([$e['id'], $e['title'], $e['restaurant_id'], $e['city_id'], $host, $e['event_date'],
                       $e['max_attendees'], $e['notes'], $e['is_active'], $e['created_at']]);
        $n++;
    }
    echo "  pc_events: {$n}\n";

    $n = 0;
    $ins = $db->prepare('INSERT INTO pc_rsvps (id, event_id, user_id, created_at) VALUES (?, ?, ?, ?)');
    foreach ($db->query('SELECT * FROM pcimp_rsvps ORDER BY created_at, id')->fetchAll() as $r) {
        $uid = $userMap[$r['user_id']] ?? throw new RuntimeException("rsvp {$r['id']}: unknown user {$r['user_id']}");
        $ins->execute([$r['id'], $r['event_id'], $uid, $r['created_at']]);
        $n++;
    }
    echo "  pc_rsvps: {$n}\n";

    if ($dry) {
        $db->rollBack();
        echo "Dry run: rolled back, nothing kept.\n";
    } else {
        $db->commit();
        echo "Imported. {$noPass} new tools account(s) need \"Forgot your password?\" for their first sign-in.\n";
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Rolled back: ' . $e->getMessage() . "\n");
    drop_staging($db, $tables);
    echo "  staging tables dropped\n";
    exit(1);
}

drop_staging($db, $tables);
echo "  staging tables dropped\n";
