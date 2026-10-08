<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

/**
 * TOTP (RFC 6238) over HMAC-SHA1, 6 digits, 30-second steps.
 *
 * Implemented directly rather than pulled in as a dependency — the algorithm
 * is about sixty lines and Pilotage ships with zero Composer packages.
 *
 * Compatible with Google Authenticator, 1Password, Authy, and anything else
 * that speaks otpauth://.
 */
final class TwoFactor
{
    public const DIGITS = 6;
    public const PERIOD = 30;

    /** How many steps either side of now to accept, for clock drift. */
    public const WINDOW = 1;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A fresh base32 secret. 20 bytes = 160 bits, the RFC 4226 recommendation. */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * The counter (time step) for a given timestamp. Exposed so callers can
     * store the last-used counter and refuse a replay of the same code.
     */
    public static function counterAt(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::PERIOD);
    }

    /** The 6-digit code for a given counter. */
    public static function codeForCounter(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);

        if ($key === '') {
            throw new \InvalidArgumentException('TOTP secret is not valid base32.');
        }

        // 8-byte big-endian counter.
        $binCounter = pack('J', $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);

        // Dynamic truncation, RFC 4226 §5.4.
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
               | ((ord($hash[$offset + 1]) & 0xFF) << 16)
               | ((ord($hash[$offset + 2]) & 0xFF) << 8)
               |  (ord($hash[$offset + 3]) & 0xFF);

        $code = $value % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** The code for right now. Mostly useful in tests and for QR previews. */
    public static function codeNow(string $secret, ?int $timestamp = null): string
    {
        return self::codeForCounter($secret, self::counterAt($timestamp));
    }

    /**
     * Verify a presented code.
     *
     * @param int|null $lastUsedCounter The counter of the last code this user
     *        successfully presented. Codes at or before it are refused, so a
     *        code shoulder-surfed inside its 30-second window cannot be reused.
     * @return int|null The matched counter on success, null on failure. Store
     *         the returned counter as the new lastUsedCounter.
     */
    public static function verify(
        string $secret,
        string $presented,
        ?int $lastUsedCounter = null,
        ?int $timestamp = null
    ): ?int {
        $presented = preg_replace('/\s+/', '', $presented) ?? '';

        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $presented)) {
            return null;
        }

        $current = self::counterAt($timestamp);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            $counter = $current + $offset;

            if ($counter < 0) {
                continue;
            }
            if ($lastUsedCounter !== null && $counter <= $lastUsedCounter) {
                continue; // replay
            }

            if (hash_equals(self::codeForCounter($secret, $counter), $presented)) {
                return $counter;
            }
        }

        return null;
    }

    /**
     * otpauth:// URI for QR rendering.
     *
     * $issuer appears as the account label in the authenticator app, so it
     * should be the tenant's firm name, not "Pilotage" — a coach with several
     * client firms needs to tell the entries apart.
     */
    public static function provisioningUri(string $secret, string $accountEmail, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountEmail);

        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . $label . '?' . $params;
    }

    /**
     * Recovery codes, for when the phone is lost. Returned in plaintext once;
     * only hashes are stored.
     *
     * @return string[]
     */
    public static function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // Grouped for legibility when someone writes them on paper.
            $codes[] = strtolower(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        }
        return $codes;
    }

    // ------------------------------------------------------------- base32

    public static function base32Encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= self::BASE32[bindec($chunk)];
        }

        // No '=' padding: authenticator apps universally accept unpadded, and
        // padding only creates copy/paste mistakes.
        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[\s=]+/', '', $secret) ?? '');

        if ($secret === '' || strspn($secret, self::BASE32) !== strlen($secret)) {
            return '';
        }

        $bits = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $index = strpos(self::BASE32, $secret[$i]);
            $bits .= str_pad(decbin((int) $index), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break; // trailing partial byte from unpadded input
            }
            $out .= chr(bindec($chunk));
        }

        return $out;
    }
}
