<?php

declare(strict_types=1);

namespace TimeBank\Core;

/**
 * The current community, from the first segment of the route (not the URL
 * path: routes travel in ?r= on this server, see Url).
 *   /demo/dashboard -> slug "demo" -> tm_tenants.subdomain = 'demo'
 */
class Tenant
{
    /** First segments that are not communities. */
    public const RESERVED = ['', 'assets', 'payments'];

    private static ?array $current  = null;
    private static string $slug     = '';
    private static bool   $resolved = false;

    public static function resolve(string $routePath): ?array
    {
        if (static::$resolved) {
            return static::$current;
        }
        static::$resolved = true;

        $parts = explode('/', trim($routePath, '/'));
        $slug  = $parts[0] ?? '';

        if (in_array($slug, self::RESERVED, true)) {
            static::$slug    = '';
            static::$current = null;
            return null;
        }

        $tenant = DB::fetch(
            'SELECT * FROM `tm_tenants` WHERE subdomain = ? AND is_active = 1',
            [$slug]
        );

        static::$slug    = $slug;   // kept even when unknown, so App can 404 it
        static::$current = $tenant ?: null;

        return static::$current;
    }

    public static function get(): ?array
    {
        return static::$current;
    }

    public static function id(): ?int
    {
        return isset(static::$current['id']) ? (int) static::$current['id'] : null;
    }

    public static function slug(): string
    {
        return static::$slug;
    }

    /** "/demo/dashboard" -> "/dashboard", "/demo" -> "/". */
    public static function stripPrefix(string $path): string
    {
        $slug = static::$slug;
        if ($slug === '') {
            return $path;
        }
        $prefix = '/' . $slug;
        if ($path === $prefix) {
            return '/';
        }
        if (str_starts_with($path, $prefix . '/')) {
            return substr($path, strlen($prefix));
        }
        return $path;
    }

    public static function reset(): void
    {
        static::$current  = null;
        static::$slug     = '';
        static::$resolved = false;
    }
}
