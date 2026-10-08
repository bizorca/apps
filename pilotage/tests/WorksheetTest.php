<?php

declare(strict_types=1);

/**
 * M10: worksheets, scored assessments, and anonymous surveys.
 *
 * The load-bearing group is "Anonymity is structural". FR-10.6 hides
 * individual responses from the COACH, which a visibility flag cannot deliver
 * — anyone with database access could still read who said what. So the test
 * that matters queries the raw table and asserts the link does not exist.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Worksheets;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_worksheet_subscores', 'pl_worksheet_answers', 'pl_worksheet_responses',
          'pl_worksheet_assignments', 'pl_worksheet_bands', 'pl_worksheet_fields',
          'pl_worksheets', 'pl_engagements', 'pl_client_orgs', 'pl_users', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme','active'),(2,'other','Other','active')");

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);
$orgId = (new ClientOrgRepository(1))->createOrg(['name' => 'Alpha', 'status' => 'active']);
$owner = $users->create(['email' => 'dana@alpha.test', 'name' => 'Dana', 'role' => 'client_owner', 'client_org_id' => $orgId, 'status' => 'active']);
$m1 = $users->create(['email' => 'jo@alpha.test', 'name' => 'Jo', 'role' => 'client_member', 'client_org_id' => $orgId, 'status' => 'active']);
$m2 = $users->create(['email' => 'sam@alpha.test', 'name' => 'Sam', 'role' => 'client_member', 'client_org_id' => $orgId, 'status' => 'active']);
$engId = (new EngagementRepository(1))->createEngagement(['client_org_id' => $orgId, 'title' => 'Q3', 'status' => 'active']);


T::group('Authoring');

$wsId = Worksheets::create(1, ['title' => 'Business health check', 'kind' => 'assessment'], $coachId);
T::ok($wsId > 0, 'a worksheet is created');

T::throws(InvalidArgumentException::class,
    static fn () => Worksheets::create(1, ['title' => '  ']), 'it needs a title');
T::throws(InvalidArgumentException::class,
    static fn () => Worksheets::create(1, ['title' => 'X', 'kind' => 'quiz']), 'and a known type');

Worksheets::addField(1, $wsId, ['field_type' => 'section', 'label' => 'Money']);
$cash = Worksheets::addField(1, $wsId, [
    'field_type' => 'scale', 'label' => 'How confident are you in next quarter\'s cash?',
    'scale_min' => 1, 'scale_max' => 10, 'category' => 'Finance', 'required' => 1,
]);
$margin = Worksheets::addField(1, $wsId, [
    'field_type' => 'scale', 'label' => 'Do you know your margin by product?',
    'scale_min' => 1, 'scale_max' => 10, 'category' => 'Finance', 'weight' => 2,
]);
$people = Worksheets::addField(1, $wsId, [
    'field_type' => 'scale', 'label' => 'Could you take a month off?',
    'scale_min' => 1, 'scale_max' => 10, 'category' => 'People',
]);
$note = Worksheets::addField(1, $wsId, ['field_type' => 'long_text', 'label' => 'Anything else?']);

T::throws(InvalidArgumentException::class,
    static fn () => Worksheets::addField(1, $wsId, ['field_type' => 'scale', 'label' => '']), 'a field needs a label');
T::throws(InvalidArgumentException::class,
    static fn () => Worksheets::addField(1, $wsId, ['field_type' => 'select_one', 'label' => 'Pick']),
    'a choice field needs choices');

T::same(5, count(Worksheets::fields(1, $wsId)), 'five fields including the heading');
T::same(4, count(Worksheets::answerableFields(1, $wsId)), 'four of them take an answer');

$structural = $db->query("SELECT required FROM pl_worksheet_fields WHERE field_type = 'section'")->fetch();
T::same(0, (int) $structural['required'], 'a heading cannot be required — there is nothing to fill in');

Worksheets::addBand(1, $wsId, ['min_percent' => 0, 'max_percent' => 39, 'label' => 'Fragile',
    'interpretation' => 'Most of the business is in your head.']);
Worksheets::addBand(1, $wsId, ['min_percent' => 40, 'max_percent' => 69, 'label' => 'Coming together']);
Worksheets::addBand(1, $wsId, ['min_percent' => 70, 'max_percent' => 100, 'label' => 'Solid']);

T::throws(InvalidArgumentException::class,
    static fn () => Worksheets::addBand(1, $wsId, ['min_percent' => 80, 'max_percent' => 20, 'label' => 'Backwards']),
    'a band cannot start above where it ends');


T::group('Publishing gates assignment');

T::throws(RuntimeException::class,
    static fn () => Worksheets::assign(1, $wsId, $engId, $owner), 'a draft cannot be sent out');

$empty = Worksheets::create(1, ['title' => 'Nothing in it']);
T::throws(RuntimeException::class,
    static fn () => Worksheets::publish(1, $empty), 'an empty worksheet cannot be published');

T::ok(Worksheets::publish(1, $wsId), 'a worksheet with questions publishes');


T::group('Answering and save-and-resume');

$a1 = Worksheets::assign(1, $wsId, $engId, $owner, 'Baseline', null, $coachId);
$r = Worksheets::startOrResume(1, $a1, $owner);
T::ok((int) $r['id'] > 0, 'a response is started');
T::same('in_progress', $r['status'], 'and is in progress');
T::same($owner, (int) $r['respondent_user_id'], 'named, because this worksheet is not anonymous');

Worksheets::saveAnswer(1, (int) $r['id'], $cash, '4');

$again = Worksheets::startOrResume(1, $a1, $owner);
T::same((int) $r['id'], (int) $again['id'], 'coming back resumes the same response rather than starting over');

$answers = Worksheets::answersByField(1, (int) $r['id']);
T::same(4.0, (float) $answers[$cash]['value_number'], 'the saved answer is still there');

Worksheets::saveAnswer(1, (int) $r['id'], $cash, '5');
$answers = Worksheets::answersByField(1, (int) $r['id']);
T::same(5.0, (float) $answers[$cash]['value_number'], 'and changing it overwrites rather than duplicating');


T::group('Required fields block submission');

$blocked = Worksheets::submit(1, (int) $r['id']);
T::same([], $blocked['missing'], 'the one required field is answered, so nothing is missing');

$r2assign = Worksheets::assign(1, $wsId, $engId, $m1, 'Baseline', null, $coachId);
$r2 = Worksheets::startOrResume(1, $r2assign, $m1);
$result = Worksheets::submit(1, (int) $r2['id']);
T::same(1, count($result['missing']), 'a missing required answer blocks submission');
T::ok(str_contains($result['missing'][0], 'cash'), 'and names the question');
T::same('in_progress', Worksheets::response(1, (int) $r2['id'])['status'], 'the response stays open');


T::group('Scoring');

// 5/10 on cash (weight 1), 8/10 on margin (weight 2), 3/10 on people (weight 1).
$r3assign = Worksheets::assign(1, $wsId, $engId, $m2, 'Baseline', null, $coachId);
$r3 = Worksheets::startOrResume(1, $r3assign, $m2);
Worksheets::saveAnswer(1, (int) $r3['id'], $cash, 5);
Worksheets::saveAnswer(1, (int) $r3['id'], $margin, 8);
Worksheets::saveAnswer(1, (int) $r3['id'], $people, 3);
$scored = Worksheets::submit(1, (int) $r3['id']);

// cash (5-1)/9 = .444; margin (8-1)/9 = .778 weighted x2; people (3-1)/9 = .222
// overall = (.444 + 1.556 + .222) / 4 = .5556 -> 55.56%
T::ok(abs($scored['score'] - 55.56) < 0.1, 'weighted scoring is proportional to each scale');
T::same('Coming together', $scored['band'], 'and lands in the right band');

$subs = Worksheets::subscores(1, (int) $r3['id']);
$byCat = [];
foreach ($subs as $s) { $byCat[(string) $s['category']] = (float) $s['score_percent']; }
T::same(2, count($byCat), 'two category subscores');
T::ok(abs($byCat['Finance'] - 66.67) < 0.1, 'Finance averages its two weighted questions');
T::ok(abs($byCat['People'] - 22.22) < 0.1, 'People reflects its single low answer');

// An unanswered OPTIONAL question must not be scored as zero.
$r4assign = Worksheets::assign(1, $wsId, $engId, $owner, 'Partial', null, $coachId);
$r4 = Worksheets::startOrResume(1, $r4assign, $owner);
Worksheets::saveAnswer(1, (int) $r4['id'], $cash, 10);
$partial = Worksheets::submit(1, (int) $r4['id']);
T::same(100.0, (float) $partial['score'],
    'skipping optional questions does not score you down — they leave the denominator');


T::group('Anonymity is structural, not a flag');

$survey = Worksheets::create(1, ['title' => 'How is it going?', 'kind' => 'survey', 'min_responses' => 3], $coachId);
$morale = Worksheets::addField(1, $survey, [
    'field_type' => 'scale', 'label' => 'How is morale?', 'scale_min' => 1, 'scale_max' => 5,
]);
$gripe = Worksheets::addField(1, $survey, ['field_type' => 'long_text', 'label' => 'What would you change?']);
Worksheets::publish(1, $survey);

$w = Worksheets::find(1, $survey);
T::same(1, (int) $w['is_anonymous'], 'a survey is anonymous by definition, without being asked');

T::throws(InvalidArgumentException::class,
    static fn () => Worksheets::assign(1, $survey, $engId, $owner),
    'an anonymous worksheet cannot be sent to ONE person — that would not be anonymous');

$sa = Worksheets::assign(1, $survey, $engId, null, 'Q3 pulse', null, $coachId);

foreach ([[$owner, 2, 'Too many meetings'], [$m1, 4, 'It is fine'], [$m2, 1, 'Leadership does not listen']] as [$uid, $score, $text]) {
    $resp = Worksheets::startOrResume(1, $sa, $uid);
    Worksheets::saveAnswer(1, (int) $resp['id'], $morale, $score);
    Worksheets::saveAnswer(1, (int) $resp['id'], $gripe, $text);
    Worksheets::submit(1, (int) $resp['id']);
}

// The assertion that matters: the link is GONE, not hidden.
$raw = $db->query(
    "SELECT respondent_user_id FROM pl_worksheet_responses WHERE assignment_id = {$sa}"
)->fetchAll(PDO::FETCH_COLUMN);

T::same(3, count($raw), 'three responses were recorded');
foreach ($raw as $who) {
    T::same(null, $who, 'and NOT ONE of them stores who wrote it — even in the raw table');
}

$results = Worksheets::results(1, $sa);
T::ok($results['anonymous'], 'results know they are anonymous');
T::same([], $results['responses'], 'and return no individual responses at all');
T::same(3, $results['count'], 'while still reporting how many came in');

$json = json_encode($results);
T::ok(!str_contains($json, 'Leadership does not listen'), 'no free-text answer leaks into the coach view');
T::ok(!str_contains($json, 'Too many meetings'), 'really, none of it');

$agg = $results['aggregates'];
$moraleAgg = null;
foreach ($agg as $a) { if (str_contains((string) $a['label'], 'morale')) { $moraleAgg = $a; } }
T::ok($moraleAgg !== null, 'the scale question is aggregated');
T::ok(abs((float) $moraleAgg['mean'] - 2.33) < 0.05, 'with a mean the coach can act on');

$textAgg = null;
foreach ($agg as $a) { if (str_contains((string) $a['label'], 'change')) { $textAgg = $a; } }
T::same(3, (int) $textAgg['n'], 'free text reports HOW MANY answered');
T::ok(!isset($textAgg['values']), 'but never the words — a sentence identifies its author to a team of eight');


T::group('The threshold withholds small samples');

$small = Worksheets::create(1, ['title' => 'Tiny survey', 'kind' => 'survey', 'min_responses' => 5], $coachId);
Worksheets::addField(1, $small, ['field_type' => 'scale', 'label' => 'Rate it', 'scale_min' => 1, 'scale_max' => 5]);
Worksheets::publish(1, $small);
$smallA = Worksheets::assign(1, $small, $engId, null, 'Too few', null, $coachId);

foreach ([$owner, $m1] as $uid) {
    $resp = Worksheets::startOrResume(1, $smallA, $uid);
    Worksheets::saveAnswer(1, (int) $resp['id'], (int) Worksheets::answerableFields(1, $small)[0]['id'], 3);
    Worksheets::submit(1, (int) $resp['id']);
}

$r = Worksheets::results(1, $smallA);
T::ok($r['withheld'], 'two responses against a threshold of five is withheld');
T::same([], $r['aggregates'], 'and no aggregate is given — arithmetic would de-anonymise it');
T::same(2, $r['count'], 'though the count is shown, so the coach knows to chase');


T::group('The same assessment over time (FR-10.5)');

$trend = Worksheets::overTime(1, $wsId, $engId);
T::ok(count($trend) >= 2, 'multiple assignments of one assessment line up');
T::same(null, $trend[0]['delta'], 'the first has nothing to compare against');
T::ok($trend[1]['delta'] !== null, 'later ones carry a delta — the ROI story in one column');


T::group('CSV export');

$csv = Worksheets::exportCsv(1, $a1);
T::ok(str_contains($csv, 'Respondent'), 'a named export has a respondent column');
T::ok(str_contains($csv, 'Dana'), 'and names them');

$anonCsv = Worksheets::exportCsv(1, $sa);
T::ok(!str_contains($anonCsv, 'Respondent'), 'an anonymous export has no respondent column');
T::ok(!str_contains($anonCsv, 'Leadership does not listen'), 'and no free text');
T::ok(str_contains($anonCsv, 'Mean'), 'it exports aggregates instead');

$withheldCsv = Worksheets::exportCsv(1, $smallA);
T::ok(str_contains($withheldCsv, 'Withheld'), 'a below-threshold export says so rather than leaking');


T::group('Tenant isolation');

T::same(null, Worksheets::find(2, $wsId), "tenant B cannot read tenant A's worksheet");
T::same([], Worksheets::fields(2, $wsId), 'nor its fields');
T::same([], Worksheets::all(2), 'nor list them');
T::same(null, Worksheets::assignment(2, $a1), 'nor its assignments');
T::same([], Worksheets::assignmentsFor(2, $engId), 'nor an engagement\'s');
T::same(null, Worksheets::response(2, (int) $r3['id']), 'nor a response');
T::same([], Worksheets::answersByField(2, (int) $r3['id']), 'nor its answers');
T::same([], Worksheets::subscores(2, (int) $r3['id']), 'nor its subscores');
T::same([], Worksheets::overTime(2, $wsId, $engId), 'nor a trend');
T::throws(RuntimeException::class, static fn () => Worksheets::results(2, $a1), 'nor results');
T::throws(RuntimeException::class, static fn () => Worksheets::exportCsv(2, $a1), 'nor an export');
// Refused, though by a slightly indirect route: tenant B cannot see the
// fields, so the "needs a question" guard fires before anything else. Either
// way nothing of tenant A's is published.
$refused = false;
try {
    Worksheets::publish(2, $wsId);
} catch (Throwable) {
    $refused = true;
}
T::ok($refused, "nor publish tenant A's worksheet");
T::same('published', Worksheets::find(1, $wsId)['status'], "and tenant A's own status is untouched");
