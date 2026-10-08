<?php

namespace Bizorca\Consulting\Auth;

/**
 * Foundry's view of the shared tools session.
 *
 * Identity is the shared account (tl_user()); Foundry keeps only its own keys
 * in the session, prefixed fd_ because every tool shares one session. Lazy
 * like the rest of the site: reading never creates a session, so anonymous
 * visitors to the landing page get no cookie. Writing a flash does.
 */
class Session
{
    public static function start(): void
    {
        tl_session();   // resume only
    }

    /**
     * The signed-in user in the shape Foundry's views expect, or null.
     * The shared account has one name; Foundry's views print first and last,
     * so it is split at the first space ("first last" prints as before).
     */
    public static function user(): ?array
    {
        $u = tl_user();
        if (!$u) {
            return null;
        }
        [$first, $last] = array_pad(explode(' ', trim((string) $u['name']), 2), 2, '');
        return [
            'id'         => (int) $u['id'],
            'email'      => $u['email'],
            'name'       => $u['name'],
            'first_name' => $first,
            'last_name'  => $last,
            'is_admin'   => fd_user_is_admin((int) $u['id'], (bool) $u['is_admin']),
        ];
    }

    public static function logout(): void
    {
        tl_logout();
    }

    public static function flash(string $key, mixed $value): void
    {
        tl_session(true);
        $_SESSION['fd_flash'][$key] = $value;
    }

    public static function getFlash(string $key): mixed
    {
        if (!tl_session()) {
            return null;
        }
        $value = $_SESSION['fd_flash'][$key] ?? null;
        unset($_SESSION['fd_flash'][$key]);
        return $value;
    }
}
