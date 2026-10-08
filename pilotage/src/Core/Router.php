<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Front-controller router.
 *
 * Patterns use {name} placeholders, which match a single path segment and are
 * passed to the handler as an associative array.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern: string, regex: string, keys: string[], handler: callable}>> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $keys = [];
        $regex = preg_replace_callback(
            '/\{([a-z_][a-z0-9_]*)\}/i',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];
                return '([^/]+)';
            },
            $pattern
        ) ?? $pattern;

        $this->routes[strtoupper($method)][] = [
            'pattern' => $pattern,
            'regex'   => '#^' . $regex . '$#',
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    /**
     * @throws HttpException 404 when nothing matches, 405 when the path exists
     *                       under a different method.
     */
    public function dispatch(string $method, string $path): mixed
    {
        $method = strtoupper($method);
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');
        if ($path === '/') {
            $path = '/';
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $m) === 1) {
                array_shift($m);
                $params = $route['keys'] === [] ? [] : array_combine($route['keys'], $m);
                return ($route['handler'])($params ?: []);
            }
        }

        // Distinguish "wrong method" from "no such path" — it makes debugging
        // form posts much less annoying.
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $method) {
                continue;
            }
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path) === 1) {
                    throw new HttpException(405, 'Method ' . $method . ' not allowed for ' . $path);
                }
            }
        }

        throw new HttpException(404, 'No route for ' . $path);
    }
}
