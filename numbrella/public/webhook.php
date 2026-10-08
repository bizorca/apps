<?php
declare(strict_types=1);

/**
 * Stripe webhook for one-time report purchases. DORMANT: answers 404 while
 * NB_PAYMENTS_ENABLED is false. Endpoint to register when payments go live:
 * https://tools.bizorca.com/numbrella/webhook.php, event checkout.session.completed,
 * its signing secret in .env.php as NB_STRIPE_WEBHOOK_SECRET.
 * No session, no CSRF: the Stripe signature is the authentication.
 */

$payload = (string) file_get_contents('php://input');
$sig     = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json');

if (!NB_PAYMENTS_ENABLED) {
    http_response_code(404);
    echo json_encode(['error' => 'payments disabled']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

require_once NB_ROOT . '/includes/payments.php';

if (!verifyNumbrellaWebhook($payload, $sig)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid signature']);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || ($event['type'] ?? '') !== 'checkout.session.completed') {
    echo json_encode(['received' => true]);
    exit;
}

try {
    $note = handleCheckoutCompleted((array) ($event['data']['object'] ?? []));
} catch (Throwable $e) {
    // 500 makes Stripe retry; handling is idempotent on the session id.
    error_log('numbrella webhook ' . ($event['id'] ?? '?') . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'processing failed']);
    exit;
}

echo json_encode(['received' => true, 'note' => $note]);
