<?php

declare(strict_types=1);

/**
 * Cohorts — several client organizations running one playbook together.
 *
 * The group that carries this file is "The wall holds". A cohort deliberately
 * creates a shared surface between client organizations that otherwise have no
 * relationship, and a shared surface is exactly where a confidentiality wall
 * breaks. So the tests do not assume the design is safe because it is described
 * as safe — they put Alpha and Beta in one cohort and then try, from Alpha's
 * side, to read Beta's commitments, documents, metrics, messages and notes.
 *
 * If cohorts are ever reworked, that group is the one to keep.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\TaskRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Cohorts;
use Bizorca\Pilotage\Services\Messaging;
use Bizorca\Pilotage\Services\Scorecard;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
// Every table this file reads back from, including the ones it only writes to
// indirectly. pl_goals and pl_issues were missing from an earlier draft and
// accumulated across runs, which turned two wall assertions into "expected 1,
// got 2" — a failure that says nothing about the wall and everything about the
// fixture.
foreach (['pl_cohort_materials', 'pl_cohort_members', 'pl_cohorts', 'pl_announcements',
          'pl_notifications', 'pl_metric_values', 'pl_metrics',
          'pl_goal_milestones', 'pl_goals', 'pl_issue_resolutions', 'pl_issues',
          'pl_messages', 'pl_mentions', 'pl_thread_participants', 'pl_threads',
          'pl_session_notes_shared', 'pl_session_notes_private',
          'pl_session_attendees', 'pl_sessions', 'pl_tasks',
          'pl_document_deliveries', 'pl_document_versions', 'pl_documents',
          'pl_engagement_members', 'pl_engagements', 'pl_client_orgs',
          'pl_users', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme','active'),(2,'other','Other','active')");

$users = new UserRepository(1);
$orgs = new ClientOrgRepository(1);
$engagements = new EngagementRepository(1);

$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'Ada Coach', 'role' => 'coach', 'status' => 'active']);

$alphaOrg = $orgs->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active']);
$betaOrg  = $orgs->createOrg(['name' => 'Beta Dental', 'status' => 'active']);
$gammaOrg = $orgs->createOrg(['name' => 'Gamma Legal', 'status' => 'active']);

$alphaUser = $users->create(['email' => 'a@alpha.test', 'name' => 'Ann Alpha', 'role' => 'client_owner', 'client_org_id' => $alphaOrg, 'status' => 'active']);
$betaUser  = $users->create(['email' => 'b@beta.test',  'name' => 'Ben Beta',  'role' => 'client_owner', 'client_org_id' => $betaOrg,  'status' => 'active']);
$gammaUser = $users->create(['email' => 'g@gamma.test', 'name' => 'Gus Gamma', 'role' => 'client_owner', 'client_org_id' => $gammaOrg, 'status' => 'active']);

$alphaEng = $engagements->createEngagement(['client_org_id' => $alphaOrg, 'title' => 'Alpha growth', 'status' => 'active', 'coach_user_id' => $coachId]);
$betaEng  = $engagements->createEngagement(['client_org_id' => $betaOrg,  'title' => 'Beta growth',  'status' => 'active', 'coach_user_id' => $coachId]);
$gammaEng = $engagements->createEngagement(['client_org_id' => $gammaOrg, 'title' => 'Gamma growth', 'status' => 'active', 'coach_user_id' => $coachId]);

$coach = $users->find($coachId);
$ann   = $users->find($alphaUser);
$ben   = $users->find($betaUser);
$gus   = $users->find($gammaUser);


T::group('Creating a cohort');

$cohortId = Cohorts::create(1, [
    'name'        => 'Autumn operators group',
    'description' => 'Six owner-managed businesses, one operating rhythm.',
    'cadence'     => 'monthly',
    'starts_on'   => date('Y-m-d'),
], $coachId);

T::ok($cohortId > 0, 'a cohort is created');

$cohort = Cohorts::find(1, $cohortId);
T::same('Autumn operators group', (string) $cohort['name'], 'with its name');
T::same(0, (int) $cohort['roster_visible'],
    'and the roster is HIDDEN by default — publishing one nobody agreed to publish must never be what happens if you do nothing');

T::throws(InvalidArgumentException::class,
    static fn () => Cohorts::create(1, ['name' => '  ']), 'it needs a name');
T::throws(InvalidArgumentException::class,
    static fn () => Cohorts::create(1, ['name' => 'x', 'cadence' => 'fortnightly']), 'and a real cadence');


T::group('Membership is by engagement');

T::same(true, Cohorts::addMember(1, $cohortId, $alphaEng), 'Alpha joins');
T::same(true, Cohorts::addMember(1, $cohortId, $betaEng), 'Beta joins');

T::same(2, count(Cohorts::members(1, $cohortId)), 'two members');

Cohorts::addMember(1, $cohortId, $alphaEng);
T::same(2, count(Cohorts::members(1, $cohortId)), 'adding the same one twice does not double it');

// Cross-tenant: both ends are checked rather than trusted.
$otherOrg = (new ClientOrgRepository(2))->createOrg(['name' => 'Theirs', 'status' => 'active']);
$otherEng = (new EngagementRepository(2))->createEngagement(['client_org_id' => $otherOrg, 'title' => 'Theirs', 'status' => 'active']);

T::same(false, Cohorts::addMember(1, $cohortId, $otherEng),
    "another firm's engagement cannot be added to this firm's cohort");
T::same(false, Cohorts::addMember(2, $cohortId, $alphaEng),
    'nor can another firm add to a cohort that is not theirs');
T::same(2, count(Cohorts::members(1, $cohortId)), 'and neither attempt changed anything');


T::group('THE WALL HOLDS');

/**
 * Alpha and Beta are now in one cohort. Everything below is an attempt to read
 * Beta's private records from Alpha's side. Every one of them must fail, and
 * they must fail because the records were never reachable — not because
 * something remembered to filter them.
 */

// Give Beta a full set of private records to try to reach.
$tasks = new TaskRepository(1);
$betaTask = $tasks->createTask([
    'engagement_id' => $betaEng, 'title' => 'Beta: renegotiate the lease',
    'owner_user_id' => $betaUser, 'status' => 'open',
]);
$betaMetric = Scorecard::createMetric(1, $betaEng, ['name' => 'Beta chair utilisation', 'frequency' => 'weekly']);
Scorecard::record(1, $betaMetric, Scorecard::normalizePeriod(date('Y-m-d')), 61.0, $coachId);
$betaGoal = Scorecard::createGoal(1, $betaEng, ['title' => 'Beta: two new associates']);
$betaIssue = Scorecard::raiseIssue(1, $betaEng, 'Beta: cash is tight', 'Four months of runway.', 'ad_hoc', $coachId);
$betaThread = Messaging::createThread(1, $betaEng, 'Beta: the lease', 'Confidential to Beta.', $coach, true);

// Commitments. Each of these checks that the record EXISTS on Beta's side
// before checking it is absent from Alpha's — otherwise "absent" would be
// satisfied by there being nothing anywhere.
T::same(1, count($tasks->forEngagement($betaEng)), 'Beta has a commitment');

$titles = array_column($tasks->forEngagement($alphaEng), 'title');
T::ok(!in_array('Beta: renegotiate the lease', $titles, true),
    "Alpha's commitment list does not contain Beta's commitments");

// Metrics and goals. Note the key is `rows` — an earlier draft of this test
// read a `metrics` key that does not exist, so the assertion passed against an
// empty array and proved nothing. A wall test that cannot fail is worse than no
// wall test, because it is believed.
$alphaGrid = Scorecard::grid(1, $alphaEng);
$betaGrid = Scorecard::grid(1, $betaEng);

T::same(1, count($betaGrid['rows']), "Beta really does have a metric to leak");
T::same('Beta chair utilisation', (string) $betaGrid['rows'][0]['name'], 'and this is it');

$metricNames = array_column($alphaGrid['rows'], 'name');
T::ok(!in_array('Beta chair utilisation', $metricNames, true), "nor Beta's metrics");
T::same(0, count($alphaGrid['rows']), "Alpha's scorecard is empty, as it should be");

T::same(1, count(Scorecard::goals(1, $betaEng)), 'Beta has a goal');
$alphaGoals = array_column(Scorecard::goals(1, $alphaEng), 'title');
T::ok(!in_array('Beta: two new associates', $alphaGoals, true), "nor Beta's goals");

// Issues.
T::same(1, count(Scorecard::issues(1, $betaEng)), 'Beta has an issue');
$alphaIssues = array_column(Scorecard::issues(1, $alphaEng), 'title');
T::ok(!in_array('Beta: cash is tight', $alphaIssues, true), "nor Beta's issues");

// Messages.
T::same(1, count(Messaging::threads(1, $betaEng, true)), 'Beta has a thread');
$alphaThreads = array_column(Messaging::threads(1, $alphaEng, true), 'subject');
T::ok(!in_array('Beta: the lease', $alphaThreads, true), "nor Beta's message threads");

// And the direct attempts, by id, from Alpha's side.
T::same([], array_filter($tasks->forOwner($alphaUser, false),
    static fn (array $t): bool => (int) $t['id'] === $betaTask),
    "asking for Alpha's own commitments never returns Beta's");

// The engagement itself.
$asAlpha = $engagements->forMember($alphaUser);
T::ok(!in_array($betaEng, array_map(static fn (array $e): int => (int) $e['id'], $asAlpha), true),
    "Beta's engagement is not among Alpha's");

// Cohort membership grants NOTHING about the other engagement.
T::same(true, Cohorts::visibleTo(1, $cohortId, $ann), 'Alpha can see the cohort');
T::same(true, Cohorts::visibleTo(1, $cohortId, $ben), 'so can Beta');
T::same(false, Cohorts::visibleTo(1, $cohortId, $gus), 'Gamma, who is not in it, cannot');


T::group('The roster is off unless the coach turns it on');

T::same([], Cohorts::rosterFor(1, $cohortId, $ann),
    'a member sees no roster while it is hidden');

$firmView = Cohorts::rosterFor(1, $cohortId, $coach);
T::same(2, count($firmView), 'but the coach always sees who is in their own cohort');

Cohorts::update(1, $cohortId, ['roster_visible' => 1]);

$annView = Cohorts::rosterFor(1, $cohortId, $ann);
T::same(2, count($annView), 'once published, a member sees the roster');
T::ok(in_array('Beta Dental', $annView, true), 'including the peer organization name');

T::same([], Cohorts::rosterFor(1, $cohortId, $gus),
    'and a non-member still sees nothing, published or not');

// The roster is names only. Not engagement titles, not progress, not contacts.
foreach ($annView as $entry) {
    T::ok(is_string($entry), 'the roster is organization names and nothing else');
}
T::ok(!in_array('Beta growth', $annView, true),
    "a peer's engagement title is not in it — that is their business, not the cohort's");


T::group('Cohort sessions are one meeting, and drop out of engagement views');

$sessionId = Cohorts::scheduleSession(1, $cohortId, 'Month one: the operating rhythm',
    date('Y-m-d H:i:s', strtotime('+7 days')), $coachId, 'Zoom', 90);

T::ok($sessionId > 0, 'a cohort session is scheduled');

$row = $db->query("SELECT * FROM pl_sessions WHERE id = {$sessionId}")->fetch();
T::same(null, $row['engagement_id'], 'it belongs to no engagement');
T::same($cohortId, (int) $row['cohort_id'], 'and to the cohort');

/**
 * The safety property that made reusing pl_sessions comfortable: every
 * existing engagement query INNER JOINs the engagement, so a cohort session
 * cannot appear in one. Asserted rather than assumed.
 */
$alphaSessions = \Bizorca\Pilotage\Services\SessionService::forEngagement(1, $alphaEng);
T::same(0, count(array_filter($alphaSessions,
    static fn (array $s): bool => (int) $s['id'] === $sessionId)),
    "a cohort session does not appear in a member's own engagement sessions");

T::same(1, count(Cohorts::sessions(1, $cohortId)), 'it appears in the cohort');

// The database refuses a session that belongs to both, or to neither.
$bothFailed = false;
try {
    $db->exec("INSERT INTO pl_sessions (tenant_id, engagement_id, cohort_id, title)
               VALUES (1, {$alphaEng}, {$cohortId}, 'Belongs to both')");
} catch (\PDOException) {
    $bothFailed = true;
}
T::same(true, $bothFailed, 'a session cannot belong to an engagement AND a cohort');

$neitherFailed = false;
try {
    $db->exec("INSERT INTO pl_sessions (tenant_id, engagement_id, cohort_id, title)
               VALUES (1, NULL, NULL, 'Belongs to nothing')");
} catch (\PDOException) {
    $neitherFailed = true;
}
T::same(true, $neitherFailed,
    'nor to neither — a session with no audience is how notes reach the wrong people');


T::group('Everyone in the cohort is told about a session');

$queued = $db->query("SELECT DISTINCT user_id FROM pl_notifications WHERE event_type = 'session.scheduled'")
             ->fetchAll(PDO::FETCH_COLUMN);
$queued = array_map('intval', $queued);

T::ok(in_array($alphaUser, $queued, true), 'Alpha hears about it');
T::ok(in_array($betaUser, $queued, true), 'Beta hears about it');
T::ok(!in_array($gammaUser, $queued, true), 'Gamma, who is not a member, does not');


T::group('Material is published deliberately, and only from the library');

$library = $db->prepare(
    "INSERT INTO pl_documents (tenant_id, engagement_id, context, title, status)
     VALUES (1, NULL, 'library', :title, 'draft')"
);
$library->execute(['title' => 'The operating rhythm worksheet']);
$libraryDoc = (int) $db->lastInsertId();

$deliverable = $db->prepare(
    "INSERT INTO pl_documents (tenant_id, engagement_id, context, title, status)
     VALUES (1, :eid, 'deliverable', :title, 'draft')"
);
$deliverable->execute(['eid' => $betaEng, 'title' => 'Beta: private valuation']);
$betaDoc = (int) $db->lastInsertId();

T::same(true, Cohorts::publishMaterial(1, $cohortId, $libraryDoc, 'Read before month two.', $coachId),
    'a library document can go to the cohort');

T::throws(InvalidArgumentException::class,
    static fn () => Cohorts::publishMaterial(1, $cohortId, $betaDoc, null, $coachId),
    "but Beta's own deliverable CANNOT — publishing it would hand it to their peers");

T::same(1, count(Cohorts::materials(1, $cohortId)), 'only the library document is there');

T::same(true, Cohorts::grantsDocumentAccess(1, $libraryDoc, $ann),
    'a member can open the shared material');
T::same(false, Cohorts::grantsDocumentAccess(1, $betaDoc, $ann),
    "and gets no access at all to Beta's document through the cohort");
T::same(false, Cohorts::grantsDocumentAccess(1, $libraryDoc, $gus),
    'a non-member cannot open the shared material either');

Cohorts::withdrawMaterial(1, $cohortId, $libraryDoc);
T::same(false, Cohorts::grantsDocumentAccess(1, $libraryDoc, $ann),
    'and withdrawing it takes the access back');

Cohorts::publishMaterial(1, $cohortId, $libraryDoc, null, $coachId);


T::group('Leaving is recorded, and ends the shared surface');

T::same(true, Cohorts::removeMember(1, $cohortId, $betaEng), 'Beta leaves');
T::same(1, count(Cohorts::members(1, $cohortId)), 'the active roster shrinks');
T::same(2, count(Cohorts::members(1, $cohortId, true)),
    'but the history still knows they were there — "who was in the room in March" stays answerable');

T::same(false, Cohorts::visibleTo(1, $cohortId, $ben), 'Beta can no longer see the cohort');
T::same(false, Cohorts::grantsDocumentAccess(1, $libraryDoc, $ben),
    'nor open material published to it');

T::same(true, Cohorts::addMember(1, $cohortId, $betaEng), 'and rejoining works');
T::same(true, Cohorts::visibleTo(1, $cohortId, $ben), 'restoring access');


T::group('Announcements reach members and nobody else');

$db->exec('TRUNCATE TABLE pl_notifications');

$told = Cohorts::announce(1, $cohortId, 'Month two moves to the 14th',
    'Same time, one week later. Sorry for the shuffle.', $coachId);

T::ok($told >= 2, 'both members are told');

$recipients = array_map('intval',
    $db->query('SELECT DISTINCT user_id FROM pl_notifications')->fetchAll(PDO::FETCH_COLUMN));

T::ok(in_array($alphaUser, $recipients, true), 'Alpha');
T::ok(in_array($betaUser, $recipients, true), 'Beta');
T::ok(!in_array($gammaUser, $recipients, true), 'and not Gamma');

$recorded = $db->query("SELECT * FROM pl_announcements WHERE cohort_id = {$cohortId}")->fetch();
T::ok($recorded !== false, 'the announcement is recorded');
T::same('cohort', (string) $recorded['segment'], 'as a cohort announcement');
T::same($told, (int) $recorded['recipient_count'], 'with how many it reached');

T::throws(InvalidArgumentException::class,
    static fn () => Cohorts::announce(1, $cohortId, '  ', 'body'), 'it needs a subject');
T::throws(RuntimeException::class,
    static fn () => Cohorts::announce(1, 99999, 'x', 'y'), 'and a real cohort');


T::group('Progress across the cohort is the coach\'s whole reason for the screen');

$members = Cohorts::members(1, $cohortId);

T::same(2, count($members), 'both members are listed');

foreach ($members as $member) {
    T::ok(array_key_exists('progress', $member), $member['org_name'] . ' carries its progress');
    T::ok(array_key_exists('org_name', $member), 'and its name');
}


T::group('Everything is tenant-scoped');

T::same(null, Cohorts::find(2, $cohortId), "another firm cannot read this firm's cohort");
T::same([], Cohorts::members(2, $cohortId), 'nor its members');
T::same([], Cohorts::sessions(2, $cohortId), 'nor its sessions');
T::same([], Cohorts::materials(2, $cohortId), 'nor its material');
T::same([], Cohorts::announcements(2, $cohortId), 'nor its announcements');
T::same([], Cohorts::all(2), 'and its cohort list is empty');
T::same(false, Cohorts::update(2, $cohortId, ['name' => 'Hijacked']),
    "and it cannot be renamed from outside");
T::same('Autumn operators group', (string) Cohorts::find(1, $cohortId)['name'], 'the name is untouched');
