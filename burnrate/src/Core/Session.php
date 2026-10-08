<?php

namespace App\Core;

/**
 * Burn Rate's view of the shared tools session.
 *
 * The session is the site-wide one (cookie `tools_session`, configured by the
 * shared bootstrap), so every key Burn Rate stores is prefixed br_. It is also
 * lazy: reading never creates a session, so a visitor to the marketing pages
 * gets no cookie. Only writing (flash, player choice, CSRF) opens one.
 */
class Session
{
    private const PREFIX = 'br_';

    public function __construct()
    {
        tl_session();   // resume if a cookie exists; never create
    }

    public function set(string $key, mixed $value): void
    {
        tl_session(true);
        $_SESSION[self::PREFIX . $key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!tl_session()) {
            return $default;
        }
        return $_SESSION[self::PREFIX . $key] ?? $default;
    }

    public function has(string $key): bool
    {
        return tl_session() && isset($_SESSION[self::PREFIX . $key]);
    }

    public function remove(string $key): void
    {
        if (tl_session()) {
            unset($_SESSION[self::PREFIX . $key]);
        }
    }

    public function flash(string $key, mixed $value): void
    {
        tl_session(true);
        $_SESSION[self::PREFIX . 'flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        if (!tl_session()) {
            return $default;
        }
        $value = $_SESSION[self::PREFIX . 'flash'][$key] ?? $default;
        unset($_SESSION[self::PREFIX . 'flash'][$key]);
        return $value;
    }

    public function hasFlash(string $key): bool
    {
        return tl_session() && isset($_SESSION[self::PREFIX . 'flash'][$key]);
    }

    public function csrfToken(): string
    {
        if (!$this->has('csrf_token')) {
            $this->set('csrf_token', bin2hex(random_bytes(32)));
        }
        return $this->get('csrf_token');
    }

    public function verifyCsrf(string $token): bool
    {
        return $this->has('csrf_token') && hash_equals((string) $this->get('csrf_token'), $token);
    }
}
