<?php

declare(strict_types=1);

namespace Dispatch\Core;

/**
 * Dispatch's view of the shared tools session.
 *
 * The session is the site-wide one (cookie `tools_session`, configured by the
 * shared bootstrap), so every key Dispatch stores is prefixed dp_. It is also
 * lazy: reading never creates a session, so an anonymous visitor to the
 * marketing pages gets no cookie. Only writing (flash, CSRF) opens one.
 */
class Session
{
    private const PREFIX = 'dp_';

    /** Resume an existing session if the visitor has one. Never creates. */
    public static function start(): void
    {
        tl_session();
    }

    public static function set(string $key, mixed $value): void
    {
        tl_session(true);
        $_SESSION[self::PREFIX . $key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!tl_session()) {
            return $default;
        }
        return $_SESSION[self::PREFIX . $key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return tl_session() && isset($_SESSION[self::PREFIX . $key]);
    }

    public static function remove(string $key): void
    {
        if (tl_session()) {
            unset($_SESSION[self::PREFIX . $key]);
        }
    }

    public static function setFlash(string $type, string $message): void
    {
        tl_session(true);
        $_SESSION[self::PREFIX . 'flash'][$type] = $message;
    }

    public static function getFlash(string $type): ?string
    {
        if (!tl_session()) {
            return null;
        }
        $message = $_SESSION[self::PREFIX . 'flash'][$type] ?? null;
        unset($_SESSION[self::PREFIX . 'flash'][$type]);
        return $message;
    }

    public static function getAndClearFlash(): array
    {
        if (!tl_session()) {
            return [];
        }
        $flash = $_SESSION[self::PREFIX . 'flash'] ?? [];
        unset($_SESSION[self::PREFIX . 'flash']);
        return $flash;
    }
}
