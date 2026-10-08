<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Database;

/**
 * Billing and subscription (M13).
 *
 * Stripe over its REST API, not its PHP SDK — same reasoning as the mailer.
 * Zero Composer dependencies is what keeps deployment at "rsync the files", and
 * the four endpoints actually needed here are a curl call each.
 *
 * ---------------------------------------------------------------------------
 * WHO IS THE CUSTOMER
 *
 * Bizorca bills the FIRM. Always, only, and per active coach seat. A client
 * organization is never billed by the platform and never sees a platform
 * payment screen (FR-13.4). That is not a UI preference: the client belongs to
 * the coach, and putting our payment form in front of the coach's customer
 * breaks the white-label promise the entire subdomain architecture exists to
 * protect.
 *
 * WHAT HAPPENS WHEN THEY DO NOT PAY
 *
 * The workspace goes READ-ONLY. Nothing is deleted, nothing is hidden, and the
 * export keeps working (FR-13.3). A firm that stops paying has still got a
 * year of client relationships in here, and holding those hostage is both
 * wrong and — since the export is the thing that makes trusting us rational in
 * the first place — self-defeating. Enforcement is in Entitlements, not here.
 *
 * WHAT IS TRUE, AND WHERE
 *
 * Stripe is the source of truth for money. The columns on pl_tenants are a
 * local cache of what Stripe last told us, so a page load is not an API call
 * and an outage at Stripe does not lock every firm out of its own data. When
 * they disagree, Stripe wins and the webhook corrects us.
 * ---------------------------------------------------------------------------
 */
final class Billing
{
    private const API = 'https://api.stripe.com/v1/';

    /** Days of trial. No card, per FR-13.3. */
    public const TRIAL_DAYS = 14;

    /**
     * The plan catalogue.
     *
     * In code rather than in a table, for the same reason the notification
     * catalogue is: these are product decisions that ship with a release, not
     * data that varies per install. A price change is a deploy, and it should
     * be — it needs the Stripe price to exist first.
     *
     * `price` values are Stripe Price IDs and come from the environment, since
     * they differ between test and live mode and must never be committed.
     *
     * There is no `custom_domain` feature. Custom domains were considered and
     * ruled out (SPEC §11) — every firm lives on a subdomain of the primary
     * domain, permanently. A plan flag for a capability that will not be built
     * is a promise to customers that cannot be kept, so it is not here.
     *
     * @var array<string,array<string,mixed>>
     */
    public const PLANS = [
        'trial' => [
            'name'          => 'Trial',
            'blurb'         => 'Everything, for two weeks, without a card.',
            'monthly'       => 0,
            'yearly'        => 0,
            'seats'         => 2,
            'storage_gb'    => 1,
            'remove_credit' => false,
            'scoring'       => true,
        ],
        'solo' => [
            'name'          => 'Solo',
            'blurb'         => 'One advisor, unlimited clients.',
            'monthly'       => 7900,   // cents
            'yearly'        => 79000,  // two months free
            'seats'         => 1,
            'storage_gb'    => 10,
            'remove_credit' => false,
            'scoring'       => true,
        ],
        'practice' => [
            'name'          => 'Practice',
            'blurb'         => 'A small firm, with your name on it instead of ours.',
            'monthly'       => 12900,
            'yearly'        => 129000,
            'seats'         => 5,
            'storage_gb'    => 50,
            'remove_credit' => true,
            'scoring'       => true,
        ],
        'firm' => [
            'name'          => 'Firm',
            'blurb'         => 'Several advisors, one shared library, room to grow.',
            'monthly'       => 24900,
            'yearly'        => 249000,
            'seats'         => 25,
            'storage_gb'    => 250,
            'remove_credit' => true,
            'scoring'       => true,
        ],
    ];

    /** Plans a firm can actually buy. Trial is arrived at, not chosen. */
    public const PURCHASABLE = ['solo', 'practice', 'firm'];

    public static function plan(string $key): ?array
    {
        return self::PLANS[$key] ?? null;
    }

    /** Is Stripe wired up at all? False in development, and that is fine. */
    public static function configured(): bool
    {
        return trim((string) Config::get('stripe.secret_key', '')) !== '';
    }

    /**
     * Start the trial clock. Called once, at tenant creation.
     *
     * Idempotent: a tenant that already has a trial end date keeps it. Calling
     * this twice must not hand someone another fortnight.
     */
    public static function startTrial(int $tenantId): void
    {
        Database::conn()->prepare(
            "UPDATE pl_tenants
             SET plan = 'trial', billing_status = 'trialing',
                 trial_ends_at = NOW() + INTERVAL :days DAY
             WHERE id = :id AND trial_ends_at IS NULL"
        )->execute(['days' => self::TRIAL_DAYS, 'id' => $tenantId]);
    }

    /**
     * Seats currently in use: active firm-side users.
     *
     * Client-side contacts are never seats. A firm with four coaches and three
     * hundred client contacts pays for four, and if that were ever ambiguous
     * the product would have a perverse incentive to make coaches invite fewer
     * of their clients' people — the opposite of what it is for.
     */
    public static function seatsInUse(int $tenantId): int
    {
        $stmt = Database::conn()->prepare(
            "SELECT COUNT(*) AS c FROM pl_users
             WHERE tenant_id = :tid AND client_org_id IS NULL AND status = 'active'"
        );
        $stmt->execute(['tid' => $tenantId]);

        return (int) $stmt->fetch()['c'];
    }

    /**
     * Record a seat change and, if there is a live subscription, tell Stripe.
     *
     * Recorded locally even when Stripe is not configured or the call fails, so
     * "why did my bill go up in March" is answerable from our own data. A row
     * with synced_at still null is a reconciliation job's to-do list.
     */
    public static function recordSeatChange(
        int $tenantId,
        int $before,
        int $after,
        string $reason,
        ?int $changedBy = null
    ): void {
        if ($before === $after) {
            return;
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_seat_changes (tenant_id, seats_before, seats_after, reason, changed_by)
             VALUES (:tid, :before, :after, :reason, :by)'
        )->execute([
            'tid' => $tenantId, 'before' => $before, 'after' => $after,
            'reason' => mb_substr($reason, 0, 120), 'by' => $changedBy,
        ]);

        $changeId = (int) $db->lastInsertId();
        $tenant = self::tenant($tenantId);

        if ($tenant === null || $tenant['stripe_subscription_id'] === null || !self::configured()) {
            return;
        }

        $item = self::subscriptionItemId((string) $tenant['stripe_subscription_id']);

        if ($item === null) {
            return;
        }

        $ok = self::post('subscription_items/' . $item, ['quantity' => $after]);

        if ($ok !== null) {
            $db->prepare('UPDATE pl_seat_changes SET synced_at = NOW() WHERE id = :id')
               ->execute(['id' => $changeId]);
        }
    }

    // ------------------------------------------------------------- checkout

    /**
     * A Stripe Checkout session for a firm to subscribe.
     *
     * Checkout rather than a card form of our own, deliberately: card details
     * never touch this server, which removes an entire class of liability and
     * most of a PCI questionnaire for a product that is not a payments company.
     *
     * @return string|null The URL to send them to, or null if Stripe refused.
     */
    public static function checkoutUrl(
        int $tenantId,
        string $planKey,
        string $interval,
        string $successUrl,
        string $cancelUrl
    ): ?string {
        if (!in_array($planKey, self::PURCHASABLE, true)) {
            throw new \InvalidArgumentException('That plan cannot be bought directly.');
        }

        if (!in_array($interval, ['month', 'year'], true)) {
            throw new \InvalidArgumentException('Billing is monthly or yearly.');
        }

        if (!self::configured()) {
            throw new \RuntimeException('Billing is not configured on this installation.');
        }

        $priceId = self::priceId($planKey, $interval);

        if ($priceId === null) {
            throw new \RuntimeException('No Stripe price is configured for ' . $planKey . '/' . $interval . '.');
        }

        $tenant = self::tenant($tenantId);

        if ($tenant === null) {
            throw new \RuntimeException('No such firm.');
        }

        $params = [
            'mode'                => 'subscription',
            'success_url'         => $successUrl,
            'cancel_url'          => $cancelUrl,
            'line_items[0][price]'    => $priceId,
            'line_items[0][quantity]' => max(1, self::seatsInUse($tenantId)),
            // The tenant id rides along so the webhook can map the resulting
            // subscription back to a firm without guessing from an email
            // address the customer may have changed in Checkout.
            'client_reference_id' => (string) $tenantId,
            'metadata[tenant_id]' => (string) $tenantId,
            'subscription_data[metadata][tenant_id]' => (string) $tenantId,
        ];

        if ($tenant['stripe_customer_id'] !== null) {
            $params['customer'] = (string) $tenant['stripe_customer_id'];
        }

        $response = self::post('checkout/sessions', $params);

        return $response['url'] ?? null;
    }

    /**
     * A Stripe Billing Portal session — where a firm changes card, downloads
     * invoices, or cancels.
     *
     * Cancellation lives there rather than here on purpose. Building our own
     * cancel button means building the retention dark patterns that come with
     * it, and a firm that finds cancelling hard tells other firms so.
     */
    public static function portalUrl(int $tenantId, string $returnUrl): ?string
    {
        $tenant = self::tenant($tenantId);

        if ($tenant === null || $tenant['stripe_customer_id'] === null || !self::configured()) {
            return null;
        }

        $response = self::post('billing_portal/sessions', [
            'customer'   => (string) $tenant['stripe_customer_id'],
            'return_url' => $returnUrl,
        ]);

        return $response['url'] ?? null;
    }

    // -------------------------------------------------------------- webhook

    /**
     * Verify a Stripe webhook signature.
     *
     * Implemented here rather than pulled from the SDK, and it is worth being
     * exact about why each step matters. Without this, the endpoint is an
     * unauthenticated "give my firm a free subscription" API.
     *
     *   - The signed payload is "{timestamp}.{raw body}". Signing the body
     *     alone would let anyone replay a real event forever.
     *   - The timestamp is checked against a tolerance for the same reason.
     *   - Comparison is hash_equals, so a wrong signature does not leak how
     *     wrong it was through timing.
     *   - The RAW body is what gets signed. Re-encoding decoded JSON produces
     *     different bytes and a signature that never matches; this must be
     *     given the string as it arrived.
     */
    public static function verifySignature(string $rawBody, string $header, string $secret, int $tolerance = 300): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $bits = explode('=', trim($part), 2);

            if (count($bits) !== 2) {
                continue;
            }

            if ($bits[0] === 't') {
                $timestamp = (int) $bits[1];
            } elseif ($bits[0] === 'v1') {
                $signatures[] = $bits[1];
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - $timestamp) > $tolerance) {
            return false;   // too old to be anything but a replay
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle one webhook event.
     *
     * Records first and handles second. The unique index on stripe_event_id is
     * the idempotency mechanism: Stripe retries anything it did not get a 2xx
     * for, and will deliver the same event twice when a response is merely
     * slow. A handler that extends a period without checking would extend it
     * twice.
     *
     * @param array<string,mixed> $event
     * @return array{handled:bool, note:string}
     */
    public static function handleEvent(array $event): array
    {
        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if ($eventId === '' || $type === '') {
            return ['handled' => false, 'note' => 'Malformed event.'];
        }

        $db = Database::conn();
        $object = $event['data']['object'] ?? [];
        $tenantId = self::tenantFor($object);

        try {
            $db->prepare(
                'INSERT INTO pl_billing_events (tenant_id, stripe_event_id, event_type, payload)
                 VALUES (:tid, :eid, :type, :payload)'
            )->execute([
                'tid' => $tenantId,
                'eid' => $eventId,
                'type' => $type,
                'payload' => json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['handled' => false, 'note' => 'Already seen.'];
            }

            throw $e;
        }

        $note = self::apply($type, $object, $tenantId);

        $db->prepare(
            'UPDATE pl_billing_events SET handled_at = NOW(), handler_note = :note
             WHERE stripe_event_id = :eid'
        )->execute(['note' => mb_substr($note, 0, 500), 'eid' => $eventId]);

        return ['handled' => true, 'note' => $note];
    }

    /**
     * @param array<string,mixed> $object
     */
    private static function apply(string $type, array $object, ?int $tenantId): string
    {
        if ($tenantId === null) {
            return 'No firm could be identified for this event.';
        }

        return match ($type) {
            'checkout.session.completed' => self::onCheckoutComplete($tenantId, $object),
            'customer.subscription.created',
            'customer.subscription.updated' => self::onSubscriptionChange($tenantId, $object),
            'customer.subscription.deleted' => self::onSubscriptionEnded($tenantId),
            'invoice.payment_failed'        => self::setStatus($tenantId, 'past_due', 'A payment failed.'),
            'invoice.payment_succeeded'     => self::setStatus($tenantId, 'active', 'A payment succeeded.'),
            default => 'Nothing to do for ' . $type . '.',
        };
    }

    /** @param array<string,mixed> $object */
    private static function onCheckoutComplete(int $tenantId, array $object): string
    {
        $customer = $object['customer'] ?? null;
        $subscription = $object['subscription'] ?? null;

        Database::conn()->prepare(
            'UPDATE pl_tenants
             SET stripe_customer_id = COALESCE(:cust, stripe_customer_id),
                 stripe_subscription_id = COALESCE(:sub, stripe_subscription_id)
             WHERE id = :id'
        )->execute([
            'cust' => is_string($customer) ? $customer : null,
            'sub'  => is_string($subscription) ? $subscription : null,
            'id'   => $tenantId,
        ]);

        return 'Checkout completed; customer and subscription linked.';
    }

    /**
     * The workhorse. Stripe tells us the plan, the interval, the state and the
     * period end, and we believe it.
     *
     * @param array<string,mixed> $object
     */
    private static function onSubscriptionChange(int $tenantId, array $object): string
    {
        $item = $object['items']['data'][0] ?? [];
        $priceId = (string) ($item['price']['id'] ?? '');
        $interval = (string) ($item['price']['recurring']['interval'] ?? 'month');

        $plan = self::planForPrice($priceId);

        $status = (string) ($object['status'] ?? 'active');

        // Stripe has more states than we do, and the extra ones are all
        // varieties of "not paying". Mapping them onto ours rather than
        // widening the enum keeps the read-only decision to one comparison.
        $localStatus = match ($status) {
            'trialing'                     => 'trialing',
            'active'                       => 'active',
            'past_due'                     => 'past_due',
            'canceled', 'incomplete_expired' => 'canceled',
            default                        => 'unpaid',
        };

        $periodEnd = isset($object['current_period_end'])
            ? date('Y-m-d H:i:s', (int) $object['current_period_end'])
            : null;

        Database::conn()->prepare(
            'UPDATE pl_tenants
             SET plan = COALESCE(:plan, plan),
                 billing_status = :status,
                 billing_interval = :interval,
                 current_period_end = :period_end,
                 cancel_at_period_end = :cancelling,
                 stripe_subscription_id = COALESCE(:sub, stripe_subscription_id),
                 seat_limit = COALESCE(:seats, seat_limit)
             WHERE id = :id'
        )->execute([
            'plan'       => $plan,
            'status'     => $localStatus,
            'interval'   => in_array($interval, ['month', 'year'], true) ? $interval : 'month',
            'period_end' => $periodEnd,
            'cancelling' => !empty($object['cancel_at_period_end']) ? 1 : 0,
            'sub'        => isset($object['id']) ? (string) $object['id'] : null,
            'seats'      => $plan === null ? null : self::PLANS[$plan]['seats'],
            'id'         => $tenantId,
        ]);

        return 'Subscription is ' . $localStatus . ($plan === null ? '' : ' on ' . $plan) . '.';
    }

    private static function onSubscriptionEnded(int $tenantId): string
    {
        Database::conn()->prepare(
            "UPDATE pl_tenants
             SET billing_status = 'canceled', stripe_subscription_id = NULL
             WHERE id = :id"
        )->execute(['id' => $tenantId]);

        return 'Subscription ended. The workspace is read-only; nothing was deleted.';
    }

    private static function setStatus(int $tenantId, string $status, string $note): string
    {
        Database::conn()->prepare('UPDATE pl_tenants SET billing_status = :s WHERE id = :id')
                        ->execute(['s' => $status, 'id' => $tenantId]);

        return $note;
    }

    /**
     * Which firm an event is about.
     *
     * Metadata first, because it is what we put there. Falling back to the
     * customer id covers events Stripe generates itself — an invoice, say —
     * which carry no metadata of ours.
     *
     * @param array<string,mixed> $object
     */
    public static function tenantFor(array $object): ?int
    {
        foreach ([
            $object['metadata']['tenant_id'] ?? null,
            $object['client_reference_id'] ?? null,
        ] as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        $customer = $object['customer'] ?? null;

        if (is_string($customer) && $customer !== '') {
            $stmt = Database::conn()->prepare('SELECT id FROM pl_tenants WHERE stripe_customer_id = :c LIMIT 1');
            $stmt->execute(['c' => $customer]);
            $row = $stmt->fetch();

            if ($row !== false) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    /** Which plan a Stripe price belongs to, or null if we do not recognise it. */
    public static function planForPrice(string $priceId): ?string
    {
        if ($priceId === '') {
            return null;
        }

        foreach (self::PURCHASABLE as $key) {
            foreach (['month', 'year'] as $interval) {
                if (self::priceId($key, $interval) === $priceId) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function priceId(string $planKey, string $interval): ?string
    {
        $value = trim((string) Config::get('stripe.prices.' . $planKey . '_' . $interval, ''));

        return $value === '' ? null : $value;
    }

    // ------------------------------------------------------------ internals

    /** @return array<string,mixed>|null */
    public static function tenant(int $tenantId): ?array
    {
        $stmt = Database::conn()->prepare('SELECT * FROM pl_tenants WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private static function subscriptionItemId(string $subscriptionId): ?string
    {
        $response = self::get('subscriptions/' . $subscriptionId);

        return $response['items']['data'][0]['id'] ?? null;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null Null on any failure.
     */
    private static function post(string $path, array $params): ?array
    {
        return self::call('POST', $path, $params);
    }

    /** @return array<string,mixed>|null */
    private static function get(string $path): ?array
    {
        return self::call('GET', $path, []);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    private static function call(string $method, string $path, array $params): ?array
    {
        $key = trim((string) Config::get('stripe.secret_key', ''));

        if ($key === '') {
            return null;
        }

        $ch = curl_init(self::API . $path);

        if ($ch === false) {
            error_log('Billing: curl_init failed');
            return null;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($body === false || $status >= 300) {
            // Never log the body: it can carry customer details, and on an
            // error path it definitely carries our request back at us.
            error_log('Billing: ' . $method . ' ' . $path . ' failed, status ' . $status . ' ' . $error);
            return null;
        }

        $decoded = json_decode((string) $body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
