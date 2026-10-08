<?php

declare(strict_types=1);

/**
 * The heartbeat: background work triggered by page loads rather than cron.
 *
 * The behaviour worth testing is not "does the tick run" — that is covered
 * everywhere else. It is the scheduling around it, where the failures are
 * quiet and expensive:
 *
 *   - a slot claimed twice means every concurrent page load fires its own tick
 *   - a slot never released means the background layer stops, silently, which
 *     is exactly what happened in production and started all of this
 *   - a claim that ignores the interval means a tick per request
 *
 * The detached HTTP request itself is not exercised here. It needs a real
 * multi-process server; `tests/http/heartbeat.sh` drives it over one.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Heartbeat;

$db = Database::conn();
$db->exec('TRUNCATE TABLE pl_tick_state');
$db->exec('TRUNCATE TABLE pl_tick_token');
$db->exec("INSERT INTO pl_tick_state (mode) VALUES ('five-minute'), ('nightly')");

$state = static function (string $mode) use ($db): array {
    $stmt = $db->prepare('SELECT * FROM pl_tick_state WHERE mode = :m');
    $stmt->execute(['m' => $mode]);

    return $stmt->fetch() ?: [];
};


T::group('A slot is claimed once');

T::same(true, Heartbeat::claim('five-minute'), 'the first caller takes it');
T::same(false, Heartbeat::claim('five-minute'), 'the second does not');
T::same(false, Heartbeat::claim('five-minute'), 'nor the third');

/**
 * The reason there is no SELECT-then-UPDATE anywhere in this class: between
 * those two statements, every concurrent page load also decides it should run.
 * The UPDATE is the lock, so a hundred simultaneous requests produce one claim.
 */
$db->exec("UPDATE pl_tick_state SET last_started_at = NULL WHERE mode = 'five-minute'");

$won = 0;

for ($i = 0; $i < 25; $i++) {
    if (Heartbeat::claim('five-minute')) {
        $won++;
    }
}

T::same(1, $won, 'twenty-five callers, one winner');

T::same(false, Heartbeat::claim('not-a-mode'), 'an unknown mode claims nothing');


T::group('The interval is respected');

Heartbeat::finished('five-minute', 'done', 1);

T::same(false, Heartbeat::claim('five-minute'),
    'a run that just finished does not immediately become due again');

// Wind the clock back past the interval.
$db->exec("UPDATE pl_tick_state
           SET last_started_at = NOW() - INTERVAL 400 SECOND,
               last_finished_at = NOW() - INTERVAL 400 SECOND
           WHERE mode = 'five-minute'");

T::same(true, Heartbeat::claim('five-minute'), 'once the interval has passed it is due again');

// The nightly slot has its own, much longer interval.
Heartbeat::finished('nightly', 'done', 1);
$db->exec("UPDATE pl_tick_state
           SET last_started_at = NOW() - INTERVAL 2 HOUR,
               last_finished_at = NOW() - INTERVAL 2 HOUR
           WHERE mode = 'nightly'");

T::same(false, Heartbeat::claim('nightly'),
    'two hours is not a day — the nightly slot is not due');


T::group('A lost run does not wedge the slot forever');

/**
 * The failure this guards against: a claim is taken, the detached request never
 * arrives, and nothing ever runs again because the slot looks permanently busy.
 * That is the production incident, reproduced in miniature.
 */
$db->exec("UPDATE pl_tick_state
           SET last_started_at = NOW(), last_finished_at = NULL
           WHERE mode = 'five-minute'");

T::same(false, Heartbeat::claim('five-minute'),
    'while a run might still be in flight, nobody else starts one');

$db->exec("UPDATE pl_tick_state
           SET last_started_at = NOW() - INTERVAL 700 SECOND, last_finished_at = NULL
           WHERE mode = 'five-minute'");

T::same(true, Heartbeat::claim('five-minute'),
    'but a claim older than the timeout with no finish is assumed dead and retried');
T::ok(Heartbeat::CLAIM_TIMEOUT > Heartbeat::INTERVALS['five-minute'],
    'and the timeout is longer than the interval, so a slow run is not trampled by a due one');


T::group('The receiving request adopts the claim it was sent for');

/**
 * Without this the endpoint would refuse the work it was just asked to do: the
 * trigger claims, fires, and the receiver finds the slot not due — because the
 * trigger holds it.
 */
$db->exec("UPDATE pl_tick_state
           SET last_started_at = NOW(), last_finished_at = NULL
           WHERE mode = 'five-minute'");

T::same(true, Heartbeat::claimedRecently('five-minute'),
    'a claim taken moments ago is recognised');

$db->exec("UPDATE pl_tick_state SET last_finished_at = NOW() WHERE mode = 'five-minute'");
T::same(false, Heartbeat::claimedRecently('five-minute'),
    'a finished one is not — there is nothing outstanding to adopt');

$db->exec("UPDATE pl_tick_state
           SET last_started_at = NOW() - INTERVAL 5 MINUTE, last_finished_at = NULL
           WHERE mode = 'five-minute'");
T::same(false, Heartbeat::claimedRecently('five-minute'),
    'nor is a stale one — an external pinger must take its own claim, not inherit a dead one');


T::group('Silence is visible');

/**
 * The whole point. The background layer was dormant in production for a day and
 * the only symptom was mail that never arrived. Anything that can go quiet has
 * to be able to say so.
 */
$db->exec("UPDATE pl_tick_state SET last_started_at = NULL, last_finished_at = NULL, stalled_count = 0");

$status = Heartbeat::status();
T::same(2, count($status), 'both modes report');

foreach ($status as $row) {
    T::same(false, $row['healthy'], $row['mode'] . ' that has never run is not healthy');
    T::same(true, $row['never_run'], 'and says so');
    T::ok(str_contains((string) $row['human'], 'never'), 'in words a person can read');
}

T::same(true, Heartbeat::unhealthy(), 'and the installation reports itself unhealthy');

Heartbeat::finished('five-minute', 'ok', 2);
Heartbeat::finished('nightly', 'ok', 3);

T::same(false, Heartbeat::unhealthy(), 'two fresh runs and it is healthy');

foreach (Heartbeat::status() as $row) {
    T::same(true, $row['healthy'], $row['mode'] . ' is healthy');
    T::ok(str_contains((string) $row['human'], 'just now'), 'and says when');
}

// Stale is judged per mode. A nightly job that ran twenty hours ago is fine; a
// five-minute one that ran twenty hours ago is not.
$db->exec("UPDATE pl_tick_state SET last_finished_at = NOW() - INTERVAL 20 HOUR");

$byMode = [];

foreach (Heartbeat::status() as $row) {
    $byMode[(string) $row['mode']] = $row;
}

T::same(false, $byMode['five-minute']['healthy'],
    'twenty hours is far too long for the five-minute slot');
T::same(true, $byMode['nightly']['healthy'],
    'but well within tolerance for the nightly one — staleness is judged against each interval');

T::ok(str_contains((string) $byMode['five-minute']['human'], 'longer than it should be'),
    'and the unhealthy one explains itself rather than just showing a timestamp');


T::group('The run counter and the stall counter');

$db->exec("TRUNCATE TABLE pl_tick_state");
$db->exec("INSERT INTO pl_tick_state (mode) VALUES ('five-minute')");

Heartbeat::claim('five-minute');
T::same(1, (int) $state('five-minute')['stalled_count'], 'claiming increments the stall counter');

Heartbeat::finished('five-minute', 'ok', 1);
T::same(0, (int) $state('five-minute')['stalled_count'], 'finishing clears it');
T::same(1, (int) $state('five-minute')['run_count'], 'and counts the run');

// Three claims that never finish: the count is what makes a broken trigger
// visible rather than merely quiet.
for ($i = 0; $i < 3; $i++) {
    $db->exec("UPDATE pl_tick_state SET last_started_at = NOW() - INTERVAL 700 SECOND, last_finished_at = NULL");
    Heartbeat::claim('five-minute');
}

T::same(3, (int) $state('five-minute')['stalled_count'],
    'three starts without a finish are counted, so a trigger that never lands is loud');


T::group('The token');

$token = Heartbeat::token();

T::same(64, strlen($token), 'it is 32 bytes of hex');
T::same($token, Heartbeat::token(), 'and stable across calls — two racing requests must agree');
T::same(true, Heartbeat::verifyToken($token), 'the right one verifies');
T::same(false, Heartbeat::verifyToken(strrev($token)), 'a wrong one does not');
T::same(false, Heartbeat::verifyToken(''), 'nor an empty one');
T::same(false, Heartbeat::verifyToken(substr($token, 0, 32)), 'nor a prefix of the right one');
