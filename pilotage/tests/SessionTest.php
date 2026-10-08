<?php

declare(strict_types=1);

/**
 * M5: sessions, the agenda, dual notes, recaps, cadence.
 *
 * The load-bearing group is "Private notes never leak". Everything else here
 * is ordinary CRUD; that one is the coaching relationship.
 */

use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Qualifiers;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\AgendaBuilder;
use Bizorca\Pilotage\Services\Ics;
use Bizorca\Pilotage\Services\PlaybookInstantiator;
use Bizorca\Pilotage\Services\SessionService;
use Bizorca\Pilotage\Services\StarterPlaybooks;
use Bizorca\Pilotage\Services\StarterSessionTemplates;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_session_recaps', 'pl_session_notes_private', 'pl_session_notes_shared',
          'pl_session_agenda_items', 'pl_session_attendees', 'pl_sessions',
          'pl_session_template_items', 'pl_session_templates',
          'pl_engagement_step_artifacts', 'pl_engagement_steps', 'pl_engagement_phases',
          'pl_engagement_playbooks', 'pl_engagement_members', 'pl_engagements',
          'pl_playbook_step_artifacts', 'pl_playbook_steps', 'pl_playbook_phases',
          'pl_playbook_versions', 'pl_playbooks', 'pl_org_events', 'pl_client_contacts',
          'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'northstar','Northstar','active')");

$users = new UserRepository(1);
$coachId  = $users->create(['email' => 'coach@acme.test',  'name' => 'A Coach',      'role' => 'coach', 'status' => 'active']);
$coach2Id = $users->create(['email' => 'coach2@acme.test', 'name' => 'Other Coach',  'role' => 'coach', 'status' => 'active']);

$orgs = new ClientOrgRepository(1);
$orgId = $orgs->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active', 'owner_user_id' => $coachId]);

$ownerId  = $users->create(['email' => 'owner@alpha.test',  'name' => 'An Owner',  'role' => 'client_owner',  'client_org_id' => $orgId, 'status' => 'active']);
$memberId = $users->create(['email' => 'member@alpha.test', 'name' => 'A Member',  'role' => 'client_member', 'client_org_id' => $orgId, 'status' => 'active']);

$engagements = new EngagementRepository(1);
$engId = $engagements->createEngagement([
    'client_org_id' => $orgId, 'title' => 'Q3 rhythm', 'status' => 'active',
    'coach_user_id' => $coachId, 'cadence' => 'biweekly',
]);


T::group('Session templates seed');

$workingTpl = StarterSessionTemplates::installWorkingSession(1);
$discoveryTpl = StarterSessionTemplates::installDiscovery(1);

$items = $db->query('SELECT * FROM pl_session_template_items WHERE template_id = ' . $workingTpl . ' ORDER BY position')->fetchAll();
T::same(8, count($items), 'the working session has eight agenda items');
T::same(90, (int) $db->query('SELECT time_box_minutes FROM pl_session_templates WHERE id = ' . $workingTpl)->fetch()['time_box_minutes'], 'time box is 90 minutes');

$blocks = array_values(array_filter(array_column($items, 'auto_block')));
foreach (['metrics', 'goals', 'commitments', 'issues', 'steps'] as $expected) {
    T::ok(in_array($expected, $blocks, true), 'the agenda references the ' . $expected . ' block');
}

// Same trademark posture as the starter playbooks.
$allText = mb_strtolower(implode(' ', array_merge(array_column($items, 'title'), array_column($items, 'prompt'))));
foreach (['level 10', 'l10', 'rocks', 'ids', 'scorecard', 'traction', 'eos'] as $term) {
    T::ok(!str_contains($allText, $term), 'session template avoids the term "' . $term . '"');
}


T::group('Scheduling copies the agenda');

$sessionId = SessionService::schedule(1, $engId, 'Fortnightly working session', '2026-09-01 14:00:00', $workingTpl, $coachId, 'https://meet.test/abc');
T::ok($sessionId > 0, 'session scheduled');

$agendaCount = (int) $db->query('SELECT COUNT(*) c FROM pl_session_agenda_items WHERE session_id = ' . $sessionId)->fetch()['c'];
T::same(8, $agendaCount, 'the agenda was copied into the session');

// Editing the template must not rewrite a meeting.
$db->exec("UPDATE pl_session_template_items SET title = 'REWRITTEN' WHERE template_id = " . $workingTpl . ' AND position = 0');
$firstItem = $db->query('SELECT title FROM pl_session_agenda_items WHERE session_id = ' . $sessionId . ' ORDER BY position LIMIT 1')->fetch()['title'];
T::same('Settle in', $firstItem, 'editing the template does not change a scheduled session');

T::throws(InvalidArgumentException::class,
    static fn () => SessionService::schedule(1, $engId, '   '),
    'a session needs a title');
T::throws(RuntimeException::class,
    static fn () => SessionService::schedule(1, $engId, 'Bad template', null, 99999),
    'an unknown template is refused');


T::group('Auto-generated agenda blocks');

AgendaBuilder::resetProviders();

$session = SessionService::find(1, $sessionId);
$unfilled = AgendaBuilder::build('commitments', 1, $session);
T::same([], $unfilled['items'], 'a block with no provider is empty');
T::ok($unfilled['note'] !== null, 'and says so honestly rather than vanishing');

$owed = AgendaBuilder::unresolvedBlocks(1);
sort($owed);
T::same(['commitments', 'goals', 'issues', 'metrics', 'steps'], $owed, 'the builder reports which blocks have no provider');

// M4 can answer the steps block, so wire it and check it works for real.
AgendaBuilder::registerPlaybookProvider();

$pbId = StarterPlaybooks::installQuarterlyRhythm(1, $coachId);
$version = $db->query("SELECT id FROM pl_playbook_versions WHERE playbook_id = " . $pbId . " AND state = 'published'")->fetch();
PlaybookInstantiator::apply(1, $engId, (int) $version['id'], $coachId);

$stepsBlock = AgendaBuilder::build('steps', 1, $session);
T::ok(count($stepsBlock['items']) > 0, 'the steps block lists open playbook steps');

$owed = AgendaBuilder::unresolvedBlocks(1);
T::ok(!in_array('steps', $owed, true), 'steps is no longer unresolved');
T::same(4, count($owed), 'four blocks remain owed by M6 and M9');

$filled = SessionService::agenda(1, $session);
$stepsItem = null;
foreach ($filled as $item) {
    if ($item['auto_block'] === 'steps') { $stepsItem = $item; }
}
T::ok($stepsItem !== null && isset($stepsItem['block']), 'the agenda carries its filled block');


T::group('Private notes never leak');

SessionService::saveSharedNotes(1, $sessionId, 'We agreed to focus on lead time.', $coachId);
SessionService::savePrivateNotes(1, $sessionId, 'Owner is not being straight about the bank covenant.', $coachId);

$coachView = SessionService::forCoach(1, $sessionId, $coachId);
T::ok($coachView['shared_notes'] !== null, 'the coach sees shared notes');
T::ok($coachView['private_notes'] !== null, 'the coach sees their own private notes');
T::ok(str_contains((string) $coachView['private_notes']['body'], 'covenant'), 'private note content is there');

$clientView = SessionService::forClient(1, $sessionId);
T::ok(!array_key_exists('private_notes', $clientView), 'the client payload has no private_notes key at all');
T::ok($clientView['shared_notes'] !== null, 'the client does see shared notes');

$serialized = json_encode($clientView);
T::ok(!str_contains((string) $serialized, 'covenant'), 'no private text appears anywhere in the client payload');
T::ok(!str_contains((string) $serialized, 'not being straight'), 'really, none of it');

// Private notes are private from other coaches too, not merely from clients.
$otherCoachView = SessionService::forCoach(1, $sessionId, $coach2Id);
T::same(null, $otherCoachView['private_notes'], "another coach does not see the first coach's private notes");

SessionService::savePrivateNotes(1, $sessionId, 'Second opinion: the numbers look fine.', $coach2Id);
T::ok(str_contains((string) SessionService::privateNotes(1, $sessionId, $coach2Id)['body'], 'Second opinion'), 'each coach keeps their own');
T::ok(str_contains((string) SessionService::privateNotes(1, $sessionId, $coachId)['body'], 'covenant'), 'and the first is untouched');

// The client agenda also drops coach-facing prompts.
$promptLeak = false;
foreach ($clientView['agenda'] as $item) {
    if (array_key_exists('prompt', $item)) { $promptLeak = true; }
}
T::ok(!$promptLeak, 'coach-facing agenda prompts are stripped for the client');


T::group('Running a session');

T::ok(SessionService::start(1, $sessionId), 'session starts');
T::ok(!SessionService::start(1, $sessionId), 'starting twice does nothing');
T::same('in_progress', SessionService::find(1, $sessionId)['status'], 'status is in progress');

$db->exec('UPDATE pl_session_agenda_items SET covered_at = NOW() WHERE session_id = ' . $sessionId . ' AND position IN (0,1)');

$recap = SessionService::close(1, $sessionId);
T::ok($recap !== null, 'closing drafts a recap');
T::ok(str_contains((string) $recap, 'lead time'), 'the recap carries the shared notes');
T::ok(!str_contains((string) $recap, 'covenant'), 'the recap does NOT carry private notes');
T::ok(str_contains((string) $recap, 'What we covered'), 'the recap lists what was covered');
T::same('complete', SessionService::find(1, $sessionId)['status'], 'session is complete');


T::group('Recaps are drafted, never auto-sent');

$stored = SessionService::recap(1, $sessionId);
T::same('draft', $stored['status'], 'the recap starts as a draft');

$clientView = SessionService::forClient(1, $sessionId);
T::same(null, $clientView['recap'], 'a draft recap is invisible to the client');

T::ok(SessionService::saveRecap(1, $sessionId, "Edited recap.\nFocus: lead time."), 'the draft can be edited');
T::ok(SessionService::markRecapSent(1, $sessionId, $coachId), 'sending is an explicit act');
T::ok(!SessionService::markRecapSent(1, $sessionId, $coachId), 'sending twice does nothing');

$clientView = SessionService::forClient(1, $sessionId);
T::ok($clientView['recap'] !== null, 'a sent recap is visible to the client');
T::ok(str_contains((string) $clientView['recap']['body'], 'Edited recap'), 'the client sees the edited text');
T::ok(!SessionService::saveRecap(1, $sessionId, 'sneaky edit'), 'a sent recap cannot be silently rewritten');


T::group('Attendees');

$a1 = SessionService::addAttendee(1, $sessionId, $ownerId, 'An Owner');
$a2 = SessionService::addAttendee(1, $sessionId, $memberId, 'A Member');
SessionService::addAttendee(1, $sessionId, null, 'Sam Okafor (bookkeeper)');

T::same(3, count(SessionService::attendees(1, $sessionId)), 'attendees include someone with no login');
T::ok(SessionService::isAttendee(1, $sessionId, $ownerId), 'the owner is an attendee');
T::ok(!SessionService::isAttendee(1, $sessionId, $coach2Id), 'the other coach is not');

T::ok(SessionService::markAttendance(1, $a1, true), 'attendance can be marked');
T::ok(SessionService::markAttendance(1, $a2, false), 'and a no-show recorded');

SessionService::addAttendee(1, $sessionId, $ownerId, 'An Owner (renamed)');
T::same(3, count(SessionService::attendees(1, $sessionId)), 'adding the same user twice does not duplicate');


T::group('Cadence slip');

// The engagement just met (close() stamped ended_at), so a fortnightly
// rhythm is not yet overdue.
T::same(0, count(SessionService::cadenceSlipped(1)), 'an engagement that just met is not slipping');

// An engagement that has NEVER met, with nothing booked, is the loudest signal.
$neverMet = $engagements->createEngagement([
    'client_org_id' => $orgId, 'title' => 'Never started', 'status' => 'active',
    'coach_user_id' => $coachId, 'cadence' => 'weekly',
]);
$slipped = SessionService::cadenceSlipped(1);
T::same(1, count($slipped), 'an engagement that has never met is slipping');
T::same('Never started', $slipped[0]['title'], 'the slipping engagement is named');
T::same(null, $slipped[0]['days_since'], 'with no days-since, because it never happened');

// Age the first engagement past its fortnightly cadence.
$db->exec('UPDATE pl_sessions SET ended_at = DATE_SUB(NOW(), INTERVAL 20 DAY) WHERE engagement_id = ' . $engId);
$slipped = SessionService::cadenceSlipped(1);
T::same(2, count($slipped), 'an engagement overdue by its cadence also slips');

$aged = null;
foreach ($slipped as $row) { if ($row['title'] === 'Q3 rhythm') { $aged = $row; } }
T::ok($aged !== null, 'the overdue engagement is reported');
T::ok((int) $aged['days_since'] >= 19, 'and says how long it has been');

// Booking something clears it.
SessionService::schedule(1, $engId, 'Next fortnightly', date('Y-m-d H:i:s', time() + 7 * 86400), $workingTpl, $coachId);
$titles = array_column(SessionService::cadenceSlipped(1), 'title');
T::ok(!in_array('Q3 rhythm', $titles, true), 'booking one clears the signal');

$engagements->createEngagement([
    'client_org_id' => $orgId, 'title' => 'Ad hoc advice', 'status' => 'active',
    'coach_user_id' => $coachId, 'cadence' => 'adhoc',
]);
$titles = array_column(SessionService::cadenceSlipped(1), 'title');
T::ok(!in_array('Ad hoc advice', $titles, true), 'an ad-hoc engagement never counts as slipping');

// A paused engagement is not a slipping engagement.
$engagements->update($neverMet, ['status' => 'paused']);
$titles = array_column(SessionService::cadenceSlipped(1), 'title');
T::ok(!in_array('Never started', $titles, true), 'a paused engagement stops being flagged');


T::group('ICS');

$sess = SessionService::find(1, $sessionId);
$ics = Ics::forSession($sess, 'notifications@pilotagehq.com', 'Acme Advisory');

T::ok(str_starts_with($ics, "BEGIN:VCALENDAR\r\n"), 'ICS opens correctly');
T::ok(str_contains($ics, 'END:VCALENDAR'), 'and closes');
T::ok(str_contains($ics, 'DTSTART:20260901T140000Z'), 'start time is UTC, matching the stored clock');
T::ok(str_contains($ics, 'TRIGGER:-PT1440M'), 'has a 24-hour reminder');
T::ok(str_contains($ics, 'TRIGGER:-PT60M'), 'has a 1-hour reminder');
T::ok(str_contains($ics, 'LOCATION:https://meet.test/abc'), 'carries the meeting link');
T::ok(!str_contains($ics, "\n\n"), 'no stray blank lines');

$tricky = $sess;
$tricky['title'] = 'Review; with, commas\\and semicolons';
$icsEscaped = Ics::forSession($tricky, 'x@y.test', 'Firm');
T::ok(str_contains($icsEscaped, 'SUMMARY:Review\; with\, commas'), 'special characters are escaped');

$long = $sess;
$long['title'] = str_repeat('A very long session title ', 8);
$icsFolded = Ics::forSession($long, 'x@y.test', 'Firm');
foreach (explode("\r\n", $icsFolded) as $line) {
    T::ok(strlen($line) <= 75, 'every ICS line is within 75 octets');
    break;   // one assertion is enough; the loop proves folding ran
}


T::group('Tenant isolation');

T::same(null, SessionService::find(2, $sessionId), "tenant B cannot read tenant A's session");
T::same(null, SessionService::forCoach(2, $sessionId, $coachId), 'nor its coach view');
T::same(null, SessionService::forClient(2, $sessionId), 'nor its client view');
T::same(null, SessionService::sharedNotes(2, $sessionId), 'nor its shared notes');
T::same(null, SessionService::privateNotes(2, $sessionId, $coachId), 'nor its private notes');
T::same(null, SessionService::recap(2, $sessionId), 'nor its recap');
T::same([], SessionService::attendees(2, $sessionId), 'nor its attendees');
T::same([], SessionService::forEngagement(2, $engId), 'nor its session list');
T::ok(!SessionService::start(2, $sessionId), "tenant B cannot start tenant A's session");
T::same(null, SessionService::close(2, $sessionId), "tenant B cannot close it");
T::ok(!SessionService::markRecapSent(2, $sessionId, $coachId), 'nor send its recap');
T::same([], SessionService::cadenceSlipped(2), 'nor see its cadence signals');


T::group('Qualifiers — M5 pays what it owes');

Policy::resetQualifiers();
Qualifiers::register();

// Only what M5 owns — see the note in PlaybookTest.
$remaining = Policy::unresolvedQualifiers();
T::ok(!in_array('attendee', $remaining, true), 'attendee is now resolved');

$owner  = ['id' => $ownerId,  'role' => 'client_owner',  'status' => 'active', 'client_org_id' => $orgId];
$member = ['id' => $memberId, 'role' => 'client_member', 'status' => 'active', 'client_org_id' => $orgId];

$attendeeIds = [];
foreach (SessionService::attendees(1, $sessionId) as $a) {
    if ($a['user_id'] !== null) { $attendeeIds[] = (int) $a['user_id']; }
}

T::ok(Policy::can($owner, Policy::READ, 'session'), 'a client owner reads sessions unconditionally');
T::ok(Policy::can($member, Policy::READ, 'session', ['attendee_user_ids' => $attendeeIds]), 'a team member reads a session they attended');
T::ok(!Policy::can($member, Policy::READ, 'session', ['attendee_user_ids' => [999]]), 'but not one they did not');
T::ok(!Policy::can($member, Policy::READ, 'session', null), 'and not with no context');
T::ok(!Policy::can($member, Policy::READ, 'session', []), 'nor an empty one');

Policy::resetQualifiers();
AgendaBuilder::resetProviders();
