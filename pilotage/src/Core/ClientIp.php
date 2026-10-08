<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * The visitor's real IP address.
 *
 * Pilotage sits behind Cloudflare, so `REMOTE_ADDR` is a Cloudflare edge
 * address, not the visitor's. RateLimiter keys its per-IP budgets on this;
 * without the correction those budgets collapse into a handful of addresses
 * and stop discriminating between an attacker and everyone else. That is a
 * silent degradation — nothing errors, the protection simply is not there.
 *
 * `CF-Connecting-IP` is only trusted when the request actually arrived from a
 * Cloudflare address. Trusting the header unconditionally would be worse than
 * not reading it at all: anyone could then set it to whatever they liked and
 * sidestep rate limiting entirely by rotating a string.
 */
final class ClientIp
{
    /**
     * Cloudflare's published ranges (https://www.cloudflare.com/ips/).
     * Refresh occasionally; a stale list fails CLOSED — we fall back to
     * REMOTE_ADDR, which is merely the old behaviour, not a hole.
     */
    private const CLOUDFLARE_V4 = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    ];

    private const CLOUDFLARE_V6 = [
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /** @param array<string,mixed> $server Usually $_SERVER. */
    public static function resolve(array $server): ?string
    {
        $remote = $server['REMOTE_ADDR'] ?? null;
        $remote = is_string($remote) && $remote !== '' ? $remote : null;

        $forwarded = $server['HTTP_CF_CONNECTING_IP'] ?? null;

        if (!is_string($forwarded) || $forwarded === '' || $remote === null) {
            return $remote;
        }

        if (!self::isCloudflare($remote)) {
            // The header is present but the request did not come from
            // Cloudflare. Somebody is spoofing; ignore it.
            return $remote;
        }

        return filter_var($forwarded, FILTER_VALIDATE_IP) === false ? $remote : $forwarded;
    }

    public static function isCloudflare(string $ip): bool
    {
        $ranges = str_contains($ip, ':') ? self::CLOUDFLARE_V6 : self::CLOUDFLARE_V4;

        foreach ($ranges as $cidr) {
            if (self::inRange($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public static function inRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton((string) $subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = chr(0xFF << (8 - $remainder) & 0xFF);

        return (($ipBin[$bytes] ?? "\0") & $mask) === (($subnetBin[$bytes] ?? "\0") & $mask);
    }
}
