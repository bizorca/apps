<?php

declare(strict_types=1);

/**
 * M12: engagement health, the firm roll-up, and the period report.
 *
 * The group that carries this file is "No evidence is not bad evidence". A
 * health score that opens a brand-new engagement by calling it At risk teaches
 * a coach to ignore the number within a week, and a number people ignore is
 * worse than no number — it still gets shown, it still shapes an impression,
 * and nobody interrogates it.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\TaskRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Health;
use Bizorca\Pilotage\Services\Reports;
use Bizorca\Pilotage\Services\Scorecard;
use Bizorca\Pilotage\Services\SessionService;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_notifications', 'pl_metric_values', 'pl_metrics', 'pl_goals', 'pl_issues',
          'pl_messages', 'pl_thread_participants', 'pl_threads',
          'pl_session_attendees', 'pl_sessions', 'pl_tasks', 'pl_user_sessions',
          'pl_engagements', 'pl_client_orgs', 'pl_users', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'other','Other','active')");

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'Ada Coach', 'role' => 'coach', 'status' => 'active']);
$orgs = new ClientOrgRepository(1);
$engagements = new EngagementRepository(1);

$aOrg = $orgs->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active']);
$bOrg = $orgs->createOrg(['name' => 'Beta Dental', 'status' => 'active']);

$danaId = $users->create(['email' => 'dana@alpha.test', 'name' => 'Dana Owner', 'role' => 'client_owner', 'client_org_id' => $aOrg, 'status' => 'active']);
$bevId  = $users->create(['email' => 'bev@beta.test',  'name' => 'Bev Owner',  'role' => 'client_owner', 'client_org_id' => $bOrg, 'status' => 'active']);

$alpha = $engagements->createEngagement(['client_org_id' => $aOrg, 'title' => 'Alpha Q3', 'status' => 'active', 'coach_user_id' => $coachId]);
$beta  = $engagements->createEngagement(['client_org_id' => $bOrg, 'title' => 'Beta Q3',  'status' => 'active', 'coach_user_id' => $coachId]);

$tasks = new TaskRepository(1);


T::group('No evidence is not bad evidence');

$fresh = Health::forEngagement(1, $beta);

T::same(null, $fresh['score'], 'a brand-new engagement gets no score at all');
T::same(0, $fresh['measured'], 'because nothing has happened to measure');
T::ok(str_contains($fresh['note'], 'Too early'), 'and it says so plainly');
T::same(5, count($fresh['factors']), 'all five factors are still reported');

foreach ($fresh['factors'] as $factor) {
    T::same(false, $factor['counted'], $factor['label'] . ' is explicitly not counted');
    T::same(null, $factor['value'], 'and carries no value rather than a zero');
    T::ok($factor['detail'] !== '', 'but still explains why');
}


T::group('Commitments kept');

// Four due in the window: three kept, one still open and late.
foreach ([['-30 days', 'done'], ['-20 days', 'done'], ['-10 days', 'done'], ['-5 days', 'open']] as $i => [$when, $status]) {
    $id = $tasks->createTask([
        'engagement_id' => $alpha, 'title' => 'Commitment ' . $i,
        'owner_user_id' => $danaId, 'due_on' => date('Y-m-d', strtotime($when)), 'status' => 'open',
    ]);

    if ($status === 'done') {
        $tasks->complete($id, $danaId);
    }
}

// One due far in the future — not yet anyone's problem.
$tasks->createTask([
    'engagement_id' => $alpha, 'title' => 'Later',
    'owner_user_id' => $danaId, 'due_on' => date('Y-m-d', strtotime('+30 days')), 'status' => 'open',
]);

// One with no due date at all — nothing can be late that was never due.
$tasks->createTask([
    'engagement_id' => $alpha, 'title' => 'Someday',
    'owner_user_id' => $danaId, 'status' => 'open',
]);

$h = Health::forEngagement(1, $alpha);
$commitments = null;

foreach ($h['factors'] as $f) {
    if ($f['key'] === 'commitments') {
        $commitments = $f;
    }
}

T::same(75, $commitments['percent'], 'three of four that came due were kept');
T::ok(str_contains($commitments['detail'], '3 of 4'), 'and the detail says exactly that');
T::ok(str_contains($commitments['detail'], '1 still open'), 'naming the one that is late');
T::ok($h['score'] !== null, 'now there is a score');
T::same(1, $h['measured'], 'built from the one signal that has evidence');


T::group('A cancelled session and a no-show are not the same thing');

$s1 = SessionService::schedule(1, $alpha, 'Held one', date('Y-m-d H:i:s', strtotime('-20 days')), null, $coachId);
$s2 = SessionService::schedule(1, $alpha, 'Held two', date('Y-m-d H:i:s', strtotime('-13 days')), null, $coachId);
$s3 = SessionService::schedule(1, $alpha, 'Cancelled', date('Y-m-d H:i:s', strtotime('-6 days')), null, $coachId);
$s4 = SessionService::schedule(1, $alpha, 'Not attended', date('Y-m-d H:i:s', strtotime('-3 days')), null, $coachId);

$db->exec("UPDATE pl_sessions SET status='complete', ended_at = scheduled_at WHERE id IN ({$s1},{$s2})");
$db->exec("UPDATE pl_sessions SET status='cancelled' WHERE id = {$s3}");
$db->exec("UPDATE pl_sessions SET status='no_show' WHERE id = {$s4}");

$sessions = null;

foreach (Health::forEngagement(1, $alpha)['factors'] as $f) {
    if ($f['key'] === 'sessions') {
        $sessions = $f;
    }
}

T::same(50, $sessions['percent'], 'two of four booked were held');
T::ok(str_contains($sessions['detail'], '1 cancelled'), 'a cancellation is named');
T::ok(str_contains($sessions['detail'], '1 not attended'), 'and a no-show is named separately');


T::group('Weights are applied, and a weak signal drags');

$h = Health::forEngagement(1, $alpha);

// commitments 0.75 × 3.0, sessions 0.50 × 2.5 → 3.5 / 5.5
$expected = round((0.75 * 3.0 + 0.5 * 2.5) / (3.0 + 2.5) * 100, 1);

T::same($expected, $h['score'], 'the score is the weighted mean of what could be measured');
T::same(2, $h['measured'], 'over exactly two signals');
T::ok($h['band'] !== null, 'and it lands in a band');
T::ok($h['note'] !== '', 'which says what to do about it');


T::group('Bands say what to do, not what colour to be');

T::same('Healthy',  Health::bandFor(95.0)['label'], '95 is healthy');
T::same('Steady',   Health::bandFor(70.0)['label'], '70 is steady');
T::same('Drifting', Health::bandFor(45.0)['label'], '45 is drifting');
T::same('At risk',  Health::bandFor(10.0)['label'], '10 is at risk');
T::same('Healthy',  Health::bandFor(80.0)['label'], 'the boundary belongs to the better band');

foreach (Health::BANDS as $band) {
    T::ok(strlen($band['note']) > 20, $band['label'] . ' carries a sentence, not a colour');
}


T::group('Metric consistency is measured against elapsed periods, not the calendar');

$metricId = Scorecard::createMetric(1, $alpha, ['name' => 'Weekly revenue', 'frequency' => 'weekly', 'unit' => 'USD']);

// Backdate the metric eight weeks and enter four of the eight.
$db->exec("UPDATE pl_metrics SET created_at = NOW() - INTERVAL 56 DAY WHERE id = {$metricId}");

for ($w = 1; $w <= 4; $w++) {
    Scorecard::record(1, $metricId, date('Y-m-d', strtotime("-{$w} weeks")), 1000.0 + $w, $coachId);
}

$metrics = null;

foreach (Health::forEngagement(1, $alpha)['factors'] as $f) {
    if ($f['key'] === 'metrics') {
        $metrics = $f;
    }
}

T::ok($metrics['counted'], 'the metric factor now counts');
T::ok($metrics['percent'] >= 45 && $metrics['percent'] <= 55, 'roughly half the expected entries are in');
T::ok(str_contains($metrics['detail'], 'Behind'), 'and it names what is behind');

// A metric created this morning is not eight weeks behind.
$newMetric = Scorecard::createMetric(1, $beta, ['name' => 'Brand new', 'frequency' => 'weekly']);
$betaMetrics = null;

foreach (Health::forEngagement(1, $beta)['factors'] as $f) {
    if ($f['key'] === 'metrics') {
        $betaMetrics = $f;
    }
}

T::same(false, $betaMetrics['counted'], 'a metric defined today is too new to be behind on');
T::ok(str_contains($betaMetrics['detail'], 'too new'), 'and says so rather than scoring zero');


T::group('Responsiveness measures the client, not the coach');

$coach = $db->query("SELECT * FROM pl_users WHERE id = {$coachId}")->fetch();
$dana  = $db->query("SELECT * FROM pl_users WHERE id = {$danaId}")->fetch();

$answered = \Bizorca\Pilotage\Services\Messaging::createThread(1, $alpha, 'Answered', 'Question one?', $coach, true);
\Bizorca\Pilotage\Services\Messaging::post(1, $answered, 'Here you go.', $dana, true);

\Bizorca\Pilotage\Services\Messaging::createThread(1, $alpha, 'Ignored', 'Question two?', $coach, true);

// A thread the CLIENT started is not evidence about whether they answer.
\Bizorca\Pilotage\Services\Messaging::createThread(1, $alpha, 'Their own', 'A thought.', $dana, true);

$responses = null;

foreach (Health::forEngagement(1, $alpha)['factors'] as $f) {
    if ($f['key'] === 'responses') {
        $responses = $f;
    }
}

T::same(50, $responses['percent'], 'one of the two threads the firm started got a reply');
T::ok(str_contains($responses['detail'], '1 of 2'), 'and the detail is countable');


T::group('The firm roll-up');

$firm = Reports::firmDashboard(1);

T::same(2, (int) $firm['counts']['active'], 'two active engagements');
T::same(2, (int) $firm['counts']['clients'], 'two clients');
T::same(1, count($firm['coaches']), 'one coach holds a seat');
T::same(2, (int) $firm['coaches'][0]['engagements'], 'and carries both engagements');
T::ok((int) $firm['coaches'][0]['open_commitments'] > 0, 'with open commitments counted');
T::same(2, (int) $firm['coaches'][0]['sessions_30d'], 'and sessions actually held in the last 30 days');

T::same(2, count($firm['engagements']), 'every active engagement is scored');

foreach ($firm['at_risk'] as $risky) {
    T::ok($risky['health']['score'] < 60, 'everything on the at-risk list scores under 60');
}

if (count($firm['at_risk']) > 1) {
    T::ok(
        $firm['at_risk'][0]['health']['score'] <= $firm['at_risk'][1]['health']['score'],
        'and the weakest is first'
    );
}


T::group('The period report');

Scorecard::createGoal(1, $alpha, ['title' => 'Reach $1.2m run rate', 'quarter_label' => Scorecard::quarterLabel()]);
Scorecard::raiseIssue(1, $alpha, 'Hiring is stalled', 'Two roles open since June.', 'ad_hoc', $coachId);

[$from, $to] = [date('Y-m-d', strtotime('-90 days')), date('Y-m-d')];
$report = Reports::engagementReport(1, $alpha, $from, $to);

T::same('Alpha Manufacturing', (string) $report['engagement']['org_name'], 'the report knows whose it is');
T::same(4, $report['commitments']['total'], 'four commitments came due in the period');
T::same(3, $report['commitments']['kept'], 'three of them were kept');
T::same(4, count($report['sessions']), 'four sessions fall in the period');
T::same(1, count($report['goals']), 'one goal is live');
T::same(1, count($report['issues']), 'one issue was raised');
T::ok($report['health']['score'] !== null, 'and the health read comes along with it');

$movement = $report['metrics'];
T::same(1, count($movement), 'one metric is tracked');
T::ok($movement[0]['period_first'] !== null, 'it has a value at the start of the period');
T::ok($movement[0]['period_last'] !== null, 'and one at the end');


T::group('Movement is judged against the metric\'s own direction');

$down = Scorecard::createMetric(1, $alpha, ['name' => 'Days to collect', 'frequency' => 'weekly', 'direction' => 'lower']);
Scorecard::record(1, $down, date('Y-m-d', strtotime('-3 weeks')), 50.0, $coachId);
Scorecard::record(1, $down, date('Y-m-d', strtotime('-1 week')), 32.0, $coachId);

$movement = Reports::metricMovement(1, $alpha, $from, $to);
$daysToCollect = null;

foreach ($movement as $m) {
    if ($m['name'] === 'Days to collect') {
        $daysToCollect = $m;
    }
}

T::ok((float) $daysToCollect['delta'] < 0, 'days to collect went down');
T::same(true, $daysToCollect['improved'], 'and for a down-is-good metric that is an improvement');

$revenue = null;

foreach ($movement as $m) {
    if ($m['name'] === 'Weekly revenue') {
        $revenue = $m;
    }
}

T::same(false, $revenue['improved'], 'while revenue falling would not be — same arithmetic, opposite reading');


T::group('One run is a measurement, not a trend');

T::same([], Reports::assessmentDeltas(1, $beta), 'an engagement with no assessments reports none');


T::group('CSV export');

$csv = Reports::toCsv($report);

T::ok(str_contains($csv, 'Engagement report'), 'the export is titled');
T::ok(str_contains($csv, 'Alpha Manufacturing'), 'and names the client');
T::ok(str_contains($csv, 'Commitments'), 'it has a commitments section');
T::ok(str_contains($csv, 'Metric movement'), 'a metrics section');
T::ok(str_contains($csv, 'Health'), 'and the health read');
T::ok(str_contains($csv, 'not measured') || str_contains($csv, '%'), 'with each factor scored or explicitly not');

// A title containing a comma must not become two columns.
$tricky = $tasks->createTask([
    'engagement_id' => $alpha, 'title' => 'Reconcile bank, card, and petty cash',
    'owner_user_id' => $danaId, 'due_on' => date('Y-m-d', strtotime('-2 days')), 'status' => 'open',
]);
$csv = Reports::toCsv(Reports::engagementReport(1, $alpha, $from, $to));

T::ok(str_contains($csv, '"Reconcile bank, card, and petty cash"'),
    'a comma inside a title is quoted, not allowed to shift every column right');


T::group('Reports are tenant-scoped');

$otherOrg = (new ClientOrgRepository(2))->createOrg(['name' => 'Not Yours', 'status' => 'active']);
$otherEng = (new EngagementRepository(2))->createEngagement(['client_org_id' => $otherOrg, 'title' => 'Theirs', 'status' => 'active']);

T::throws(RuntimeException::class,
    static fn () => Reports::engagementReport(1, $otherEng, $from, $to),
    "one tenant cannot report on another's engagement");

$otherFirm = Reports::firmDashboard(2);
T::same(1, (int) $otherFirm['counts']['active'], 'and each firm roll-up counts only its own');

$scoped = Health::forEngagement(2, $alpha);
T::same(null, $scoped['score'], "scoring another tenant's engagement finds nothing to score");


T::group('The default period is the current quarter to date');

[$qFrom, $qTo] = Reports::defaultPeriod('2026-11-14');
T::same('2026-10-01', $qFrom, 'November falls in the quarter starting in October');
T::same('2026-11-14', $qTo, 'and it runs to today, not to the end of the quarter');

[$qFrom] = Reports::defaultPeriod('2026-01-02');
T::same('2026-01-01', $qFrom, 'the second of January is two days into Q1');


T::group('Enough to compute is not enough to pronounce on');

/**
 * The failure this guards against was found by loading the page, not by a
 * test: an engagement whose only measurable signal was "somebody signed in
 * today" rendered as Healthy, 100. Arithmetically correct — it IS the weighted
 * mean of everything measurable — and completely misleading. A coach reading
 * that would believe something about a relationship on the strength of one
 * login.
 */
$thin = $engagements->createEngagement([
    'client_org_id' => $bOrg, 'title' => 'Barely started', 'status' => 'active', 'coach_user_id' => $coachId,
]);

// Give it presence and nothing else: a client-side session, weight 1.0.
$db->prepare(
    "INSERT INTO pl_user_sessions (id, tenant_id, user_id, is_firm_side, expires_at)
     VALUES (:id, 1, :uid, 0, NOW() + INTERVAL 1 DAY)"
)->execute(['id' => str_repeat('a', 64), 'uid' => $bevId]);

$h = Health::forEngagement(1, $thin);

T::same(1, $h['measured'], 'exactly one signal can be measured');
T::same(1.0, $h['possible'], 'carrying a weight of 1.0');
T::same(null, $h['score'], 'and NO score is published — one login is not a verdict');
T::same(null, $h['band'], 'nor a band');
T::ok(str_contains($h['note'], 'too thin'), 'the note says why rather than going quiet');
T::ok(str_contains($h['note'], 'client signing in'), 'and names what little is known');

// Alpha, with commitments (3.0) plus sessions (2.5), clears the bar easily.
$strong = Health::forEngagement(1, $alpha);
T::ok($strong['possible'] >= Health::MIN_WEIGHT_FOR_BAND, 'a real engagement clears the threshold');
T::ok($strong['score'] !== null, 'and does get a score');

T::ok(Health::MIN_WEIGHT_FOR_BAND > Health::WEIGHTS['presence'],
    'the threshold is set above presence alone, deliberately');


T::group('Numbers are rendered as people write them');

/**
 * MySQL returns DECIMAL columns as fixed-scale strings, so a metric of 34 comes
 * back as "34.0000". Rendered raw it makes a scoreboard look like a database
 * dump, and "36.0000% margin" claims a precision the number does not have.
 */
T::same('34', num('34.0000'), 'trailing zeros go');
T::same('3.5', num('3.5000'), 'but a genuine decimal stays');
T::same('1,234.5', num(1234.5), 'thousands are separated');
T::same('0', num(0), 'zero is zero, not an empty string');
T::same('', num(null), 'and null is empty');
T::same('-18', num('-18.0000'), 'negatives survive');
T::same('16.67', num(16.666666), 'and precision is capped rather than sprawling');
