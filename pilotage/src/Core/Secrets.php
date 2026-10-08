<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Encryption at rest for third-party credentials.
 *
 * Everything else secret in this codebase is HASHED — passwords, token
 * verifiers, recovery codes — because nothing ever needs to read them back.
 * OAuth refresh tokens are the exception: they have to be presented to Google
 * or Microsoft verbatim, so they must be recoverable, which means encrypted
 * rather than hashed.
 *
 * That distinction is worth being explicit about, because reaching for this
 * class when a hash would do is a downgrade. If you never need the plaintext
 * back, use Token::hash(). This is for the narrow case where you do.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS MATTERS MORE THAN IT LOOKS
 *
 * A Google refresh token is a bearer credential for someone's entire calendar,
 * valid until revoked. A database leak that hands over a table of them is
 * materially worse than one that hands over password hashes, because hashes
 * have to be cracked and these do not. The key lives in the environment, not
 * the database, so the two have to be stolen separately.
 * ---------------------------------------------------------------------------
 *
 * libsodium's secretbox: XSalsa20-Poly1305, authenticated, with a random nonce
 * per message stored alongside the ciphertext. Authenticated matters — an
 * attacker who can write to the column must not be able to flip bits in a
 * token and have us present the result to Google. Bundled with PHP since 7.2,
 * so this costs no dependency.
 */
final class Secrets
{
    /** Marks our format, so a rotation to something else is detectable. */
    private const PREFIX = 'ptg1:';

    /**
     * Is a key configured?
     *
     * Callers check this rather than catching. Calendar sync simply does not
     * offer itself when there is nowhere safe to put the tokens, which is a
     * better failure than storing them in the clear and hoping.
     */
    public static function available(): bool
    {
        return self::key(false) !== null;
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::key(true);

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, (string) $key);

        // Nonce first, then ciphertext. The nonce is not secret; reusing one
        // would be catastrophic, which is why it is random per message rather
        // than a counter we would have to store and increment correctly.
        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    /**
     * @return string|null Null when the value is unreadable — wrong key,
     *         tampered ciphertext, or a format we no longer speak. Callers
     *         treat that as "the connection is broken, ask them to reconnect",
     *         which is the honest response and does not throw on a page load.
     */
    public static function decrypt(string $stored): ?string
    {
        $key = self::key(false);

        if ($key === null || !str_starts_with($stored, self::PREFIX)) {
            return null;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        // False means the authentication tag did not verify: either the key is
        // wrong or someone changed the bytes. Both are "do not use this".
        return $plain === false ? null : $plain;
    }

    /** Generate a key for the .env. Run once, per installation. */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    private static function key(bool $required): ?string
    {
        $configured = trim((string) Config::get('app.secret_key', ''));

        if ($configured === '') {
            if ($required) {
                throw new \RuntimeException(
                    'APP_SECRET_KEY is not set, so third-party tokens cannot be stored safely. '
                    . 'Generate one with: php -r \'echo base64_encode(random_bytes(32));\''
                );
            }

            return null;
        }

        $key = base64_decode($configured, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            if ($required) {
                throw new \RuntimeException(
                    'APP_SECRET_KEY must be 32 random bytes, base64 encoded.'
                );
            }

            return null;
        }

        return $key;
    }
}
