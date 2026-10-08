<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

use Bizorca\Pilotage\Core\Database;

/**
 * Rate limiting for authentication endpoints (§6).
 *
 * Deliberately NOT tenant-scoped. An attacker who can enumerate subdomains
 * must not get a fresh attempt budget for each one, so this is the rare table
 * that lives outside the Repository scoping rule — and it holds no tenant
 * data, only counters.
 *
 * Two independent limits per action: one keyed on the identifier (an email,
 * usually) and one on the source IP. The identifier limit stops a targeted
 * attack on one account; the IP limit stops a spray across many.
 */
final class RateLimiter
{
    /** action => [maxPerIdentifier, maxPerIp, windowSeconds] */
    private const LIMITS = [
        'login'          => [5,  30, 900],   // 5 per email / 30 per IP per 15 min
        'magic_link'     => [3,  20, 900],   // issuing links is an email-send; keep it tight
        'invite_accept'  => [10, 40, 3600],
        'totp'           => [6,  40, 900],
        'password_reset' => [3,  20, 3600],
        // Public, unauthenticated. Generous per submitter (a genuine person may
        // retry after a validation error) but tight per hour, since the whole
        // surface is a spam magnet.
        'intake'         => [5,  10, 3600],
        // Self-serve firm creation. Unauthenticated and it mints a subdomain,
        // so the IP ceiling is the one that matters — the identifier here is
        // also the IP, because a would-be squatter supplies a fresh email
        // every time and an email-keyed limit would never fire.
        'signup'         => [5,  10, 3600],
    ];

    public static function tooManyAttempts(string $action, string $identifier, ?string $ip): bool
    {
        [$maxIdentifier, $maxIp, $window] = self::limitsFor($action);

        $db = Database::conn();

        // Window arithmetic stays in SQL so it uses the same clock that wrote
        // the row. Doing it in PHP is how this check silently stopped working.
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS c FROM pl_auth_attempts
             WHERE action = :a AND identifier = :i AND successful = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL :secs SECOND)'
        );
        $stmt->execute(['a' => $action, 'i' => self::normalize($identifier), 'secs' => $window]);

        if ((int) $stmt->fetch()['c'] >= $maxIdentifier) {
            return true;
        }

        if ($ip !== null && $ip !== '') {
            $packed = self::packIp($ip);
            if ($packed !== null) {
                $stmt = $db->prepare(
                    'SELECT COUNT(*) AS c FROM pl_auth_attempts
                     WHERE action = :a AND ip = :ip AND successful = 0
                       AND created_at >= DATE_SUB(NOW(), INTERVAL :secs SECOND)'
                );
                $stmt->execute(['a' => $action, 'ip' => $packed, 'secs' => $window]);

                if ((int) $stmt->fetch()['c'] >= $maxIp) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function record(string $action, string $identifier, ?string $ip, bool $successful): void
    {
        $stmt = Database::conn()->prepare(
            'INSERT INTO pl_auth_attempts (action, identifier, ip, successful)
             VALUES (:a, :i, :ip, :s)'
        );

        $stmt->execute([
            'a'  => $action,
            'i'  => self::normalize($identifier),
            'ip' => $ip === null ? null : self::packIp($ip),
            's'  => $successful ? 1 : 0,
        ]);
    }

    /**
     * Clear an identifier's failures after a success, so a user who fats
     * their password four times and then gets it right is not left one
     * mistake away from a lockout for the next quarter hour.
     */
    public static function clear(string $action, string $identifier): void
    {
        $stmt = Database::conn()->prepare(
            'DELETE FROM pl_auth_attempts WHERE action = :a AND identifier = :i AND successful = 0'
        );
        $stmt->execute(['a' => $action, 'i' => self::normalize($identifier)]);
    }

    /** Housekeeping for the cron tick. */
    public static function prune(int $olderThanSeconds = 86400): int
    {
        $stmt = Database::conn()->prepare(
            'DELETE FROM pl_auth_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL :secs SECOND)'
        );
        $stmt->execute(['secs' => $olderThanSeconds]);

        return $stmt->rowCount();
    }

    /** Seconds until the identifier limit frees up, for the "try again in..." message. */
    public static function retryAfter(string $action, string $identifier): int
    {
        [, , $window] = self::limitsFor($action);

        $stmt = Database::conn()->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()) AS elapsed
             FROM pl_auth_attempts
             WHERE action = :a AND identifier = :i AND successful = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL :secs SECOND)'
        );
        $stmt->execute(['a' => $action, 'i' => self::normalize($identifier), 'secs' => $window]);

        $elapsed = $stmt->fetch()['elapsed'] ?? null;

        if ($elapsed === null) {
            return 0;
        }

        return max(0, $window - (int) $elapsed);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function limitsFor(string $action): array
    {
        if (!isset(self::LIMITS[$action])) {
            throw new \InvalidArgumentException('Unknown rate-limited action: ' . $action);
        }
        return self::LIMITS[$action];
    }

    private static function normalize(string $identifier): string
    {
        return mb_strtolower(trim($identifier));
    }

    /** @return string|null Packed binary form, or null if the address is unparseable. */
    public static function packIp(string $ip): ?string
    {
        $packed = @inet_pton($ip);
        return $packed === false ? null : $packed;
    }
}
