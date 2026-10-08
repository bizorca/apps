<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Audit;
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Invitation;
use Bizorca\Pilotage\Auth\Password;
use Bizorca\Pilotage\Auth\RateLimiter;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Auth\TwoFactor;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\UserRepository;

/**
 * Getting into a firm.
 *
 * On tools.bizorca.com WHO you are is the shared Bizorca Tools account: one
 * password for every tool, signed in and out at /account. What this controller
 * decides is whether that account may act in THIS firm, and as whom:
 *
 *   1. No tools session            -> /account/login.php, then back here.
 *   2. Signed in, no active membership of this firm (pl_users row with this
 *      account_id)                 -> "not a member here".
 *   3. Member                      -> the original second step, unchanged:
 *      TOTP challenge if enrolled, forced enrolment for firm owners, then a
 *      Pilotage session (Auth\Session) bound to that account.
 *
 * Gone with the port, because the shared account does them for every tool:
 * per-firm passwords, the emailed sign-in link (MagicLink) and password reset
 * (PasswordReset). An invitation is still Pilotage's, and accepting one now
 * attaches the membership to a tools account (creating it if needed).
 */
final class AuthController
{
    /** Per-firm second-step state, inside the shared session. */
    private const PENDING_KEY = 'pl_pending_2fa';
    private const PENDING_TTL = 300; // 5 minutes to enter a code

    public function showLogin(): string
    {
        $tenant = $this->requireTenant();
        $redirect = self::safeRedirect($_GET['redirect'] ?? null);

        if (Session::user() !== null) {
            redirect(url($redirect ?? '/'));
        }

        $account = tl_user();

        if ($account === null) {
            // Come back to this same step once signed in, carrying the page
            // they were actually after.
            $back = pl_route('/f/' . $tenant['slug'] . '/login'
                . ($redirect !== null ? '?redirect=' . rawurlencode($redirect) : ''));
            redirect('/account/login.php?next=' . rawurlencode($back));
        }

        $users = new UserRepository((int) $tenant['id']);
        $user = $users->findByAccountId((int) $account['id']);

        if ($user === null || $user['status'] !== 'active') {
            return View::render('auth.login', [
                'title'        => 'Not a member here',
                'errors'       => [],
                'notice'       => null,
                'accountEmail' => (string) $account['email'],
            ]);
        }

        return $this->completeOrChallenge($user, (int) $tenant['id'], $redirect, $this->ip());
    }

    private function completeOrChallenge(array $user, int $tenantId, ?string $redirect, ?string $ip): string
    {
        $twoFactor = $this->twoFactorFor($tenantId, (int) $user['id']);
        $users = new UserRepository($tenantId);

        if ($twoFactor !== null && $twoFactor['confirmed_at'] !== null) {
            tl_session(true);
            $_SESSION[self::PENDING_KEY] = [
                'tenant_id' => $tenantId,
                'user_id'   => (int) $user['id'],
                'redirect'  => $redirect,
                'at'        => time(),
            ];
            redirect(url('/login/2fa'));
        }

        // Firm owners must enrol before they get anywhere (FR-2.2).
        if ($users->requiresTwoFactor($user)) {
            Session::start($user, $tenantId, $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);
            Audit::record(Audit::LOGIN_SUCCESS, $tenantId, $user, null, null, ['mfa' => 'enrolment_required'], $ip);
            redirect(url('/2fa/setup'));
        }

        Session::start($user, $tenantId, $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);
        Audit::record(Audit::LOGIN_SUCCESS, $tenantId, $user, null, null, ['mfa' => 'none'], $ip);
        redirect(url($redirect ?? '/'));
    }

    public function showTwoFactorChallenge(): string
    {
        $this->pendingUserId(); // 403s if there is no live pending state

        return View::render('auth.two_factor_challenge', [
            'title'  => 'Two-factor authentication',
            'errors' => [],
        ]);
    }

    public function verifyTwoFactor(): string
    {
        $tenant = $this->requireTenant();
        Csrf::check($_POST);

        $userId = $this->pendingUserId();
        $ip = $this->ip();
        $tenantId = (int) $tenant['id'];

        $users = new UserRepository($tenantId);
        $user = $users->find($userId);

        // The person typing the code must still be the tools account that
        // started this sign-in.
        $account = tl_user();
        if ($user === null || $user['status'] !== 'active'
            || $account === null || (int) ($user['account_id'] ?? 0) !== (int) $account['id']) {
            unset($_SESSION[self::PENDING_KEY]);
            throw new HttpException(403, 'Sign in again.');
        }

        $identifier = (string) $user['email'];

        if (RateLimiter::tooManyAttempts('totp', $identifier, $ip)) {
            return View::render('auth.two_factor_challenge', [
                'title'  => 'Two-factor authentication',
                'errors' => ['Too many attempts. Try again shortly.'],
            ]);
        }

        $recovery = trim((string) ($_POST['recovery_code'] ?? ''));
        $code = trim((string) ($_POST['code'] ?? ''));

        $passed = $recovery !== ''
            ? $this->consumeRecoveryCode($tenantId, $userId, $recovery)
            : $this->consumeTotp($tenantId, $userId, $code);

        if (!$passed) {
            RateLimiter::record('totp', $identifier, $ip, false);
            Audit::record(Audit::TWO_FACTOR_FAILED, $tenantId, $user, null, null, null, $ip);

            return View::render('auth.two_factor_challenge', [
                'title'  => 'Two-factor authentication',
                'errors' => ['That code is not right.'],
            ]);
        }

        RateLimiter::clear('totp', $identifier);

        $redirect = self::safeRedirect($_SESSION[self::PENDING_KEY]['redirect'] ?? null);
        unset($_SESSION[self::PENDING_KEY]);

        Session::start($user, $tenantId, $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);
        Audit::record(Audit::LOGIN_SUCCESS, $tenantId, $user, null, null, ['mfa' => $recovery !== '' ? 'recovery' : 'totp'], $ip);

        redirect(url(is_string($redirect) ? $redirect : '/'));
    }

    /**
     * Signing out of Pilotage on a shared site signs you out of the site: every
     * firm session this browser holds is revoked, then the tools account.
     */
    public function logout(): string
    {
        $tenant = Tenant::current();
        $user = Session::user();

        Csrf::check($_POST);

        if ($user !== null && $tenant !== null) {
            Audit::record(Audit::LOGOUT, (int) $tenant['id'], $user, null, null, null, $this->ip());
        }

        Session::destroy();
        tl_logout();

        redirect(app_url('/'));
    }

    public function showInvitation(array $params): string
    {
        $tenant = $this->requireTenant();
        $token = (string) ($params['token'] ?? '');

        $invitation = Invitation::lookup($token, (int) $tenant['id']);

        if ($invitation === null) {
            throw new HttpException(404, 'That invitation is no longer valid.');
        }

        return $this->renderInvitation($tenant, $invitation, $token, []);
    }

    public function acceptInvitation(array $params): string
    {
        $tenant = $this->requireTenant();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $token = (string) ($params['token'] ?? '');
        $ip = $this->ip();

        $invitation = Invitation::lookup($token, $tenantId);

        if ($invitation === null) {
            throw new HttpException(404, 'That invitation is no longer valid.');
        }

        if (RateLimiter::tooManyAttempts('invite_accept', $token, $ip)) {
            throw new HttpException(429, 'Too many attempts. Try again shortly.');
        }

        $invitedEmail = mb_strtolower((string) $invitation['email']);
        $name = trim((string) ($_POST['name'] ?? ''));
        $account = tl_user();
        $errors = [];

        if ($name === '') {
            $errors[] = 'Please enter your name.';
        }

        if ($account !== null) {
            // An invitation is for an address. Accepting it as anyone else
            // would hand that person the invitee's place in the firm.
            if (mb_strtolower((string) $account['email']) !== $invitedEmail) {
                throw new HttpException(403, 'This invitation is for a different email address.');
            }
        } else {
            if (self::accountExists($invitedEmail)) {
                // Never create a second account, never attach to one without
                // its password: sign in first (the page links there).
                return $this->renderInvitation($tenant, $invitation, $token, []);
            }

            $password = (string) ($_POST['password'] ?? '');
            $errors = array_merge($errors, Password::problems($password));
            if ($password !== (string) ($_POST['password_confirm'] ?? '')) {
                $errors[] = 'The two passwords do not match.';
            }

            if ($errors === []) {
                [$ok, $message] = tl_register($invitedEmail, $password, $name);
                if (!$ok) {
                    $errors[] = $message;
                }
                $account = tl_user();
            }
        }

        if ($errors !== [] || $account === null) {
            RateLimiter::record('invite_accept', $token, $ip, false);
            return $this->renderInvitation($tenant, $invitation, $token, $errors ?: ['Something went wrong. Try again.']);
        }

        $accepted = Invitation::accept($token, $tenantId, $name, (int) $account['id']);

        if ($accepted === null) {
            throw new HttpException(404, 'That invitation is no longer valid.');
        }

        $users = new UserRepository($tenantId);
        $user = $users->find($accepted['user_id']);

        // A firm-side invitation accepted is a seat taken, and a seat is a
        // billable quantity. Client-side contacts are never seats.
        if (($user['client_org_id'] ?? null) === null) {
            $seats = \Bizorca\Pilotage\Services\Billing::seatsInUse($tenantId);
            \Bizorca\Pilotage\Services\Billing::recordSeatChange(
                $tenantId, max(0, $seats - 1), $seats,
                (string) ($user['name'] ?? 'A colleague') . ' joined', $accepted['user_id']
            );
        }

        Audit::record(
            Audit::INVITATION_ACCEPTED,
            $tenantId,
            $user,
            'user',
            $accepted['user_id'],
            ['role' => $accepted['role']],
            $ip
        );

        Session::start($user, $tenantId, $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);

        // Firm owners land straight on enrolment.
        if ($users->requiresTwoFactor($user)) {
            redirect(url('/2fa/setup'));
        }

        redirect(url('/'));
    }

    private function renderInvitation(array $tenant, array $invitation, string $token, array $errors): string
    {
        $back = pl_route('/f/' . $tenant['slug'] . '/invite/' . $token);

        return View::render('auth.invite', [
            'title'         => 'Accept your invitation',
            'invitation'    => $invitation,
            'token'         => $token,
            'errors'        => $errors,
            'account'       => tl_user(),
            'accountExists' => self::accountExists((string) $invitation['email']),
            'signInUrl'     => '/account/login.php?next=' . rawurlencode($back),
        ]);
    }

    private static function accountExists(string $email): bool
    {
        $stmt = Database::conn()->prepare('SELECT 1 FROM users WHERE email = :e LIMIT 1');
        $stmt->execute(['e' => mb_strtolower(trim($email))]);

        return $stmt->fetch() !== false;
    }

    /**
     * A "come back to" path that is safe to hand to url(): firm-relative,
     * single leading slash, no header injection. (Was MagicLink::safeRedirect,
     * which went with the emailed links.)
     */
    public static function safeRedirect(mixed $path): ?string
    {
        if (!is_string($path) || $path === '') {
            return null;
        }
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return null;
        }

        return mb_substr($path, 0, 512);
    }

    public function showTwoFactorSetup(): string
    {
        $tenant = $this->requireTenant();
        $user = $this->requireUser();

        $users = new UserRepository((int) $tenant['id']);
        $secret = TwoFactor::generateSecret();

        return View::render('auth.two_factor_setup', [
            'title'         => 'Set up two-factor',
            'secret'        => $secret,
            'uri'           => TwoFactor::provisioningUri($secret, (string) $user['email'], (string) $tenant['name']),
            'mandatory'     => $users->requiresTwoFactor($user),
            'errors'        => [],
            'recoveryCodes' => null,
        ]);
    }

    public function confirmTwoFactorSetup(): string
    {
        $tenant = $this->requireTenant();
        $user = $this->requireUser();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $users = new UserRepository($tenantId);

        $secret = (string) ($_POST['secret'] ?? '');
        $code = (string) ($_POST['code'] ?? '');

        $counter = TwoFactor::verify($secret, $code);

        if ($counter === null) {
            return View::render('auth.two_factor_setup', [
                'title'         => 'Set up two-factor',
                'secret'        => $secret,
                'uri'           => TwoFactor::provisioningUri($secret, (string) $user['email'], (string) $tenant['name']),
                'mandatory'     => $users->requiresTwoFactor($user),
                'errors'        => ['That code is not right. Check your app and try again.'],
                'recoveryCodes' => null,
            ]);
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_user_2fa (user_id, tenant_id, secret, confirmed_at, last_used_counter)
             VALUES (:uid, :tid, :secret, NOW(), :counter)
             ON DUPLICATE KEY UPDATE secret = VALUES(secret), confirmed_at = NOW(), last_used_counter = VALUES(last_used_counter)'
        )->execute(['uid' => (int) $user['id'], 'tid' => $tenantId, 'secret' => $secret, 'counter' => $counter]);

        $codes = TwoFactor::generateRecoveryCodes();

        $db->prepare('DELETE FROM pl_2fa_recovery_codes WHERE user_id = :uid')
           ->execute(['uid' => (int) $user['id']]);

        $insert = $db->prepare(
            'INSERT INTO pl_2fa_recovery_codes (user_id, tenant_id, code_hash) VALUES (:uid, :tid, :hash)'
        );
        foreach ($codes as $recoveryCode) {
            $insert->execute([
                'uid'  => (int) $user['id'],
                'tid'  => $tenantId,
                'hash' => \Bizorca\Pilotage\Auth\Token::hash($recoveryCode),
            ]);
        }

        Audit::record(Audit::TWO_FACTOR_ENABLED, $tenantId, $user, null, null, null, $this->ip());

        return View::render('auth.two_factor_setup', [
            'title'         => 'Save your recovery codes',
            'secret'        => $secret,
            'uri'           => '',
            'mandatory'     => false,
            'errors'        => [],
            'recoveryCodes' => $codes,
        ]);
    }

    // ------------------------------------------------------------- internals

    private function consumeTotp(int $tenantId, int $userId, string $code): bool
    {
        $row = $this->twoFactorFor($tenantId, $userId);

        if ($row === null || $row['confirmed_at'] === null) {
            return false;
        }

        $counter = TwoFactor::verify(
            (string) $row['secret'],
            $code,
            $row['last_used_counter'] === null ? null : (int) $row['last_used_counter']
        );

        if ($counter === null) {
            return false;
        }

        Database::conn()->prepare(
            'UPDATE pl_user_2fa SET last_used_at = NOW(), last_used_counter = :c WHERE user_id = :uid'
        )->execute(['c' => $counter, 'uid' => $userId]);

        return true;
    }

    private function consumeRecoveryCode(int $tenantId, int $userId, string $presented): bool
    {
        $hash = \Bizorca\Pilotage\Auth\Token::hash(mb_strtolower(trim($presented)));

        $stmt = Database::conn()->prepare(
            'UPDATE pl_2fa_recovery_codes SET used_at = NOW()
             WHERE user_id = :uid AND tenant_id = :tid AND code_hash = :hash AND used_at IS NULL'
        );
        $stmt->execute(['uid' => $userId, 'tid' => $tenantId, 'hash' => $hash]);

        return $stmt->rowCount() === 1;
    }

    private function twoFactorFor(int $tenantId, int $userId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_user_2fa WHERE user_id = :uid AND tenant_id = :tid LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'tid' => $tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function pendingUserId(): int
    {
        $pending = $_SESSION[self::PENDING_KEY] ?? null;

        if (!is_array($pending) || !isset($pending['user_id'], $pending['at'], $pending['tenant_id'])) {
            throw new HttpException(403, 'Sign in first.');
        }

        // Pending state is per firm: a challenge started in one firm cannot be
        // finished in another.
        if ((int) $pending['tenant_id'] !== (int) Tenant::currentId()) {
            unset($_SESSION[self::PENDING_KEY]);
            throw new HttpException(403, 'Sign in first.');
        }

        if (time() - (int) $pending['at'] > self::PENDING_TTL) {
            unset($_SESSION[self::PENDING_KEY]);
            throw new HttpException(403, 'That took too long. Sign in again.');
        }

        return (int) $pending['user_id'];
    }

    private function requireTenant(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'No tenant in scope.');
        }

        return $tenant;
    }

    private function requireUser(): array
    {
        $user = Session::user();

        if ($user === null) {
            redirect(url('/login'));
        }

        return $user;
    }

    /**
     * The visitor's real address. Behind Cloudflare REMOTE_ADDR is an edge IP,
     * which would collapse RateLimiter's per-IP budgets into a handful of
     * values — see Core\ClientIp.
     */
    private function ip(): ?string
    {
        return \Bizorca\Pilotage\Core\ClientIp::resolve($_SERVER);
    }
}
