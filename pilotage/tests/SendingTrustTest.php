<?php

declare(strict_types=1);

/**
 * The outbound sending throttle.
 *
 * What this protects is not any one firm — it is the shared sending domain.
 * Every firm mails from pilotagehq.com, so one new account blasting
 * invitations degrades every other firm's ability to have a sign-in link
 * arrive. Self-serve signup means nobody is watching the front door, so the
 * limit has to be structural.
 *
 * The property that matters most is the last group: the check lives inside
 * Invitation::issue(), so a caller that forgets it is still covered, and the
 * exception it throws is not the kind either call site swallows.
 */

use Bizorca\Pilotage\Auth\Invitation;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Services\SendingTrust;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_invitations', 'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

// Three firms: brand new, new-with-a-client, and old-with-a-client.
$db->exec("INSERT INTO pl_tenants (id, slug, name, status, created_at) VALUES
    (1,'fresh','Fresh Advisory','active', NOW()),
    (2,'settling','Settling Advisory','active', NOW()),
    (3,'seasoned','Seasoned Advisory','active', DATE_SUB(NOW(), INTERVAL 60 DAY)),
    (4,'vouchedfirm','Vouched Advisory','active', NOW())");

$db->exec("INSERT INTO pl_client_orgs (id, tenant_id, name, status) VALUES
    (1, 2, 'Alpha Manufacturing', 'active'),
    (2, 3, 'Gamma Logistics', 'active')");

Tenant::setCurrent(['id' => 1, 'slug' => 'fresh', 'name' => 'Fresh Advisory']);

/** Invitations are counted by row, so seed them directly. */
$seed = static function (int $tenantId, int $n, string $ago = '0 SECOND') use ($db): void {
    for ($i = 0; $i < $n; $i++) {
        $db->exec(sprintf(
            "INSERT INTO pl_invitations
                (tenant_id, email, role, selector, verifier_hash, expires_at, created_at)
             VALUES (%d, 'seed%d_%d@x.test', 'coach', '%s', REPEAT('a',64),
                     DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL %s))",
            $tenantId,
            $tenantId,
            $i,
            substr(md5($tenantId . '_' . $i . '_' . $ago . mt_rand()), 0, 32),
            $ago
        ));
    }
};


T::group('Which band a firm is in');

T::same(SendingTrust::UNPROVEN, SendingTrust::state(1), 'a firm with no clients is unproven, however old it is');
T::same(SendingTrust::NEW, SendingTrust::state(2), 'a firm with a client but only days old is new');
T::same(SendingTrust::ESTABLISHED, SendingTrust::state(3), 'a firm with a client and some history is established');
T::same(SendingTrust::UNPROVEN, SendingTrust::state(99), 'an unknown firm is treated as unproven, not as trusted');

// The bar is a client organization rather than the onboarding button, because
// onboarding is one click and a client is somebody typing a real business name.
T::same(SendingTrust::UNPROVEN, SendingTrust::state(4), 'firm 4 starts unproven');
SendingTrust::vouch(4);
T::same(SendingTrust::ESTABLISHED, SendingTrust::state(4), 'vouching promotes it straight past the queue — that is the point of vouching');
SendingTrust::vouch(4);
T::same(SendingTrust::ESTABLISHED, SendingTrust::state(4), 'and vouching twice is harmless');


T::group('An unproven firm gets a small lifetime allowance');

$check = SendingTrust::check(1);
T::same(true, $check['allowed'], 'it may send its first invitation');
T::same(SendingTrust::UNPROVEN_TOTAL, $check['remaining'], 'with the full allowance available');

$seed(1, SendingTrust::UNPROVEN_TOTAL - 1);
T::same(1, SendingTrust::check(1)['remaining'], 'the allowance counts down');
T::same(true, SendingTrust::check(1)['allowed'], 'and the last one is still allowed');

$seed(1, 1);
T::same(false, SendingTrust::check(1)['allowed'], 'then it stops');
T::same(0, SendingTrust::check(1)['remaining'], 'with nothing left');
T::ok(str_contains((string) SendingTrust::check(1)['reason'], 'first client'), 'and the reason says exactly what to do about it');

// Lifetime, not daily. Waiting does not refill it — adding a client does.
$db->exec('UPDATE pl_invitations SET created_at = DATE_SUB(NOW(), INTERVAL 30 DAY) WHERE tenant_id = 1');
T::same(false, SendingTrust::check(1)['allowed'], 'the unproven allowance does not refill with time');

$db->exec("INSERT INTO pl_client_orgs (id, tenant_id, name, status) VALUES (3, 1, 'First Client', 'active')");
T::same(true, SendingTrust::check(1)['allowed'], 'adding a client lifts it immediately — the promised escape hatch works');


T::group('A proven firm gets a daily ceiling');

T::same(SendingTrust::NEW_DAILY, SendingTrust::check(2)['remaining'], 'a new firm starts its day with the full quota');

$seed(2, SendingTrust::NEW_DAILY);
T::same(false, SendingTrust::check(2)['allowed'], 'and is stopped at the ceiling');
T::ok(str_contains((string) SendingTrust::check(2)['reason'], 'ceiling'), 'and told so plainly');

// Rolling, so yesterday's sends stop counting.
$db->exec('UPDATE pl_invitations SET created_at = DATE_SUB(NOW(), INTERVAL 25 HOUR) WHERE tenant_id = 2');
T::same(true, SendingTrust::check(2)['allowed'], 'the window is rolling — yesterday does not count against today');

T::same(SendingTrust::ESTABLISHED_DAILY, SendingTrust::check(3)['remaining'], 'an established firm gets the larger ceiling');
T::ok(SendingTrust::ESTABLISHED_DAILY > SendingTrust::NEW_DAILY, 'which is larger than a new firm gets');


T::group('Revoking an invitation does not refund the quota');

/**
 * The resource being spent is a message that has already left the building.
 * If a revoke gave the quota back, the loop is: invite, revoke, invite,
 * revoke — which is the exact thing being throttled.
 */
$db->exec('TRUNCATE TABLE pl_invitations');
$seed(2, SendingTrust::NEW_DAILY);
$db->exec('UPDATE pl_invitations SET revoked_at = NOW() WHERE tenant_id = 2');

T::same(false, SendingTrust::check(2)['allowed'], 'revoked invitations still count against the limit');


T::group('The gate is inside Invitation::issue, not only in the controllers');

$db->exec('TRUNCATE TABLE pl_invitations');
$db->exec('DELETE FROM pl_client_orgs WHERE tenant_id = 1');   // back to unproven
$seed(1, SendingTrust::UNPROVEN_TOTAL);

T::same(false, SendingTrust::check(1)['allowed'], 'firm 1 is over its allowance');

$threw = null;
try {
    Invitation::issue(1, 'fresh', 'someone@example.test', 'coach');
} catch (Throwable $e) {
    $threw = $e;
}

T::ok($threw instanceof HttpException, 'issuing anyway is refused at the chokepoint');
T::same(429, $threw instanceof HttpException ? $threw->statusCode() : 0,
    '429, not 403 — this is "not yet", and the message says how to get past it');

/**
 * The failure this guards against is subtle and was live-adjacent: both call
 * sites wrap issue() in `catch (\RuntimeException)`, and ClientOrgController
 * swallows that one SILENTLY to mean "already has an account". If the throttle
 * threw a RuntimeException, a blocked firm would add contacts all day and
 * quietly never invite anyone.
 */
T::ok(!($threw instanceof RuntimeException), 'and it is NOT a RuntimeException, which both call sites swallow');

T::same(
    (int) SendingTrust::UNPROVEN_TOTAL,
    (int) $db->query('SELECT COUNT(*) c FROM pl_invitations WHERE tenant_id = 1')->fetch()['c'],
    'and no invitation row was written'
);

/**
 * Ordering that matters: the throttle runs before the account-existence probe.
 *
 * `issue()` normally distinguishes "already has an account in this firm" from
 * every other failure, which is useful to a coach and an enumeration oracle to
 * anyone else. A blocked firm must not get to ask that question. Argument
 * validation running first is fine — a malformed address tells nobody anything.
 */
$db->exec("INSERT INTO pl_users (tenant_id, email, name, role, status)
           VALUES (1, 'already@example.test', 'Already Here', 'coach', 'active')");

$probe = null;
try {
    Invitation::issue(1, 'fresh', 'already@example.test', 'coach');
} catch (Throwable $e) {
    $probe = $e;
}

T::ok($probe instanceof HttpException, 'a blocked firm gets the throttle, not an answer about who exists');
T::ok(
    !str_contains($probe?->getMessage() ?? '', 'already has an account'),
    'so it cannot use the invitation form to enumerate the firm'
);

$db->exec("DELETE FROM pl_users WHERE tenant_id = 1 AND email = 'already@example.test'");


T::group('A proven firm is unaffected');

$db->exec("INSERT INTO pl_client_orgs (id, tenant_id, name, status) VALUES (4, 1, 'A Client', 'active')");

$invite = Invitation::issue(1, 'fresh', 'welcome@example.test', 'coach');
T::ok(isset($invite['url']) && str_contains($invite['url'], '/invite/'), 'issuing works normally once there is a client');


T::group('The notice tells people before they hit the wall');

$db->exec('TRUNCATE TABLE pl_invitations');
$db->exec('DELETE FROM pl_client_orgs WHERE tenant_id = 1');

T::ok(str_contains((string) SendingTrust::notice(1), '3 more invitations'), 'an unproven firm is told what it has left');
T::same(null, SendingTrust::notice(3), 'an established firm is told nothing — there is nothing useful to say');

$seed(1, SendingTrust::UNPROVEN_TOTAL);
T::ok(str_contains((string) SendingTrust::notice(1), 'first client'), 'and once spent, the notice says how to lift it');
