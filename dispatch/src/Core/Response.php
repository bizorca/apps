<?php

declare(strict_types=1);

namespace Dispatch\Core;

class Response
{
    public static function redirect(string $url, int $status = 302): never
    {
        // App paths ('/campaigns/5') become real URLs via Url::to(); a full
        // URL (redirectBack's referer) or the shared /account/ pages pass as is.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_starts_with($url, '/account/')) {
            $url = Url::to($url);
        }
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }

    public static function redirectBack(string $fallback = '/dashboard'): never
    {
        // Only back to a Dispatch page on this host; anything else falls back.
        $referer     = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $appHost     = strtolower((string) parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        $refHost     = $referer !== '' ? strtolower((string) parse_url($referer, PHP_URL_HOST)) : '';
        $refPath     = (string) parse_url($referer, PHP_URL_PATH);
        $safeReferer = ($refHost !== '' && $refHost === $appHost && str_starts_with($refPath, DP_BASE . '/')) ? $referer : null;

        self::redirect($safeReferer ?? $fallback);
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'Page Not Found'], 'app');
        exit;
    }

    public static function forbidden(): never
    {
        http_response_code(403);
        View::render('errors/403', ['title' => 'Access Denied'], 'app');
        exit;
    }
}
