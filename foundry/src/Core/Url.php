<?php

declare(strict_types=1);

namespace Bizorca\Consulting\Core;

/**
 * Every Foundry URL is built and read here, so the query-string routing this
 * server needs today can become clean URLs with one constant (FD_CLEAN_URLS in
 * includes/config.php). Same design as Dispatch's Url.
 *
 *   query mode   Url::to('/admin/engagements/5')  ->  /foundry/?r=/admin/engagements/5
 *   clean mode   Url::to('/admin/engagements/5')  ->  /foundry/admin/engagements/5
 *
 * A fragment stays outside the query: Url::to('/engagements/5#card-3') keeps
 * "#card-3" as the fragment in both modes.
 */
final class Url
{
    private static ?string $path = null;

    public static function prefix(): string
    {
        return FD_CLEAN_URLS ? FD_BASE : FD_BASE . '/?r=';
    }

    public static function to(string $path): string
    {
        if ($path === '' || $path === '/') {
            return FD_BASE . '/';
        }
        return self::prefix() . $path;
    }

    /** The app path of this request ('/admin/engagements/5'), without any query. */
    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }

        if (FD_CLEAN_URLS) {
            $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (str_starts_with($uri, FD_BASE)) {
                $uri = substr($uri, strlen(FD_BASE));
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

        $uri = '/' . trim($uri, '/');
        return self::$path = $uri;
    }
}
