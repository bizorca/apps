<?php

namespace Anglerfish\Core;

final class Session
{
    /**
     * The shared tools session (cookie tools_session, configured in the core's
     * bootstrap.php). Anglerfish's own keys are af_-prefixed inside it, since
     * every tool on the site shares the one session.
     */
    public static function start(): void
    {
        tl_session(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION['af_' . $key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION['af_' . $key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION['af_' . $key]);
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['af_csrf'])) {
            $_SESSION['af_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['af_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['af_csrf'])
            && hash_equals($_SESSION['af_csrf'], $token);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['af_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        $f = $_SESSION['af_flash'] ?? [];
        unset($_SESSION['af_flash']);
        return $f;
    }
}
