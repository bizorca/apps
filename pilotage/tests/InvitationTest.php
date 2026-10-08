<?php

declare(strict_types=1);

/**
 * Invitations, and how a firm membership is bound to a tools account.
 *
 * Was InvitationMagicLinkTest. Emailed sign-in links went with the port (the
 * shared Bizorca Tools account signs people in for every tool), so their
 * groups went with the class. The invitation groups are the original ones,
 * adapted: accepting now binds the new pl_users row to the accepting tools
 * account instead of setting a per-firm password. The interesting cases are
 * still the ways a token should NOT work: reused, expired, revoked, or
 * presented at the wrong tenant.
 */

use Bizorca\Pilotage\Auth\Invitation;
use Bizorca\Pilotage\Controllers\AuthController;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Repositories\UserRepository;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_invitations', 'pl_user_sessions', 'pl_users', 'pl_client_orgs', 'pl_tenants', 'users'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id, slug, name, status) VALUES (1,'acme','Acme Advisory','active'),(2,'northstar','Northstar','active')");
$db->exec("INSERT INTO pl_client_orgs (id, tenant_id, name, status) VALUES (1,1,'Alpha Manufacturing','active'),(2,2,'Gamma Logistics','active')");

// Shared tools accounts (the `users` table every tool signs in with).
$account = static function (string $email, string $name) use ($db): int {
    $db->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)')
       ->execute([$email, password_hash('a-good-long-password', PASSWORD_DEFAULT), $name]);
    return (int) $db->lastInsertId();
};
$acctNewCoach = $account('newcoach@acme.test', 'New Coach');
$acctMember   = $account('member@alpha.test', 'A Member');
$acctOther    = $account('other@acme.test', 'Someone Else');

Tenant::setCurrent(['id' => 1, 'slug' => 'acme', 'name' => 'Acme Advisory']);

$usersA = new UserRepository(1);
$coachId = $usersA->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);
$ownerId = $usersA->create(['email' => 'owner@alpha.test', 'name' => 'An Owner', 'role' => 'client_owner', 'client_org_id' => 1, 'status' => 'active']);

T::group('Invitations — the happy path');

$invite = Invitation::issue(1, 'acme', 'newcoach@acme.test', 'coach', null, 'New Coach', $coachId);
T::ok(str_contains($invite['url'], '/invite/'), 'invitation url points at the accept route');

$looked = Invitation::lookup($invite['plaintext'], 1);
T::ok($looked !== null, 'pending invitation looks up');
T::same('coach', $looked['role'], 'role is carried on the invitation');

$accepted = Invitation::accept($invite['plaintext'], 1, 'New Coach', $acctNewCoach);
T::ok($accepted !== null, 'invitation accepted');
T::same('coach', $accepted['role'], 'user created with the invited role');

$created = $usersA->findByEmail('newcoach@acme.test');
T::ok($created !== null, 'user exists after acceptance');
T::same('active', $created['status'], 'accepted user is active');
T::same($acctNewCoach, (int) $created['account_id'], 'the membership is bound to the tools account that accepted');
T::same((int) $created['id'], (int) $usersA->findByAccountId($acctNewCoach)['id'], 'and sign-in finds it by that account');
T::same(null, $created['password_hash'], 'no per-firm password is written: it lives on the shared account');

T::group('Invitations — refusals');

T::same(null, Invitation::accept($invite['plaintext'], 1, 'Someone Else', $acctOther), 'an accepted invitation cannot be reused');
T::same(null, Invitation::lookup($invite['plaintext'], 1), 'an accepted invitation no longer looks up');

$inviteB = Invitation::issue(1, 'acme', 'other@acme.test', 'associate', null, null, $coachId);
T::same(null, Invitation::lookup($inviteB['plaintext'], 2), 'invitation for tenant A does not resolve on tenant B');
T::same(null, Invitation::accept($inviteB['plaintext'], 2, 'Nope', $acctOther), 'and cannot be accepted there');

Invitation::revoke(1, (int) $inviteB['id']);
T::same(null, Invitation::lookup($inviteB['plaintext'], 1), 'revoked invitation stops working');

$inviteC = Invitation::issue(1, 'acme', 'expired@acme.test', 'associate', null, null, $coachId);
$db->exec('UPDATE pl_invitations SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ' . (int) $inviteC['id']);
T::same(null, Invitation::lookup($inviteC['plaintext'], 1), 'expired invitation refused');

T::group('Invitations — role cannot be forged or misapplied');

T::throws(InvalidArgumentException::class,
    static fn () => Invitation::issue(1, 'acme', 'x@acme.test', 'coach', 1),
    'a firm-side invitation cannot name a client organization');
T::throws(InvalidArgumentException::class,
    static fn () => Invitation::issue(1, 'acme', 'x@acme.test', 'client_owner', null),
    'a client-side invitation must name a client organization');
T::throws(InvalidArgumentException::class,
    static fn () => Invitation::issue(1, 'acme', 'x@acme.test', 'superuser', null),
    'an unknown role is refused');
T::throws(InvalidArgumentException::class,
    static fn () => Invitation::issue(1, 'acme', 'not-an-email', 'coach', null),
    'an invalid email is refused');
T::throws(RuntimeException::class,
    static fn () => Invitation::issue(1, 'acme', 'coach@acme.test', 'coach', null),
    'someone already in the firm cannot be re-invited');

// Passwords live on the shared tools account, so what every invitee needs
// now is that account, firm-side and client-side alike.
$inviteFirm = Invitation::issue(1, 'acme', 'needsacct@acme.test', 'associate', null, null, $coachId);
T::throws(InvalidArgumentException::class,
    static fn () => Invitation::accept($inviteFirm['plaintext'], 1, 'No Account', null),
    'an invitation cannot be accepted without a tools account');
T::ok(Invitation::lookup($inviteFirm['plaintext'], 1) !== null, 'and the refused attempt does not spend it');

$inviteClient = Invitation::issue(1, 'acme', 'member@alpha.test', 'client_member', 1, null, $coachId);
$acceptedClient = Invitation::accept($inviteClient['plaintext'], 1, 'A Member', $acctMember);
T::ok($acceptedClient !== null, 'a client-side invitee accepts with their tools account');

$member = $usersA->findByEmail('member@alpha.test');
T::same(1, (int) $member['client_org_id'], 'client-side invitee is attached to the invited organization');
T::same($acctMember, (int) $member['account_id'], 'and bound to their tools account');

T::group('Invitations — superseding');

$first = Invitation::issue(1, 'acme', 'resend@acme.test', 'coach', null, null, $coachId);
$second = Invitation::issue(1, 'acme', 'resend@acme.test', 'coach', null, null, $coachId);
T::same(null, Invitation::lookup($first['plaintext'], 1), 're-inviting the same address kills the earlier invitation');
T::ok(Invitation::lookup($second['plaintext'], 1) !== null, 'the newest invitation is the live one');

T::group('Membership binding — one account, one membership per firm');

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
$db->exec('TRUNCATE TABLE pl_users');
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$usersA = new UserRepository(1);
$usersB = new UserRepository(2);
$inA = $usersA->create(['email' => 'two@firms.test', 'name' => 'Two Firms', 'role' => 'coach', 'status' => 'active']);
$inB = $usersB->create(['email' => 'two@firms.test', 'name' => 'Two Firms', 'role' => 'coach', 'status' => 'active']);
$twoFirms = $account('two@firms.test', 'Two Firms');
T::ok($usersA->attachAccount($inA, $twoFirms), 'an account binds to its membership in firm A');
T::ok($usersB->attachAccount($inB, $twoFirms), 'and the same account to its membership in firm B');
T::same($inA, (int) $usersA->findByAccountId($twoFirms)['id'], 'firm A finds its own row for that account');
T::same($inB, (int) $usersB->findByAccountId($twoFirms)['id'], 'firm B finds its own row — the repository scope holds');
T::ok(!$usersB->attachAccount($inA, $twoFirms), "firm B's repository cannot bind firm A's row");

$second = $usersA->create(['email' => 'alias@firms.test', 'name' => 'Alias', 'role' => 'associate', 'status' => 'active']);
T::throws(PDOException::class,
    static fn () => $usersA->attachAccount($second, $twoFirms),
    'one account cannot hold two memberships in the same firm (uq_tenant_account)');

$cols = array_column($db->query('SHOW COLUMNS FROM pl_users')->fetchAll(), 'Field');
T::ok(in_array('account_id', $cols, true), 'pl_users carries account_id');
T::ok(!in_array('account_id', (new ReflectionMethod(UserRepository::class, 'writable'))->invoke($usersA), true),
    'and it is NOT on the writable allowlist: attachAccount() is its only write path');

T::group('Sign-in redirects stay on this site (was MagicLink::safeRedirect)');

T::same('/tasks', AuthController::safeRedirect('/tasks'), 'relative path allowed');
T::same('/tasks?x=1', AuthController::safeRedirect('/tasks?x=1'), 'with its query');
T::same(null, AuthController::safeRedirect('https://evil.com'), 'absolute url rejected');
T::same(null, AuthController::safeRedirect('//evil.com'), 'protocol-relative url rejected');
T::same(null, AuthController::safeRedirect('/\\evil.com'), 'backslash trick rejected (browsers read it as //)');
T::same(null, AuthController::safeRedirect("/ok\r\nSet-Cookie: x=1"), 'crlf injection rejected');
T::same(null, AuthController::safeRedirect('tasks'), 'path without leading slash rejected');
T::same(null, AuthController::safeRedirect(['/tasks']), 'a non-string is rejected, not coerced');

Tenant::reset();
