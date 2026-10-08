<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

/**
 * Selector/verifier tokens for magic links and invitations.
 *
 * A token handed to a user looks like:  <selector>.<verifier>
 *
 * The selector is stored in plaintext and indexed, so lookup is a single
 * indexed query with no scanning. The verifier is stored only as a SHA-256
 * hash and compared with hash_equals.
 *
 * Why not just hash the whole token? Because you would then have to either
 * scan every row comparing hashes (slow, and a timing oracle), or index the
 * hash and accept that a database leak hands an attacker working tokens. The
 * split gives an indexed lookup AND a useless-on-leak store.
 *
 * SHA-256 rather than bcrypt here is deliberate: these are 32 bytes of
 * cryptographic randomness, not user-chosen secrets, so there is nothing for
 * a slow hash to defend against and login latency stays low.
 */
final class Token
{
    public const SELECTOR_BYTES = 16;  // 32 hex chars
    public const VERIFIER_BYTES = 32;  // 64 hex chars

    /**
     * Mint a new token.
     *
     * @return array{plaintext: string, selector: string, verifier_hash: string}
     */
    public static function create(): array
    {
        $selector = bin2hex(random_bytes(self::SELECTOR_BYTES));
        $verifier = bin2hex(random_bytes(self::VERIFIER_BYTES));

        return [
            'plaintext'     => $selector . '.' . $verifier,
            'selector'      => $selector,
            'verifier_hash' => hash('sha256', $verifier),
        ];
    }

    /**
     * Split a presented token into its halves.
     *
     * @return array{0: string, 1: string}|null Null if malformed.
     */
    public static function split(string $plaintext): ?array
    {
        // Exactly one separator, both halves the right length and hex.
        if (substr_count($plaintext, '.') !== 1) {
            return null;
        }

        [$selector, $verifier] = explode('.', $plaintext, 2);

        if (strlen($selector) !== self::SELECTOR_BYTES * 2 || strlen($verifier) !== self::VERIFIER_BYTES * 2) {
            return null;
        }
        if (!ctype_xdigit($selector) || !ctype_xdigit($verifier)) {
            return null;
        }

        return [$selector, $verifier];
    }

    /** Constant-time verification of a presented verifier against a stored hash. */
    public static function verify(string $verifier, string $storedHash): bool
    {
        return hash_equals($storedHash, hash('sha256', $verifier));
    }

    /** Hash a recovery code or similar single-use secret. */
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
