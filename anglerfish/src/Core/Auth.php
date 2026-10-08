<?php

namespace Anglerfish\Core;

/**
 * Single-operator gate. Bizorca SSO is gone: Anglerfish now lives on
 * tools.bizorca.com and every page requires a site admin on the shared tools
 * account (users.is_admin). public/index.php applies the gate before routing;
 * Router calls require() again per route, which is a cheap no-op by then.
 */
final class Auth
{
    public static function user(): ?array
    {
        return tl_user();
    }

    public static function check(): bool
    {
        $u = tl_user();
        return $u !== null && !empty($u['is_admin']);
    }

    /** Sign-in redirect for strangers, 403 for signed-in non-admins. */
    public static function require(): void
    {
        tl_require_admin();
    }
}
