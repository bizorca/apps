<?php

namespace Bizorca\Consulting\Core;

/*
 * Routes are matched against Url::path(): /foundry/?r=/admin today, the
 * request path itself once FD_CLEAN_URLS is on. The {param} matching is the
 * original's.
 */

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method): void
    {
        $path = Url::path();

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            foreach ($this->routes[$method] ?? [] as $pattern => $h) {
                $regex = preg_replace('/\{[a-z_]+\}/', '([^/]+)', $pattern);
                $regex = '#^' . $regex . '$#';
                if (preg_match($regex, $path, $matches)) {
                    array_shift($matches);
                    $handler = fn() => $h(...$matches);
                    break;
                }
            }
        }

        if ($handler === null) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Page not found.']);
            return;
        }

        $handler();
    }
}
