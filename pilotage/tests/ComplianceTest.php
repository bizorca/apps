<?php

declare(strict_types=1);

/**
 * M14: retention, erasure, archival, and the coaching agreement.
 *
 * Everything here destroys something, so everything here is tested for what it
 * REFUSES to destroy. The two groups that matter:
 *
 * "Nothing is destroyed without warning" — the retention sweep schedules and
 * announces on one night and purges thirty days later, and the purge refuses to
 * act on a notice the owner was never actually emailed about. Without that
 * refusal, a mail outage on scheduling night produces a silent deletion a month
 * later: destruction with no warning, which is the exact thing the feature is
 * built to prevent.
 *
 * "Erasure defaults to pseudonymisation" — a client asks to be forgotten, and
 * the commitments they made are the engagement record their coach may be
 * obliged to keep. Deleting the user row would cascade and take the record with
 * it. So the person goes and the shape of what happened stays.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Mailer;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\TaskRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Compliance;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_erasure_requests', 'pl_retention_notices', 'pl_audit_log', 'pl_notifications',
          'pl_messages', 'pl_thread_participants', 'pl_threads', 'pl_org_events',
          'pl_session_attendees', 'pl_sessions', 'pl_tasks',
          // Worksheet responses too: erasureConflicts reads them, and a row left
          // behind by another test file would show up as a conflict here.
          'pl_worksheet_answers', 'pl_worksheet_responses', 'pl_worksheet_assignments',
          'pl_document_deliveries',
          'pl_engagements', 'pl_client_orgs', 'pl_users', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'other','Other','active')");

$users = new UserRepository(1);
$ownerId = $users->create(['email' => 'owner@acme.test', 'name' => 'Ada Owner', 'role' => 'firm_owner', 'status' => 'active']);
$orgId = (new ClientOrgRepository(1))->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active']);
$danaId = $users->create(['email' => 'dana@alpha.test', 'name' => 'Dana Owner', 'role' => 'client_owner', 'client_org_id' => $orgId, 'status' => 'active']);

$engagements = new EngagementRepository(1);
$live = $engagements->createEngagement(['client_org_id' => $orgId, 'title' => 'Live one', 'status' => 'active']);
$closed = $engagements->createEngagement(['client_org_id' => $orgId, 'title' => 'Long finished', 'status' => 'active']);

$tenant = static fn (): array => $db->query('SELECT * FROM pl_tenants WHERE id = 1')->fetch();


T::group('Retention defaults to keeping everything');

T::same(0, (int) $tenant()['retention_months'], 'a new firm keeps records forever');
T::same([], Compliance::scheduleRetention(1),
    'and the sweep schedules nothing — a default that deletes would destroy records because nobody visited a settings page');

T::throws(InvalidArgumentException::class,
    static fn () => Compliance::setRetention(1, -1, $ownerId), 'a negative period is refused');
T::throws(InvalidArgumentException::class,
    static fn () => Compliance::setRetention(1, 999, $ownerId), 'and an absurd one');

Compliance::setRetention(1, 24, $ownerId);
T::same(24, (int) $tenant()['retention_months'], 'a real policy is stored');
T::ok($tenant()['retention_confirmed_at'] !== null, 'and records that someone actively chose it');


T::group('Scheduling warns; it does not destroy');

// Close one engagement three years ago, well past the two-year policy.
$db->exec("UPDATE pl_engagements SET status = 'complete', completed_at = NOW() - INTERVAL 36 MONTH WHERE id = {$closed}");

$scheduled = Compliance::scheduleRetention(1);

T::same(1, count($scheduled), 'the long-closed engagement is scheduled');
T::same($closed, (int) $scheduled[0]['id'], 'and it is the right one');

T::ok($db->query("SELECT id FROM pl_engagements WHERE id = {$closed}")->fetch() !== false,
    'AND IT STILL EXISTS — scheduling is not destroying');

$notice = $db->query('SELECT * FROM pl_retention_notices')->fetch();
$daysOut = (int) round((strtotime((string) $notice['purge_after']) - time()) / 86400);

T::same(Compliance::NOTICE_DAYS, $daysOut, 'destruction is a full notice period away');
T::same(null, $notice['notified_at'], 'and nobody has been told yet');

T::same(0, count(Compliance::scheduleRetention(1)),
    'running the sweep again schedules nothing twice — the unique index sees to it');

T::same(0, count(Compliance::purge(1)), 'and a purge today destroys nothing');

// An active engagement is never scheduled, however old.
T::ok($db->query("SELECT id FROM pl_engagements WHERE id = {$live}")->fetch() !== false, 'the live one is untouched');
T::same(1, (int) $db->query('SELECT COUNT(*) AS c FROM pl_retention_notices')->fetch()['c'],
    'only closed engagements are ever scheduled');


T::group('Nothing is purged that the owner was not warned about');

// Wind the clock past the notice period WITHOUT having sent the warning.
$db->exec("UPDATE pl_retention_notices SET purge_after = NOW() - INTERVAL 1 DAY");

T::same([], Compliance::purge(1),
    'the period has passed but no email was sent, so nothing is destroyed');
T::ok($db->query("SELECT id FROM pl_engagements WHERE id = {$closed}")->fetch() !== false,
    'a mail outage on scheduling night must not become a silent deletion a month later');

Mailer::startCapturing();
$db->exec("UPDATE pl_retention_notices SET purge_after = NOW() + INTERVAL 30 DAY, notified_at = NULL");
$scheduledAgain = $db->query('SELECT n.id AS notice_id, e.title, o.name AS org_name
                              FROM pl_retention_notices n
                              JOIN pl_engagements e ON e.id = n.engagement_id
                              JOIN pl_client_orgs o ON o.id = e.client_org_id')->fetchAll();

$sent = Compliance::announce(1, $scheduledAgain, $tenant());

T::same(1, $sent, 'the owner is emailed');

$mail = Mailer::captured()[0];
T::ok(str_contains((string) $mail['subject'], 'scheduled for deletion'), 'the subject says what is about to happen');
T::ok(str_contains((string) $mail['text_body'], 'Long finished'), 'the body names the engagement');
T::ok(str_contains((string) $mail['text_body'], 'cannot be undone'), 'and is blunt about it being permanent');
T::ok(str_contains((string) $mail['text_body'], 'one click'), 'while saying stopping it is easy');
T::ok(str_contains((string) $mail['text_body'], 'cannot be switched off'),
    'and that this particular notice is not something you can have turned off');

T::ok($db->query('SELECT notified_at FROM pl_retention_notices')->fetch()['notified_at'] !== null,
    'and the notice records that it was sent');


T::group('Only then, and only after the period, is anything destroyed');

$db->exec("UPDATE pl_retention_notices SET purge_after = NOW() - INTERVAL 1 DAY");
$purged = Compliance::purge(1);

T::same(1, count($purged), 'now it is purged');
T::same($closed, $purged[0], 'the one that was scheduled');
T::same(false, $db->query("SELECT id FROM pl_engagements WHERE id = {$closed}")->fetch(),
    'and the engagement is genuinely gone');
T::ok($db->query("SELECT id FROM pl_engagements WHERE id = {$live}")->fetch() !== false,
    'while the live one is still there');

$receipt = $db->query("SELECT * FROM pl_audit_log WHERE action = 'retention.purged'")->fetch();
T::ok($receipt !== false, 'a receipt is in the audit log');
T::same($closed, (int) $receipt['object_id'], 'naming what was destroyed');
T::ok(str_contains((string) $receipt['meta'], 'Long finished'),
    'and keeping its title — after this the audit entry is the only trace it ever existed');

T::same([], Compliance::purge(1), 'and a second purge finds nothing left to do');


T::group('An owner can stop the clock, and the record shows they did');

$second = $engagements->createEngagement(['client_org_id' => $orgId, 'title' => 'Also finished', 'status' => 'active']);
$db->exec("UPDATE pl_engagements SET status = 'complete', completed_at = NOW() - INTERVAL 36 MONTH WHERE id = {$second}");
Compliance::scheduleRetention(1);

$noticeId = (int) $db->query("SELECT id FROM pl_retention_notices WHERE engagement_id = {$second}")->fetch()['id'];

T::throws(InvalidArgumentException::class,
    static fn () => Compliance::cancelNotice(1, $noticeId, $ownerId, '   '),
    'a reason is required — "someone clicked cancel" is not an answer to an auditor');

T::same(true, Compliance::cancelNotice(1, $noticeId, $ownerId, 'Litigation hold.'), 'with a reason it works');

$db->exec("UPDATE pl_retention_notices SET purge_after = NOW() - INTERVAL 1 DAY, notified_at = NOW()");

T::same([], Compliance::purge(1), 'and a cancelled notice is never acted on, however overdue');
T::ok($db->query("SELECT id FROM pl_engagements WHERE id = {$second}")->fetch() !== false, 'the engagement survives');

$cancelled = $db->query("SELECT * FROM pl_retention_notices WHERE id = {$noticeId}")->fetch();
T::same('Litigation hold.', (string) $cancelled['cancel_reason'], 'and the reason is on the record');


T::group('Archival, and reopening stops the clock');

$third = $engagements->createEngagement(['client_org_id' => $orgId, 'title' => 'Wrapping up', 'status' => 'active']);

T::same(true, Compliance::archive(1, $third, $ownerId), 'an engagement can be closed');
T::same('complete', (string) $engagements->find($third)['status'], 'and is complete');
T::ok($engagements->find($third)['completed_at'] !== null, 'with a completion date');
T::same(false, Compliance::archive(1, $third, $ownerId), 'closing it twice does nothing');

$db->exec("UPDATE pl_engagements SET completed_at = NOW() - INTERVAL 36 MONTH WHERE id = {$third}");
Compliance::scheduleRetention(1);

$pending = (int) $db->query("SELECT COUNT(*) AS c FROM pl_retention_notices
                             WHERE engagement_id = {$third} AND cancelled_at IS NULL")->fetch()['c'];
T::same(1, $pending, 'it gets scheduled for deletion');

T::same(true, Compliance::reopen(1, $third, $ownerId), 'and can be reopened');
T::same('active', (string) $engagements->find($third)['status'], 'back to active');
T::same(null, $engagements->find($third)['completed_at'], 'with the completion date cleared');

$stillPending = (int) $db->query("SELECT COUNT(*) AS c FROM pl_retention_notices
                                  WHERE engagement_id = {$third} AND cancelled_at IS NULL")->fetch()['c'];
T::same(0, $stillPending,
    'and reopening cancels the scheduled deletion — an engagement someone just picked back up is not one to delete in a fortnight');


T::group('Erasure: the conflict is the point');

$tasks = new TaskRepository(1);
$tasks->createTask(['engagement_id' => $live, 'title' => 'Send the P&L', 'owner_user_id' => $danaId, 'status' => 'open']);
$tasks->createTask(['engagement_id' => $live, 'title' => 'Book the audit', 'owner_user_id' => $danaId, 'status' => 'done']);

$dana = $db->query("SELECT * FROM pl_users WHERE id = {$danaId}")->fetch();
$threadId = \Bizorca\Pilotage\Services\Messaging::createThread(1, $live, 'A thread', 'Something Dana wrote.', $dana, true);

$conflicts = Compliance::erasureConflicts(1, $danaId);
$kinds = array_column($conflicts, 'kind');

T::ok(in_array('commitments', $kinds, true), 'the commitments they own are a conflict');
T::ok(in_array('messages', $kinds, true), 'so are the messages they wrote');

foreach ($conflicts as $c) {
    T::ok($c['count'] > 0, $c['kind'] . ' reports a real count');
    T::ok(strlen($c['note']) > 20, 'and explains why it matters rather than just naming a table');
}

$clean = $users->create(['email' => 'nobody@alpha.test', 'name' => 'Not Involved', 'role' => 'client_member', 'client_org_id' => $orgId, 'status' => 'active']);
T::same([], Compliance::erasureConflicts(1, $clean), 'someone with no history has no conflicts');


T::group('Erasure: pseudonymisation removes the person, not the record');

$requestId = Compliance::requestErasure(1, $danaId, $ownerId, 'Asked by email.');
$request = $db->query("SELECT * FROM pl_erasure_requests WHERE id = {$requestId}")->fetch();

T::same('Dana Owner', (string) $request['subject_label'], 'the request records who it was about');
T::same('open', (string) $request['status'], 'and starts open');
T::ok(str_contains((string) $request['conflicts'], 'commitments'),
    'with the conflicts captured AS THEY STOOD — recomputing later would answer a different question');

$tasksBefore = (int) $db->query("SELECT COUNT(*) AS c FROM pl_tasks WHERE owner_user_id = {$danaId}")->fetch()['c'];
$messagesBefore = (int) $db->query('SELECT COUNT(*) AS c FROM pl_messages')->fetch()['c'];

T::same(true, Compliance::pseudonymise(1, $requestId, $ownerId, 'Kept the record, removed the person.'),
    'the request is fulfilled by pseudonymisation');

$after = $db->query("SELECT * FROM pl_users WHERE id = {$danaId}")->fetch();

T::same(Compliance::ANONYMOUS_LABEL, (string) $after['name'], 'the name is gone');
T::same(false, str_contains((string) $after['email'], 'dana@alpha.test'), 'the email is gone');
T::same(null, $after['password_hash'], 'and they can no longer sign in');
T::same('disabled', (string) $after['status'], 'the account is disabled');

T::same($tasksBefore, (int) $db->query("SELECT COUNT(*) AS c FROM pl_tasks WHERE owner_user_id = {$danaId}")->fetch()['c'],
    'BUT every commitment survives — deleting the user would have cascaded and taken the record with it');
T::same($messagesBefore, (int) $db->query('SELECT COUNT(*) AS c FROM pl_messages')->fetch()['c'],
    'and every message');

$authorLabel = $db->query('SELECT author_label FROM pl_messages LIMIT 1')->fetch()['author_label'];
T::same(Compliance::ANONYMOUS_LABEL, (string) $authorLabel,
    'the denormalised author label is rewritten too — missing it would leave the erased name printed on every message');

$storedRequest = $db->query("SELECT * FROM pl_erasure_requests WHERE id = {$requestId}")->fetch();
T::same('pseudonymised', (string) $storedRequest['status'], 'the request is closed');
T::same(null, $storedRequest['subject_email'], 'and the email is cleared from the request itself');
T::same('Dana Owner', (string) $storedRequest['subject_label'],
    'while the label survives, because the record of WHO asked has to outlive the erasure');

T::same(false, Compliance::pseudonymise(1, $requestId, $ownerId, 'again'),
    'a closed request cannot be actioned twice');


T::group('Erasure: a refusal needs a reason');

$second = Compliance::requestErasure(1, $clean, $ownerId, null);

T::throws(InvalidArgumentException::class,
    static fn () => Compliance::refuseErasure(1, $second, $ownerId, '  '),
    'an unexplained refusal is indistinguishable from ignoring the request');

T::same(true, Compliance::refuseErasure(1, $second, $ownerId, 'Record required for seven years by statute.'),
    'with a reason it is a legitimate outcome');

$refused = $db->query("SELECT * FROM pl_erasure_requests WHERE id = {$second}")->fetch();
T::same('refused', (string) $refused['status'], 'and is recorded as refused');
T::ok(str_contains((string) $refused['decision'], 'statute'), 'with the reason on the record');


T::group('The coaching agreement is flagged, never gated');

$eng = $engagements->find($live);
$status = Compliance::agreementStatus($eng);

T::same(false, $status['present'], 'a new engagement has no agreement');
T::same(false, $status['waived'], 'and has not waived one');
T::ok(str_contains((string) $status['flag'], 'No coaching agreement'), 'so it is flagged');

// Flagged, not gated: everything still works without one.
$stillWorks = $tasks->createTask(['engagement_id' => $live, 'title' => 'Work continues', 'status' => 'open']);
T::ok($stillWorks > 0, 'and work continues regardless — blocking would lock out every coach who signed on paper');

T::same(true, Compliance::waiveAgreement(1, $live, 'Signed on paper in March.'), 'the flag can be dismissed');

$waived = Compliance::agreementStatus($engagements->find($live));
T::same(true, $waived['waived'], 'as waived');
T::ok(str_contains((string) $waived['flag'], 'Signed elsewhere'), 'and it says so rather than going blank');
T::ok(str_contains((string) $waived['flag'], 'paper in March'), 'carrying the note that was given');


T::group('All of it is tenant-scoped');

$otherOrg = (new ClientOrgRepository(2))->createOrg(['name' => 'Theirs', 'status' => 'active']);
$otherEng = (new EngagementRepository(2))->createEngagement(['client_org_id' => $otherOrg, 'title' => 'Theirs', 'status' => 'active']);
$db->exec("UPDATE pl_engagements SET status = 'complete', completed_at = NOW() - INTERVAL 60 MONTH WHERE id = {$otherEng}");

T::same([], Compliance::scheduleRetention(1),
    "one firm's sweep never schedules another firm's engagement, however overdue");

T::same(false, Compliance::archive(1, $otherEng, $ownerId), "nor can it archive one");
T::same(false, Compliance::waiveAgreement(1, $otherEng, 'x'), 'nor waive its agreement');
T::same(false, Compliance::cancelNotice(2, $noticeId, $ownerId, 'Trying it on'),
    "nor can another tenant cancel this one's notice");

T::throws(RuntimeException::class,
    static fn () => Compliance::requestErasure(2, $danaId, null, null),
    "and erasure cannot be requested for someone in another firm");

T::same([], Compliance::notices(2, false), 'notices are scoped');
T::same([], Compliance::erasureRequests(2), 'and so are erasure requests');

Mailer::stopCapturing();
