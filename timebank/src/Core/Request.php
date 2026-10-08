<?php

declare(strict_types=1);

namespace TimeBank\Core;

class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** The route inside the community ('/offers/5'), set by App. */
    public function path(): string
    {
        return Url::tenantPath();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function file(string $key): array|null
    {
        return $_FILES[$key] ?? null;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * Client address. Cloudflare sits in front of this server and sets
     * CF-Connecting-IP; the original trusted Client-IP and X-Forwarded-For,
     * which any client can set to anything.
     */
    public function ip(): string
    {
        $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public function host(): string
    {
        return $_SERVER['HTTP_HOST'] ?? '';
    }
}
