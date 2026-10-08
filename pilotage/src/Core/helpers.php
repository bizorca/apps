<?php

declare(strict_types=1);

use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Tenant;

/**
 * Global helpers. Kept deliberately small.
 *
 * Every URL in the application is built through one of the *_url() functions
 * below. The primary domain is written down in exactly one place (config), so
 * the three-domain split from spec §7 cannot drift — nobody can hardcode
 * getpilotage.com into an email template six months from now.
 */

if (!function_exists('h')) {
    /** Escape for HTML output. Use on every echoed value, without exception. */
    function h(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/*
 * URLs on tools.bizorca.com.
 *
 * Pilotage lived on subdomains (acme.pilotagehq.com). It now lives under
 * /pilotage on the shared tools domain, and a firm is a ROUTE PREFIX:
 *
 *   /pilotage/?r=/f/acme/clients/3          (query routing, today)
 *   /pilotage/f/acme/clients/3              (PL_CLEAN_URLS, once Cloudways
 *                                            adds a try_files fallback)
 *
 * Every URL in the product is still built by tenant_url() / url() / app_url(),
 * exactly as before, so the 317 call sites did not change: only these helpers
 * did. The firm comes from the route, never from anything the client can set
 * separately, and Tenant::resolveSlug() is the one place it is looked up.
 */

if (!defined('PL_BASE')) {
    define('PL_BASE', '/pilotage');
}
if (!defined('PL_CLEAN_URLS')) {
    // Flip to true only after nginx routes /pilotage/<anything> to index.php.
    define('PL_CLEAN_URLS', false);
}

if (!function_exists('pl_origin')) {
    /**
     * scheme://host for absolute links. From the request when there is one
     * (so local dev links stay local), from PL_ORIGIN for CLI work like the
     * tick and digests, which have no request to read.
     */
    function pl_origin(): string
    {
        $configured = rtrim((string) (function_exists('tl_env') ? tl_env('PL_ORIGIN', '') : ''), '/');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (PHP_SAPI === 'cli' || $host === '') {
            return $configured !== '' ? $configured : 'https://tools.bizorca.com';
        }
        $https = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off');
        return ($https ? 'https' : 'http') . '://' . $host;
    }
}

if (!function_exists('pl_route')) {
    /**
     * A site-relative URL for an internal route such as "/f/acme/clients?x=1#c".
     * Any query string on the route is carried along; a fragment stays last.
     */
    function pl_route(string $route): string
    {
        $fragment = '';
        if (($hash = strpos($route, '#')) !== false) {
            $fragment = substr($route, $hash);
            $route = substr($route, 0, $hash);
        }
        $query = '';
        if (($q = strpos($route, '?')) !== false) {
            $query = substr($route, $q + 1);
            $route = substr($route, 0, $q);
        }
        $route = '/' . ltrim($route, '/');

        if (PL_CLEAN_URLS) {
            return PL_BASE . $route . ($query !== '' ? '?' . $query : '') . $fragment;
        }

        $r = str_replace(['%2F', '%40'], ['/', '@'], rawurlencode($route));
        return PL_BASE . '/?r=' . $r . ($query !== '' ? '&' . $query : '') . $fragment;
    }
}

if (!function_exists('pl_current_route')) {
    /** The route this request asked for, always starting with "/". */
    function pl_current_route(): string
    {
        if (PL_CLEAN_URLS) {
            $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
            if (str_starts_with($path, PL_BASE)) {
                $path = substr($path, strlen(PL_BASE));
            }
            return '/' . ltrim($path, '/');
        }
        $r = $_GET['r'] ?? '/';
        return is_string($r) && $r !== '' ? '/' . ltrim($r, '/') : '/';
    }
}

if (!function_exists('pl_parse_route')) {
    /**
     * Split a route into [firm slug or null, path below it].
     *   "/f/acme/clients/3" => ["acme", "/clients/3"]
     *   "/f/acme"           => ["acme", "/"]
     *   "/pricing"          => [null, "/pricing"]
     * The slug is returned as written; Tenant::resolveSlug() validates it.
     */
    function pl_parse_route(string $route): array
    {
        $route = '/' . ltrim($route, '/');
        if (preg_match('#^/f/([^/]+)(/.*)?$#', $route, $m) === 1) {
            $rest = $m[2] ?? '/';
            return [strtolower($m[1]), $rest === '' ? '/' : $rest];
        }
        return [null, $route];
    }
}

if (!function_exists('pl_request_path')) {
    /**
     * This request's path BELOW the firm prefix, with its query string minus
     * the routing parameter: "/clients/3?tab=x". It is what url() takes, so it
     * is what a "come back here after signing in" redirect must carry. The raw
     * REQUEST_URI (/pilotage/?r=/f/acme/...) fed back through url() would nest
     * the prefix twice.
     */
    function pl_request_path(): string
    {
        [, $path] = pl_parse_route(strtok(pl_current_route(), '?') ?: '/');
        $query = $_GET;
        unset($query['r']);
        return $path . ($query === [] ? '' : '?' . http_build_query($query));
    }
}

if (!function_exists('pl_route_field')) {
    /**
     * Hidden input for a method="get" form. A GET form REPLACES the query
     * string of its action, which would throw away ?r= and land on the home
     * page; this puts the route back in as a field. Empty with clean URLs.
     */
    function pl_route_field(?string $tenantPath = null): string
    {
        if (PL_CLEAN_URLS) {
            return '';
        }
        if ($tenantPath === null) {
            $route = strtok(pl_current_route(), '?') ?: '/';
        } else {
            $slug = Tenant::current()['slug'] ?? null;
            $route = ($slug === null ? '' : '/f/' . $slug) . '/' . ltrim($tenantPath, '/');
        }
        return '<input type="hidden" name="r" value="' . h($route) . '">';
    }
}

if (!function_exists('base_domain')) {
    /** The host the product is served from — tools.bizorca.com in production. */
    function base_domain(): string
    {
        return (string) (parse_url(pl_origin(), PHP_URL_HOST) ?: 'tools.bizorca.com');
    }
}

if (!function_exists('scheme')) {
    function scheme(): string
    {
        return (string) (parse_url(pl_origin(), PHP_URL_SCHEME) ?: 'https');
    }
}

if (!function_exists('app_url')) {
    /**
     * An absolute URL outside any firm: the Pilotage landing page, signup,
     * the calendar OAuth callback, the Stripe webhook, the tick.
     */
    function app_url(string $path = '/'): string
    {
        return pl_origin() . pl_route('/' . ltrim($path, '/'));
    }
}

if (!function_exists('tenant_url')) {
    /**
     * An absolute URL inside a firm. Defaults to the firm resolved for this
     * request, which is what almost every call site wants.
     */
    function tenant_url(string $path = '/', ?string $slug = null): string
    {
        $slug = $slug ?? (Tenant::current()['slug'] ?? null);

        if ($slug === null) {
            return app_url($path);
        }

        $path = '/' . ltrim($path, '/');
        return pl_origin() . pl_route('/f/' . $slug . ($path === '/' ? '' : $path));
    }
}

if (!function_exists('url')) {
    /** Alias for tenant_url() — the common case inside the product. */
    function url(string $path = '/'): string
    {
        return tenant_url($path);
    }
}

if (!function_exists('marketing_url')) {
    /**
     * Was the getpilotage.com call-to-action domain. Abandoned with
     * pilotagehq.com, so it is the Pilotage landing page now.
     */
    function marketing_url(string $path = '/'): string
    {
        return app_url($path);
    }
}

if (!function_exists('short_url')) {
    /**
     * Was the pilotage.cc SMS short-link domain. SMS is still deferred (no
     * sender exists), so this only has to produce a working link.
     */
    function short_url(string $token): string
    {
        return app_url('/t/' . ltrim($token, '/'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to, int $status = 302): never
    {
        header('Location: ' . $to, true, $status);
        exit;
    }
}

if (!function_exists('is_dev')) {
    function is_dev(): bool
    {
        return Config::get('app.env', 'production') !== 'production';
    }
}

if (!function_exists('num')) {
    /**
     * A metric value, rendered as a person would write it.
     *
     * MySQL hands back DECIMAL columns as fixed-scale strings, so a metric of 34
     * arrives as "34.0000" and renders that way unless something intervenes. It
     * makes a scoreboard look like a database dump — and worse, "36.0000%
     * margin" reads as false precision about a number that is an estimate.
     *
     * Trailing zeros go; genuine decimals stay. 34.0000 -> 34, 3.5000 -> 3.5,
     * 1234.5 -> 1,234.5.
     */
    function num(int|float|string|null $value, int $precision = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = number_format((float) $value, $precision, '.', ',');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }
}
