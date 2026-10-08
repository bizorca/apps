<?php

declare(strict_types=1);

/**
 * The permission matrix (SPEC.md §8, FR-2.5).
 *
 * The tests that matter most here are the denials — particularly the two
 * walls the spec calls out explicitly:
 *   - a firm owner is not party to the coaching relationship, so they never
 *     see session private notes
 *   - a sponsor sees progress, never content
 */

use Bizorca\Pilotage\Auth\Policy;

$user = static fn (string $role, string $status = 'active'): array
    => ['id' => 1, 'role' => $role, 'status' => $status];

Policy::resetQualifiers();

T::group('Policy — unqualified grants');

T::ok(Policy::can($user('firm_owner'), Policy::UPDATE, 'tenant_settings'), 'firm owner updates tenant settings');
T::ok(Policy::can($user('firm_owner'), Policy::DELETE, 'billing'), 'firm owner manages billing');
T::ok(Policy::can($user('coach'), Policy::CREATE, 'playbook_template'), 'coach creates playbook templates');
T::ok(Policy::can($user('associate'), Policy::READ, 'playbook_template'), 'associate reads playbook templates');
T::ok(Policy::can($user('coach'), Policy::DELETE, 'session'), 'coach deletes a session');
T::ok(Policy::can($user('client_owner'), Policy::CREATE, 'document_client_file'), 'client owner uploads their own files');

T::group('Policy — role denials');

T::ok(!Policy::can($user('coach'), Policy::READ, 'tenant_settings'), 'coach cannot read tenant settings');
T::ok(!Policy::can($user('coach'), Policy::READ, 'billing'), 'coach cannot see billing');
T::ok(!Policy::can($user('coach'), Policy::READ, 'audit_log'), 'coach cannot read the audit log');
T::ok(!Policy::can($user('associate'), Policy::UPDATE, 'playbook_template'), 'associate cannot edit templates');
T::ok(!Policy::can($user('client_member'), Policy::CREATE, 'goal'), 'client team member cannot create goals');
T::ok(!Policy::can($user('associate'), Policy::DELETE, 'task'), 'associate cannot delete tasks');
T::ok(!Policy::can($user('client_owner'), Policy::UPDATE, 'metric_definition'), 'client owner cannot redefine metrics');

T::group('Policy — the private notes wall');

T::ok(!Policy::can($user('firm_owner'), Policy::READ, 'session_private_note'), 'firm owner cannot read private notes');
T::ok(!Policy::can($user('associate'), Policy::READ, 'session_private_note'), 'associate cannot read private notes');
T::ok(!Policy::can($user('client_owner'), Policy::READ, 'session_private_note'), 'client owner cannot read private notes');
T::ok(!Policy::can($user('client_member'), Policy::READ, 'session_private_note'), 'client member cannot read private notes');
T::ok(!Policy::can($user('sponsor'), Policy::READ, 'session_private_note'), 'sponsor cannot read private notes');

T::group('Policy — the sponsor wall');

T::ok(!Policy::can($user('sponsor'), Policy::READ, 'session'), 'sponsor cannot read session notes');
T::ok(!Policy::can($user('sponsor'), Policy::READ, 'message_thread'), 'sponsor cannot read messages');
T::ok(!Policy::can($user('sponsor'), Policy::READ, 'issue'), 'sponsor cannot read the issues list');
T::ok(!Policy::can($user('sponsor'), Policy::READ, 'task'), 'sponsor cannot read tasks');
T::ok(!Policy::can($user('sponsor'), Policy::CREATE, 'goal'), 'sponsor cannot create goals');
T::ok(Policy::can($user('sponsor'), Policy::READ, 'goal'), 'sponsor CAN read goals — progress is what they get');
T::ok(Policy::can($user('sponsor'), Policy::READ, 'metric_definition'), 'sponsor can read metric definitions');

T::group('Policy — unknown things are denied');

T::ok(!Policy::can($user('firm_owner'), Policy::READ, 'no_such_object'), 'unknown object type denied even for the owner');
T::ok(!Policy::can(['id' => 1, 'status' => 'active'], Policy::READ, 'goal'), 'user with no role denied');
T::ok(!Policy::can($user('coach', 'disabled'), Policy::READ, 'goal'), 'disabled user denied everything');
T::ok(!Policy::can($user('coach', 'invited'), Policy::READ, 'goal'), 'not-yet-accepted user denied everything');

T::group('Policy — qualifiers fail closed until registered');

// 'own' is unregistered at this point, so the cell must deny.
T::ok(!Policy::can($user('coach'), Policy::UPDATE, 'engagement'), 'coach denied on engagement while "own" has no resolver');
T::ok(!Policy::can($user('client_owner'), Policy::READ, 'engagement'), 'client owner denied while "own" has no resolver');

Policy::registerQualifier('own', static fn (array $u, ?array $ctx): bool
    => ($ctx['owner_id'] ?? null) === $u['id']);

T::ok(Policy::can($user('coach'), Policy::UPDATE, 'engagement', ['owner_id' => 1]), 'coach may update their own engagement');
T::ok(!Policy::can($user('coach'), Policy::UPDATE, 'engagement', ['owner_id' => 2]), "coach may not update another coach's engagement");
T::ok(!Policy::can($user('coach'), Policy::UPDATE, 'engagement', null), 'no context means no grant');

T::group('Policy — named actions beyond CRUD');

T::ok(Policy::can($user('client_owner'), 'accept', 'scope_item'), 'client owner may accept scope');
T::ok(!Policy::can($user('client_member'), 'accept', 'scope_item'), 'client member may not accept scope');
T::ok(Policy::can($user('coach'), 'review', 'change_request'), 'coach may review a change request');
T::ok(!Policy::can($user('client_owner'), 'review', 'change_request'), 'client owner may not review their own change request');
T::ok(Policy::can($user('client_owner'), 'fulfill', 'document_request'), 'client owner may fulfill a document request');
T::ok(!Policy::can($user('coach'), 'accept', 'scope_item'), 'coach may not accept scope on the client behalf');
T::ok(Policy::can($user('client_owner'), Policy::READ, 'scope_item'), 'the R in "R accept" still grants read');

T::group('Policy — authorize() throws with the right status');

T::throws(
    \Bizorca\Pilotage\Core\HttpException::class,
    static fn () => Policy::authorize($user('coach'), Policy::READ, 'billing'),
    'authorize throws on a denied read'
);

try {
    Policy::authorize($user('coach'), Policy::READ, 'billing');
} catch (\Bizorca\Pilotage\Core\HttpException $e) {
    T::same(404, $e->statusCode(), 'denied READ returns 404, not 403 — existence is itself a leak');
}

try {
    Policy::authorize($user('coach'), Policy::UPDATE, 'billing');
} catch (\Bizorca\Pilotage\Core\HttpException $e) {
    T::same(403, $e->statusCode(), 'denied WRITE returns 403');
}

T::group('Policy — matrix coverage');

$objects = Policy::objectTypes();
T::same(23, count($objects), 'all object types from SPEC.md §8 are present');
T::ok(in_array('client_portal_access', $objects, true), 'client portal access is distinct from firm seats');
T::ok(in_array('session_private_note', $objects, true), 'private notes are a distinct object type');
T::ok(in_array('document_client_file', $objects, true), 'client files are distinct from deliverables');

// Every qualifier the matrix references should be listed as unresolved here,
// which is what tells us the later modules still owe us a resolver.
$unresolved = Policy::unresolvedQualifiers();
sort($unresolved);
T::same(
    ['assigned', 'attendee', 'client_facing', 'delivered', 'own_org', 'owner', 'progress_only', 'shared', 'until_lock'],
    $unresolved,
    'the matrix reports exactly which qualifiers later modules still owe a resolver'
);

Policy::resetQualifiers();


T::group('Policy — live matrix coverage');

/**
 * The one place that tracks how much of the §8 matrix is actually enforceable.
 *
 * Update this list when a module registers a new qualifier — that is the point.
 * Module tests deliberately assert only their own, so shipping M7 does not turn
 * M4's suite red.
 */
Policy::resetQualifiers();
\Bizorca\Pilotage\Auth\Qualifiers::register();

$live = Policy::unresolvedQualifiers();
sort($live);

T::same([], $live, 'every qualifier in the SPEC section 8 matrix now has a resolver');

Policy::resetQualifiers();


T::group('HttpException survives controller catch blocks');

/**
 * Regression: HttpException used to extend \RuntimeException, so every
 * controller `catch (\InvalidArgumentException | \RuntimeException $e)` — the
 * standard "turn a domain error into a 422" block — also caught the
 * controller's own deliberate throws and rewrote the status. A considered 403
 * came out as 422.
 */
$e = new \Bizorca\Pilotage\Core\HttpException(403, 'Nope');

T::ok(!($e instanceof \RuntimeException), 'HttpException is NOT a RuntimeException');
T::ok(!($e instanceof \InvalidArgumentException), 'nor an InvalidArgumentException');
T::ok($e instanceof \Exception, 'but it is still an Exception');
T::same(403, $e->statusCode(), 'and it carries its status');

// Simulate the controller pattern.
$status = null;
try {
    try {
        throw new \Bizorca\Pilotage\Core\HttpException(403, 'Only the client acknowledges receipt.');
    } catch (\InvalidArgumentException | \RuntimeException $inner) {
        $status = 422;   // the bug: this used to fire
    }
} catch (\Bizorca\Pilotage\Core\HttpException $outer) {
    $status = $outer->statusCode();
}

T::same(403, $status, 'a deliberate 403 passes through the domain-error catch untouched');


T::group('A firm owner can actually run a solo practice');

/**
 * An earlier reading of §8 gave the firm owner read-only access to every
 * engagement-level object, which left a one-person firm — where the owner IS
 * the coach — unable to run an engagement at all. Caught by logging in as an
 * owner and finding every button return 403.
 */
$fo = ['id' => 1, 'role' => 'firm_owner', 'status' => 'active', 'client_org_id' => null];

Policy::resetQualifiers();
\Bizorca\Pilotage\Auth\Qualifiers::register();

foreach ([
    ['scope_item',           Policy::CREATE, ['scope_locked' => false], 'add scope'],
    ['change_request',       'review',       null,                      'review a change request'],
    ['session',              Policy::CREATE, null,                      'schedule a session'],
    ['task',                 Policy::CREATE, null,                      'assign a commitment'],
    ['document_deliverable', Policy::CREATE, null,                      'produce a deliverable'],
    ['document_request',     Policy::CREATE, null,                      'request documents'],
    ['message_thread',       Policy::CREATE, null,                      'message the client'],
    ['goal',                 Policy::CREATE, null,                      'set a priority'],
    ['metric_definition',    Policy::CREATE, null,                      'define a metric'],
    ['metric_value',         Policy::UPDATE, null,                      'record a number'],
    ['issue',                Policy::UPDATE, null,                      'resolve an issue'],
    ['playbook_instance',    Policy::UPDATE, null,                      'run the playbook'],
] as [$object, $action, $ctx, $label]) {
    T::ok(Policy::can($fo, $action, $object, $ctx), 'a firm owner can ' . $label);
}

// The one thing that does NOT come with the badge.
T::ok(!Policy::can($fo, Policy::READ, 'session_private_note', ['owner_user_id' => 1]),
    "but never reads a coach's private session notes — owning the firm is not being in the room");

// And scope still respects the lock.
T::ok(!Policy::can($fo, Policy::UPDATE, 'scope_item', ['scope_locked' => true]),
    'nor edits scope once it is locked; the owner uses a change request like everyone else');

Policy::resetQualifiers();
