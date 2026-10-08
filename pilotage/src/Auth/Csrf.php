<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

/**
 * CSRF tokens. Required on every state-changing POST (§6).
 *
 * Per-session token rather than per-form: simpler, and with SameSite=Lax
 * cookies plus an Origin check it is enough. Rotated on privilege change
 * (login, logout, 2FA confirmation) so a token captured before authentication
 * is useless after it.
 */
final class Csrf
{
    /** Where the token lives in the (shared) session. pl_-prefixed: every tool shares it. */
    private const KEY = 'pl_csrf';

    /** The form field name, unchanged from before the port. */
    private const FIELD = '_csrf';

    public static function token(): string
    {
        // The session is lazy on tools.bizorca.com: a page that renders a form
        // is the moment one is created, not every page view.
        if (session_status() !== PHP_SESSION_ACTIVE && function_exists('tl_session')) {
            tl_session(true);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('CSRF token requested with no active session.');
        }

        if (!isset($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY]) || $_SESSION[self::KEY] === '') {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    /** Ready-to-echo hidden input. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . h(self::token()) . '">';
    }

    public static function verify(?string $presented): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if (!isset($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            return false;
        }
        if ($presented === null || $presented === '') {
            return false;
        }

        return hash_equals($_SESSION[self::KEY], $presented);
    }

    /** Verify the request, or fail closed. */
    public static function check(array $post): void
    {
        $token = isset($post[self::FIELD]) && is_string($post[self::FIELD]) ? $post[self::FIELD] : null;

        if (!self::verify($token)) {
            throw new \Bizorca\Pilotage\Core\HttpException(419, 'CSRF token missing or invalid.');
        }
    }

    /**
     * Belt and braces alongside SameSite: reject cross-origin POSTs outright.
     * A tenant may only be posted to from its own host.
     */
    public static function checkOrigin(array $server, string $expectedHost): void
    {
        $origin = $server['HTTP_ORIGIN'] ?? null;

        if ($origin === null) {
            $referer = $server['HTTP_REFERER'] ?? null;
            if ($referer === null) {
                return; // no signal to check; SameSite is carrying this one
            }
            $origin = $referer;
        }

        $host = parse_url((string) $origin, PHP_URL_HOST);

        if ($host === null || $host === false || strcasecmp((string) $host, $expectedHost) !== 0) {
            throw new \Bizorca\Pilotage\Core\HttpException(403, 'Cross-origin request refused.');
        }
    }

    /** Rotate on any privilege change. */
    public static function rotate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
    }
}
