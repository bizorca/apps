<?php

declare(strict_types=1);

namespace TimeBank\Core;

/**
 * Every TimeBank URL is built and read here, so today's query-string routing
 * can become clean URLs with one constant (TM_CLEAN_URLS in includes/config.php).
 *
 *   query mode   Url::to('/demo/offers/5?x=1')  ->  /timebank/?r=/demo/offers/5?x=1
 *   clean mode   Url::to('/demo/offers/5?x=1')  ->  /timebank/demo/offers/5?x=1
 *
 * A route's first segment is the community slug, as the first path segment
 * was in the original. In query mode a route's own "?a=b" rides inside r and
 * path() folds it back into $_GET, so views keep writing url('/offers?page=2').
 */
final class Url
{
    private static ?string $path = null;
    private static string $tenantPath = '/';

    public static function prefix(): string
    {
        return TM_CLEAN_URLS ? TM_BASE : TM_BASE . '/?r=';
    }

    public static function to(string $routePath): string
    {
        return self::prefix() . $routePath;
    }

    public static function absolute(string $routePath): string
    {
        return TM_ORIGIN . self::to($routePath);
    }

    /** The full route of this request, slug included ('/demo/offers/5'), no query. */
    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }

        if (TM_CLEAN_URLS) {
            $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (str_starts_with($uri, TM_BASE)) {
                $uri = substr($uri, strlen(TM_BASE));
            }
        } else {
            $uri = is_string($_GET['r'] ?? null) ? $_GET['r'] : '/';
            $q = strpos($uri, '?');
            if ($q !== false) {
                // "?r=/demo/offers?category=3&page=2": PHP put page in $_GET but
                // category inside r. Recover it; a real top-level key wins.
                parse_str(substr($uri, $q + 1), $inner);
                $_GET += $inner;
                $uri = substr($uri, 0, $q);
            }
        }

        return self::$path = '/' . trim($uri, '/');
    }

    /** Set by App once the slug is stripped: '/offers/5'. */
    public static function setTenantPath(string $path): void
    {
        self::$tenantPath = $path;
    }

    /** The route inside the community ('/offers/5'), for nav highlighting. */
    public static function tenantPath(): string
    {
        return self::$tenantPath;
    }
}
