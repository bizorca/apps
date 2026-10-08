<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

/*
 * Community-fund donations by PayPal or Stripe.
 *
 * No page in the original (or here) links to these endpoints yet; they are
 * ported so the plumbing is ready. Keys are global, from .env.php
 * (TM_PAYPAL_*, TM_STRIPE_*); with none set every endpoint answers 503 and
 * nothing is charged. Stripe is called over its REST API instead of the
 * 2.9 MB SDK, and its webhook signature is verified here (HMAC-SHA256 over
 * "timestamp.payload", 5-minute tolerance), as the SDK did.
 */

use TimeBank\Core\DB;
use TimeBank\Models\Donation;

class PaymentController extends BaseController
{
    // -------------------------------------------------------------------------
    // PayPal — Create Order
    // -------------------------------------------------------------------------

    public function paypalCreate(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();
        $amount   = round((float) $this->request->post('amount', '0'), 2);

        if (TM_PAYPAL_CLIENT_ID === '' || TM_PAYPAL_CLIENT_SECRET === '') {
            $this->json(['error' => 'PayPal is not configured.'], 503);
        }
        if ($amount <= 0 || $amount > 10000) {
            $this->json(['error' => 'Invalid amount.'], 422);
        }

        $accessToken = $this->getPayPalAccessToken();
        if (!$accessToken) {
            $this->json(['error' => 'PayPal authorization failed.'], 500);
        }

        $baseUrl = TM_PAYPAL_MODE === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $payload = json_encode([
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'USD',
                    'value'         => number_format($amount, 2, '.', ''),
                ],
                'description' => 'Timebank community fund donation',
            ]],
            // Where PayPal sends the donor back. The original set neither, so
            // the success handler below could never have been reached.
            'application_context' => [
                'return_url' => url_abs('/payments/paypal/success'),
                'cancel_url' => url_abs('/payments/paypal/cancel'),
            ],
        ]);

        $ch = curl_init($baseUrl . '/v2/checkout/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . bin2hex(random_bytes(16)),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            error_log('PayPal create order failed: ' . $response);
            $this->json(['error' => 'Failed to create PayPal order.'], 500);
        }

        $order = json_decode($response, true);

        // Create a pending donation record to track this payment
        $donationModel = new Donation($tenantId);
        DB::insert('tm_donations', [
            'tenant_id'      => $tenantId,
            'member_id'      => $userId,
            'amount_usd'     => $amount,
            'payment_method' => 'paypal',
            'status'         => 'pending',
            'payment_reference' => $order['id'] ?? null,
            'requested_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->json(['order_id' => $order['id']]);
    }

    // -------------------------------------------------------------------------
    // PayPal — Capture on Return
    // -------------------------------------------------------------------------

    public function paypalSuccess(): void
    {
        $this->requireAuth();

        $token    = $this->request->get('token', '');
        $payerId  = $this->request->get('PayerID', '');
        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();

        if (TM_PAYPAL_CLIENT_ID === '' || TM_PAYPAL_CLIENT_SECRET === '') {
            flash('error', 'PayPal is not configured.');
            $this->redirect('/dashboard');
        }
        if ($token === '' || !preg_match('/^[A-Z0-9]{5,40}$/', $token)) {
            flash('error', 'Invalid PayPal return. No order token received.');
            $this->redirect('/dashboard');
        }

        $accessToken = $this->getPayPalAccessToken();
        if (!$accessToken) {
            flash('error', 'Could not connect to PayPal to confirm your payment.');
            $this->redirect('/dashboard');
        }

        $baseUrl = TM_PAYPAL_MODE === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $ch = curl_init($baseUrl . '/v2/checkout/orders/' . $token . '/capture');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 201 || ($result['status'] ?? '') !== 'COMPLETED') {
            error_log('PayPal capture failed: ' . $response);
            flash('error', 'PayPal payment capture failed. Please contact support.');
            $this->redirect('/dashboard');
        }

        // Find and mark the pending donation
        $donation = DB::fetch(
            "SELECT * FROM `tm_donations` WHERE payment_reference = ? AND member_id = ? AND tenant_id = ? LIMIT 1",
            [$token, $userId, $tenantId]
        );

        if ($donation) {
            $donationModel = new Donation($tenantId);
            $donationModel->markPaid((int) $donation['id'], $token, 'paypal');
        } else {
            // No pre-existing record — create a completed one
            $capturedAmount = (float) ($result['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
            DB::insert('tm_donations', [
                'tenant_id'         => $tenantId,
                'member_id'         => $userId,
                'amount_usd'        => $capturedAmount,
                'payment_method'    => 'paypal',
                'status'            => 'paid',
                'payment_reference' => $token,
                'requested_at'      => date('Y-m-d H:i:s'),
                'paid_at'           => date('Y-m-d H:i:s'),
            ]);
        }

        flash('success', 'Thank you! Your PayPal donation has been received.');
        $this->redirect('/dashboard');
    }

    // -------------------------------------------------------------------------
    // PayPal — Cancel
    // -------------------------------------------------------------------------

    public function paypalCancel(): void
    {
        $this->requireAuth();

        flash('error', 'Your PayPal donation was cancelled. No charge was made.');
        $this->redirect('/dashboard');
    }

    // -------------------------------------------------------------------------
    // Stripe — Create PaymentIntent
    // -------------------------------------------------------------------------

    public function stripeCreate(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();
        $amount   = round((float) $this->request->post('amount', '0'), 2);

        if ($amount <= 0) {
            $this->json(['error' => 'Invalid amount.'], 422);
        }

        if (TM_STRIPE_SECRET_KEY === '') {
            $this->json(['error' => 'Card payments are not configured.'], 503);
        }

        $amountCents = (int) round($amount * 100);

        $ch = curl_init('https://api.stripe.com/v1/payment_intents');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => TM_STRIPE_SECRET_KEY . ':',
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_POSTFIELDS     => http_build_query([
                'amount'                    => $amountCents,
                'currency'                  => 'usd',
                'description'               => 'Timebank community fund donation',
                'metadata'                  => ['tenant_id' => $tenantId, 'member_id' => $userId],
                'automatic_payment_methods' => ['enabled' => 'true'],
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $intent = json_decode((string) $response, true);
        if ($httpCode !== 200 || empty($intent['id']) || empty($intent['client_secret'])) {
            error_log('Stripe PaymentIntent creation failed: HTTP ' . $httpCode);
            $this->json(['error' => 'Failed to initialise payment. Please try again.'], 500);
        }

        DB::insert('tm_donations', [
            'tenant_id'         => $tenantId,
            'member_id'         => $userId,
            'amount_usd'        => number_format($amount, 2, '.', ''),
            'payment_method'    => 'stripe',
            'status'            => 'pending',
            'payment_reference' => $intent['id'],
            'requested_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->json(['client_secret' => $intent['client_secret']]);
    }

    // -------------------------------------------------------------------------
    // Stripe — Webhook
    // -------------------------------------------------------------------------

    public function stripeWebhook(): never
    {
        $payload   = (string) file_get_contents('php://input');
        $sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

        if (TM_STRIPE_WEBHOOK_SECRET === '') {
            http_response_code(503);
            exit;
        }
        if ($payload === '') {
            http_response_code(400);
            exit;
        }
        if (!self::stripeSignatureValid($payload, $sigHeader, TM_STRIPE_WEBHOOK_SECRET)) {
            error_log('Stripe webhook: invalid signature');
            http_response_code(403);
            exit;
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            http_response_code(400);
            exit;
        }

        if (($event['type'] ?? '') === 'payment_intent.succeeded') {
            $intent   = $event['data']['object'] ?? [];
            $intentId = (string) ($intent['id'] ?? '');

            $donation = $intentId !== '' ? DB::fetch(
                'SELECT * FROM `tm_donations` WHERE payment_reference = ? LIMIT 1',
                [$intentId]
            ) : false;

            if ($donation) {
                (new Donation((int) $donation['tenant_id']))->markPaid((int) $donation['id'], $intentId, 'stripe');
            } else {
                // Webhook arrived before (or without) the pending row.
                $tenantId = (int) ($intent['metadata']['tenant_id'] ?? 0);
                $memberId = (int) ($intent['metadata']['member_id'] ?? 0);
                $member   = $tenantId > 0 && $memberId > 0
                    ? DB::fetch('SELECT id FROM `tm_members` WHERE id = ? AND tenant_id = ?', [$memberId, $tenantId])
                    : false;
                if ($member) {
                    DB::insert('tm_donations', [
                        'tenant_id'         => $tenantId,
                        'member_id'         => $memberId,
                        'amount_usd'        => number_format(((int) ($intent['amount'] ?? 0)) / 100, 2, '.', ''),
                        'payment_method'    => 'stripe',
                        'status'            => 'paid',
                        'payment_reference' => $intentId,
                        'requested_at'      => date('Y-m-d H:i:s'),
                        'paid_at'           => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['received' => true]);
        exit;
    }

    /** Stripe-Signature: "t=<unix>,v1=<hex hmac>[,v1=...]", signed over "<t>.<payload>". */
    public static function stripeSignatureValid(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = ctype_digit($v) ? (int) $v : null;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if ($t === null || !$sigs || abs(time() - $t) > $tolerance) {
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Exchange client credentials for a PayPal access token.
     * Returns the token string on success, null on failure.
     */
    private function getPayPalAccessToken(): ?string
    {
        $baseUrl = TM_PAYPAL_MODE === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $ch = curl_init($baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_USERPWD        => TM_PAYPAL_CLIENT_ID . ':' . TM_PAYPAL_CLIENT_SECRET,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('PayPal token request failed (' . $httpCode . '): ' . $response);
            return null;
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
}
