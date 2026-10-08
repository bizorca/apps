<?php

declare(strict_types=1);

/**
 * M9: goals, metrics, the scorecard, and the issues workflow.
 */

use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Qualifiers;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\AgendaBuilder;
use Bizorca\Pilotage\Services\Scorecard;
use Bizorca\Pilotage\Services\SessionService;
use Bizorca\Pilotage\Services\StarterSessionTemplates;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_issue_resolutions', 'pl_metric_values', 'pl_metrics', 'pl_goal_milestones', 'pl_goals',
          'pl_issues', 'pl_session_agenda_items', 'pl_sessions', 'pl_session_template_items',
          'pl_session_templates', 'pl_engagement_members', 'pl_engagements',
          'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'northstar','Northstar','active')");

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);

$orgs = new ClientOrgRepository(1);
$orgId = $orgs->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active', 'owner_user_id' => $coachId]);
$ownerId  = $users->create(['email' => 'dana@alpha.test', 'name' => 'Dana Reyes', 'role' => 'client_owner',  'client_org_id' => $orgId, 'status' => 'active']);
$memberId = $users->create(['email' => 'jo@alpha.test',   'name' => 'Jo Fletcher','role' => 'client_member', 'client_org_id' => $orgId, 'status' => 'active']);

$engagements = new EngagementRepository(1);
$engId = $engagements->createEngagement(['client_org_id' => $orgId, 'title' => 'Q3 rhythm', 'status' => 'active', 'coach_user_id' => $coachId]);


T::group('Period maths is anchored, not relative');

T::same('2026-08-03', Scorecard::normalizePeriod('2026-08-06'), 'a week starts on the Monday');
T::same('2026-08-03', Scorecard::normalizePeriod('2026-08-09'), 'and the Sunday belongs to the same week');
T::same('2026-08-10', Scorecard::normalizePeriod('2026-08-10'), 'the next Monday is a new week');
T::same('2026-08-01', Scorecard::normalizePeriod('2026-08-19', 'monthly'), 'months start on the 1st');
T::same('2026-07-01', Scorecard::normalizePeriod('2026-08-19', 'quarterly'), 'quarters start on the quarter month');

T::same('2026-Q3', Scorecard::quarterLabel('2026-08-06'), 'August is Q3');
T::same('2026-Q1', Scorecard::quarterLabel('2026-01-01'), 'January is Q1');
T::same('2026-09-30', Scorecard::quarterEnd('2026-08-06'), 'Q3 ends 30 September');
T::same('2026-12-31', Scorecard::nextQuarterEnd('2026-08-06'), 'the next quarter ends 31 December');
T::same('2026-06-30', Scorecard::quarterEndFromLabel('2026-Q2'), 'a label maps back to its end date');
T::throws(InvalidArgumentException::class, static fn () => Scorecard::quarterEndFromLabel('nope'), 'a bad label is refused');

$periods = Scorecard::periodStarts('2026-08-06');
T::same(13, count($periods), 'thirteen columns — one quarter of weeks');
T::same('2026-08-03', end($periods), 'the last column is the current week');
T::ok(strtotime($periods[0]) < strtotime($periods[12]), 'and they run forwards');


T::group('Goals — the soft cap advises, it does not block');

$load = Scorecard::goalLoad(1, $engId);
T::same(0, $load['count'], 'no goals yet');
T::ok($load['advice'] !== null, 'and it says so');

for ($i = 1; $i <= 5; $i++) {
    Scorecard::createGoal(1, $engId, ['title' => 'Priority ' . $i, 'owner_user_id' => $ownerId]);
}

$load = Scorecard::goalLoad(1, $engId);
T::same(5, $load['count'], 'five priorities');
T::ok(!$load['over_cap'], 'five is within the cap');
T::same(null, $load['advice'], 'and needs no advice');

for ($i = 6; $i <= 9; $i++) {
    Scorecard::createGoal(1, $engId, ['title' => 'Priority ' . $i]);
}

$load = Scorecard::goalLoad(1, $engId);
T::same(9, $load['count'], 'nine were accepted — the cap does not block');
T::ok($load['over_cap'], 'but it is flagged');
T::ok(str_contains((string) $load['advice'], 'list of none'), 'with advice that says why');

T::throws(InvalidArgumentException::class,
    static fn () => Scorecard::createGoal(1, $engId, ['title' => '  ']), 'a goal needs a title');

$goals = Scorecard::goals(1, $engId);
T::same(9, count($goals), 'all nine are listed');
T::same('Priority 1', $goals[0]['title'], 'in creation order');
T::same('Dana Reyes', $goals[0]['owner_name'], 'with the owner resolved');
T::same(Scorecard::quarterLabel(), $goals[0]['quarter_label'], 'stamped with the current quarter');

T::ok(Scorecard::setGoalStatus(1, (int) $goals[0]['id'], 'at_risk'), 'status can be set');
T::throws(InvalidArgumentException::class,
    static fn () => Scorecard::setGoalStatus(1, (int) $goals[0]['id'], 'vibes'), 'an unknown status is refused');


T::group('Quarterly rollover');

$decisions = [
    (int) $goals[0]['id'] => 'done',
    (int) $goals[1]['id'] => 'carry',
    (int) $goals[2]['id'] => 'drop',
];

$result = Scorecard::rollQuarter(1, $engId, $decisions);
T::same(3, $result['scored'], 'three goals scored');
T::same(1, $result['carried'], 'one carried forward');

$after = $db->query('SELECT id, status, reviewed_at FROM pl_goals WHERE id IN (' . implode(',', array_keys($decisions)) . ')')->fetchAll();
$byId = [];
foreach ($after as $r) { $byId[(int) $r['id']] = $r; }

T::same('done', $byId[(int) $goals[0]['id']]['status'], 'the completed one is done');
T::same('off_track', $byId[(int) $goals[1]['id']]['status'], 'the carried one is recorded as off track — carrying forward means it did not land');
T::same('dropped', $byId[(int) $goals[2]['id']]['status'], 'the dropped one is dropped');
T::ok($byId[(int) $goals[0]['id']]['reviewed_at'] !== null, 'and all are stamped as reviewed');

$next = Scorecard::goals(1, $engId, Scorecard::quarterLabel(Scorecard::nextQuarterEnd()));
T::same(1, count($next), 'the carried goal appears in the next quarter');
T::same('Priority 2', $next[0]['title'], 'with its title');
T::ok($next[0]['carried_from_id'] !== null, 'linked back to the original — a priority that has slipped is visibly a priority that has slipped');


T::group('Metrics and the grid');

$revenue = Scorecard::createMetric(1, $engId, [
    'name' => 'Weekly revenue', 'unit' => '$', 'direction' => 'higher',
    'target_value' => 50000, 'owner_user_id' => $ownerId,
]);
$leadTime = Scorecard::createMetric(1, $engId, [
    'name' => 'Lead time', 'unit' => 'hours', 'direction' => 'lower',
    'target_value' => 6, 'owner_user_id' => $memberId,
]);
$noTarget = Scorecard::createMetric(1, $engId, ['name' => 'Headcount']);

T::throws(InvalidArgumentException::class,
    static fn () => Scorecard::createMetric(1, $engId, ['name' => '']), 'a metric needs a name');
T::throws(InvalidArgumentException::class,
    static fn () => Scorecard::createMetric(1, $engId, ['name' => 'X', 'direction' => 'sideways']), 'direction must be higher or lower');
T::throws(InvalidArgumentException::class,
    static fn () => Scorecard::createMetric(1, $engId, ['name' => 'X', 'frequency' => 'hourly']), 'unknown frequency refused');

$thisWeek = Scorecard::normalizePeriod(date('Y-m-d'));
$lastWeek = date('Y-m-d', strtotime($thisWeek . ' -7 days'));

Scorecard::record(1, $revenue, $thisWeek, 61000, $ownerId);
Scorecard::record(1, $revenue, $lastWeek, 42000, $ownerId);
Scorecard::record(1, $leadTime, $thisWeek, 4, $memberId);
Scorecard::record(1, $noTarget, $thisWeek, 42, $ownerId);

$grid = Scorecard::grid(1, $engId);
T::same(13, count($grid['periods']), 'the grid is thirteen periods wide');
T::same(3, count($grid['rows']), 'three metrics');

$rev = $grid['rows'][0];
T::same('Weekly revenue', $rev['name'], 'first row is revenue');
T::same(2, $rev['recorded'], 'two numbers recorded');
T::same(1, $rev['on_target'], 'one of them on target');

$last = end($rev['cells']);
T::same(61000.0, $last['value'], 'the current week holds the latest number');
T::same(true, $last['hit'], 'and it beats the target');

// Direction matters: lower-is-better inverts the comparison.
$lead = $grid['rows'][1];
$leadLast = end($lead['cells']);
T::same(true, $leadLast['hit'], '4 hours against a target of 6 is a hit when lower is better');

$hc = $grid['rows'][2];
$hcLast = end($hc['cells']);
T::same(null, $hcLast['hit'], 'a metric with no target has no verdict');

// Corrections replace rather than duplicate.
Scorecard::record(1, $revenue, $thisWeek, 59000, $ownerId);
$grid = Scorecard::grid(1, $engId);
$last = end($grid['rows'][0]['cells']);
T::same(59000.0, $last['value'], 'a re-entry corrects the number');
T::same(2, $grid['rows'][0]['recorded'], 'and does not create a second row');

// Entering a Sunday date lands in the same week as its Monday.
Scorecard::record(1, $leadTime, date('Y-m-d', strtotime($thisWeek . ' +6 days')), 9, $coachId, true);
$grid = Scorecard::grid(1, $engId);
$leadLast = end($grid['rows'][1]['cells']);
T::same(9.0, $leadLast['value'], 'a mid-week date normalises to the period start');
T::same(true, $leadLast['on_behalf'], 'and a coach entry is flagged as on-behalf');
T::same(false, $leadLast['hit'], '9 hours against a target of 6 is a miss');

T::ok(!Scorecard::record(1, 999999, $thisWeek, 1, $coachId), 'an unknown metric records nothing');


T::group('Missing numbers');

$db->exec('DELETE FROM pl_metric_values WHERE metric_id = ' . $noTarget);
$missing = array_column(Scorecard::missingThisPeriod(1, $engId), 'name');
T::ok(in_array('Headcount', $missing, true), 'a metric with no number this period is listed');
T::ok(!in_array('Weekly revenue', $missing, true), 'one that has a number is not');


T::group('Issues and the resolution record');

$issueId = Scorecard::raiseIssue(1, $engId, 'Changeover keeps slipping', 'Third week running.', 'ad_hoc', $coachId, 'high');
$urgent = Scorecard::raiseIssue(1, $engId, 'Bank covenant', null, 'session', $coachId, 'high');
Scorecard::raiseIssue(1, $engId, 'Parking', null, 'ad_hoc', $coachId, 'low');

T::throws(InvalidArgumentException::class,
    static fn () => Scorecard::raiseIssue(1, $engId, '   '), 'an issue needs a title');

$issues = Scorecard::issues(1, $engId);
T::same(3, count($issues), 'three open issues');
T::same('high', $issues[0]['priority'], 'high priority sorts first');
T::same('low', $issues[2]['priority'], 'low priority sorts last');

T::throws(InvalidArgumentException::class,
    static fn () => Scorecard::resolveIssue(1, $issueId, 'ident', 'disc', '   ', $coachId),
    'an issue is not resolved until something is DECIDED');

T::ok(Scorecard::resolveIssue(1, $issueId, 'Supplier lead time, not the floor.', 'Talked through the schedule.', 'Dual-source the bearing by 30 Sept.', $coachId),
    'resolving with a decision works');
T::ok(!Scorecard::resolveIssue(1, $issueId, 'a', 'b', 'c', $coachId), 'resolving twice does nothing');

$res = $db->query('SELECT * FROM pl_issue_resolutions WHERE issue_id = ' . $issueId)->fetch();
T::ok(str_contains((string) $res['decided'], 'Dual-source'), 'the decision is recorded');
T::ok(str_contains((string) $res['identified'], 'Supplier lead time'), 'as is what the problem really was');

T::same(2, count(Scorecard::issues(1, $engId)), 'the resolved issue leaves the open list');
T::same(3, count(Scorecard::issues(1, $engId, false)), 'but is still there in the full list');


T::group('Agenda blocks — M9 pays off the last three');

AgendaBuilder::resetProviders();
$tpl = StarterSessionTemplates::installWorkingSession(1);
$sessionId = SessionService::schedule(1, $engId, 'Fortnightly', date('Y-m-d H:i:s'), $tpl, $coachId);
$session = SessionService::find(1, $sessionId);

T::same(5, count(AgendaBuilder::unresolvedBlocks(1)), 'all five blocks start unresolved');

Scorecard::registerAgendaProviders();
$owed = AgendaBuilder::unresolvedBlocks(1);
sort($owed);
T::same(['commitments', 'steps'], $owed, 'M9 resolves metrics, goals and issues');

$metricsBlock = AgendaBuilder::build('metrics', 1, $session);
T::ok(count($metricsBlock['items']) > 0, 'the numbers block is filled');
T::ok(str_contains(implode(' ', $metricsBlock['items']), 'OFF TARGET'), 'and calls out a miss plainly');

$goalsBlock = AgendaBuilder::build('goals', 1, $session);
T::ok(count($goalsBlock['items']) > 0, 'the priorities block is filled');

$issuesBlock = AgendaBuilder::build('issues', 1, $session);
T::ok(count($issuesBlock['items']) > 0, 'the issues block is filled');
T::ok(str_contains(implode(' ', $issuesBlock['items']), '!'), 'high priority is marked');


T::group('Tenant isolation');

T::same([], Scorecard::goals(2, $engId), "tenant B sees none of tenant A's goals");
T::same(0, Scorecard::goalLoad(2, $engId)['count'], 'nor its goal load');
T::same([], Scorecard::grid(2, $engId)['rows'], 'nor its metrics');
T::same([], Scorecard::issues(2, $engId), 'nor its issues');
T::same([], Scorecard::missingThisPeriod(2, $engId), 'nor its missing numbers');
T::same(null, Scorecard::metric(2, $revenue), 'nor a metric by id');
T::ok(!Scorecard::record(2, $revenue, $thisWeek, 1, $coachId), "nor record against tenant A's metric");
T::ok(!Scorecard::setGoalStatus(2, (int) $goals[3]['id'], 'done'), "nor change tenant A's goal");
T::ok(!Scorecard::resolveIssue(2, $urgent, 'a', 'b', 'c', $coachId), "nor resolve tenant A's issue");
T::same(['scored' => 0, 'carried' => 0], Scorecard::rollQuarter(2, $engId, [(int) $goals[3]['id'] => 'done']), "nor roll tenant A's quarter");


T::group('Qualifiers — M9 pays what it owes');

Policy::resetQualifiers();
Qualifiers::register();

T::ok(!in_array('owner', Policy::unresolvedQualifiers(), true), 'owner is now resolved');

$member = ['id' => $memberId, 'role' => 'client_member', 'status' => 'active', 'client_org_id' => $orgId];

T::ok(Policy::can($member, Policy::UPDATE, 'metric_value', ['owner_user_id' => $memberId]),
    'a team member may enter a number for a metric they own');
T::ok(!Policy::can($member, Policy::UPDATE, 'metric_value', ['owner_user_id' => $ownerId]),
    "but not for someone else's — a scorecard anyone can edit is not a scorecard");
T::ok(!Policy::can($member, Policy::UPDATE, 'metric_value', []), 'a missing owner denies');
T::ok(!Policy::can($member, Policy::UPDATE, 'metric_value', null), 'as does no context');

Policy::resetQualifiers();
AgendaBuilder::resetProviders();
