<?php

declare(strict_types=1);

/**
 * M11: notifications, preferences, digests, and unsubscribe.
 *
 * Two groups carry the module.
 *
 * "Transactional mail cannot be switched off" is the compliance boundary in
 * FR-11.5. It is enforced in code rather than in data specifically so that a
 * bug in the preferences screen, or a well-meaning support request, cannot
 * quietly turn off the email telling a client their coach needs a signature.
 *
 * "A digest is sent once per window" is the correctness boundary. The tick runs
 * every five minutes; if the idempotency record does not hold, a coach receives
 * their morning digest 288 times.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Mailer;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\TaskRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Digest;
use Bizorca\Pilotage\Services\NotificationDispatch;
use Bizorca\Pilotage\Services\Notifications;
use Bizorca\Pilotage\Services\NotificationTemplates;
use Bizorca\Pilotage\Services\Unsubscribe;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_unsubscribe_tokens', 'pl_notification_templates', 'pl_digests',
          'pl_notification_prefs', 'pl_notifications', 'pl_tasks',
          'pl_engagements', 'pl_client_orgs', 'pl_users', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status,digest_hour,client_digest_day)
           VALUES (1,'acme','Acme Advisory','active',13,1),(2,'other','Other','active',13,1)");

$tenant = $db->query('SELECT * FROM pl_tenants WHERE id = 1')->fetch();

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'Ada Coach', 'role' => 'coach', 'status' => 'active']);
$orgId = (new ClientOrgRepository(1))->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active']);
$danaId = $users->create(['email' => 'dana@alpha.test', 'name' => 'Dana Owner', 'role' => 'client_owner', 'client_org_id' => $orgId, 'status' => 'active']);
$joId = $users->create(['email' => 'jo@alpha.test', 'name' => 'Jo Member', 'role' => 'client_member', 'client_org_id' => $orgId, 'status' => 'active']);
$engId = (new EngagementRepository(1))->createEngagement([
    'client_org_id' => $orgId, 'title' => 'Q3', 'status' => 'active', 'coach_user_id' => $coachId,
]);


T::group('The catalogue is the contract');

T::ok(count(Notifications::CATALOGUE) > 10, 'there is a catalogue');

foreach (Notifications::CATALOGUE as $type => $meta) {
    if (!in_array($meta['default'], Notifications::CHANNELS, true)) {
        T::ok(false, "{$type} has a valid default channel");
    }
    if (!in_array($meta['audience'], [Notifications::FIRM, Notifications::CLIENT, Notifications::BOTH], true)) {
        T::ok(false, "{$type} has a valid audience");
    }
    if ($meta['transactional'] && $meta['default'] !== Notifications::EMAIL) {
        T::ok(false, "{$type} is transactional, so its default must be email");
    }
    if (!in_array('recipient_name', $meta['vars'], true)) {
        T::ok(false, "{$type} offers recipient_name to templates");
    }
}
T::ok(true, 'every event has a valid default, audience, and variable set');

T::throws(InvalidArgumentException::class,
    static fn () => Notifications::queue(1, $coachId, 'not.a.real.event', 'x'),
    'queueing an unknown event throws rather than sending nothing quietly');


T::group('Queueing records; it does not send');

Mailer::startCapturing();

$n1 = Notifications::queue(1, $coachId, 'task.completed', 'Dana finished "Reconcile the bank"',
    null, '/tasks/1', ['client_org_id' => $orgId, 'object_type' => 'task', 'object_id' => 1]);

T::ok($n1 > 0, 'a notification is queued');
T::same(0, count(Mailer::captured()), 'and NOTHING was sent — that is the whole point');
T::same('pending', $db->query("SELECT delivery FROM pl_notifications WHERE id = {$n1}")->fetch()['delivery'],
    'it sits undecided until the dispatcher reaches it');

T::throws(InvalidArgumentException::class,
    static fn () => Notifications::queue(1, $coachId, 'task.completed', 'x', null, 'https://evil.test/x'),
    'an absolute URL is refused — the host belongs to the tenant');
T::throws(InvalidArgumentException::class,
    static fn () => Notifications::queue(1, $coachId, 'task.completed', ''),
    'an empty title is refused');


T::group('queueOnce does not repeat itself');

$first = Notifications::queueOnce(1, $danaId, 'task.overdue', 'Overdue: file the return',
    null, '/tasks/9', ['object_type' => 'task', 'object_id' => 9]);
$second = Notifications::queueOnce(1, $danaId, 'task.overdue', 'Overdue: file the return',
    null, '/tasks/9', ['object_type' => 'task', 'object_id' => 9]);

T::ok($first > 0, 'the first nightly sweep queues it');
T::same(null, $second, 'the second does not — a sweep rediscovers the same overdue task every night');

$other = Notifications::queueOnce(1, $danaId, 'task.overdue', 'Overdue: something else',
    null, '/tasks/10', ['object_type' => 'task', 'object_id' => 10]);
T::ok($other > 0, 'but a different task still gets through');

T::throws(InvalidArgumentException::class,
    static fn () => Notifications::queueOnce(1, $danaId, 'task.overdue', 'x'),
    'queueOnce without an object throws — there is nothing for it to be once about');


T::group('queueMany skips the actor');

$queued = Notifications::queueMany(1, [$danaId, $joId, $coachId], 'issue.raised',
    'Dana raised "Cash is tight"', null, '/issues/1',
    ['client_org_id' => $orgId], $danaId);

T::same(2, $queued, 'everyone but the person who did it');


T::group('Preferences: defaults, overrides, and the wall');

T::same(Notifications::DIGEST, Notifications::channelFor(1, $coachId, 'task.completed'),
    'a coach hears about completed tasks in the digest by default');
T::same(Notifications::EMAIL, Notifications::channelFor(1, $coachId, 'message.mentioned'),
    'but an @-mention is immediate — FR-11.2');

Notifications::setPreference(1, $coachId, 'task.completed', Notifications::OFF);
T::same(Notifications::OFF, Notifications::channelFor(1, $coachId, 'task.completed'),
    'an override wins over the default');

T::same(Notifications::DIGEST, Notifications::channelFor(1, $danaId, 'task.completed'),
    "and it is one person's override, not everyone's");

T::throws(InvalidArgumentException::class,
    static fn () => Notifications::setPreference(1, $coachId, 'task.completed', 'carrier_pigeon'),
    'an unknown channel is refused');


T::group('Transactional mail cannot be switched off');

$transactional = [];

foreach (Notifications::CATALOGUE as $type => $meta) {
    if ($meta['transactional']) {
        $transactional[] = $type;
    }
}

T::ok($transactional !== [], 'some events are transactional');

foreach ($transactional as $type) {
    T::throws(InvalidArgumentException::class,
        static fn () => Notifications::setPreference(1, $danaId, $type, Notifications::OFF),
        "{$type} refuses to be switched off");
}

// The belt-and-braces half: even with a row written straight into the table,
// the resolver ignores it. A preferences screen with a bug, or a hand-edited
// row, must not be able to silence a document a client has to acknowledge.
$db->prepare('INSERT INTO pl_notification_prefs (tenant_id, user_id, event_type, channel)
              VALUES (1, :u, :t, :c)')
   ->execute(['u' => $danaId, 't' => 'document.delivered', 'c' => 'off']);

T::same(Notifications::EMAIL, Notifications::channelFor(1, $danaId, 'document.delivered'),
    'a hand-written "off" row is ignored for a transactional event');

$db->exec("DELETE FROM pl_notification_prefs WHERE user_id = {$danaId} AND event_type = 'document.delivered'");


T::group('Dispatch decides, and decides once');

$db->exec('TRUNCATE TABLE pl_notifications');
Mailer::startCapturing();

$immediate  = Notifications::queue(1, $danaId, 'document.delivered', 'Your Q3 report is ready', null, '/documents/1', ['client_org_id' => $orgId]);
$held       = Notifications::queue(1, $coachId, 'worksheet.submitted', 'Alpha returned the health check', null, '/worksheets/results/1', ['client_org_id' => $orgId]);
$suppressed = Notifications::queue(1, $coachId, 'task.completed', 'Dana finished something', null, '/tasks/2', ['client_org_id' => $orgId]);

Notifications::setPreference(1, $joId, 'issue.raised', Notifications::IN_APP);
$inApp = Notifications::queue(1, $joId, 'issue.raised', 'A new issue was raised', null, '/issues/2', ['client_org_id' => $orgId]);

$result = NotificationDispatch::run(1);

T::same(1, $result['sent'], 'the immediate one is sent');
T::same(1, $result['held'], 'the digest one is held');
T::same(1, $result['in_app'], 'the inbox-only one is decided and not mailed');
T::same(1, $result['suppressed'], 'the switched-off one is suppressed');

$deliveries = [];
foreach ($db->query('SELECT id, delivery, emailed_at FROM pl_notifications')->fetchAll() as $row) {
    $deliveries[(int) $row['id']] = [$row['delivery'], $row['emailed_at']];
}

T::same('immediate', $deliveries[$immediate][0], 'the delivered document was decided immediate');
T::ok($deliveries[$immediate][1] !== null, 'and stamped with when it left');
T::same('digest', $deliveries[$held][0], 'the worksheet notice is held');
T::same(null, $deliveries[$held][1], 'and has not left yet');
T::same('in_app', $deliveries[$inApp][0], 'the inbox-only one is in_app');
T::same(null, $deliveries[$inApp][1], 'and never gets an emailed_at');
T::same('suppressed', $deliveries[$suppressed][0], 'the off one is suppressed');

T::same(1, count(Mailer::captured()), 'exactly one email left the building');

$sent = Mailer::captured()[0];
T::ok(str_contains((string) $sent['to'][0], 'dana@alpha.test'), 'it went to the client');
T::ok(str_contains((string) $sent['sender'], 'Acme Advisory'),
    'signed by the firm, because a client-side reader should see their advisor');
T::ok(str_contains((string) $sent['text_body'], 'Alpha Manufacturing'),
    'and it says which relationship it belongs to');

// Running again must be a no-op. This is the property that lets a five-minute
// tick exist at all.
Mailer::startCapturing();
$again = NotificationDispatch::run(1);
T::same(0, $again['sent'] + $again['held'] + $again['in_app'] + $again['suppressed'],
    'a second dispatch finds nothing left to decide');
T::same(0, count(Mailer::captured()), 'and sends nothing');


T::group('A suppressed notification is hidden; an in-app one is not');

$inbox = Notifications::inbox(1, $coachId);
$types = array_column($inbox, 'event_type');

T::ok(in_array('worksheet.submitted', $types, true), 'a held item still shows in the inbox');
T::ok(!in_array('task.completed', $types, true), 'a suppressed item does not');

T::same(1, Notifications::unreadCount(1, $joId), 'the inbox-only reader has one unread');
T::same(1, Notifications::markRead(1, $joId, $inApp), 'marking it read touches one row');
T::same(0, Notifications::unreadCount(1, $joId), 'and the count drops');
T::same(0, Notifications::markRead(1, $joId, $inApp), 'marking it again touches nothing');


T::group('The inbox is tenant-scoped');

$otherUsers = new UserRepository(2);
$intruder = $otherUsers->create(['email' => 'x@other.test', 'name' => 'X', 'role' => 'coach', 'status' => 'active']);

T::same(0, count(Notifications::inbox(2, $coachId)), "another tenant cannot read this tenant's inbox");
T::same(null, Notifications::find(2, $coachId, $held), 'nor fetch one row of it');
T::same(null, Notifications::find(1, $joId, $held), "nor can a different reader in the same tenant");
T::ok(Notifications::find(1, $coachId, $held) !== null, 'the actual reader can');


T::group('Digest windows are computed in SQL, on the UTC clock');

$daily = Digest::dailyWindow(13);
T::ok(str_ends_with($daily['end'], '13:00:00'), 'the daily boundary lands on the configured hour');
T::same(86400, strtotime($daily['end']) - strtotime($daily['start']), 'and the window is one day wide');
T::ok(strtotime($daily['end']) <= time() + 2, 'the boundary is in the past, not the future');

$weekly = Digest::weeklyWindow(1, 13);   // 1 = Monday
T::same('Monday', date('l', strtotime($weekly['end'])), 'the weekly boundary lands on the configured day');
T::ok(str_ends_with($weekly['end'], '13:00:00'), 'at the configured hour');
T::same(7 * 86400, strtotime($weekly['end']) - strtotime($weekly['start']), 'and the window is one week wide');
T::ok(strtotime($weekly['end']) <= time() + 2, 'and it is the most recent one, not the next one');

/**
 * The same windows under production's connection collation.
 *
 * Background: the weekly boundary was once computed with a MySQL user variable
 * read back in the same SELECT that assigned it. MySQL's own documentation says
 * the evaluation order of expressions involving user variables is undefined.
 * Locally it evaluated in the helpful order and worked; on SiteGround it did
 * not, and the tick threw "illegal mix of collations (utf8mb4_general_ci,
 * COERCIBLE) and (latin1_swedish_ci, IMPLICIT)" — an unassigned user variable
 * carrying the server default. Every digest stopped, silently.
 *
 * BE CLEAR ABOUT WHAT THIS TEST DOES AND DOES NOT DO. Changing the connection
 * collation does not reproduce the fault, because the fault was evaluation
 * ORDER, which cannot be forced from here — the buggy version passes this
 * group. What it does check is that the windows compute correctly under the
 * collation production actually uses, which is worth knowing on its own.
 *
 * The regression itself is guarded structurally, immediately below.
 */
$originalCollation = (string) $db->query("SELECT @@session.collation_connection AS c")->fetch()['c'];
$db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

$w = Digest::weeklyWindow(1, 13);
T::same('Monday', date('l', strtotime($w['end'])),
    'the weekly window computes under production\'s connection collation');
T::same(7 * 86400, strtotime($w['end']) - strtotime($w['start']), 'and is still a week wide');

$d = Digest::dailyWindow(13);
T::ok(str_ends_with($d['end'], '13:00:00'), 'so does the daily window');
T::same(86400, strtotime($d['end']) - strtotime($d['start']), 'and is still a day wide');

$db->exec("SET NAMES utf8mb4 COLLATE " . $originalCollation);
$db->exec("SET time_zone = '+00:00'");   // SET NAMES does not touch it, but be explicit

/**
 * The actual guard, and it is structural because the behavioural one cannot be
 * written: no user variables in the time-window code.
 *
 * A crude test, deliberately. The bug it prevents is undefined behaviour that
 * manifests only on some servers, so there is no input that reliably triggers
 * it — which means the only reliable check is "do not write it that way". If
 * this ever fails, the fix is a derived table, not a smarter assertion.
 */
$codeOnly = static function (string $path): string {
    // Tokenised rather than regexed over the raw file: the first version of
    // this check matched the comment in Digest.php that DESCRIBES the old
    // `@cand :=` code, and failed on the fixed file. A guard that fires on its
    // own explanation is not a guard.
    $out = '';

    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $out .= is_array($token) ? $token[1] : $token;
    }

    return $out;
};

foreach (['src/Services/Digest.php', 'src/Services/CalendarSync.php', 'src/Services/Health.php',
          'src/Services/Reports.php', 'src/Services/Compliance.php'] as $file) {
    T::same(0, preg_match('/@[a-z_]+\s*:=/i', $codeOnly(dirname(__DIR__) . '/' . $file)),
        basename($file) . ' computes its windows without MySQL user variables');
}

// The clock invariant, stated as an assertion. If PHP and MySQL disagree here,
// every window in this file is wrong and so is everything else time-based.
$sqlNow = strtotime((string) $db->query('SELECT NOW() AS n')->fetch()['n']);
T::ok(abs($sqlNow - time()) <= 2, 'PHP and MySQL agree on what time it is');


T::group('The firm digest batches, and sends once per window');

$db->exec('TRUNCATE TABLE pl_notifications');
$db->exec('TRUNCATE TABLE pl_digests');
$db->exec("DELETE FROM pl_notification_prefs WHERE user_id = {$coachId}");
Mailer::startCapturing();

foreach (['Dana finished "Reconcile the bank"', 'Dana uploaded "Bank statement"', 'Alpha returned the health check'] as $i => $title) {
    Notifications::queue(1, $coachId, ['task.completed', 'document.uploaded', 'worksheet.submitted'][$i],
        $title, null, '/tasks/' . $i, ['client_org_id' => $orgId]);
}

NotificationDispatch::run(1);
T::same(0, count(Mailer::captured()), 'three events, and not one email yet');
T::same(3, count(Digest::heldItems(1, $coachId)), 'all three are held');

$sentCount = Digest::run($tenant);
T::same(1, $sentCount['firm'], 'the digest goes to one coach');

$captured = Mailer::captured();
$firmMail = null;
foreach ($captured as $m) {
    if (str_contains((string) $m['to'][0], 'coach@acme.test')) {
        $firmMail = $m;
    }
}

T::ok($firmMail !== null, 'the coach got one email');
T::ok(str_contains((string) $firmMail['subject'], '3 updates'), 'and it says how many things happened');
T::ok(str_contains((string) $firmMail['text_body'], 'Reconcile the bank'), 'it carries the first item');
T::ok(str_contains((string) $firmMail['text_body'], 'Bank statement'), 'and the second');
T::ok(str_contains((string) $firmMail['text_body'], 'health check'), 'and the third');
T::ok(str_contains((string) $firmMail['text_body'], 'Alpha Manufacturing'), 'grouped under the client it concerns');
T::ok(str_contains((string) $firmMail['sender'], 'Pilotage'),
    'signed by Pilotage — a coach reading their own digest is our customer, not their own firm');

T::same(0, count(Digest::heldItems(1, $coachId)), 'the held items are now spent');

Mailer::startCapturing();
$twice = Digest::run($tenant);
T::same(0, $twice['firm'], 'running the tick again does not send a second digest for the same window');
T::same(0, count(array_filter(Mailer::captured(),
    static fn (array $m): bool => str_contains((string) $m['to'][0], 'coach@acme.test'))),
    'and no second email');


T::group('An empty firm digest is not sent at all');

$db->exec('TRUNCATE TABLE pl_digests');
Mailer::startCapturing();

$empty = Digest::run($tenant);
T::same(0, $empty['firm'], 'nothing held means nothing sent — a routinely empty daily email gets filtered as noise');


T::group('The client weekly digest reports live state, not only events');

$db->exec('TRUNCATE TABLE pl_notifications');
$db->exec('TRUNCATE TABLE pl_digests');
$db->exec('TRUNCATE TABLE pl_tasks');
Mailer::startCapturing();

$tasks = new TaskRepository(1);
$overdueId = $tasks->createTask([
    'engagement_id' => $engId, 'title' => 'File the quarterly return',
    'owner_user_id' => $danaId, 'due_on' => date('Y-m-d', strtotime('-6 days')), 'status' => 'open',
]);
$tasks->createTask([
    'engagement_id' => $engId, 'title' => 'Draft the hiring plan',
    'owner_user_id' => $danaId, 'due_on' => date('Y-m-d', strtotime('+3 days')), 'status' => 'open',
]);
$tasks->createTask([
    'engagement_id' => $engId, 'title' => 'Something far away',
    'owner_user_id' => $danaId, 'due_on' => date('Y-m-d', strtotime('+90 days')), 'status' => 'open',
]);

$open = Digest::openCommitments(1, $danaId);
T::same(1, count($open['overdue']), 'one commitment is past due');
T::same(1, count($open['due']), 'one is due this week');
T::ok(count($open['due']) === 1, 'and one ninety days out is not this week\'s problem');

$sentCount = Digest::run($tenant);
T::ok($sentCount['client'] >= 1, 'the client weekly goes out on live state alone, with no queued events');

$clientMail = null;
foreach (Mailer::captured() as $m) {
    if (str_contains((string) $m['to'][0], 'dana@alpha.test')) {
        $clientMail = $m;
    }
}

T::ok($clientMail !== null, 'Dana got her weekly summary');
T::ok(str_contains((string) $clientMail['text_body'], 'Past due:'), 'it leads with what is past due');
T::ok(str_contains((string) $clientMail['text_body'], 'File the quarterly return'), 'naming it');
T::ok(str_contains((string) $clientMail['text_body'], 'Coming up this week:'), 'then what is coming');
T::ok(str_contains((string) $clientMail['text_body'], 'Draft the hiring plan'), 'naming that too');
T::ok(!str_contains((string) $clientMail['text_body'], 'Something far away'), 'and not the one ninety days out');
T::ok(str_contains((string) $clientMail['text_body'], 'File the quarterly return'), 'the call to action is the most overdue thing');
T::ok(str_contains((string) $clientMail['sender'], 'Acme Advisory'),
    'signed by the firm — the client sees their advisor, not us');
T::ok(str_contains((string) $clientMail['text_body'], 'no longer the right thing to be doing'),
    'and it invites renegotiation rather than nagging harder');

Mailer::startCapturing();
$twice = Digest::run($tenant);
T::same(0, $twice['client'], 'and it is once per week, not once per tick');


T::group('Tenant-editable copy (FR-11.4)');

T::same('Your Q3 report is ready',
    NotificationTemplates::render(1, 'document.delivered', 'Your Q3 report is ready', null, [])['subject'],
    'with no override, the module\'s own words are used');

NotificationTemplates::save(1, 'document.delivered',
    '{{firm_name}} has sent you {{document_title}}',
    'Hello {{recipient_name}}, your document is ready.', $coachId);

$rendered = NotificationTemplates::render(1, 'document.delivered', 'ignored', null, [
    'firm_name' => 'Acme Advisory', 'document_title' => 'the Q3 report', 'recipient_name' => 'Dana',
]);

T::same('Acme Advisory has sent you the Q3 report', $rendered['subject'], 'an override replaces the subject');
T::same('Hello Dana, your document is ready.', $rendered['body'], 'and the body');

T::throws(InvalidArgumentException::class,
    static fn () => NotificationTemplates::save(1, 'document.delivered', 'Hi {{naem}}', ''),
    'a mistyped variable is caught in the editor, not by a client receiving {{naem}}');

T::same(['naem'], NotificationTemplates::unknownVariables('document.delivered', 'Hi {{naem}}'),
    'and the editor is told which one');
T::same([], NotificationTemplates::unknownVariables('document.delivered', 'Hi {{recipient_name}}'),
    'a real one passes');

// Whatever gets past the save-time check must never reach a reader.
T::same('Hello Dana, your report is ready.',
    NotificationTemplates::substitute(
        'Hello {{recipient_name}}, your report is ready.{{leftover}}',
        ['recipient_name' => 'Dana']
    ),
    'anything still braced at render time is stripped, not shipped');

NotificationTemplates::save(1, 'document.delivered', '', '');
T::same(null, NotificationTemplates::find(1, 'document.delivered'),
    'clearing both fields removes the override rather than storing two empty strings');

T::same(0, count(array_filter(NotificationTemplates::all(2),
    static fn (array $r): bool => $r['customised'])), "another tenant does not inherit this tenant's copy");


T::group('Unsubscribe (FR-11.5)');

$token = Unsubscribe::tokenFor(1, $danaId);
T::ok(str_contains($token, '.'), 'the token is selector.verifier');

$stored = $db->query("SELECT * FROM pl_unsubscribe_tokens WHERE user_id = {$danaId}")->fetch();
T::ok(!str_contains($token, (string) $stored['verifier_hash']),
    'the verifier is stored only as a hash — a database leak is not a pile of working links');

$who = Unsubscribe::resolve($token);
T::same($danaId, $who['user_id'] ?? 0, 'a valid token resolves to its reader');
T::same(null, Unsubscribe::resolve('garbage'), 'a malformed token does not');

[$sel] = explode('.', $token);
T::same(null, Unsubscribe::resolve($sel . '.' . str_repeat('a', 64)),
    'a right selector with a wrong verifier does not');

$result = Unsubscribe::apply($token);
T::ok($result['switched_off'] > 0, 'it switches off the optional events');
T::ok($result['still_receiving'] !== [],
    'and says what will keep arriving — letting someone believe everything stopped is the real harm');

T::same(Notifications::OFF, Notifications::channelFor(1, $danaId, 'task.completed'), 'optional mail is off');
T::same(Notifications::EMAIL, Notifications::channelFor(1, $danaId, 'document.delivered'),
    'but a document they must acknowledge still arrives — that is the service, not marketing');

T::ok(Unsubscribe::apply($token) !== null,
    'clicking twice is not an error — people forward emails and click from two devices');

Unsubscribe::revoke(1, $danaId);
T::same(null, Unsubscribe::resolve($token), 'a revoked token stops working');


T::group('A disabled account is not mailed');

$db->exec('TRUNCATE TABLE pl_notifications');
$users->update($joId, ['status' => 'disabled']);
Mailer::startCapturing();

Notifications::queue(1, $joId, 'document.delivered', 'Something transactional', null, '/documents/2', ['client_org_id' => $orgId]);
$r = NotificationDispatch::run(1);

T::same(0, $r['sent'], 'even a transactional message is not sent to a disabled account');
T::same(1, $r['suppressed'], 'it is suppressed instead');
T::same(0, count(Mailer::captured()), 'and no mail left');

$users->update($joId, ['status' => 'active']);
Mailer::stopCapturing();


T::group('The modules actually emit');

/**
 * The catalogue and the pipeline are useless if nothing calls them. These
 * exercise the real service methods and then look in the queue, which is the
 * only way to catch an emit point that was never wired — a bug that is silent
 * by construction.
 */

$db->exec('TRUNCATE TABLE pl_notifications');

$queuedTypes = static function (int $userId) use ($db): array {
    return $db->query(
        'SELECT event_type FROM pl_notifications WHERE tenant_id = 1 AND user_id = ' . (int) $userId
    )->fetchAll(PDO::FETCH_COLUMN);
};

// -- an issue reaches both sides
\Bizorca\Pilotage\Services\Scorecard::raiseIssue(1, $engId, 'Cash is tight', 'Runway is four months.', 'ad_hoc', $coachId);

T::ok(in_array('issue.raised', $queuedTypes($danaId), true), 'raising an issue tells the client');
T::ok(!in_array('issue.raised', $queuedTypes($coachId), true), 'but not the coach who raised it');

// -- scheduling a session tells everyone but the scheduler
$db->exec('TRUNCATE TABLE pl_notifications');
\Bizorca\Pilotage\Services\SessionService::schedule(1, $engId, 'Quarterly review',
    date('Y-m-d H:i:s', strtotime('+3 days')), null, $coachId);

T::ok(in_array('session.scheduled', $queuedTypes($danaId), true), 'scheduling a session tells the client');
T::same([], $queuedTypes($coachId), 'and not the coach who booked it');

// -- a message tells the thread; a mention beats a message
$db->exec('TRUNCATE TABLE pl_notifications');
$coach = $db->query("SELECT * FROM pl_users WHERE id = {$coachId}")->fetch();
$threadId = \Bizorca\Pilotage\Services\Messaging::createThread(1, $engId, 'About the forecast', 'First thoughts.', $coach, true);
\Bizorca\Pilotage\Services\Messaging::addParticipant(1, $threadId, $danaId);
\Bizorca\Pilotage\Services\Messaging::addParticipant(1, $threadId, $joId);
\Bizorca\Pilotage\Services\Messaging::post(1, $threadId, 'Have a look @Dana Owner', $coach, true);

T::ok(in_array('message.mentioned', $queuedTypes($danaId), true), 'the person named gets the mention');
T::ok(!in_array('message.received', $queuedTypes($danaId), true),
    'and NOT also a "new message" — two emails about one sentence is the noise this module exists to remove');
T::ok(in_array('message.received', $queuedTypes($joId), true), 'the other participant gets the plain message');

// -- an internal note stays internal, including its notification
$db->exec('TRUNCATE TABLE pl_notifications');
\Bizorca\Pilotage\Services\Messaging::post(1, $threadId, 'Internal: fees are behind', $coach, false);

T::same([], $queuedTypes($danaId), 'an internal note queues nothing for the client — a title is a leak in a smaller font');
T::same([], $queuedTypes($joId), 'nor for any client-side participant');

// -- a worksheet assignment is transactional and reaches the client
$db->exec('TRUNCATE TABLE pl_notifications');
$wsId = \Bizorca\Pilotage\Services\Worksheets::create(1, ['title' => 'Health check', 'kind' => 'form'], $coachId);
\Bizorca\Pilotage\Services\Worksheets::addField(1, $wsId, ['label' => 'How is cash?', 'field_type' => 'scale', 'required' => 1]);
\Bizorca\Pilotage\Services\Worksheets::publish(1, $wsId);
\Bizorca\Pilotage\Services\Worksheets::assign(1, $wsId, $engId, $danaId, 'Baseline', null, $coachId);

T::ok(in_array('worksheet.assigned', $queuedTypes($danaId), true), 'assigning a worksheet tells the person it went to');

// -- and an escalation reaches the firm, not the client
$db->exec('TRUNCATE TABLE pl_notifications');
$stalled = (new TaskRepository(1))->createTask([
    'engagement_id' => $engId, 'title' => 'Send the P&L',
    'owner_user_id' => $danaId, 'due_on' => date('Y-m-d', strtotime('-9 days')), 'status' => 'open',
]);
$db->exec("UPDATE pl_tasks SET miss_count = 1 WHERE id = {$stalled}");
\Bizorca\Pilotage\Services\AccountabilityLoop::sweep(1);

T::ok(in_array('commitment.escalated', $queuedTypes($coachId), true), 'a stalled commitment tells the coach');
T::ok(!in_array('commitment.escalated', $queuedTypes($danaId), true),
    'and never the person who missed it — an escalation is a prompt to ask a better question, not a demerit');

Mailer::stopCapturing();


T::group('Nothing is queued for nobody');

/**
 * The fallback in firmRecipients, asserted directly. A notification queued for
 * an empty recipient list does not error and does not appear anywhere — it is
 * the most silent bug class this module can have, so it gets its own test.
 */
$orphan = (new EngagementRepository(1))->createEngagement([
    'client_org_id' => $orgId, 'title' => 'Nobody assigned', 'status' => 'active',
]);

T::ok(Notifications::firmRecipients(1, $orphan) !== [],
    'an engagement with no coach still resolves to someone at the firm');
T::ok(in_array($coachId, Notifications::firmRecipients(1, $orphan), true),
    'and that someone is a real firm-side user');

T::ok(Notifications::clientRecipients(1, $engId) !== [], 'the client side resolves too');
T::ok(!in_array($coachId, Notifications::clientRecipients(1, $engId), true),
    'and never includes anyone firm-side');


T::group('Unsubscribing actually stops the digest');

/**
 * The hole this closes was found in production, not here: Digest wrote to every
 * active client-side reader without consulting a preference, so unsubscribing
 * switched off every individual notice and the weekly summary kept arriving. A
 * digest is exactly the thing FR-11.5 says a person may opt out of, so it is
 * now an event in the catalogue like everything else.
 */
$db->exec('TRUNCATE TABLE pl_digests');
$db->exec('TRUNCATE TABLE pl_notifications');
$db->exec("DELETE FROM pl_notification_prefs");
Mailer::startCapturing();

T::ok(Notifications::isKnown('digest.weekly'), 'the weekly digest is a real event');
T::ok(Notifications::isKnown('digest.daily'), 'and so is the daily one');
T::same(false, Notifications::CATALOGUE['digest.weekly']['transactional'],
    'and it is NOT transactional — a summary is news about the work, not the work');

// It arrives by default.
$tenantRow = $db->query('SELECT * FROM pl_tenants WHERE id = 1')->fetch();
$sent = Digest::run($tenantRow);
T::ok($sent['client'] >= 1, 'by default the weekly summary goes out');

// Now switch it off the way a person would, and it stops.
$db->exec('TRUNCATE TABLE pl_digests');
Notifications::setPreference(1, $danaId, 'digest.weekly', Notifications::OFF);
Mailer::startCapturing();

$sent = Digest::run($tenantRow);

T::same(0, count(array_filter(Mailer::captured(),
    static fn (array $m): bool => str_contains((string) $m['to'][0], 'dana@alpha.test'))),
    'switching it off actually stops it');

// And the blanket unsubscribe covers it, which was the original complaint.
$db->exec('TRUNCATE TABLE pl_digests');
$db->exec("DELETE FROM pl_notification_prefs");
Notifications::unsubscribeAll(1, $danaId);
Mailer::startCapturing();

Digest::run($tenantRow);

T::same(0, count(array_filter(Mailer::captured(),
    static fn (array $m): bool => str_contains((string) $m['to'][0], 'dana@alpha.test'))),
    'and so does unsubscribing from everything — which it did not, before');

T::same(Notifications::OFF, Notifications::channelFor(1, $danaId, 'digest.weekly'),
    'the preference is genuinely stored');

$db->exec("DELETE FROM pl_notification_prefs");
Mailer::stopCapturing();
