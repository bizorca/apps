<?php
declare(strict_types=1);

/**
 * One-time report purchases. DORMANT: only reachable when NB_PAYMENTS_ENABLED
 * is true in private_html/.env.php. While it is false every report is free
 * and finalized as the full (premium) report by the wizard.
 *
 * Stripe over REST with curl via the shared tl_stripe() (private_html/includes/
 * billing.php): no SDK, the same Stripe account and secret key as the
 * membership. The webhook has its own endpoint and secret:
 *   NB_STRIPE_WEBHOOK_SECRET  whsec_... for https://tools.bizorca.com/numbrella/webhook.php
 *                             (event: checkout.session.completed)
 * Prices: NB_PRICE_STANDARD_CENTS (2900) and NB_PRICE_PREMIUM_CENTS (9900).
 */

require_once TL_PRIVATE . '/includes/billing.php';

const NB_TIERS = [
    'standard' => ['label' => 'Standard Valuation Report'],
    'premium'  => ['label' => 'Premium Valuation Report'],
];

function tierPriceCents(string $tier): int
{
    return $tier === 'premium' ? NB_PRICE_PREMIUM : NB_PRICE_STANDARD;
}

/** Create a Stripe Checkout Session for one report and return its URL. */
function createCheckoutUrl(array $report, array $user, string $tier): string
{
    $amount = tierPriceCents($tier);
    $origin = tl_origin();

    $session = tl_stripe('POST', 'checkout/sessions', [
        'mode'                => 'payment',
        'customer_email'      => $user['email'],
        'client_reference_id' => (string) $report['id'],
        'line_items'          => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'usd',
                'unit_amount'  => $amount,
                'product_data' => [
                    'name'        => NB_TIERS[$tier]['label'] . ' (' . tl_money($amount) . ')',
                    'description' => 'Numbrella valuation report #' . $report['id'],
                ],
            ],
        ]],
        'metadata'    => ['nb_report_id' => $report['id'], 'tools_user_id' => $user['id'], 'nb_tier' => $tier],
        'success_url' => $origin . url('/report.php?id=' . $report['id'] . '&paid=1'),
        'cancel_url'  => $origin . url('/checkout.php?id=' . $report['id'] . '&cancelled=1'),
    ]);

    // The session id is known before redirecting, so the pending row is
    // written once with it (the old code inserted, then patched "the latest
    // pending row", which two quick clicks could cross).
    getDB()->prepare(
        'INSERT INTO nb_payments (report_id, user_id, tier, amount_cents, stripe_session_id) VALUES (?, ?, ?, ?, ?)'
    )->execute([$report['id'], $user['id'], $tier, $amount, $session['id']]);

    return (string) $session['url'];
}

/**
 * Stripe's v1 signature check (as tl_stripe_verify does), against
 * Numbrella's own endpoint secret, refusing anything older than five minutes.
 */
function verifyNumbrellaWebhook(string $payload, string $header, ?string $secret = null, int $tolerance = 300): bool
{
    $secret ??= (string) tl_env('NB_STRIPE_WEBHOOK_SECRET', '');
    if ($secret === '' || $header === '') {
        return false;
    }
    $t    = null;
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

/**
 * Handle a verified checkout.session.completed: mark the payment paid and
 * finalize the report at the purchased tier. Idempotent on the session id.
 * Returns a short note for the webhook's JSON reply.
 */
function handleCheckoutCompleted(array $session): string
{
    if (($session['mode'] ?? '') !== 'payment' || ($session['payment_status'] ?? '') !== 'paid') {
        return 'ignored: not a paid one-time checkout';
    }
    $reportId = (int) ($session['metadata']['nb_report_id'] ?? 0);
    $userId   = (int) ($session['metadata']['tools_user_id'] ?? 0);
    $tier     = ($session['metadata']['nb_tier'] ?? '') === 'premium' ? 'premium' : 'standard';
    if (!$reportId || !$userId) {
        return 'ignored: no report metadata';
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        $s = $db->prepare('SELECT id, status, tier, amount_cents FROM nb_payments WHERE stripe_session_id = ? FOR UPDATE');
        $s->execute([$session['id']]);
        $payment = $s->fetch();
        if ($payment && $payment['status'] === 'paid') {
            $db->commit();
            return 'already processed';
        }

        $s = $db->prepare('SELECT * FROM nb_reports WHERE id = ? AND user_id = ?');
        $s->execute([$reportId, $userId]);
        $report = $s->fetch();
        if (!$report) {
            $db->commit();
            return 'ignored: report not found';
        }

        // The tier is whatever was actually paid for, never just the metadata.
        $paid = (int) ($session['amount_total'] ?? 0);
        if ($paid < tierPriceCents($tier)) {
            $tier = $paid >= NB_PRICE_STANDARD ? 'standard' : '';
        }
        if ($tier === '') {
            $db->commit();
            return 'ignored: amount below any tier';
        }

        if ($payment) {
            $db->prepare("UPDATE nb_payments SET status = 'paid', tier = ?, paid_at = UTC_TIMESTAMP(), stripe_payment_intent = ? WHERE id = ?")
               ->execute([$tier, $session['payment_intent'] ?? null, $payment['id']]);
        } else {
            $db->prepare("INSERT INTO nb_payments (report_id, user_id, tier, amount_cents, stripe_session_id, stripe_payment_intent, status, paid_at)
                          VALUES (?, ?, ?, ?, ?, ?, 'paid', UTC_TIMESTAMP())")
               ->execute([$reportId, $userId, $tier, $paid, $session['id'], $session['payment_intent'] ?? null]);
        }

        finalizeReport($report, $tier);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return 'processed';
}
