<?php

declare(strict_types=1);

namespace TimeBank\Core;

class CSRF
{
    private const SESSION_KEY = 'tm_csrf';

    /** Creates a session: only called while rendering a form. */
    public static function token(): string
    {
        tl_session(true);
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $token): bool
    {
        return tl_session()
            && is_string($token)
            && !empty($_SESSION[self::SESSION_KEY])
            && hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(static::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
