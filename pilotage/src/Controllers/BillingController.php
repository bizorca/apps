<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Audit;
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\ClientIp;
use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Billing;
use Bizorca\Pilotage\Services\Entitlements;
use Bizorca\Pilotage\Services\Storage;

/**
 * Billing screens and the Stripe webhook (M13).
 *
 * The firm owner is the only person who sees any of this. A coach on a seat is
 * not the customer, and a client-side user must never encounter a platform
 * payment screen at all (FR-13.4).
 */
final class BillingController
{
    public function index(): string
    {
        [$tenant, $user] = $this->ownerContext();

        $tenantId = (int) $tenant['id'];

        return View::render('billing.index', [
            'title'   => 'Billing',
            'user'    => $user,
            'tenant'  => $tenant,
            'status'  => Entitlements::statusMessage($tenant),
            'plans'   => Billing::PLANS,
            'current' => (string) ($tenant['plan'] ?? 'trial'),
            'seats'   => Billing::seatsInUse($tenantId),
            'seatLimit' => Entitlements::seatLimit($tenant),
            'usage'   => Storage::tenantUsage($tenantId),
            'storageLimit' => Entitlements::storageLimitBytes($tenant),
            'configured' => Billing::configured(),
            'history' => $this->seatHistory($tenantId),
            'error'   => $_GET['error'] ?? null,
        ]);
    }

    /** Send the firm to Stripe Checkout. */
    public function subscribe(): string
    {
        [$tenant, $user] = $this->ownerContext();
        Csrf::check($_POST);

        $plan = (string) ($_POST['plan'] ?? '');
        $interval = (string) ($_POST['interval'] ?? 'month');

        try {
            $url = Billing::checkoutUrl(
                (int) $tenant['id'],
                $plan,
                $interval,
                tenant_url('/billing?subscribed=1', (string) $tenant['slug']),
                tenant_url('/billing', (string) $tenant['slug'])
            );
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            redirect(url('/billing?error=' . rawurlencode($e->getMessage())));
        }

        if ($url === null) {
            redirect(url('/billing?error=' . rawurlencode('Stripe did not return a checkout page. Try again shortly.')));
        }

        Audit::record('billing.checkout_started', (int) $tenant['id'], $user, 'tenant', (int) $tenant['id'],
            ['plan' => $plan, 'interval' => $interval], ClientIp::resolve($_SERVER));

        // Off-site to Stripe: absolute, and not through url(), which is for
        // paths on this tenant's own host.
        header('Location: ' . $url, true, 303);
        exit;
    }

    /** Send the firm to the Stripe billing portal to change card or cancel. */
    public function portal(): string
    {
        [$tenant, $user] = $this->ownerContext();
        Csrf::check($_POST);

        $url = Billing::portalUrl((int) $tenant['id'], tenant_url('/billing', (string) $tenant['slug']));

        if ($url === null) {
            redirect(url('/billing?error=' . rawurlencode('There is no billing account to manage yet.')));
        }

        header('Location: ' . $url, true, 303);
        exit;
    }

    /**
     * The Stripe webhook.
     *
     * No session, no CSRF, no tenant in scope — it is Stripe calling, not a
     * browser. The signature IS the authentication, and without it this
     * endpoint is a public "give my firm a free subscription" API. So it
     * refuses everything it cannot verify, and it refuses loudly rather than
     * silently succeeding.
     */
    public function webhook(): string
    {
        $secret = trim((string) Config::get('stripe.webhook_secret', ''));

        if ($secret === '') {
            // Not configured. Say so with a 503 rather than 200: a 200 would
            // tell Stripe the event was handled and it would never retry.
            http_response_code(503);
            return 'Billing is not configured.';
        }

        // The RAW body. Decoding and re-encoding produces different bytes and a
        // signature that can never match.
        $raw = (string) file_get_contents('php://input');
        $header = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

        if (!Billing::verifySignature($raw, $header, $secret)) {
            http_response_code(400);
            return 'Bad signature.';
        }

        $event = json_decode($raw, true);

        if (!is_array($event)) {
            http_response_code(400);
            return 'Unreadable payload.';
        }

        try {
            $result = Billing::handleEvent($event);
        } catch (\Throwable $e) {
            // A 500 makes Stripe retry, which is what we want for a transient
            // fault. The event id is enough to find it; the payload is not
            // logged because it carries customer detail.
            error_log('Billing webhook failed for ' . ($event['id'] ?? '?') . ': ' . $e->getMessage());
            http_response_code(500);
            return 'Handler error.';
        }

        http_response_code(200);

        return $result['note'];
    }

    // ------------------------------------------------------------- internals

    /** @return array<int,array<string,mixed>> */
    private function seatHistory(int $tenantId): array
    {
        $stmt = \Bizorca\Pilotage\Core\Database::conn()->prepare(
            'SELECT s.*, u.name AS changed_by_name
             FROM pl_seat_changes s
             LEFT JOIN pl_users u ON u.id = s.changed_by AND u.tenant_id = s.tenant_id
             WHERE s.tenant_id = :tid
             ORDER BY s.created_at DESC LIMIT 20'
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }

    /** @return array{0:array,1:array} */
    private function ownerContext(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'No tenant in scope.');
        }

        $user = Session::user();

        if ($user === null) {
            redirect(url('/login?redirect=' . rawurlencode(pl_request_path())));
        }

        // A client-side user must never reach a platform payment screen — not
        // even a 403, which would tell them one exists.
        if (($user['client_org_id'] ?? null) !== null) {
            throw new HttpException(404, 'Not found.');
        }

        Policy::authorize($user, Policy::READ, 'billing');

        return [$tenant, $user];
    }
}
