<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

/**
 * Password hashing. bcrypt only (house rule).
 */
final class Password
{
    /**
     * bcrypt silently truncates at 72 bytes, which turns a long passphrase
     * into a shorter one without telling anyone. Reject rather than truncate.
     */
    public const MAX_BYTES = 72;
    public const MIN_LENGTH = 12;

    private const COST = 12;

    public static function hash(string $plain): string
    {
        self::assertAcceptable($plain);

        $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => self::COST]);

        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    /**
     * Verify a password. Returns false for users with no password set (magic-link
     * only accounts) rather than throwing — a null hash is a normal state here.
     */
    public static function verify(string $plain, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            // Spend the time anyway so "no password set" and "wrong password"
            // are not distinguishable by response time.
            password_verify($plain, '$2y$12$usesomesillystringforsaltusesomesillystringfore');
            return false;
        }

        return password_verify($plain, $hash);
    }

    /** True when the stored hash should be re-hashed at the current cost. */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    /** @return string[] Human-readable problems; empty means acceptable. */
    public static function problems(string $plain): array
    {
        $problems = [];

        if (strlen($plain) > self::MAX_BYTES) {
            $problems[] = 'Password must be ' . self::MAX_BYTES . ' bytes or fewer.';
        }
        if (mb_strlen($plain) < self::MIN_LENGTH) {
            $problems[] = 'Password must be at least ' . self::MIN_LENGTH . ' characters.';
        }
        if (trim($plain) === '') {
            $problems[] = 'Password cannot be blank.';
        }

        return $problems;
    }

    private static function assertAcceptable(string $plain): void
    {
        $problems = self::problems($plain);
        if ($problems !== []) {
            throw new \InvalidArgumentException(implode(' ', $problems));
        }
    }
}
