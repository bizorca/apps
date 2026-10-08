<?php

declare(strict_types=1);

namespace Dispatch\Core;

class Request
{
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $data[$key] = $_POST[$key];
            }
        }
        return $data;
    }

    public static function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
    }

    public static function file(string $key): ?array
    {
        return self::hasFile($key) ? $_FILES[$key] : null;
    }

    /** The app route path ('/campaigns/5'), whichever URL style is in use. */
    public static function uri(): string
    {
        return Url::path();
    }

    public static function header(string $key): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$key] ?? null;
    }

}
