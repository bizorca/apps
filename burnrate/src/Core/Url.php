<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Every Burn Rate URL is built and read here, so the query-string routing this
 * server needs today can become clean URLs with one constant (BR_CLEAN_URLS in
 * includes/config.php). Same scheme as Dispatch's Url.
 *
 *   query mode   Url::to('/game?tab=stocks')  ->  /burnrate/?r=/game?tab=stocks
 *   clean mode   Url::to('/game?tab=stocks')  ->  /burnrate/game?tab=stocks
 *
 * Views that append their own "?page=2" after url('/admin/owners') produce
 * "?r=/admin/owners?page=2"; path() recovers the inner query either way.
 */
final class Url
{
    private static ?string $path = null;

    public static function prefix(): string
    {
        return BR_CLEAN_URLS ? BR_BASE : BR_BASE . '/?r=';
    }

    public static function to(string $path): string
    {
        // The marketing home is the bare tool URL in both modes.
        if ($path === '/' || $path === '') {
            return BR_BASE . '/';
        }
        return self::prefix() . $path;
    }

    /** The app path of this request ('/game'), without any query. */
    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }

        if (BR_CLEAN_URLS) {
            $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (str_starts_with($uri, BR_BASE)) {
                $uri = substr($uri, strlen(BR_BASE));
            }
            if (str_ends_with($uri, '/index.php')) {
                $uri = substr($uri, 0, -strlen('/index.php'));
            }
        } else {
            $uri = is_string($_GET['r'] ?? null) ? $_GET['r'] : '/';
            $q = strpos($uri, '?');
            if ($q !== false) {
                parse_str(substr($uri, $q + 1), $inner);
                $_GET += $inner;
                $uri = substr($uri, 0, $q);
            }
        }

        return self::$path = '/' . trim($uri, '/');
    }
}
