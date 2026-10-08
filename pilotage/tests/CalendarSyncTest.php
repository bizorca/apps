<?php

declare(strict_types=1);

/**
 * Two-way calendar sync.
 *
 * Driven through a fake Provider rather than against Google. That is not a
 * compromise — the interesting behaviour is entirely on our side of the
 * boundary, and the four rules that make sync feel sane are exactly the ones a
 * real API cannot be made to demonstrate on demand. You cannot ask Google to
 * produce a simultaneous edit conflict at a convenient moment.
 *
 * What the fake CANNOT tell us is whether the JSON shapes are right. That is
 * checked separately, against the providers' own documented payloads, in the
 * parsing group at the end.
 */

use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Secrets;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\CalendarSync;
use Bizorca\Pilotage\Services\Calendar\GoogleProvider;
use Bizorca\Pilotage\Services\Calendar\MicrosoftProvider;
use Bizorca\Pilotage\Services\SessionService;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_calendar_events', 'pl_calendar_connections', 'pl_oauth_states',
          'pl_notifications', 'pl_org_events', 'pl_session_attendees', 'pl_sessions',
          'pl_engagement_members', 'pl_engagements', 'pl_client_orgs', 'pl_users', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active')");

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'Ada Coach', 'role' => 'coach', 'status' => 'active']);
$orgId = (new ClientOrgRepository(1))->createOrg(['name' => 'Alpha', 'status' => 'active']);
$engId = (new EngagementRepository(1))->createEngagement([
    'client_org_id' => $orgId, 'title' => 'Q3', 'status' => 'active', 'coach_user_id' => $coachId,
]);


T::group('Encryption at rest');

// A key just for this run. Secrets reads it from config, so the test sets one
// rather than depending on the developer's .env.
Config::set('app.secret_key', base64_encode(random_bytes(32)));

T::same(true, Secrets::available(), 'a key is configured');

$secret = 'ya29.a0AfB_refresh_token_value';
$sealed = Secrets::encrypt($secret);

T::ok(!str_contains($sealed, $secret), 'the plaintext is not in the ciphertext');
T::same($secret, Secrets::decrypt($sealed), 'and it round-trips');
T::ok(Secrets::encrypt($secret) !== $sealed,
    'encrypting twice gives different bytes — the nonce is random, not a counter');

// Authenticated encryption: a flipped bit must fail, not decrypt to garbage we
// then hand to Google.
$tampered = substr($sealed, 0, -6) . 'AAAAAA';
T::same(null, Secrets::decrypt($tampered), 'tampered ciphertext does not decrypt');
T::same(null, Secrets::decrypt('not-even-close'), 'nor does nonsense');

$mine = $sealed;
Config::set('app.secret_key', base64_encode(random_bytes(32)));
T::same(null, Secrets::decrypt($mine), 'and a different key cannot read it');

Config::set('app.secret_key', base64_encode(random_bytes(32)));
$key = (string) Config::get('app.secret_key');


T::group('Sync does not offer itself when it cannot be safe');

Config::set('app.secret_key', '');
T::same([], CalendarSync::configured(),
    'no encryption key means no providers — there would be nowhere safe to put a refresh token');

Config::set('app.secret_key', $key);
Config::set('calendar.google.client_id', '');
Config::set('calendar.microsoft.client_id', '');
T::same([], CalendarSync::configured(), 'and a provider with no client id is not offered either');

Config::set('calendar.google.client_id', 'test-client-id');
T::same(['google'], array_keys(CalendarSync::configured()), 'configuring one offers exactly one');


T::group('The event we push');

$sessionId = SessionService::schedule(1, $engId, 'Quarterly review',
    date('Y-m-d H:i:s', strtotime('+3 days')), null, $coachId, 'Video call');
$db->exec("UPDATE pl_sessions SET duration_minutes = 90 WHERE id = {$sessionId}");

$session = $db->query("SELECT s.*, o.name AS org_name, e.title AS engagement_title
                       FROM pl_sessions s
                       JOIN pl_engagements e ON e.id = s.engagement_id
                       JOIN pl_client_orgs o ON o.id = e.client_org_id
                       WHERE s.id = {$sessionId}")->fetch();

$event = CalendarSync::eventFor($session);

T::same('Alpha — Quarterly review', $event['title'], 'the client name leads, so a diary is readable at a glance');
T::same('Video call', $event['location'], 'the location comes across');
T::same(90 * 60, strtotime($event['ends_at'] . ' UTC') - strtotime($event['starts_at'] . ' UTC'),
    'the end is the start plus the real duration, not a flat hour');
T::same($sessionId, $event['session_id'], 'and it carries the session id, so our own events are recognisable');


/**
 * A provider that records what it was asked to do and returns what it is told
 * to. Every rule below is asserted against these recordings.
 */
class FakeProvider implements Bizorca\Pilotage\Services\Calendar\Provider
{
    /** @var array<int,array<string,mixed>> */
    public array $upserts = [];
    /** @var array<int,string> */
    public array $deletes = [];
    /** @var array<int,array<string,mixed>> */
    public array $nextChanges = [];
    public ?string $nextSyncToken = 'token-2';
    public bool $reset = false;
    public string $remoteId = 'remote-1';
    public ?string $remoteUpdatedAt = null;

    public function key(): string { return 'google'; }
    public function label(): string { return 'Fake Calendar'; }
    public function authorizeUrl(string $s, string $r, ?string $c = null): string { return 'https://example.test/auth'; }
    public function exchangeCode(string $c, string $r, ?string $v = null): ?array
    {
        return ['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600, 'scope' => null];
    }
    public function refresh(string $r): ?array { return ['access_token' => 'at2', 'expires_in' => 3600]; }
    public function accountEmail(string $a): ?string { return 'coach@gmail.test'; }

    public function upsertEvent(string $a, ?string $cal, ?string $remoteId, array $event): ?array
    {
        $this->upserts[] = ['remote_id' => $remoteId, 'event' => $event];

        return [
            'id' => $remoteId ?? $this->remoteId,
            'etag' => 'etag-1',
            'updated_at' => $this->remoteUpdatedAt ?? gmdate('Y-m-d H:i:s'),
        ];
    }

    public function deleteEvent(string $a, ?string $cal, string $remoteId): bool
    {
        $this->deletes[] = $remoteId;
        return true;
    }

    public function changes(string $a, ?string $cal, ?string $syncToken): ?array
    {
        return ['events' => $this->nextChanges, 'sync_token' => $this->nextSyncToken, 'reset' => $this->reset];
    }
}

$fake = new FakeProvider();
CalendarSync::useProviders(['google' => $fake]);

// A connection to drive the fake through.
$db->prepare(
    "INSERT INTO pl_calendar_connections
        (id, tenant_id, user_id, provider, account_email, access_token, refresh_token,
         access_expires_at, status)
     VALUES (1, 1, :uid, 'google', 'coach@gmail.test', :at, :rt, NOW() + INTERVAL 1 HOUR, 'active')"
)->execute([
    'uid' => $coachId,
    'at' => Secrets::encrypt('access-token'),
    'rt' => Secrets::encrypt('refresh-token'),
]);

$connection = $db->query('SELECT * FROM pl_calendar_connections WHERE id = 1')->fetch();


T::group('Rule 4: only future sessions are pushed');

$past = SessionService::schedule(1, $engId, 'Long finished',
    date('Y-m-d H:i:s', strtotime('-40 days')), null, $coachId);
$db->exec("UPDATE pl_sessions SET status = 'complete' WHERE id = {$past}");

$far = SessionService::schedule(1, $engId, 'Next year',
    date('Y-m-d H:i:s', strtotime('+400 days')), null, $coachId);

$pushable = $db->query(
    "SELECT COUNT(*) AS c FROM pl_sessions
     WHERE tenant_id = 1 AND scheduled_at >= NOW() - INTERVAL 1 DAY
       AND scheduled_at <= NOW() + INTERVAL " . CalendarSync::HORIZON_DAYS . " DAY"
)->fetch()['c'];

T::same(1, (int) $pushable,
    'only the upcoming session is in range — not the finished one, not the one 400 days out');
T::ok(CalendarSync::HORIZON_DAYS > 30 && CalendarSync::HORIZON_DAYS <= 365,
    'the horizon is months, not years');


T::group('Rule 3: an unchanged session costs nothing');

$digestOf = static function (array $session): string {
    return hash('sha256', json_encode(CalendarSync::eventFor($session), JSON_UNESCAPED_UNICODE) ?: '');
};

$first = $digestOf($session);
$again = $digestOf($session);

T::same($first, $again, 'the same session digests the same way twice');

$moved = $session;
$moved['scheduled_at'] = date('Y-m-d H:i:s', strtotime('+4 days'));

T::ok($digestOf($moved) !== $first, 'and a moved one digests differently');

$renamed = $session;
$renamed['location'] = 'The workshop';

T::ok($digestOf($renamed) !== $first, 'so does a change of location');

// The digest is what the push compares against, so a stored digest matching
// the computed one is precisely "nothing to send".
$db->prepare(
    "INSERT INTO pl_calendar_events
        (tenant_id, connection_id, session_id, remote_event_id, pushed_at, sync_origin, pushed_digest)
     VALUES (1, 1, :sid, 'remote-1', NOW(), 'local', :digest)"
)->execute(['sid' => $sessionId, 'digest' => $first]);

$link = $db->query("SELECT * FROM pl_calendar_events WHERE session_id = {$sessionId}")->fetch();
T::same($first, (string) $link['pushed_digest'], 'the digest is stored alongside the link');


T::group('Rule 1: last writer wins, and a conflict keeps the local value');

// Remote change is OLDER than our push: we win, and the session does not move.
$db->exec("UPDATE pl_calendar_events SET pushed_at = NOW() WHERE session_id = {$sessionId}");

$before = $db->query("SELECT scheduled_at FROM pl_sessions WHERE id = {$sessionId}")->fetch()['scheduled_at'];

$fake->nextChanges = [[
    'id' => 'remote-1', 'deleted' => false, 'title' => 'Whatever',
    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('+9 days')),
    'ends_at'   => gmdate('Y-m-d H:i:s', strtotime('+9 days +1 hour')),
    'updated_at' => gmdate('Y-m-d H:i:s', strtotime('-1 hour')),   // stale
    'etag' => 'e', 'session_id' => (string) $sessionId,
]];

$result = CalendarSync::pull(1, $connection + ['provider' => 'google']);

$after = $db->query("SELECT scheduled_at FROM pl_sessions WHERE id = {$sessionId}")->fetch()['scheduled_at'];

T::same($before, $after, 'a remote change older than our push does NOT move the session');
T::same(1, $result['conflicts'], 'and it is counted as a conflict rather than silently dropped');


T::group('Rule 1: a newer remote change does move it, and says so');

$db->exec("UPDATE pl_calendar_events SET pushed_at = NOW() - INTERVAL 2 HOUR WHERE session_id = {$sessionId}");
$db->exec('TRUNCATE TABLE pl_org_events');
$db->exec('TRUNCATE TABLE pl_notifications');

$newStart = gmdate('Y-m-d H:i:s', strtotime('+9 days 14:00'));

$fake->nextChanges = [[
    'id' => 'remote-1', 'deleted' => false, 'title' => 'Whatever',
    'starts_at' => $newStart,
    'ends_at'   => gmdate('Y-m-d H:i:s', strtotime($newStart . ' +45 minutes')),
    'updated_at' => gmdate('Y-m-d H:i:s'),   // fresh
    'etag' => 'e', 'session_id' => (string) $sessionId,
]];

$result = CalendarSync::pull(1, $connection + ['provider' => 'google']);

$row = $db->query("SELECT scheduled_at, duration_minutes FROM pl_sessions WHERE id = {$sessionId}")->fetch();

T::same(strtotime($newStart), strtotime((string) $row['scheduled_at']), 'the session moved to the new time');
T::same(45, (int) $row['duration_minutes'], 'and took the new length with it');
T::same(1, $result['applied'], 'the change is counted');
T::same(0, $result['conflicts'], 'and is not a conflict');

$timeline = $db->query("SELECT * FROM pl_org_events WHERE event_type = 'session.moved_remotely'")->fetch();
T::ok($timeline !== false, 'the move is on the timeline');
T::ok(str_contains((string) $timeline['summary'], 'was'),
    'saying where it moved FROM — a time that changes with no explanation is what breaks trust in a sync');
T::same(0, (int) $timeline['client_visible'],
    'and it is coach-side: the client sees the new time, not the plumbing that produced it');

$notified = $db->query("SELECT COUNT(*) AS c FROM pl_notifications WHERE event_type = 'session.scheduled'")->fetch();
T::ok((int) $notified['c'] > 0, 'and the firm is told');

$linkAfter = $db->query("SELECT * FROM pl_calendar_events WHERE session_id = {$sessionId}")->fetch();
T::same('remote', (string) $linkAfter['sync_origin'],
    'the link records that this change came from over there');
T::ok((string) $linkAfter['pushed_digest'] !== $first,
    'and is re-digested to the new value, so the next push does not bounce it straight back');


T::group('An echo is not a move');

$db->exec('TRUNCATE TABLE pl_org_events');
$current = $db->query("SELECT scheduled_at FROM pl_sessions WHERE id = {$sessionId}")->fetch()['scheduled_at'];

$fake->nextChanges = [[
    'id' => 'remote-1', 'deleted' => false, 'title' => 'Whatever',
    'starts_at' => (string) $current,
    'ends_at'   => gmdate('Y-m-d H:i:s', strtotime((string) $current . ' +45 minutes')),
    'updated_at' => gmdate('Y-m-d H:i:s'),
    'etag' => 'e', 'session_id' => (string) $sessionId,
]];

$result = CalendarSync::pull(1, $connection + ['provider' => 'google']);

T::same(0, $result['applied'], 'reading back the time we already have changes nothing');
T::same(0, (int) $db->query('SELECT COUNT(*) AS c FROM pl_org_events')->fetch()['c'],
    'and produces no timeline noise');


T::group('Rule 2: a remote delete unlinks, it does not cancel');

$db->exec('TRUNCATE TABLE pl_notifications');

$fake->nextChanges = [[
    'id' => 'remote-1', 'deleted' => true, 'title' => '', 'starts_at' => null,
    'ends_at' => null, 'updated_at' => gmdate('Y-m-d H:i:s'), 'etag' => null, 'session_id' => null,
]];

$result = CalendarSync::pull(1, $connection + ['provider' => 'google']);

T::same(1, $result['unlinked'], 'the link is unlinked');

$marked = $db->query("SELECT * FROM pl_calendar_events WHERE session_id = {$sessionId}")->fetch();
T::ok($marked !== false, 'the link ROW survives, marked');
T::ok($marked['unlinked_at'] !== null, 'with the moment it was unlinked');

$survivor = $db->query("SELECT * FROM pl_sessions WHERE id = {$sessionId}")->fetch();
T::ok($survivor !== false, 'BUT THE SESSION SURVIVES');
T::same('scheduled', (string) $survivor['status'],
    'and is not cancelled — "off my calendar" and "call this off" are different things');

$told = $db->query("SELECT COUNT(*) AS c FROM pl_notifications WHERE user_id = {$coachId}")->fetch();
T::ok((int) $told['c'] > 0, 'and the coach is told the entry went');


T::group('Rule 2 has teeth: an unlinked event is not recreated');

/**
 * The bug this exists to prevent: deleting the link row on a remote delete
 * leaves the session looking like one that has never been synced, so the next
 * push recreates the event five minutes later. A coach who deletes a meeting
 * and watches it come back twice will switch the feature off, and be right to.
 */
$fake->upserts = [];
$conn = $db->query('SELECT * FROM pl_calendar_connections WHERE id = 1')->fetch();

CalendarSync::push(1, $conn);

$recreated = array_filter($fake->upserts,
    static fn (array $u): bool => (int) ($u['event']['session_id'] ?? 0) === $sessionId);

T::same(0, count($recreated),
    'the next push does NOT put the deleted event back');

// But a genuine change should bring it back — that is what the screen promises.
$db->exec("UPDATE pl_sessions SET scheduled_at = scheduled_at + INTERVAL 1 DAY WHERE id = {$sessionId}");
$fake->upserts = [];

CalendarSync::push(1, $conn);

$returned = array_filter($fake->upserts,
    static fn (array $u): bool => (int) ($u['event']['session_id'] ?? 0) === $sessionId);

T::same(1, count($returned), 'but moving the session does bring it back');
T::same(null, array_values($returned)[0]['remote_id'],
    'as a NEW event, not a patch of the id that no longer exists over there');
T::same(null, $db->query("SELECT unlinked_at FROM pl_calendar_events WHERE session_id = {$sessionId}")->fetch()['unlinked_at'],
    'and the unlink mark is cleared');


T::group('An unknown remote event is somebody\'s dentist appointment');

$before = (int) $db->query('SELECT COUNT(*) AS c FROM pl_sessions')->fetch()['c'];

$fake->nextChanges = [[
    'id' => 'not-ours-at-all', 'deleted' => false, 'title' => 'Dentist',
    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days')),
    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days +30 minutes')),
    'updated_at' => gmdate('Y-m-d H:i:s'), 'etag' => null, 'session_id' => null,
]];

$result = CalendarSync::pull(1, $connection + ['provider' => 'google']);

T::same(0, $result['applied'], 'it is ignored');
T::same($before, (int) $db->query('SELECT COUNT(*) AS c FROM pl_sessions')->fetch()['c'],
    'and absolutely not adopted as a coaching session');


T::group('An expired sync token is an instruction, not an error');

$db->exec("UPDATE pl_calendar_connections SET sync_token = 'stale-token' WHERE id = 1");
$fake->reset = true;
$fake->nextChanges = [];

$conn = $db->query('SELECT * FROM pl_calendar_connections WHERE id = 1')->fetch();
CalendarSync::pull(1, $conn);

T::same(null, $db->query('SELECT sync_token FROM pl_calendar_connections WHERE id = 1')->fetch()['sync_token'],
    'the token is cleared so the next run reads in full — ignoring this is how a sync silently stops seeing changes');

$fake->reset = false;


T::group('Tokens are stored encrypted, never in the clear');

$stored = $db->query('SELECT access_token, refresh_token FROM pl_calendar_connections WHERE id = 1')->fetch();

T::ok(!str_contains((string) $stored['refresh_token'], 'refresh-token'),
    'the refresh token is not readable in the column');
T::ok(str_starts_with((string) $stored['refresh_token'], 'ptg1:'),
    'it carries the format marker, so a future rotation is detectable');
T::same('refresh-token', Secrets::decrypt((string) $stored['refresh_token']),
    'and comes back with the key');


T::group('Provider payload parsing');

/**
 * The fake cannot check these: they are about the shape of what Google and
 * Microsoft actually send. The payloads below are trimmed from the providers'
 * own documented responses.
 */
$google = new GoogleProvider();
$microsoft = new MicrosoftProvider();

T::same('google', $google->key(), 'google identifies itself');
T::same('microsoft', $microsoft->key(), 'so does microsoft');

$authUrl = $google->authorizeUrl('the-state', 'https://acme.pilotagehq.com/calendar/callback/google', 'chal');

T::ok(str_contains($authUrl, 'access_type=offline'),
    'google is asked for offline access — without it there is no refresh token');
T::ok(str_contains($authUrl, 'prompt=consent'),
    'and for consent, because Google withholds the refresh token on silent re-auth');
T::ok(str_contains($authUrl, 'code_challenge=chal'), 'PKCE is included');
T::ok(str_contains($authUrl, 'state=the-state'), 'and the state nonce');
T::ok(!str_contains($authUrl, 'client_secret'), 'the secret is never in a URL the browser sees');

$msUrl = $microsoft->authorizeUrl('the-state', 'https://acme.pilotagehq.com/calendar/callback/microsoft');

T::ok(str_contains($msUrl, 'offline_access'),
    'microsoft is asked for offline_access — the scope that returns a refresh token');
T::ok(str_contains(rawurldecode($msUrl), 'Calendars.ReadWrite'), 'and for calendar write access');
T::ok(!str_contains($msUrl, 'client_secret'), 'again, no secret in the URL');


T::group('Pushing');

$db->exec('TRUNCATE TABLE pl_calendar_events');
$fake->upserts = [];
$fake->deletes = [];

$conn = $db->query('SELECT * FROM pl_calendar_connections WHERE id = 1')->fetch();
$pushed = CalendarSync::push(1, $conn);

T::same(1, $pushed, 'the one in-range session is pushed');
T::same(1, count($fake->upserts), 'exactly one call was made');
T::same(null, $fake->upserts[0]['remote_id'], 'as a create, since there was no link');
T::ok(str_contains((string) $fake->upserts[0]['event']['title'], 'Alpha'), 'carrying the client name');

$link = $db->query("SELECT * FROM pl_calendar_events WHERE session_id = {$sessionId}")->fetch();
T::ok($link !== false, 'a link is recorded');
T::same('remote-1', (string) $link['remote_event_id'], 'with the provider\'s id');
T::ok($link['pushed_digest'] !== null, 'and the digest of what was sent');

// Rule 3, end to end.
$fake->upserts = [];
T::same(0, CalendarSync::push(1, $conn), 'pushing again sends nothing');
T::same(0, count($fake->upserts), 'and makes no API call at all — an unchanged session is free');

// A change goes out as a patch of the existing event, not a duplicate.
$db->exec("UPDATE pl_sessions SET location = 'The workshop' WHERE id = {$sessionId}");
$fake->upserts = [];

T::same(1, CalendarSync::push(1, $conn), 'a changed session is pushed');
T::same('remote-1', $fake->upserts[0]['remote_id'],
    'as an update of the existing event, not a second copy in their diary');


T::group('Cancelling here does remove the event there');

$fake->deletes = [];
$db->exec("UPDATE pl_sessions SET status = 'cancelled' WHERE id = {$sessionId}");

CalendarSync::push(1, $conn);

T::same(['remote-1'], $fake->deletes,
    'cancelling a session removes the calendar entry — unlike the reverse, this one is unambiguous');
T::same(0, (int) $db->query("SELECT COUNT(*) AS c FROM pl_calendar_events WHERE session_id = {$sessionId}")->fetch()['c'],
    'and the link goes with it');


T::group('A dead connection asks to be reconnected rather than retrying forever');

class RefusingProvider extends FakeProvider
{
    public function refresh(string $r): ?array { return null; }
}

CalendarSync::useProviders(['google' => new RefusingProvider()]);

$db->exec("UPDATE pl_calendar_connections SET access_expires_at = NOW() - INTERVAL 1 HOUR WHERE id = 1");
$expired = $db->query('SELECT * FROM pl_calendar_connections WHERE id = 1')->fetch();

T::same(null, CalendarSync::accessToken($expired), 'a refused refresh yields no token');

$after = $db->query('SELECT * FROM pl_calendar_connections WHERE id = 1')->fetch();
T::same('needs_reauth', (string) $after['status'], 'and the connection is marked as needing re-auth');
T::ok(str_contains((string) $after['last_error'], 'Reconnect'), 'with something a coach can act on');

// A needs_reauth connection is skipped by the tick, so it does not hammer a
// revoked grant every five minutes forever.
$totals = CalendarSync::run(1);
T::same(0, $totals['pushed'] + $totals['pulled'], 'and the tick leaves it alone');

CalendarSync::useProviders(['google' => $fake]);


T::group('Unreadable credentials are a broken connection, not a crash');

$db->exec("UPDATE pl_calendar_connections
           SET access_expires_at = NOW() - INTERVAL 1 HOUR,
               refresh_token = 'ptg1:this-is-not-valid-ciphertext', status = 'active'
           WHERE id = 1");
$broken = $db->query('SELECT * FROM pl_calendar_connections WHERE id = 1')->fetch();

T::same(null, CalendarSync::accessToken($broken),
    'a token encrypted with a key we no longer have does not throw on a page load');
T::same('needs_reauth', (string) $db->query('SELECT status FROM pl_calendar_connections WHERE id = 1')->fetch()['status'],
    'it just asks them to reconnect');


// The real providers go back, so nothing after this file inherits the fake.
CalendarSync::resetProviders();


T::group('One callback URI for every firm');

/**
 * Providers match redirect URIs character for character, and every firm lives on
 * its own subdomain. Per-tenant callbacks therefore mean registering one URI per
 * customer — bookkeeping at ten firms, impossible at a thousand.
 *
 * So the callback lands on the bare domain and recovers the firm from the state
 * nonce. These assertions are about that recovery, because it is the whole
 * mechanism: get it wrong and either nobody can connect, or somebody connects
 * their calendar to another firm's account.
 */
$db->exec('TRUNCATE TABLE pl_oauth_states');

Config::set('calendar.google.client_id', 'test-client-id');

$state = bin2hex(random_bytes(32));
$db->prepare(
    "INSERT INTO pl_oauth_states (tenant_id, user_id, provider, state, verifier, expires_at)
     VALUES (1, :uid, 'google', :state, 'v', NOW() + INTERVAL 900 SECOND)"
)->execute(['uid' => $coachId, 'state' => $state]);

$lookup = static function (string $state) use ($db): array|false {
    $stmt = $db->prepare(
        "SELECT s.*, t.slug FROM pl_oauth_states s
         JOIN pl_tenants t ON t.id = s.tenant_id
         WHERE s.state = :state AND s.provider = 'google'
           AND s.used_at IS NULL AND s.expires_at > NOW()"
    );
    $stmt->execute(['state' => $state]);

    return $stmt->fetch();
};

$found = $lookup($state);

T::ok($found !== false, 'the state recovers its row without a tenant in scope');
T::same(1, (int) $found['tenant_id'], 'and says which firm it belongs to');
T::same($coachId, (int) $found['user_id'], 'and which person');
T::same('acme', (string) $found['slug'], 'and the slug to send them home to');

// The authorize URL must point at the apex, not the tenant. If these ever
// diverge the token exchange fails, because the exchange has to present the
// same redirect_uri the authorize request used.
$authUrl = (new GoogleProvider())->authorizeUrl('s', app_url('/calendar/callback/google'));
T::ok(str_contains(rawurldecode($authUrl), '/calendar/callback/google'), 'the callback path is in the authorize URL');
T::ok(!str_contains(rawurldecode($authUrl), 'acme.'), 'and it is NOT a tenant subdomain — one URI serves every firm');

// Single use, decided by the UPDATE rather than by a prior read: a replayed
// callback has to lose the race rather than be honoured twice.
$consume = static function (int $id) use ($db): bool {
    $stmt = $db->prepare('UPDATE pl_oauth_states SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
    $stmt->execute(['id' => $id]);

    return $stmt->rowCount() === 1;
};

T::same(true, $consume((int) $found['id']), 'the first callback consumes the state');
T::same(false, $consume((int) $found['id']), 'a replay does not');
T::same(false, $lookup($state), 'and it no longer resolves at all');

// Expiry is enforced in the same lookup.
$stale = bin2hex(random_bytes(32));
$db->prepare(
    "INSERT INTO pl_oauth_states (tenant_id, user_id, provider, state, expires_at)
     VALUES (1, :uid, 'google', :state, NOW() - INTERVAL 1 SECOND)"
)->execute(['uid' => $coachId, 'state' => $stale]);

T::same(false, $lookup($stale), 'an expired state does not resolve either');

// And a state minted for one provider cannot be redeemed at another's callback.
$crossed = bin2hex(random_bytes(32));
$db->prepare(
    "INSERT INTO pl_oauth_states (tenant_id, user_id, provider, state, expires_at)
     VALUES (1, :uid, 'microsoft', :state, NOW() + INTERVAL 900 SECOND)"
)->execute(['uid' => $coachId, 'state' => $crossed]);

T::same(false, $lookup($crossed),
    "a Microsoft state is not accepted at Google's callback");
