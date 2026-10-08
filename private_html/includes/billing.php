<?php
/**
 * Membership billing for the shared account.
 *
 * One paid membership (monthly or annual) unlocks every paid tool. Ported from
 * billing.bizorca.com 2026-10-08, without its per-app plan matrix. Stripe is
 * called over its REST API with curl: no SDK, no Composer, same as the rest of
 * the platform.
 *
 * THE ONE QUESTION A TOOL ASKS: tl_has_access('<tool>').
 *   - TL_BILLING_ENFORCE false (the default) -> always true.
 *   - the tool is not in TL_PAID_TOOLS        -> true. The list defaults to
 *     empty: every tool is free until it is listed.
 *   - otherwise: site admins and members only.
 *
 * Membership = a Stripe subscription in good standing, or an unexpired comp.
 * users.is_paid is kept in step as a cache for code that reads the row, but
 * tl_is_member() is the source of truth.
 *
 * .env.php keys: STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET,
 * STRIPE_PRICE_MONTHLY, STRIPE_PRICE_ANNUAL, and optionally
 * TL_MEMBERSHIP_MONTHLY_CENTS / TL_MEMBERSHIP_ANNUAL_CENTS (display only),
 * TL_BILLING_ENFORCE, TL_PAID_TOOLS (comma list of tool folder names).
 */

declare(strict_types=1);

/** Stripe statuses that count as a paying member. past_due/unpaid keep access
 *  while Stripe retries the card, as the original billing did. */
const TL_MEMBER_STATUSES = ['active', 'trialing', 'past_due', 'unpaid'];

function tl_billing_enforced(): bool
{
    return filter_var(tl_env('TL_BILLING_ENFORCE', false), FILTER_VALIDATE_BOOLEAN);
}

/** @return list<string> */
function tl_paid_tools(): array
{
    $raw = (string) tl_env('TL_PAID_TOOLS', '');
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

function tl_is_member(int $userId): bool
{
    $db = tl_db();

    $in = implode(',', array_fill(0, count(TL_MEMBER_STATUSES), '?'));
    $s  = $db->prepare("SELECT 1 FROM tl_subscriptions WHERE user_id = ? AND status IN ($in) LIMIT 1");
    $s->execute(array_merge([$userId], TL_MEMBER_STATUSES));
    if ($s->fetchColumn()) {
        return true;
    }

    $c = $db->prepare('SELECT 1 FROM tl_comps WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
    $c->execute([$userId]);
    return (bool) $c->fetchColumn();
}

/** Can the signed-in visitor use this tool? See the header comment. */
function tl_has_access(string $tool): bool
{
    if (!tl_billing_enforced() || !in_array($tool, tl_paid_tools(), true)) {
        return true;
    }
    $u = tl_user();
    return $u !== null && ((bool) $u['is_admin'] || tl_is_member((int) $u['id']));
}

/** For a tool page: send a visitor without access to the membership page. */
function tl_require_access(string $tool): void
{
    if (tl_has_access($tool)) {
        return;
    }
    if (!tl_user()) {
        tl_require_login();
    }
    header('Location: /account/billing.php?tool=' . rawurlencode($tool), true, 303);
    exit;
}

/** Recompute the users.is_paid cache after anything that can change it. */
function tl_billing_sync(int $userId): void
{
    tl_db()->prepare('UPDATE users SET is_paid = ? WHERE id = ?')
           ->execute([tl_is_member($userId) ? 1 : 0, $userId]);
}

/* ───────────────────────────────────────────────────────────── Stripe REST ── */

/**
 * One Stripe API call. Form-encoded, as Stripe's API expects; nested params
 * use PHP's http_build_query brackets, which Stripe accepts.
 *
 * @return array<string, mixed>
 */
function tl_stripe(string $method, string $path, array $params = []): array
{
    $key = (string) tl_env('STRIPE_SECRET_KEY', '');
    if ($key === '') {
        throw new RuntimeException('Stripe is not configured (STRIPE_SECRET_KEY).');
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $ch  = curl_init();
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERPWD        => $key . ':',
        CURLOPT_HTTPHEADER     => ['Stripe-Version: 2024-06-20'],
    ];
    if ($method === 'GET') {
        $opt[CURLOPT_URL] = $url . ($params ? '?' . http_build_query($params) : '');
    } else {
        $opt[CURLOPT_URL]        = $url;
        $opt[CURLOPT_POST]       = true;
        $opt[CURLOPT_POSTFIELDS] = http_build_query($params);
    }
    curl_setopt_array($ch, $opt);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $json = is_string($body) ? json_decode($body, true) : null;
    if (!is_array($json) || $code >= 400) {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? 'unknown error') : 'no response';
        throw new RuntimeException("Stripe {$method} {$path} failed ({$code}): {$msg}");
    }
    return $json;
}

/** The account's Stripe customer id, creating the customer on first use. */
function tl_stripe_customer(array $user): string
{
    $s = tl_db()->prepare('SELECT stripe_customer_id FROM tl_billing_customers WHERE user_id = ?');
    $s->execute([$user['id']]);
    $id = $s->fetchColumn();
    if (is_string($id) && $id !== '') {
        return $id;
    }

    $c = tl_stripe('POST', 'customers', [
        'email'    => $user['email'],
        'name'     => $user['name'],
        'metadata' => ['tools_user_id' => $user['id']],
    ]);
    tl_db()->prepare('INSERT INTO tl_billing_customers (user_id, stripe_customer_id) VALUES (?, ?)')
           ->execute([$user['id'], $c['id']]);
    return (string) $c['id'];
}

/** @return string the Checkout URL to redirect to */
function tl_stripe_checkout_url(array $user, string $interval, string $origin): string
{
    $price = (string) tl_env($interval === 'year' ? 'STRIPE_PRICE_ANNUAL' : 'STRIPE_PRICE_MONTHLY', '');
    if ($price === '') {
        throw new RuntimeException('No Stripe price configured for ' . $interval . '.');
    }
    $session = tl_stripe('POST', 'checkout/sessions', [
        'mode'                  => 'subscription',
        'customer'              => tl_stripe_customer($user),
        'line_items'            => [['price' => $price, 'quantity' => 1]],
        'allow_promotion_codes' => 'true',
        'client_reference_id'   => (string) $user['id'],
        'metadata'              => ['tools_user_id' => $user['id']],
        'subscription_data'     => ['metadata' => ['tools_user_id' => $user['id']]],
        'success_url'           => $origin . '/account/billing.php?checkout=success',
        'cancel_url'            => $origin . '/account/billing.php?checkout=cancel',
    ]);
    return (string) $session['url'];
}

function tl_stripe_portal_url(array $user, string $origin): string
{
    $session = tl_stripe('POST', 'billing_portal/sessions', [
        'customer'   => tl_stripe_customer($user),
        'return_url' => $origin . '/account/billing.php',
    ]);
    return (string) $session['url'];
}

/**
 * Verify a webhook delivery the way Stripe's SDK does: HMAC-SHA256 of
 * "{timestamp}.{payload}" with the endpoint secret, against any v1 signature
 * in the header, and refuse anything older than five minutes (replay).
 */
function tl_stripe_verify(string $payload, string $header, int $tolerance = 300): bool
{
    $secret = (string) tl_env('STRIPE_WEBHOOK_SECRET', '');
    if ($secret === '' || $header === '') {
        return false;
    }
    $t = null;
    $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') {
            $t = (int) $v;
        } elseif ($k === 'v1') {
            $sigs[] = $v;
        }
    }
    if (!$t || !$sigs || abs(time() - $t) > $tolerance) {
        return false;
    }
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}

/** Mirror one Stripe subscription object into tl_subscriptions. */
function tl_billing_store_subscription(array $sub, ?int $userId = null): ?int
{
    $db = tl_db();

    if ($userId === null) {
        $userId = (int) ($sub['metadata']['tools_user_id'] ?? 0);
    }
    if (!$userId) {
        $s = $db->prepare('SELECT user_id FROM tl_billing_customers WHERE stripe_customer_id = ?');
        $s->execute([(string) $sub['customer']]);
        $userId = (int) $s->fetchColumn();
    }
    if (!$userId) {
        return null;   // not ours (another product on the same Stripe account)
    }

    // current_period_end moved onto subscription items in newer API versions;
    // accept either place so a version bump cannot null it out.
    $periodEnd = $sub['current_period_end'] ?? ($sub['items']['data'][0]['current_period_end'] ?? null);
    $interval  = (string) ($sub['items']['data'][0]['price']['recurring']['interval'] ?? 'month');

    $db->prepare(
        'INSERT INTO tl_subscriptions
            (user_id, stripe_subscription_id, stripe_customer_id, billing_interval, status, current_period_end, cancel_at_period_end)
         VALUES (?, ?, ?, ?, ?, ?, ?) AS new
         ON DUPLICATE KEY UPDATE
            status               = new.status,
            billing_interval     = new.billing_interval,
            current_period_end   = new.current_period_end,
            cancel_at_period_end = new.cancel_at_period_end'
    )->execute([
        $userId,
        (string) $sub['id'],
        (string) $sub['customer'],
        $interval,
        (string) $sub['status'],
        $periodEnd ? gmdate('Y-m-d H:i:s', (int) $periodEnd) : null,
        !empty($sub['cancel_at_period_end']) ? 1 : 0,
    ]);

    tl_billing_sync($userId);
    return $userId;
}

/** The account's current subscription row, if any (newest first). */
function tl_billing_subscription(int $userId): ?array
{
    $s = tl_db()->prepare('SELECT * FROM tl_subscriptions WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
    $s->execute([$userId]);
    return $s->fetch() ?: null;
}

function tl_billing_comp(int $userId): ?array
{
    $s = tl_db()->prepare('SELECT * FROM tl_comps WHERE user_id = ?');
    $s->execute([$userId]);
    return $s->fetch() ?: null;
}

function tl_money(int $cents): string
{
    return '$' . number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
}

function tl_origin(): string
{
    $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'tools.bizorca.com');
}
