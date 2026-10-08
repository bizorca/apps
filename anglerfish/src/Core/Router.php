<?php

namespace Anglerfish\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, bool $auth = true, bool $csrf = true): void
    {
        $this->routes['GET'][$path] = [$handler, $auth, $csrf];
    }

    public function post(string $path, callable|array $handler, bool $auth = true, bool $csrf = true): void
    {
        $this->routes['POST'][$path] = [$handler, $auth, $csrf];
    }

    /** Worker API: bearer-token auth, no session, no CSRF. */
    public function api(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = [$handler, false, false];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?? '/', '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        $route = $this->routes[$method][$path] ?? null;

        if ($route === null) {
            // Try one dynamic segment: /library/{slug}
            foreach ($this->routes[$method] ?? [] as $pattern => $candidate) {
                if (!str_contains($pattern, '{')) {
                    continue;
                }
                $regex = '#^' . preg_replace('#\{[a-z_]+\}#', '([^/]+)', $pattern) . '$#';
                if (preg_match($regex, $path, $m)) {
                    array_shift($m);
                    $this->run($candidate, $m);
                    return;
                }
            }
            http_response_code(404);
            View::render('error', ['code' => 404, 'message' => 'Not found'], 'Not found');
            return;
        }

        $this->run($route, []);
    }

    private function run(array $route, array $args): void
    {
        [$handler, $auth, $csrf] = $route;

        if ($auth) {
            Auth::require();
        }

        if ($csrf && $_SERVER['REQUEST_METHOD'] === 'POST'
            && !Session::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            View::render('error', ['code' => 419, 'message' => 'Session expired. Go back and retry.'], 'Expired');
            return;
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            (new $class())->$method(...$args);
            return;
        }

        $handler(...$args);
    }
}
