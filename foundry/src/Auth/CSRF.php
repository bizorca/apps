<?php

namespace Bizorca\Consulting\Auth;

/**
 * The original's CSRF, on the shared session. token() creates a session, so
 * it is only ever called while rendering a form or checking a POST.
 */
class CSRF
{
    public static function token(): string
    {
        tl_session(true);
        if (empty($_SESSION['fd_csrf'])) {
            $_SESSION['fd_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['fd_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function valid(?string $submitted): bool
    {
        return tl_session()
            && !empty($_SESSION['fd_csrf'])
            && is_string($submitted)
            && hash_equals($_SESSION['fd_csrf'], $submitted);
    }

    public static function verify(): void
    {
        if (!self::valid($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            die('Invalid CSRF token.');
        }
    }
}
