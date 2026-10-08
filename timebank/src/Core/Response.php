<?php

declare(strict_types=1);

namespace TimeBank\Core;

class Response
{
    /**
     * Redirect. A community path ('/dashboard') gets the community prefix and
     * the routing prefix via url(); the original sent '/dashboard' to the site
     * root, which then read "dashboard" as a community name and 404'd (after
     * every sign-in, among other places). Shared account pages (/account/...)
     * pass through, and a full URL (a referer) is only followed if it points
     * back into TimeBank on this host.
     */
    public static function redirect(string $url, int $code = 302): never
    {
        if (str_starts_with($url, '/account/')) {
            // shared account page: as is
        } elseif (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = url($url);
        } elseif (!self::isOwnUrl($url)) {
            $url = url('/dashboard');
        }

        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    /** Back to the referring TimeBank page, or $fallback. */
    public static function back(string $fallback): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        self::redirect($ref !== '' && self::isOwnUrl($ref) ? $ref : $fallback);
    }

    private static function isOwnUrl(string $url): bool
    {
        $host    = strtolower((string) parse_url($url, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        $path    = (string) parse_url($url, PHP_URL_PATH);
        return $host !== '' && $host === $appHost && str_starts_with($path, TM_BASE . '/');
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);

        $titles = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ];

        $title   = $titles[$code] ?? 'Error';
        $display = $message ?: $title;
        $data = ['message' => $display, 'code' => $code, 'title' => $title, 'pageTitle' => $title];

        // errors/403, 404 and 500 are complete pages of their own; anything
        // else uses the generic one inside the normal layout. (The original
        // wrapped the complete pages in the layout too: a page in a page.)
        if (is_file(TM_ROOT . '/src/Views/errors/' . $code . '.php')) {
            View::render('errors/' . $code, $data, 'none');
        } else {
            View::render('errors/generic', $data, 'layout/base');
        }
        exit;
    }

    public static function notFound(): never
    {
        static::abort(404);
    }
}
