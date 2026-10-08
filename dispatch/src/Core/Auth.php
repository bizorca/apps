<?php

declare(strict_types=1);

namespace Dispatch\Core;

use Dispatch\Models\User;

/**
 * Dispatch auth on top of the shared tools account.
 *
 * Who you are comes from tl_user() (sign-in, registration and passwords live
 * at /account/). What you may do in Dispatch comes from dp_profiles.role:
 *
 *   user   standard
 *   admin  venue library + submission triage
 *   sysop  everything, including role changes and venue deletion
 *
 * A site admin (users.is_admin, the owner of tools.bizorca.com) is always a
 * Dispatch sysop. A signed-in account that has never opened Dispatch gets a
 * dp_profiles row on its first authenticated request (role user, daily digest,
 * 3-day window: the old registration defaults).
 */
class Auth
{
    /** Shared users row merged with the Dispatch profile, cached per request. */
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function userId(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['admin', 'sysop'], true);
    }

    public static function isSysOp(): bool
    {
        return self::role() === 'sysop';
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $shared = tl_user();
        if (!$shared) {
            return self::$user = null;
        }

        $user = User::findById((int) $shared['id']) ?? User::ensureProfile((int) $shared['id']);
        if ((int) $shared['is_admin'] === 1) {
            $user['role'] = 'sysop';
        }
        return self::$user = $user;
    }

    /** Send a signed-out visitor to the shared sign-in, and back here after. */
    public static function requireAuth(): void
    {
        if (!self::check()) {
            $next = Url::to(Url::path());
            header('Location: /account/login.php?next=' . rawurlencode($next));
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (!self::isAdmin()) {
            Response::forbidden();
        }
    }

    public static function requireSysOp(): void
    {
        self::requireAuth();
        if (!self::isSysOp()) {
            Response::forbidden();
        }
    }

    public static function generateToken(int $length = 64): string
    {
        return bin2hex(random_bytes(intdiv($length, 2)));
    }

    /** Creates a session; only reached on pages that show a form. */
    public static function csrfToken(): string
    {
        if (!Session::has('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return Session::get('csrf_token');
    }

    public static function verifyCsrf(): bool
    {
        $token = $_POST['_csrf'] ?? '';
        return is_string($token) && hash_equals((string) Session::get('csrf_token', ''), $token);
    }
}
