<?php

declare(strict_types=1);

namespace Dispatch\Core;

/**
 * Every Dispatch URL is built and read here, so the query-string routing this
 * server needs today can become clean URLs with one constant (DP_CLEAN_URLS
 * in includes/config.php).
 *
 *   query mode   Url::to('/campaigns/5?x=1')  ->  /dispatch/?r=/campaigns/5?x=1
 *   clean mode   Url::to('/campaigns/5?x=1')  ->  /dispatch/campaigns/5?x=1
 *
 * In query mode a path's own "?a=b" rides inside r and path() folds it back
 * into $_GET, so views can keep writing "<?= $_base ?>/calendar?month=..."
 * exactly as before.
 */
final class Url
{
    private static ?string $path = null;

    /** What views get as $_base: prepend it to an app path. */
    public static function prefix(): string
    {
        return DP_CLEAN_URLS ? DP_BASE : DP_BASE . '/?r=';
    }

    public static function to(string $path): string
    {
        return self::prefix() . $path;
    }

    /** Absolute, for email. */
    public static function absolute(string $path): string
    {
        return DP_ORIGIN . self::to($path);
    }

    /** The app path of this request ('/campaigns/5'), without any query. */
    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }

        if (DP_CLEAN_URLS) {
            $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (str_starts_with($uri, DP_BASE)) {
                $uri = substr($uri, strlen(DP_BASE));
            }
        } else {
            $uri = is_string($_GET['r'] ?? null) ? $_GET['r'] : '/';
            $q = strpos($uri, '?');
            if ($q !== false) {
                // "?r=/x?month=2026-10&page=2": PHP put page in $_GET but
                // month inside r. Recover it; a real top-level key wins.
                parse_str(substr($uri, $q + 1), $inner);
                $_GET += $inner;
                $uri = substr($uri, 0, $q);
            }
        }

        $uri = '/' . trim($uri, '/');
        return self::$path = $uri;
    }
}
