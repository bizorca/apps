<?php
/**
 * Stripe webhook receiver. Register in Stripe as
 *   https://tools.bizorca.com/account/stripe-webhook.php
 * with checkout.session.completed and customer.subscription.created / updated /
 * deleted. No session, no CSRF: the Stripe-Signature header is the
 * authentication, checked against STRIPE_WEBHOOK_SECRET.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/private_html/includes/bootstrap.php';
require_once TL_PRIVATE . '/includes/billing.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = (string) file_get_contents('php://input');
if (!tl_stripe_verify($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''))) {
    http_response_code(400);
    exit('Signature verification failed.');
}

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
    http_response_code(400);
    exit('Bad payload.');
}

// Stripe can deliver the same event more than once; handle each id once.
$first = tl_db()->prepare('INSERT IGNORE INTO tl_stripe_events (event_id, type) VALUES (?, ?)');
$first->execute([$event['id'], $event['type']]);
if ($first->rowCount() === 0) {
    exit('duplicate');
}

try {
    $obj = $event['data']['object'] ?? [];
    switch ($event['type']) {
        case 'checkout.session.completed':
            if (($obj['mode'] ?? '') === 'subscription' && !empty($obj['subscription'])) {
                $userId = (int) ($obj['client_reference_id'] ?? $obj['metadata']['tools_user_id'] ?? 0);
                $sub    = tl_stripe('GET', 'subscriptions/' . $obj['subscription']);
                tl_billing_store_subscription($sub, $userId ?: null);
            }
            break;
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
            tl_billing_store_subscription($obj);
            break;
    }
} catch (Throwable $e) {
    // Let Stripe retry: forget the event id and answer 500.
    tl_db()->prepare('DELETE FROM tl_stripe_events WHERE event_id = ?')->execute([$event['id']]);
    error_log('stripe-webhook ' . $event['type'] . ': ' . $e->getMessage());
    http_response_code(500);
    exit('error');
}

echo 'ok';
