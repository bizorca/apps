<?php

declare(strict_types=1);

namespace TimeBank\Core;

/**
 * TimeBank auth on top of the shared tools account.
 *
 * Who you are comes from tl_user() (sign-in, registration and passwords live
 * at /account/). Whether you belong to THIS community is a tm_members row for
 * (tenant, user). One person can belong to several communities; each
 * membership has its own profile, balance and role (member | admin |
 * super_admin), exactly like the original's per-community member rows.
 *
 * A site admin (users.is_admin: the owner of tools.bizorca.com) acts as a
 * community admin everywhere they are a member.
 *
 * The original resolved the member from a session id alone and never checked
 * it against the community in the URL, so a member of one timebank was
 * "signed in" to every other timebank. Here the membership is looked up for
 * the current community on every request.
 */
class Auth
{
    private static ?array $member = null;
    private static bool $loaded = false;

    /** The shared tools account, or null. */
    public static function account(): ?array
    {
        return tl_user();
    }

    /** This community's membership row for the signed-in account (any status), or null. */
    public static function membership(): ?array
    {
        if (self::$loaded) {
            return self::$member;
        }
        self::$loaded = true;

        $account  = tl_user();
        $tenantId = Tenant::id();
        if (!$account || $tenantId === null) {
            return self::$member = null;
        }

        $row = DB::fetch(
            'SELECT * FROM `tm_members` WHERE tenant_id = ? AND user_id = ? LIMIT 1',
            [$tenantId, (int) $account['id']]
        );
        if (!$row) {
            return self::$member = null;
        }

        // The email on a membership mirrors the shared account (account email
        // cannot be changed in /account/settings, but keep them honest anyway).
        if ($row['email'] !== $account['email']) {
            DB::update('tm_members', ['email' => $account['email']], ['id' => (int) $row['id']]);
            $row['email'] = $account['email'];
        }

        return self::$member = $row;
    }

    /** The usable membership (active and approved), or null. Views call this. */
    public static function user(): ?array
    {
        $m = self::membership();
        return ($m && (int) $m['is_active'] === 1 && (int) $m['is_approved'] === 1) ? $m : null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function isLoggedIn(): bool
    {
        return self::user() !== null;
    }

    public static function check(): bool
    {
        return self::isLoggedIn();
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        return in_array($u['role'], ['admin', 'super_admin'], true) || (int) (tl_user()['is_admin'] ?? 0) === 1;
    }

    public static function isSuperAdmin(): bool
    {
        $u = self::user();
        return $u !== null && ($u['role'] === 'super_admin' || (int) (tl_user()['is_admin'] ?? 0) === 1);
    }

    /**
     * Signed out -> shared sign-in, back here after.
     * Signed in, not a member -> this community's join page.
     * Member, but pending approval or deactivated -> say so.
     */
    public static function require(): void
    {
        if (!tl_user()) {
            header('Location: /account/login.php?next=' . rawurlencode(url(Url::tenantPath()) . self::queryTail()));
            exit;
        }

        $m = self::membership();
        if (!$m) {
            Response::redirect('/join');
        }
        if ((int) $m['is_active'] !== 1) {
            View::render('membership/status', ['state' => 'inactive', 'pageTitle' => 'Membership inactive']);
            exit;
        }
        if ((int) $m['is_approved'] !== 1) {
            View::render('membership/status', ['state' => 'pending', 'pageTitle' => 'Awaiting approval']);
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        static::require();
        if (!static::isAdmin()) {
            Flash::set('error', 'You do not have permission to access that area.');
            Response::redirect('/dashboard');
        }
    }

    /** Drop the per-request cache after a membership is created or changed. */
    public static function forget(): void
    {
        self::$member = null;
        self::$loaded = false;
    }

    /** The current request's own query (minus r), to come back to after sign-in. */
    private static function queryTail(): string
    {
        $q = $_GET;
        unset($q['r']);
        return $q ? ('?' . http_build_query($q)) : '';
    }
}
