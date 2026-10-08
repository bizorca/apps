<?php

namespace Bizorca\Pod\Auth;

use Bizorca\Pod\Core\Database;

/**
 * Pod's view of the shared tools account and session.
 *
 * The session is the site-wide one (cookie `tools_session`), so every key Pod
 * stores is prefixed pd_, and it is lazy: reading never creates one. The
 * original cached the whole user row in its own session at SSO sign-in, so a
 * promotion, demotion or "disable access" did nothing until the person signed
 * in again (and a disabled member kept their 7-day session). Here the row is
 * read live on every request, from pd_users (shared account + pd_profiles).
 */
class Session
{
    private const PREFIX = 'pd_';
    private static ?array $user = null;

    /**
     * The signed-in Pod member, or null. Creates the pd_profiles row on a
     * signed-in account's first visit (Pod was open to every Bizorca account).
     */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $account = tl_user();
        if (!$account) {
            return null;
        }
        Database::query('INSERT IGNORE INTO pd_profiles (user_id) VALUES (?)', [$account['id']]);
        return self::$user = Database::fetchOne('SELECT * FROM pd_users WHERE id = ?', [$account['id']]);
    }

    public static function isLoggedIn(): bool
    {
        return self::user() !== null;
    }

    /**
     * Staff see every ticket and answer as staff. Admins count as staff: in the
     * original they did not, so an admin who was not also flagged staff (both
     * live admins) got "Ticket not found" on every ticket linked from their own
     * dashboard, and could not be made staff either (toggleStaff skips admins).
     */
    public static function isStaff(): bool
    {
        $u = self::user();
        return $u !== null && (!empty($u['is_staff']) || !empty($u['is_admin']));
    }

    public static function isAdmin(): bool
    {
        return !empty(self::user()['is_admin']);
    }

    public static function flash(string $key, mixed $value): void
    {
        tl_session(true);
        $_SESSION[self::PREFIX . 'flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        if (!tl_session()) {
            return $default;
        }
        $val = $_SESSION[self::PREFIX . 'flash'][$key] ?? $default;
        unset($_SESSION[self::PREFIX . 'flash'][$key]);
        return $val;
    }
}
