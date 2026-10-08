<?php

declare(strict_types=1);

/**
 * M4: the playbook engine.
 *
 * The tests that matter most are the ones proving instantiation is a real
 * copy. If a template edit can reach a live engagement, the product has
 * rewritten what a client agreed to — which is worse than a crash, because
 * nobody notices.
 */

use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Qualifiers;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\PlaybookAuthor;
use Bizorca\Pilotage\Services\PlaybookInstantiator;
use Bizorca\Pilotage\Services\PlaybookRunner;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_engagement_step_artifacts', 'pl_engagement_steps', 'pl_engagement_phases',
          'pl_engagement_playbooks', 'pl_engagement_members', 'pl_engagements',
          'pl_playbook_step_artifacts', 'pl_playbook_steps', 'pl_playbook_phases',
          'pl_playbook_versions', 'pl_playbooks', 'pl_org_events', 'pl_client_contacts',
          'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'northstar','Northstar','active')");

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);

$orgs = new ClientOrgRepository(1);
$orgId = $orgs->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active', 'owner_user_id' => $coachId]);

$engagements = new EngagementRepository(1);
$engId = $engagements->createEngagement([
    'client_org_id' => $orgId,
    'title'         => 'Q3 operating rhythm',
    'status'        => 'active',
    'coach_user_id' => $coachId,
    'cadence'       => 'biweekly',
]);


T::group('Authoring — draft, phases, steps, publish');

$pbId = PlaybookAuthor::create(1, '90-day operating rhythm', 'Quarterly cadence', $coachId);
$draft = PlaybookAuthor::draftVersion(1, $pbId);

T::same(1, (int) $draft['version_number'], 'first version is 1');
T::same('draft', $draft['state'], 'starts as a draft');

$discovery = PlaybookAuthor::addPhase(1, (int) $draft['id'], 'Discovery');
$execution = PlaybookAuthor::addPhase(1, (int) $draft['id'], 'Execution');

$s1 = PlaybookAuthor::addStep(1, (int) $draft['id'], $discovery, 'Kickoff call', [
    'coach_guidance'  => 'Run the standard intake. Do not solve anything yet.',
    'client_guidance' => 'We will meet for 90 minutes to map the territory.',
]);
$s2 = PlaybookAuthor::addStep(1, (int) $draft['id'], $discovery, 'Financial review', [
    'coach_guidance'  => 'Trailing 12 P&L and balance sheet.',
    'completion_rule' => 'artifacts_complete',
]);
$s3 = PlaybookAuthor::addStep(1, (int) $draft['id'], $execution, 'Set quarterly rocks', [
    'client_guidance' => 'Pick three to seven priorities for the quarter.',
]);
$s4 = PlaybookAuthor::addStep(1, (int) $draft['id'], $execution, 'Optional benchmarking', [
    'is_required' => 0,
]);
$s5 = PlaybookAuthor::addStep(1, (int) $draft['id'], $execution, 'Mid-quarter check', [
    'gating'      => 'triggered',
    'gate_config' => ['type' => 'date', 'offset_days' => 45],
]);

PlaybookAuthor::addArtifact(1, $s2, 'document', 'Trailing 12 P&L', ['requested_from' => 'client_owner']);
PlaybookAuthor::addArtifact(1, $s2, 'document', 'Balance sheet', ['requested_from' => 'client_owner']);

T::throws(RuntimeException::class,
    static fn () => PlaybookAuthor::publish(1, (int) PlaybookAuthor::draftVersion(1, PlaybookAuthor::create(1, 'Empty'))['id']),
    'an empty playbook cannot be published');

T::ok(PlaybookAuthor::publish(1, (int) $draft['id'], 'first cut'), 'draft publishes');
T::ok(!PlaybookAuthor::publish(1, (int) $draft['id']), 'publishing twice does nothing');


T::group('Instantiation is a copy, not a reference');

T::throws(RuntimeException::class,
    static fn () => PlaybookInstantiator::apply(1, $engId, 99999),
    'an unknown version cannot be applied');

$epId = PlaybookInstantiator::apply(1, $engId, (int) $draft['id'], $coachId);
T::ok($epId > 0, 'playbook applied to the engagement');

T::throws(RuntimeException::class,
    static fn () => PlaybookInstantiator::apply(1, $engId, (int) $draft['id']),
    'an engagement cannot run two playbooks');

$liveSteps = $db->query('SELECT COUNT(*) c FROM pl_engagement_steps WHERE engagement_playbook_id = ' . $epId)->fetch()['c'];
T::same(5, (int) $liveSteps, 'all five steps copied');

$livePhases = $db->query('SELECT COUNT(*) c FROM pl_engagement_phases WHERE engagement_playbook_id = ' . $epId)->fetch()['c'];
T::same(2, (int) $livePhases, 'both phases copied');

$liveArtifacts = $db->query(
    'SELECT COUNT(*) c FROM pl_engagement_step_artifacts a
     JOIN pl_engagement_steps s ON s.id = a.engagement_step_id
     WHERE s.engagement_playbook_id = ' . $epId
)->fetch()['c'];
T::same(2, (int) $liveArtifacts, 'artifacts copied too');

// The load-bearing test: edit the template, live engagement must not move.
$db->exec("UPDATE pl_playbook_steps SET title = 'REWRITTEN', coach_guidance = 'REWRITTEN' WHERE id = " . $s1);

$liveTitle = $db->query(
    'SELECT title FROM pl_engagement_steps WHERE engagement_playbook_id = ' . $epId . ' AND source_step_id = ' . $s1
)->fetch()['title'];
T::same('Kickoff call', $liveTitle, 'editing the template does NOT change the live engagement');

// Put it back for the drift tests.
$db->exec("UPDATE pl_playbook_steps SET title = 'Kickoff call', coach_guidance = 'Run the standard intake. Do not solve anything yet.' WHERE id = " . $s1);


T::group('Gating');

$journey = PlaybookRunner::journey(1, $epId, false);
$byTitle = [];
foreach ($journey as $phase) {
    foreach ($phase['steps'] as $step) {
        $byTitle[$step['title']] = $step;
    }
}

T::same('available', $byTitle['Kickoff call']['status'], 'the first sequential step opens immediately');
T::same('locked', $byTitle['Financial review']['status'], 'the second sequential step is locked');
T::same('locked', $byTitle['Set quarterly rocks']['status'], 'a later phase stays locked');
T::same('locked', $byTitle['Mid-quarter check']['status'], 'a date-triggered step is closed before its offset');

$kickoffId = (int) $byTitle['Kickoff call']['id'];
$financialId = (int) $byTitle['Financial review']['id'];
$rocksId = (int) $byTitle['Set quarterly rocks']['id'];
$optionalId = (int) $byTitle['Optional benchmarking']['id'];
$midId = (int) $byTitle['Mid-quarter check']['id'];

PlaybookRunner::complete(1, $kickoffId, $coachId);

$statuses = static function () use ($db, $epId): array {
    $out = [];
    foreach ($db->query('SELECT title, status FROM pl_engagement_steps WHERE engagement_playbook_id = ' . $epId)->fetchAll() as $r) {
        $out[$r['title']] = $r['status'];
    }
    return $out;
};

$now = $statuses();
T::same('complete', $now['Kickoff call'], 'completed step is complete');
T::same('available', $now['Financial review'], 'completing a step opens the next one');
T::same('locked', $now['Set quarterly rocks'], 'the step after that stays locked');


T::group('Completion rules');

T::throws(RuntimeException::class,
    static fn () => PlaybookRunner::complete(1, $financialId, $coachId),
    'a step set to artifacts_complete cannot close with required artifacts outstanding');

T::throws(RuntimeException::class,
    static fn () => PlaybookRunner::complete(1, $rocksId, $coachId),
    'a locked step cannot be completed');

$db->exec('UPDATE pl_engagement_step_artifacts SET completed_at = NOW() WHERE engagement_step_id = ' . $financialId);
T::ok(PlaybookRunner::complete(1, $financialId, $coachId), 'it closes once the artifacts are done');

$now = $statuses();
T::same('available', $now['Set quarterly rocks'], 'the next phase opens');


T::group('Skipping requires a reason, and is kept');

T::throws(InvalidArgumentException::class,
    static fn () => PlaybookRunner::skip(1, $rocksId, $coachId, '   '),
    'a blank skip reason is refused');

$rawSkip = false;
try {
    $db->exec("UPDATE pl_engagement_steps SET status = 'skipped', skipped_reason = NULL WHERE id = " . $rocksId);
} catch (Throwable) {
    $rawSkip = true;
}
T::ok($rawSkip, 'the CHECK constraint refuses a skip with no reason even in raw SQL');

T::ok(PlaybookRunner::skip(1, $rocksId, $coachId, 'Client already runs quarterly rocks through EOS'), 'skipping with a reason works');

$skipped = $db->query('SELECT status, skipped_reason FROM pl_engagement_steps WHERE id = ' . $rocksId)->fetch();
T::same('skipped', $skipped['status'], 'step is skipped');
T::ok(str_contains((string) $skipped['skipped_reason'], 'EOS'), 'the reason is kept');

$now = $statuses();
T::same('available', $now['Optional benchmarking'], 'a skipped step unblocks what follows — deviation is allowed');


T::group('Optional steps never block');

// Optional benchmarking is not required, so the date-triggered step after it
// is governed by its own trigger, not by whether the optional step is done.
T::same('locked', $now['Mid-quarter check'], 'the triggered step is still waiting on its date');

PlaybookRunner::recomputeAvailability(1, $epId, time() + (46 * 86400));
$now = $statuses();
T::same('available', $now['Mid-quarter check'], 'it opens once the offset has passed');


T::group('Reopen and progress');

$progress = PlaybookRunner::progress(1, $epId);
T::same(5, $progress['total'], 'five steps total');
T::same(2, $progress['complete'], 'two complete');
T::same(1, $progress['skipped'], 'one skipped');
T::same(3, $progress['settled'], 'three settled');
T::same(40, $progress['percent'], 'skipped steps do not inflate the percentage');
T::ok($progress['next'] !== null, 'there is a next step');

T::ok(PlaybookRunner::reopen(1, $rocksId), 'a skipped step can be reopened');
$reopened = $db->query('SELECT status, skipped_reason FROM pl_engagement_steps WHERE id = ' . $rocksId)->fetch();
T::same('available', $reopened['status'], 'reopened step is available');
T::same(null, $reopened['skipped_reason'], 'the stale skip reason is cleared');


T::group('Client-side journey hides coach guidance');

$coachView = PlaybookRunner::journey(1, $epId, false);
$clientView = PlaybookRunner::journey(1, $epId, true);

$coachHasGuidance = false;
foreach ($coachView as $phase) {
    foreach ($phase['steps'] as $step) {
        if (!empty($step['coach_guidance'])) { $coachHasGuidance = true; }
    }
}
T::ok($coachHasGuidance, 'the coach sees coach guidance');

$clientLeak = false;
foreach ($clientView as $phase) {
    foreach ($phase['steps'] as $step) {
        if (array_key_exists('coach_guidance', $step)) { $clientLeak = true; }
    }
}
T::ok(!$clientLeak, 'the client payload has no coach_guidance key at all');

$clientHasOwn = false;
foreach ($clientView as $phase) {
    foreach ($phase['steps'] as $step) {
        if (!empty($step['client_guidance'])) { $clientHasOwn = true; }
    }
}
T::ok($clientHasOwn, 'the client still sees their own guidance');


T::group('Version drift');

$drift = PlaybookInstantiator::drift(1, $epId);
T::same(1, $drift['current_version'], 'running version 1');
T::same(1, $drift['latest_version'], 'no newer version yet');
T::same([], $drift['added'], 'nothing added');

// Revise the template: edit a step, add one, remove one.
$v2 = PlaybookAuthor::draftVersion(1, $pbId);
T::same(2, (int) $v2['version_number'], 'editing a published playbook opens version 2');

$v2Steps = $db->query('SELECT * FROM pl_playbook_steps WHERE version_id = ' . (int) $v2['id'])->fetchAll();
T::same(5, count($v2Steps), 'the new draft is seeded from the published version');

$v2ByTitle = [];
foreach ($v2Steps as $r) { $v2ByTitle[$r['title']] = $r; }

$db->exec("UPDATE pl_playbook_steps SET coach_guidance = 'Now with a pre-read' WHERE id = " . (int) $v2ByTitle['Kickoff call']['id']);
$db->exec('DELETE FROM pl_playbook_steps WHERE id = ' . (int) $v2ByTitle['Optional benchmarking']['id']);
PlaybookAuthor::addStep(1, (int) $v2['id'], (int) $v2ByTitle['Kickoff call']['phase_id'], 'Stakeholder interviews');

PlaybookAuthor::publish(1, (int) $v2['id'], 'added interviews');

$drift = PlaybookInstantiator::drift(1, $epId);
T::same(1, $drift['current_version'], 'the engagement is still on version 1');
T::same(2, $drift['latest_version'], 'version 2 is available');
T::same(1, count($drift['added']), 'one step added');
T::same('Stakeholder interviews', $drift['added'][0]['title'], 'the added step is reported');
T::same(1, count($drift['changed']), 'one step changed');
T::same('Kickoff call', $drift['changed'][0]['title'], 'the changed step is reported');
T::ok(in_array('coach_guidance', $drift['changed'][0]['fields'], true), 'the changed field is named');
T::same(1, count($drift['removed']), 'one step removed');
T::same('Optional benchmarking', $drift['removed'][0]['title'], 'the removed step is reported');

// Drift is read-only until the coach chooses.
$stillOld = $db->query('SELECT coach_guidance FROM pl_engagement_steps WHERE id = ' . $kickoffId)->fetch()['coach_guidance'];
T::ok(!str_contains((string) $stillOld, 'pre-read'), 'the live engagement is untouched by a newer version');

T::ok(PlaybookInstantiator::pullStep(1, $kickoffId, (int) $drift['changed'][0]['source_step_id']), 'a coach can pull one step forward');
$pulled = $db->query('SELECT coach_guidance, status FROM pl_engagement_steps WHERE id = ' . $kickoffId)->fetch();
T::ok(str_contains((string) $pulled['coach_guidance'], 'pre-read'), 'the pulled step has the new content');
T::same('complete', $pulled['status'], 'pulling content forward does not reset completion');


T::group('Markdown import');

$importPb = PlaybookAuthor::create(1, 'Imported from a Google Doc', null, $coachId);
$importDraft = PlaybookAuthor::draftVersion(1, $importPb);

$counts = PlaybookAuthor::importMarkdown(1, (int) $importDraft['id'], <<<MD
# Onboarding
## Welcome call
Set expectations. Ask about the last advisor.
> We will spend our first hour getting oriented.
- [ ] Send the welcome pack
- [ ] Book the second session

## Document gathering
Chase the P&L twice before escalating.

# First quarter
## Set the rocks
> Choose three to seven priorities.
MD);

T::same(2, $counts['phases'], 'two phases imported');
T::same(3, $counts['steps'], 'three steps imported');
T::same(2, $counts['artifacts'], 'two checkbox tasks imported');

$imported = $db->query(
    'SELECT * FROM pl_playbook_steps WHERE version_id = ' . (int) $importDraft['id'] . " AND title = 'Welcome call'"
)->fetch();
T::ok(str_contains((string) $imported['coach_guidance'], 'last advisor'), 'prose became coach guidance');
T::ok(str_contains((string) $imported['client_guidance'], 'getting oriented'), 'blockquotes became client guidance');
T::ok(!str_contains((string) $imported['coach_guidance'], 'getting oriented'), 'client guidance did not leak into coach guidance');

// A step before any heading still lands somewhere.
$loosePb = PlaybookAuthor::create(1, 'Loose');
$looseDraft = PlaybookAuthor::draftVersion(1, $loosePb);
$looseCounts = PlaybookAuthor::importMarkdown(1, (int) $looseDraft['id'], "## Orphan step\nSome guidance.");
T::same(1, $looseCounts['phases'], 'an orphan step gets an untitled phase rather than an error');
T::same(1, $looseCounts['steps'], 'and the step is kept');


T::group('Duplicate');

$dupId = PlaybookAuthor::duplicate(1, $pbId, 'Copy of the rhythm', $coachId);
$dupDraft = PlaybookAuthor::draftVersion(1, $dupId);
$dupSteps = $db->query('SELECT COUNT(*) c FROM pl_playbook_steps WHERE version_id = ' . (int) $dupDraft['id'])->fetch()['c'];
T::same(5, (int) $dupSteps, 'the duplicate carries the published content');
T::ok($dupId !== $pbId, 'it is a new playbook');


T::group('Tenant isolation');

T::throws(RuntimeException::class,
    static fn () => PlaybookInstantiator::apply(2, $engId, (int) $draft['id']),
    "tenant B cannot apply tenant A's playbook");

$driftB = PlaybookInstantiator::drift(2, $epId);
T::same(0, $driftB['current_version'], "tenant B sees nothing for tenant A's instance");

T::same(0, PlaybookRunner::recomputeAvailability(2, $epId), "tenant B cannot recompute tenant A's playbook");
T::ok(!PlaybookRunner::complete(2, $kickoffId, $coachId), "tenant B cannot complete tenant A's step");
T::ok(!PlaybookRunner::skip(2, $kickoffId, $coachId, 'nope'), "tenant B cannot skip tenant A's step");
T::ok(!PlaybookInstantiator::pullStep(2, $kickoffId, $s1), "tenant B cannot pull into tenant A's step");

$progressB = PlaybookRunner::progress(2, $epId);
T::same(0, $progressB['total'], "tenant B sees no progress for tenant A's engagement");
T::same([], PlaybookRunner::journey(2, $epId, false), "tenant B gets an empty journey");


T::group('Engagement policy context');

$eng = $engagements->find($engId);
$ctx = $engagements->policyContext($eng);
T::same($orgId, $ctx['client_org_id'], 'context carries the client org');
T::same($coachId, $ctx['owner_user_id'], 'context carries the lead coach');
T::ok(in_array($coachId, $ctx['assigned_user_ids'], true), 'the lead coach is a member');


T::group('Qualifiers — M4 pays what it owes');

Policy::resetQualifiers();
Qualifiers::register();

// Only what M4 owns. Asserting the whole unresolved list here means a LATER
// module paying off its own qualifier breaks this test — a good change turning
// a passing suite red. PolicyTest owns the global picture.
$remaining = Policy::unresolvedQualifiers();
T::ok(!in_array('client_facing', $remaining, true), 'client_facing is now resolved');
T::ok(!in_array('progress_only', $remaining, true), 'progress_only is now resolved');

$clientOwner = ['id' => 90, 'role' => 'client_owner', 'status' => 'active', 'client_org_id' => $orgId];
$sponsor     = ['id' => 91, 'role' => 'sponsor', 'status' => 'active', 'client_org_id' => $orgId];

T::ok(Policy::can($clientOwner, Policy::READ, 'playbook_instance', ['status' => 'available']), 'a client sees an open step');
T::ok(Policy::can($clientOwner, Policy::READ, 'playbook_instance', ['status' => 'complete']), 'and a completed one');
T::ok(!Policy::can($clientOwner, Policy::READ, 'playbook_instance', ['status' => 'locked']), 'but not a locked one');
T::ok(!Policy::can($clientOwner, Policy::READ, 'playbook_instance', null), 'and not with no context');

T::ok(Policy::can($sponsor, Policy::READ, 'playbook_instance', ['progress_summary' => true]), 'a sponsor sees a progress summary');
T::ok(!Policy::can($sponsor, Policy::READ, 'playbook_instance', ['status' => 'available']), 'a sponsor does not see step content');
T::ok(!Policy::can($sponsor, Policy::READ, 'playbook_instance', []), 'anything not marked a summary is denied');

Policy::resetQualifiers();


T::group('Starter playbook seeds and runs');

$starterId = \Bizorca\Pilotage\Services\StarterPlaybooks::installQuarterlyRhythm(1, $coachId);

$starterVersion = $db->query(
    "SELECT * FROM pl_playbook_versions WHERE playbook_id = " . $starterId . " AND state = 'published' LIMIT 1"
)->fetch();
T::ok($starterVersion !== false, 'the starter playbook publishes');

$starterSteps = (int) $db->query('SELECT COUNT(*) c FROM pl_playbook_steps WHERE version_id = ' . (int) $starterVersion['id'])->fetch()['c'];
T::ok($starterSteps >= 8, 'it has a real number of steps');

$isSystem = (int) $db->query('SELECT is_system FROM pl_playbooks WHERE id = ' . $starterId)->fetch()['is_system'];
T::same(1, $isSystem, 'it is flagged as a system playbook');

// It must survive being applied and run, not just exist.
$eng2 = $engagements->createEngagement([
    'client_org_id' => $orgId, 'title' => 'Starter run', 'status' => 'active', 'coach_user_id' => $coachId,
]);
$ep2 = \Bizorca\Pilotage\Services\PlaybookInstantiator::apply(1, $eng2, (int) $starterVersion['id'], $coachId);

$p2 = PlaybookRunner::progress(1, $ep2);
T::same($starterSteps, $p2['total'], 'every starter step instantiated');
T::same(0, $p2['complete'], 'nothing complete yet');
T::ok($p2['next'] !== null, 'there is an opening step');

$open = $db->query("SELECT COUNT(*) c FROM pl_engagement_steps WHERE engagement_playbook_id = " . $ep2 . " AND status = 'available'")->fetch()['c'];
T::ok((int) $open >= 2, 'the first sequential step and the parallel step are both open');

// No trademarked methodology terms (SPEC.md §12.2 item 5).
$allText = '';
foreach ($db->query('SELECT title, coach_guidance, client_guidance FROM pl_playbook_steps WHERE version_id = ' . (int) $starterVersion['id'])->fetchAll() as $r) {
    $allText .= ' ' . $r['title'] . ' ' . $r['coach_guidance'] . ' ' . $r['client_guidance'];
}
$allText = mb_strtolower($allText);
foreach (['rocks', 'level 10', 'l10', 'scaling up', 'rockefeller', 'traction', 'stratop', 'eos'] as $term) {
    T::ok(!str_contains($allText, $term), 'starter content avoids the term "' . $term . '"');
}
