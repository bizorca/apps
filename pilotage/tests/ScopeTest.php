<?php

declare(strict_types=1);

/** M4B: scope and change control. The lock is the thing under test. */

use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Qualifiers;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\ScopeControl;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_scope_items', 'pl_change_requests', 'pl_engagement_members', 'pl_engagements',
          'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme','active'),(2,'northstar','NS','active')");
$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);
$orgId = (new ClientOrgRepository(1))->createOrg(['name' => 'Alpha', 'status' => 'active']);
$ownerId = $users->create(['email' => 'dana@alpha.test', 'name' => 'Dana', 'role' => 'client_owner', 'client_org_id' => $orgId, 'status' => 'active']);
$engId = (new EngagementRepository(1))->createEngagement(['client_org_id' => $orgId, 'title' => 'Systems review', 'status' => 'active', 'coach_user_id' => $coachId]);


T::group('Scope before the lock');

T::ok(!ScopeControl::isEnabled(1, $engId), 'scope is off by default — pure coaches never see it');
T::ok(ScopeControl::enable(1, $engId), 'it can be switched on');
T::ok(ScopeControl::isEnabled(1, $engId), 'and stays on');
T::ok(!ScopeControl::isLocked(1, $engId), 'and starts unlocked');

$a = ScopeControl::addItem(1, $engId, 'Process map of order-to-cash', 'Current state, with timings.');
$b = ScopeControl::addItem(1, $engId, 'Recommendations memo');
$c = ScopeControl::addItem(1, $engId, 'Two working sessions');

T::same(3, count(ScopeControl::items(1, $engId)), 'three items');
T::same('Process map of order-to-cash', ScopeControl::items(1, $engId)[0]['title'], 'in order');
T::ok(ScopeControl::removeItem(1, $c), 'items can be removed while unlocked');
T::same(2, count(ScopeControl::items(1, $engId)), 'and the removal sticks');

T::throws(InvalidArgumentException::class, static fn () => ScopeControl::addItem(1, $engId, '  '), 'an item needs a title');


T::group('The lock');

T::ok(ScopeControl::lock(1, $engId), 'scope locks');
T::ok(ScopeControl::isLocked(1, $engId), 'and reads as locked');
T::ok(!ScopeControl::lock(1, $engId), 'locking twice does nothing');

T::throws(RuntimeException::class,
    static fn () => ScopeControl::addItem(1, $engId, 'One more little thing'),
    'no additions after the lock — this is what locking MEANS');
T::throws(RuntimeException::class,
    static fn () => ScopeControl::removeItem(1, $a),
    'and no removals either');

$fresh = (new EngagementRepository(1))->createEngagement(['client_org_id' => $orgId, 'title' => 'Empty', 'status' => 'active']);
T::throws(RuntimeException::class, static fn () => ScopeControl::lock(1, $fresh), 'there must be something to lock');

T::ok(ScopeControl::accept(1, $engId), 'the client accepts the locked scope');
T::ok(!ScopeControl::accept(1, $engId), 'accepting twice does nothing');

$eng = (new EngagementRepository(1))->find($engId);
T::ok($eng['scope_locked_at'] !== null, 'the lock is stamped');
T::ok($eng['client_accepted_scope_at'] !== null, 'and so is the acceptance — two events, not one');


T::group('Change requests');

$cr = ScopeControl::requestChange(1, $engId, 'Add a supplier analysis', 'Review the top 20 suppliers by spend.', 'Came out of session two.', $ownerId);
T::ok($cr > 0, 'a change request is raised');

T::throws(InvalidArgumentException::class,
    static fn () => ScopeControl::requestChange(1, $engId, '', 'desc', null, $ownerId), 'it needs a title');
T::throws(InvalidArgumentException::class,
    static fn () => ScopeControl::requestChange(1, $engId, 'Title', '   ', null, $ownerId), 'and a description of what changes');

T::same('pending', ScopeControl::changeRequests(1, $engId)[0]['status'], 'it starts pending');

T::ok(ScopeControl::approve(1, $cr, $coachId, 'Agreed, adds two weeks.'), 'approving works');
T::ok(!ScopeControl::approve(1, $cr, $coachId), 'approving twice does nothing');

$items = ScopeControl::items(1, $engId);
T::same(3, count($items), 'approval appended the work to scope');

$added = array_values(array_filter($items, static fn (array $i): bool => (int) $i['is_original'] === 0));
T::same(1, count($added), 'exactly one item is flagged as an addition');
T::same('Add a supplier analysis', $added[0]['title'], 'and it is the approved one');
T::same($cr, (int) $added[0]['change_request_id'], 'linked back to the request that created it');

$declined = ScopeControl::requestChange(1, $engId, 'Also redo the website', 'Full rebuild.', null, $ownerId);
T::ok(ScopeControl::decline(1, $declined, $coachId, 'Out of scope; happy to quote separately.'), 'declining works');
T::same(3, count(ScopeControl::items(1, $engId)), 'a declined request adds nothing to scope');

$all = ScopeControl::changeRequests(1, $engId);
T::same(2, count($all), 'both requests remain on file — the paper trail is the point');
$byTitle = [];
foreach ($all as $r) { $byTitle[$r['title']] = $r; }
T::same('declined', $byTitle['Also redo the website']['status'], 'the declined one is still visible');
T::ok(str_contains((string) $byTitle['Also redo the website']['review_note'], 'quote separately'), 'with the reason attached');
T::same('A Coach', $byTitle['Also redo the website']['reviewer_name'], 'and who decided');


T::group('Scope against deliverables');

$summary = ScopeControl::summary(1, $engId);
T::same(3, $summary['total'], 'three scope items');
T::same(0, $summary['delivered'], 'nothing delivered yet');
T::same(1, $summary['added'], 'one added post-lock');

$db->exec("INSERT INTO pl_documents (tenant_id, context, engagement_id, title, status)
           VALUES (1, 'deliverable', {$engId}, 'Process map v1', 'delivered')");
$docId = (int) $db->lastInsertId();

T::ok(ScopeControl::attachDeliverable(1, $a, $docId), 'a deliverable attaches to a scope item');
$summary = ScopeControl::summary(1, $engId);
T::same(1, $summary['delivered'], 'and the reconciliation moves');
T::same('Process map v1', ScopeControl::items(1, $engId)[0]['document_title'], 'the document is named on the item');


T::group('Tenant isolation');

T::same([], ScopeControl::items(2, $engId), "tenant B sees none of tenant A's scope");
T::same([], ScopeControl::changeRequests(2, $engId), 'nor its change requests');
T::same(null, ScopeControl::changeRequest(2, $cr), 'nor one by id');
T::ok(!ScopeControl::isEnabled(2, $engId), "nor reads tenant A's scope flag");
T::ok(!ScopeControl::isLocked(2, $engId), 'nor its lock state');
T::ok(!ScopeControl::enable(2, $engId), 'nor enables it');
T::ok(!ScopeControl::accept(2, $engId), 'nor accepts on their behalf');
T::ok(!ScopeControl::attachDeliverable(2, $a, $docId), 'nor attaches a deliverable');
T::same(0, ScopeControl::summary(2, $engId)['total'], 'nor sees a summary');


T::group('Qualifiers — M4B pays the last one');

Policy::resetQualifiers();
Qualifiers::register();

T::same([], Policy::unresolvedQualifiers(), 'every qualifier in the matrix now has a resolver');

$coach = ['id' => $coachId, 'role' => 'coach', 'status' => 'active', 'client_org_id' => null];
$assoc = ['id' => 9, 'role' => 'associate', 'status' => 'active', 'client_org_id' => null];
$owner = ['id' => $ownerId, 'role' => 'client_owner', 'status' => 'active', 'client_org_id' => $orgId];

T::ok(Policy::can($coach, Policy::UPDATE, 'scope_item', ['scope_locked' => false]), 'a coach edits scope while unlocked');
T::ok(!Policy::can($coach, Policy::UPDATE, 'scope_item', ['scope_locked' => true]), 'and cannot once locked');
T::ok(!Policy::can($coach, Policy::UPDATE, 'scope_item', []), 'a missing flag denies — the safe answer');
T::ok(!Policy::can($coach, Policy::UPDATE, 'scope_item', null), 'as does no context');
T::ok(Policy::can($assoc, Policy::CREATE, 'scope_item', ['scope_locked' => false]), 'an associate may add while unlocked');
T::ok(!Policy::can($assoc, Policy::CREATE, 'scope_item', ['scope_locked' => true]), 'and not after');
T::ok(Policy::can($owner, 'accept', 'scope_item'), 'the client owner accepts scope regardless of lock');
T::ok(Policy::can($owner, Policy::READ, 'scope_item'), 'and can always read it');
T::ok(!Policy::can($owner, Policy::UPDATE, 'scope_item', ['scope_locked' => false]), 'but never edits it');

Policy::resetQualifiers();
