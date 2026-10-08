<?php

declare(strict_types=1);

/**
 * M13: plans, seats, entitlements, and the Stripe webhook.
 *
 * Two groups carry this file, and neither is about taking money.
 *
 * "The limit is hard and non-destructive" is the promise in FR-13.3, and it is
 * the one a firm is trusting when it puts a year of client relationships in
 * here. A lapsed subscription makes the workspace read-only; it never deletes,
 * never hides, and never takes the export away.
 *
 * "The webhook signature is the authentication" is the security boundary. That
 * endpoint takes no session and no CSRF token. Without a correct signature
 * check it is a public "give my firm a free subscription" API, so the check is
 * tested against replay, forgery, and the encoding mistake that silently breaks
 * it.
 */

use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Billing;
use Bizorca\Pilotage\Services\Entitlements;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_billing_events', 'pl_seat_changes', 'pl_client_orgs', 'pl_users', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'other','Other','active')");

$users = new UserRepository(1);
$ownerId = $users->create(['email' => 'owner@acme.test', 'name' => 'Ada Owner', 'role' => 'firm_owner', 'status' => 'active']);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'Bo Coach', 'role' => 'coach', 'status' => 'active']);
$orgId = (new ClientOrgRepository(1))->createOrg(['name' => 'Alpha', 'status' => 'active']);

// Eight client-side contacts. None of them is a seat.
for ($i = 0; $i < 8; $i++) {
    $users->create([
        'email' => "person{$i}@alpha.test", 'name' => "Person {$i}",
        'role' => 'client_member', 'client_org_id' => $orgId, 'status' => 'active',
    ]);
}

$reload = static fn (int $id = 1): array => Billing::tenant($id);

/**
 * Everything below this line describes the PAID model, so beta is explicitly
 * off for it. The product ships with beta ON — free, nothing gated — and that
 * behaviour has its own group at the end of the file.
 *
 * Pinning it here rather than inheriting whatever the .env happens to say is
 * the point: these assertions are about what happens when Pilotage starts
 * charging, and they have to keep passing between now and then.
 */
Config::set('app.beta', 'false');


T::group('Clients are never seats');

T::same(2, Billing::seatsInUse(1), 'two advisors and eight client contacts is two seats');

$users->update($coachId, ['status' => 'disabled']);
T::same(1, Billing::seatsInUse(1), 'a disabled advisor stops costing money');
$users->update($coachId, ['status' => 'active']);

T::same(0, Billing::seatsInUse(2), 'and another firm counts none of ours');


T::group('The plan catalogue');

foreach (Billing::PLANS as $key => $plan) {
    T::ok(($plan['seats'] ?? 0) > 0, $key . ' includes at least one seat');
    T::ok(($plan['storage_gb'] ?? 0) > 0, $key . ' includes storage');
    T::ok(trim((string) ($plan['blurb'] ?? '')) !== '', $key . ' says what it is for in plain words');
}

foreach (Billing::PURCHASABLE as $key) {
    T::ok(isset(Billing::PLANS[$key]), $key . ' is a real plan');
    T::ok(Billing::PLANS[$key]['yearly'] < Billing::PLANS[$key]['monthly'] * 12,
        $key . ' is cheaper yearly than twelve months of monthly');
}

T::ok(!in_array('trial', Billing::PURCHASABLE, true), 'the trial cannot be bought — it is arrived at');

T::throws(InvalidArgumentException::class,
    static fn () => Billing::checkoutUrl(1, 'trial', 'month', 'https://x.test/a', 'https://x.test/b'),
    'and checkout refuses it');
T::throws(InvalidArgumentException::class,
    static fn () => Billing::checkoutUrl(1, 'solo', 'fortnightly', 'https://x.test/a', 'https://x.test/b'),
    'billing is monthly or yearly, not anything else');


T::group('The trial clock');

Billing::startTrial(1);
$t = $reload();

T::same('trialing', (string) $t['billing_status'], 'a new firm is trialing');
T::ok($t['trial_ends_at'] !== null, 'with an end date');

$first = (string) $t['trial_ends_at'];
Billing::startTrial(1);

T::same($first, (string) $reload()['trial_ends_at'],
    'and calling it again does not hand out another fortnight');

$left = Entitlements::trialDaysLeft($reload());
T::ok($left !== null && $left >= Billing::TRIAL_DAYS - 1 && $left <= Billing::TRIAL_DAYS,
    'roughly a full trial remains');


T::group('The limit is hard, and it is non-destructive');

$writable = $reload();
T::same(true, Entitlements::writable($writable), 'a live trial can write');

$db->exec("UPDATE pl_tenants SET trial_ends_at = NOW() - INTERVAL 1 DAY WHERE id = 1");
$expired = $reload();

T::same(true, Entitlements::trialExpired($expired), 'the trial has run out');
T::same(false, Entitlements::writable($expired), 'and the workspace stops being writable');

// The whole promise, stated as assertions.
T::same(true, Entitlements::allowsWrite($expired, 'GET', '/clients'),
    'reading is still allowed — always');
T::same(true, Entitlements::allowsWrite($expired, 'GET', '/documents/1'),
    'including documents already delivered');
T::same(true, Entitlements::allowsWrite($expired, 'POST', '/firm/export'),
    'THE EXPORT STILL WORKS — it is the reason trusting us was rational');
T::same(true, Entitlements::allowsWrite($expired, 'POST', '/billing/subscribe'),
    'and paying is how you get out of here');
T::same(true, Entitlements::allowsWrite($expired, 'POST', '/logout'),
    'signing out is not a paid feature');
T::same(true, Entitlements::allowsWrite($expired, 'POST', '/stripe/webhook'),
    'and Stripe can still tell us they paid');

/**
 * Getting IN is never a paid feature.
 *
 * Since the move to tools.bizorca.com the password lives on the shared Bizorca
 * Tools account (/account, outside Pilotage and outside this gate entirely).
 * What remains Pilotage's is the firm's own second step: the TOTP challenge
 * at /login/2fa and, for firm owners, mandatory enrolment at /2fa/setup. If
 * either were gated, BILLING_ENFORCE would lock a lapsed firm's owner out of
 * the very billing screen that fixes it.
 */
T::same(true, Entitlements::allowsWrite($expired, 'POST', '/login/2fa'),
    'finishing sign-in with a two-factor code still works');
T::same(true, Entitlements::allowsWrite($expired, 'POST', '/2fa/setup'),
    'and so does the enrolment firm owners are required to complete — the original list said '
    . '/two-factor, which matched no route');

// The emailed-link and per-firm reset routes went with the port. Their old
// prefixes must not linger as holes in the gate.
T::same(false, Entitlements::allowsWrite($expired, 'POST', '/auth/link/sometoken.andverifier'),
    'the removed sign-in-link route is no longer an exemption');
T::same(false, Entitlements::allowsWrite($expired, 'POST', '/password/reset/sometoken.andverifier'),
    'nor is the removed password-reset route');

T::same(false, Entitlements::allowsWrite($expired, 'POST', '/engagements/1/tasks'),
    'but no new commitments');
T::same(false, Entitlements::allowsWrite($expired, 'DELETE', '/documents/1'),
    'and nothing can be deleted either — read-only cuts both ways');
T::same(false, Entitlements::allowsWrite($expired, 'POST', '/firm/exporter'),
    'the allowlist matches whole segments, not any path merely starting with the letters');

$message = Entitlements::statusMessage($expired);
T::same('blocked', $message['tone'], 'the firm is told plainly that it is blocked');
T::ok(str_contains($message['detail'], 'still here'), 'and that nothing has been taken away');
T::ok(str_contains($message['detail'], 'export'), 'and that the export still works');


T::group('past_due keeps working, on purpose');

$db->exec("UPDATE pl_tenants SET billing_status = 'past_due', plan = 'solo' WHERE id = 1");
$pastDue = $reload();

T::same(true, Entitlements::writable($pastDue),
    'a failed payment does not lock a firm out of live client work while Stripe is still retrying');
T::same('warning', Entitlements::statusMessage($pastDue)['tone'], 'it warns rather than blocks');

$db->exec("UPDATE pl_tenants SET billing_status = 'canceled' WHERE id = 1");
T::same(false, Entitlements::writable($reload()), 'an actually cancelled subscription does block');
T::ok(str_contains(Entitlements::statusMessage($reload())['detail'], 'Nothing has been deleted'),
    'and still says nothing was deleted');


T::group('Plan features');

$db->exec("UPDATE pl_tenants SET plan = 'solo', billing_status = 'active' WHERE id = 1");
$solo = $reload();

T::same(1, Entitlements::seatLimit($solo), 'solo is one seat');
T::same(false, Entitlements::canRemoveCredit($solo), 'and keeps the credit line');
T::same(false, Entitlements::canAddSeat($solo), 'two advisors on a one-seat plan cannot add a third');

$db->exec("UPDATE pl_tenants SET plan = 'firm' WHERE id = 1");
$firm = $reload();

T::same(25, Entitlements::seatLimit($firm), 'the firm plan is twenty-five seats');
T::same(true, Entitlements::canRemoveCredit($firm), 'can drop the credit line');
T::same(true, Entitlements::canAddSeat($firm), 'with plenty of room');

$db->exec("UPDATE pl_tenants SET plan = 'nonsense' WHERE id = 1");
T::same('Trial', (string) Entitlements::plan($reload())['name'],
    'an unrecognised plan falls back to the most restrictive one rather than the most generous');


T::group('Seat changes are money events, recorded either way');

$db->exec("UPDATE pl_tenants SET plan = 'practice' WHERE id = 1");
Billing::recordSeatChange(1, 2, 3, 'Cy joined', $ownerId);

$changes = $db->query('SELECT * FROM pl_seat_changes WHERE tenant_id = 1')->fetchAll();

T::same(1, count($changes), 'the change is recorded');
T::same(2, (int) $changes[0]['seats_before'], 'with what it was');
T::same(3, (int) $changes[0]['seats_after'], 'and what it became');
T::same(null, $changes[0]['synced_at'],
    'and marked unsynced, because Stripe is not configured here — a reconciliation to-do, not a lost fact');

Billing::recordSeatChange(1, 3, 3, 'Nothing happened', $ownerId);
T::same(1, count($db->query('SELECT * FROM pl_seat_changes WHERE tenant_id = 1')->fetchAll()),
    'a change of nothing is not recorded');


T::group('The webhook signature IS the authentication');

$secret = 'whsec_test_secret';
$body = '{"id":"evt_1","type":"customer.subscription.updated"}';
$now = time();

$sign = static function (string $payload, int $ts) use ($secret): string {
    return 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $payload, $secret);
};

T::same(true, Billing::verifySignature($body, $sign($body, $now), $secret),
    'a correctly signed payload verifies');

T::same(false, Billing::verifySignature($body, $sign($body, $now - 3600), $secret),
    'an hour-old signature does not — that is a replay');
T::same(true, Billing::verifySignature($body, $sign($body, $now - 60), $secret),
    'but a minute of clock skew is fine');

T::same(false, Billing::verifySignature($body . ' ', $sign($body, $now), $secret),
    'a payload altered by one space does not verify');
T::same(false, Billing::verifySignature($body, $sign($body, $now), 'whsec_wrong'),
    'nor does the right payload with the wrong secret');
T::same(false, Billing::verifySignature($body, 't=' . $now, $secret),
    'a header with a timestamp and no signature is refused');
T::same(false, Billing::verifySignature($body, 'v1=deadbeef', $secret),
    'and one with a signature and no timestamp — otherwise replay is free');
T::same(false, Billing::verifySignature($body, '', $secret), 'an empty header is refused');
T::same(false, Billing::verifySignature($body, $sign($body, $now), ''),
    'and an unconfigured secret refuses everything rather than accepting anything');

// The encoding mistake that silently breaks this in production.
$reencoded = json_encode(json_decode($body, true));
T::ok($reencoded !== $body || true, 'json round-tripping may change the bytes');
T::same(false, Billing::verifySignature('{"id": "evt_1", "type": "customer.subscription.updated"}', $sign($body, $now), $secret),
    'the RAW body is what is signed — re-encoded JSON never matches, so it must not be decoded first');


T::group('Webhook handling is idempotent');

$event = [
    'id' => 'evt_idempotent',
    'type' => 'invoice.payment_failed',
    'data' => ['object' => ['metadata' => ['tenant_id' => '1'], 'customer' => 'cus_1']],
];

$first = Billing::handleEvent($event);
T::same(true, $first['handled'], 'the first delivery is handled');
T::same('past_due', (string) $reload()['billing_status'], 'and does what it says');

$second = Billing::handleEvent($event);
T::same(false, $second['handled'], 'a second delivery of the SAME event is not handled again');
T::same('Already seen.', $second['note'], 'and says why');

T::same(1, (int) $db->query("SELECT COUNT(*) AS c FROM pl_billing_events WHERE stripe_event_id = 'evt_idempotent'")->fetch()['c'],
    'exactly one row exists for it — the unique index is the mechanism');

// Stripe retries for three days. Without this the same event would be applied
// dozens of times.
for ($i = 0; $i < 5; $i++) {
    Billing::handleEvent($event);
}
T::same(1, (int) $db->query("SELECT COUNT(*) AS c FROM pl_billing_events WHERE stripe_event_id = 'evt_idempotent'")->fetch()['c'],
    'and five more retries change nothing');


T::group('Mapping an event back to a firm');

T::same(1, Billing::tenantFor(['metadata' => ['tenant_id' => '1']]), 'metadata first, because we put it there');
T::same(1, Billing::tenantFor(['client_reference_id' => '1']), 'then the checkout reference');

$db->exec("UPDATE pl_tenants SET stripe_customer_id = 'cus_known' WHERE id = 2");
T::same(2, Billing::tenantFor(['customer' => 'cus_known']),
    'then the customer id, which covers events Stripe generates itself');

T::same(null, Billing::tenantFor(['customer' => 'cus_unheard_of']), 'an unknown customer maps to nobody');
T::same(null, Billing::tenantFor([]), 'and an empty object to nobody');

$orphan = Billing::handleEvent([
    'id' => 'evt_orphan', 'type' => 'invoice.payment_failed',
    'data' => ['object' => ['customer' => 'cus_unheard_of']],
]);
T::same(true, $orphan['handled'], 'an unmappable event is still recorded');
T::ok(str_contains($orphan['note'], 'No firm'), 'and says it could not be applied rather than failing silently');


T::group('A subscription update is believed');

$db->exec("UPDATE pl_tenants SET plan = 'trial', billing_status = 'trialing' WHERE id = 1");

Billing::handleEvent([
    'id' => 'evt_sub_1',
    'type' => 'customer.subscription.updated',
    'data' => ['object' => [
        'id' => 'sub_123',
        'status' => 'active',
        'current_period_end' => time() + 30 * 86400,
        'cancel_at_period_end' => false,
        'metadata' => ['tenant_id' => '1'],
        'items' => ['data' => [['price' => ['id' => 'price_unknown', 'recurring' => ['interval' => 'year']]]]],
    ]],
]);

$t = $reload();
T::same('active', (string) $t['billing_status'], 'the status comes from Stripe');
T::same('year', (string) $t['billing_interval'], 'and so does the interval');
T::same('sub_123', (string) $t['stripe_subscription_id'], 'and the subscription is linked');
T::same('trial', (string) $t['plan'],
    'but an unrecognised price does NOT change the plan — guessing would be worse than leaving it');

// Stripe has more states than we do; the extra ones are all "not paying".
Billing::handleEvent([
    'id' => 'evt_sub_2', 'type' => 'customer.subscription.updated',
    'data' => ['object' => [
        'id' => 'sub_123', 'status' => 'incomplete_expired',
        'metadata' => ['tenant_id' => '1'], 'items' => ['data' => []],
    ]],
]);
T::same('canceled', (string) $reload()['billing_status'],
    "Stripe's incomplete_expired maps onto our canceled — one comparison decides read-only, not five");

Billing::handleEvent([
    'id' => 'evt_sub_3', 'type' => 'customer.subscription.deleted',
    'data' => ['object' => ['id' => 'sub_123', 'metadata' => ['tenant_id' => '1']]],
]);
$t = $reload();
T::same('canceled', (string) $t['billing_status'], 'a deleted subscription cancels');
T::same(null, $t['stripe_subscription_id'], 'and unlinks');
T::same(false, Entitlements::writable($t), 'the workspace is read-only');

// And still nothing was destroyed.
T::same(10, (int) $db->query('SELECT COUNT(*) AS c FROM pl_users WHERE tenant_id = 1')->fetch()['c'],
    'every user survives cancellation');
T::same(1, (int) $db->query('SELECT COUNT(*) AS c FROM pl_client_orgs WHERE tenant_id = 1')->fetch()['c'],
    'and every client organization');


T::group('Billing history outlives the firm');

$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (3,'gone','Gone','active')");
Billing::handleEvent([
    'id' => 'evt_gone', 'type' => 'invoice.payment_succeeded',
    'data' => ['object' => ['metadata' => ['tenant_id' => '3']]],
]);

$db->exec('DELETE FROM pl_tenants WHERE id = 3');

$survivor = $db->query("SELECT * FROM pl_billing_events WHERE stripe_event_id = 'evt_gone'")->fetch();

T::ok($survivor !== false, 'the event survives the firm being deleted');
T::same(null, $survivor['tenant_id'],
    'with its tenant link severed — a chargeback can arrive two months after they leave');


T::group('Billing is not configured in development, and that is fine');

T::same(false, Billing::configured(), 'no Stripe key here');
T::same(false, Entitlements::enforced(), 'and the read-only gate is off by default');

T::throws(RuntimeException::class,
    static fn () => Billing::checkoutUrl(1, 'solo', 'month', 'https://x.test/a', 'https://x.test/b'),
    'checkout says so plainly rather than failing obscurely');
T::same(null, Billing::portalUrl(1, 'https://x.test/'), 'and the portal simply has nowhere to send you');



T::group('Free during beta');

/**
 * While the product is free, nothing is charged and nothing is gated. The value
 * of routing all of it through Entitlements is that the banner, the billing
 * screen and the write gate cannot end up telling one firm three different
 * stories — so this group checks the whole surface, not just the flag.
 */
Config::set('app.beta', 'true');

T::same(true, Entitlements::inBeta(), 'beta is on');

// An expired trial, a cancelled subscription, an unpaid account: none of them
// locks anyone out while the product is free.
foreach (['trialing', 'canceled', 'unpaid', 'past_due'] as $state) {
    $db->exec("UPDATE pl_tenants SET billing_status = '{$state}',
               trial_ends_at = NOW() - INTERVAL 90 DAY WHERE id = 1");

    T::same(true, Entitlements::writable($reload()),
        "a '{$state}' account can still write during beta");
    T::same(true, Entitlements::allowsWrite($reload(), 'POST', '/engagements/1/tasks'),
        "and can still be worked in");
}

T::same(false, Entitlements::trialExpired($reload()),
    'a trial date ninety days in the past has NOT expired — there is no countdown during beta');
T::same(false, Entitlements::enforced(),
    'and the read-only gate is off, whatever the enforcement flag says');

// Beta beats enforcement rather than the other way round: if both were somehow
// set, the safe reading is "do not lock people out of a free product".
Config::set('stripe.enforce', 'true');
T::same(false, Entitlements::enforced(),
    'beta wins over BILLING_ENFORCE — that must not depend on remembering to unset the other one');
Config::set('stripe.enforce', 'false');

$message = Entitlements::statusMessage($reload());
T::same('ok', $message['tone'], 'the status is reassuring, not a warning');
T::same('Free during beta', $message['headline'], 'and says so plainly');
T::same(null, $message['cta'], 'with nothing to buy');
T::ok(str_contains($message['detail'], 'no card'), 'no card is mentioned');
T::ok(str_contains($message['detail'], 'notice'),
    'and it promises notice before that changes, which is the part people actually worry about');

// Limits are a billing construct. Enforcing one while charging nothing is the
// worst of both worlds — a firm cannot pay to lift it.
$db->exec("UPDATE pl_tenants SET plan = 'solo' WHERE id = 1");
T::same(true, Entitlements::canAddSeat($reload()),
    'a one-seat plan does not cap seats during beta');
T::same(true, Entitlements::canStore($reload(), 500 * 1024 * 1024 * 1024),
    'nor is storage capped');
T::same(true, Entitlements::canRemoveCredit($reload()),
    'and the credit line can be removed on any plan');

// Turning beta off restores every limit, with no other change.
Config::set('app.beta', 'false');

T::same(false, Entitlements::canAddSeat($reload()), 'turning beta off restores the seat cap');
T::same(true, Entitlements::trialExpired($reload()), 'and the trial is expired again');

// The loop above left the account past_due, which is writable by design even
// outside beta — a declined card that Stripe is still retrying must not lock a
// firm out of live client work. So the state has to be one that genuinely
// blocks for this assertion to mean anything.
$db->exec("UPDATE pl_tenants SET billing_status = 'canceled' WHERE id = 1");
T::same(false, Entitlements::writable($reload()),
    'and a cancelled account is read-only once more');

Config::set('app.beta', 'true');


T::group('Nothing gates on a plan without asking Entitlements');

/**
 * A structural guard, for the same reason the digest one exists.
 *
 * The seat check on the staff screen used to compare seatsInUse against
 * seatLimit directly. Beta lifted the limit everywhere except there — so a firm
 * would read "free, no limits" and then be refused a second advisor. The bug is
 * not the arithmetic, it is doing the arithmetic somewhere that does not know
 * about beta.
 *
 * If this fails, the fix is to call Entitlements::canAddSeat, not to add
 * another beta check at the new call site.
 */
$controllers = glob(dirname(__DIR__) . '/src/Controllers/*.php') ?: [];

foreach ($controllers as $file) {
    $source = '';

    foreach (token_get_all((string) file_get_contents($file)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $source .= is_array($token) ? $token[1] : $token;
    }

    // seatsInUse compared against anything is the shape of the bug. Reading it
    // to display a count is fine, which is why BillingController may use it.
    $comparesSeats = preg_match('/seatsInUse\([^)]*\)\s*(>=|>|<|<=)/', $source) === 1;

    T::same(false, $comparesSeats,
        basename($file) . ' does not compare seat counts itself');
}

T::same(true, method_exists(Entitlements::class, 'canAddSeat'),
    'because Entitlements::canAddSeat is where that decision belongs');
