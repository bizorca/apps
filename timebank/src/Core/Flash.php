<?php

declare(strict_types=1);

namespace TimeBank\Core;

class Flash
{
    private const SESSION_KEY = 'tm_flash';

    public static function set(string $type, string $message): void
    {
        tl_session(true);
        $_SESSION[self::SESSION_KEY][] = ['type' => $type, 'message' => $message];
    }

    /** Read and clear. Never starts a session for a visitor who has none. */
    public static function all(): array
    {
        if (!tl_session()) {
            return [];
        }
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);
        return $messages;
    }

    public static function has(): bool
    {
        return tl_session() && !empty($_SESSION[self::SESSION_KEY]);
    }
}
