<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Audit;
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\ClientIp;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\CalendarSync;

/**
 * Connecting and disconnecting a calendar.
 *
 * Firm-side only. A coach's calendar is the one that matters; client-side
 * contacts get the .ics invite, which is what they already expect from every
 * other professional they deal with, and asking a client's bookkeeper to grant
 * OAuth access to their work calendar is a conversation nobody wants.
 */
final class CalendarController
{
    /** How long an authorisation round trip may take. */
    private const STATE_TTL = 900;

    public function index(): string
    {
        [$tenant, $user] = $this->context();

        return View::render('calendar.index', [
            'title'       => 'Calendar',
            'user'        => $user,
            'tenant'      => $tenant,
            'providers'   => CalendarSync::configured(),
            'connections' => CalendarSync::connectionsFor((int) $tenant['id'], (int) $user['id']),
            'error'       => $_GET['error'] ?? null,
            'connected'   => $_GET['connected'] ?? null,
        ]);
    }

    /**
     * Start the OAuth round trip.
     *
     * A POST, because it mints state and sends the user off-site. As a GET it
     * would be triggerable from an image tag.
     *
     * @param array<string,string> $params
     */
    public function connect(array $params): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $key = (string) ($params['provider'] ?? '');
        $provider = CalendarSync::configured()[$key] ?? null;

        if ($provider === null) {
            throw new HttpException(404, 'That calendar is not available here.');
        }

        // PKCE. Not strictly required by either provider for a confidential
        // client, but it costs one hash and closes code interception if the
        // redirect is ever intercepted.
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = bin2hex(random_bytes(32));

        Database::conn()->prepare(
            'INSERT INTO pl_oauth_states (tenant_id, user_id, provider, state, verifier, expires_at)
             VALUES (:tid, :uid, :provider, :state, :verifier, NOW() + INTERVAL :ttl SECOND)'
        )->execute([
            'tid' => (int) $tenant['id'],
            'uid' => (int) $user['id'],
            'provider' => $key,
            'state' => $state,
            'verifier' => $verifier,
            'ttl' => self::STATE_TTL,
        ]);

        header('Location: ' . $provider->authorizeUrl($state, $this->redirectUri($key), $challenge), true, 303);
        exit;
    }

    /**
     * Come back from the provider — at the APEX, with no tenant in scope.
     *
     * The state parameter is the whole of the CSRF defence and now does double
     * duty: it authenticates the callback AND says which firm and person it
     * belongs to. Without it an attacker sends someone a crafted callback URL
     * and connects their own calendar to the victim's account, or the victim's
     * to theirs.
     *
     * Single use, enforced by consuming it in a conditional UPDATE rather than
     * a read followed by a write — a replayed callback must lose the race, not
     * be honoured twice.
     *
     * @param array<string,string> $params
     */
    public function callback(array $params): string
    {
        $key = (string) ($params['provider'] ?? '');
        $provider = CalendarSync::configured()[$key] ?? null;

        if ($provider === null) {
            throw new HttpException(404, 'Not found.');
        }

        $db = Database::conn();

        // A declined consent screen is an ordinary outcome, not an error. But
        // we still need the state to know where to send them back to.
        $state = (string) ($_GET['state'] ?? '');
        $code = (string) ($_GET['code'] ?? '');

        $stmt = $db->prepare(
            "SELECT s.*, t.slug
             FROM pl_oauth_states s
             JOIN pl_tenants t ON t.id = s.tenant_id
             WHERE s.state = :state AND s.provider = :provider
               AND s.used_at IS NULL AND s.expires_at > NOW()"
        );
        $stmt->execute(['state' => $state, 'provider' => $key]);
        $stored = $stmt->fetch();

        // No usable state means we cannot even say which firm this was for, so
        // there is nowhere sensible to send them. A plain page is the honest
        // answer — and it deliberately does not distinguish "expired" from
        // "never existed".
        if ($stored === false) {
            throw new HttpException(400, 'That connection attempt has expired or was already used. Start again from your calendar settings.');
        }

        $slug = (string) $stored['slug'];
        $home = tenant_url('/calendar', $slug);

        if (isset($_GET['error']) || $code === '') {
            $this->consume($db, (int) $stored['id']);

            header('Location: ' . $home . '?error=' . rawurlencode('Connection cancelled.'), true, 303);
            exit;
        }

        if (!$this->consume($db, (int) $stored['id'])) {
            header('Location: ' . $home . '?error=' . rawurlencode('That connection attempt was already used.'), true, 303);
            exit;
        }

        // The exchange must present the SAME redirect_uri the authorize request
        // used — which is the apex one, not the tenant's. That is the reason
        // this whole exchange happens here rather than after bouncing.
        $tokens = $provider->exchangeCode(
            $code,
            $this->redirectUri($key),
            $stored['verifier'] === null ? null : (string) $stored['verifier']
        );

        if ($tokens === null || $tokens['access_token'] === '') {
            header('Location: ' . $home . '?error=' . rawurlencode('The calendar service refused the connection. Try again.'), true, 303);
            exit;
        }

        $email = $provider->accountEmail($tokens['access_token']);

        CalendarSync::connect(
            (int) $stored['tenant_id'],
            (int) $stored['user_id'],
            $key,
            $tokens,
            $email
        );

        Audit::record('calendar.connected', (int) $stored['tenant_id'],
            ['id' => (int) $stored['user_id'], 'name' => null],
            'user', (int) $stored['user_id'],
            ['provider' => $key, 'account' => $email], ClientIp::resolve($_SERVER));

        // Home to their own subdomain, where their session lives.
        header('Location: ' . $home . '?connected=' . rawurlencode($provider->label()), true, 303);
        exit;
    }

    /** Single-use, decided by the UPDATE rather than by a prior read. */
    private function consume(\PDO $db, int $stateId): bool
    {
        $stmt = $db->prepare('UPDATE pl_oauth_states SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
        $stmt->execute(['id' => $stateId]);

        return $stmt->rowCount() === 1;
    }

    public function disconnect(): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $key = (string) ($_POST['provider'] ?? '');

        if (CalendarSync::disconnect((int) $tenant['id'], (int) $user['id'], $key)) {
            Audit::record('calendar.disconnected', (int) $tenant['id'], $user, 'user', (int) $user['id'],
                ['provider' => $key], ClientIp::resolve($_SERVER));
        }

        redirect(url('/calendar'));
    }

    /** Sync now, rather than waiting for the tick. */
    public function syncNow(): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $result = CalendarSync::run((int) $tenant['id']);

        redirect(url('/calendar?connected=' . rawurlencode(
            $result['pushed'] . ' sent, ' . $result['pulled'] . ' brought back'
        )));
    }

    // ------------------------------------------------------------- internals

    /**
     * The redirect URI. ONE of them, at the apex, for every firm.
     *
     * Providers match this character for character, and every firm lives on its
     * own subdomain — so the obvious implementation needs one registered URI per
     * customer, which is bookkeeping at ten firms and impossible at a thousand.
     *
     * So the callback lands on the bare domain instead. It has no tenant in
     * scope, and does not need one: the `state` nonce was minted against a
     * tenant and a user, and looking it up recovers both. The user is bounced to
     * their own subdomain once the connection is stored.
     *
     * This is why `state` is a database row rather than a session value. A
     * session cookie scoped to `acme.pilotagehq.com` is not readable at
     * `pilotagehq.com`, so a cookie-based state would fail precisely here.
     */
    private function redirectUri(string $provider): string
    {
        return app_url('/calendar/callback/' . $provider);
    }

    /** @return array{0:array,1:array} */
    private function context(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'No tenant in scope.');
        }

        $user = Session::user();

        if ($user === null) {
            redirect(url('/login?redirect=' . rawurlencode(pl_request_path())));
        }

        if (($user['client_org_id'] ?? null) !== null) {
            throw new HttpException(404, 'Not found.');
        }

        return [$tenant, $user];
    }
}
