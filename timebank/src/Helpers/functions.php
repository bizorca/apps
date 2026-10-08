<?php

declare(strict_types=1);

use TimeBank\Core\Auth;
use TimeBank\Core\CSRF;
use TimeBank\Core\Flash;
use TimeBank\Core\Response;
use TimeBank\Core\Tenant;
use TimeBank\Core\Url;
use TimeBank\Core\View;

if (!function_exists('e')) {
    /** Escape for HTML. Takes anything: the original's string-only e() was a TypeError on every NULL column. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * A link inside the current community: url('/offers/5') -> /timebank/?r=/demo/offers/5.
     * On the landing page (no community) it is a site-level route.
     */
    function url(string $path = ''): string
    {
        $slug = Tenant::slug();
        $path = '/' . ltrim($path, '/');
        return Url::to($slug !== '' ? '/' . $slug . ($path === '/' ? '' : $path) : $path);
    }
}

if (!function_exists('url_abs')) {
    /** Absolute url(), for email. */
    function url_abs(string $path = ''): string
    {
        return TM_ORIGIN . url($path);
    }
}

if (!function_exists('community_url')) {
    /** A link into a named community from outside it (the landing page). */
    function community_url(string $slug, string $path = '/'): string
    {
        $path = '/' . ltrim($path, '/');
        return Url::to('/' . $slug . ($path === '/' ? '' : $path));
    }
}

if (!function_exists('route_field')) {
    /**
     * Hidden input carrying the route for a GET form. A browser replaces an
     * action's query string with the form fields, so without this a filter
     * form would lose ?r= and land on the landing page.
     */
    function route_field(string $path): string
    {
        if (TM_CLEAN_URLS) {
            return '';
        }
        $slug  = Tenant::slug();
        $path  = '/' . ltrim($path, '/');
        $route = $slug !== '' ? '/' . $slug . $path : $path;
        return '<input type="hidden" name="r" value="' . e($route) . '">';
    }
}

if (!function_exists('asset')) {
    /** Static files shipped in public/assets. */
    function asset(string $path = ''): string
    {
        return TM_BASE . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('media_url')) {
    /**
     * An uploaded image. Stored paths look like 'uploads/avatars/3/12.png'
     * (the original's layout, kept so imported rows still resolve); they are
     * served by the community's media route from outside the web root.
     * The original printed the stored path as a relative src, which broke
     * under /<community>/ URLs.
     */
    function media_url(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return '';
        }
        if (!preg_match('#^uploads/(avatars|offers)/\d+/([A-Za-z0-9_.-]+)$#', $stored, $m)) {
            return '';
        }
        return url('/media/' . $m[1] . '/' . $m[2]);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $code = 302): never
    {
        Response::redirect($url, $code);
    }
}

if (!function_exists('old')) {
    /** Previous input after a failed validation (the original read it after View had cleared it). */
    function old(string $field, string $default = ''): string
    {
        return e((string) (View::$old[$field] ?? $default));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return CSRF::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return CSRF::token();
    }
}

if (!function_exists('auth')) {
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('tenant')) {
    function tenant(): ?array
    {
        return Tenant::get();
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Flash::set($type, $message);
    }
}

if (!function_exists('format_hours')) {
    /** 1.25 -> "1h 15m", 0.5 -> "30m", 2.0 -> "2h" */
    function format_hours(float|string $decimal): string
    {
        $totalMinutes = (int) round((float) $decimal * 60);
        $hours        = intdiv($totalMinutes, 60);
        $minutes      = $totalMinutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'm';
        } elseif ($hours > 0) {
            return $hours . 'h';
        }
        return $minutes . 'm';
    }
}

if (!function_exists('truncate')) {
    function truncate(?string $str, int $len = 100): string
    {
        $str = (string) $str;
        if (mb_strlen($str) <= $len) {
            return $str;
        }
        return mb_substr($str, 0, $len - 1) . '…';
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string
    {
        $time = strtotime((string) $datetime);
        if ($time === false) {
            return (string) $datetime;
        }

        $diff = time() - $time;

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . ' minute' . ($m !== 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . ' hour' . ($h !== 1 ? 's' : '') . ' ago';
        }
        if ($diff < 604800) {
            $d = (int) floor($diff / 86400);
            return $d . ' day' . ($d !== 1 ? 's' : '') . ' ago';
        }
        if ($diff < 2592000) {
            $w = (int) floor($diff / 604800);
            return $w . ' week' . ($w !== 1 ? 's' : '') . ' ago';
        }
        if ($diff < 31536000) {
            $mo = (int) floor($diff / 2592000);
            return $mo . ' month' . ($mo !== 1 ? 's' : '') . ' ago';
        }

        $y = (int) floor($diff / 31536000);
        return $y . ' year' . ($y !== 1 ? 's' : '') . ' ago';
    }
}

if (!function_exists('money')) {
    /** money(2.25) -> "H 2.25" */
    function money(float|string $amount, string $symbol = 'H'): string
    {
        return $symbol . ' ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('nl2p')) {
    /** Plain text with newlines -> escaped HTML paragraphs. */
    function nl2p(?string $text): string
    {
        $paragraphs = preg_split('/\n{2,}/', e((string) $text));
        $html = '';
        foreach ($paragraphs ?: [] as $para) {
            $para = trim($para);
            if ($para !== '') {
                $html .= '<p>' . nl2br($para) . '</p>';
            }
        }
        return $html;
    }
}
