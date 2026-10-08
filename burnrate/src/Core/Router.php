<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): self
    {
        $this->addRoute('GET', $path, $handler, $middleware);
        return $this;
    }

    public function post(string $path, array $handler, array $middleware = []): self
    {
        $this->addRoute('POST', $path, $handler, $middleware);
        return $this;
    }

    private function addRoute(string $method, string $path, array $handler, array $middleware): void
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /** $uri is the app path from Url::path() ('/game'), never the raw request URI. */
    public function dispatch(string $method, string $uri, App $app): void
    {
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                foreach ($route['middleware'] as $middlewareClass) {
                    $mw = new $middlewareClass($app);
                    if (!$mw->handle()) {
                        return;
                    }
                }

                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$controllerClass, $action] = $route['handler'];

                $controller = new $controllerClass($app);
                $controller->$action(...$params);
                return;
            }
        }

        http_response_code(404);
        echo $app->view('layouts/error', ['code' => 404, 'message' => 'Page Not Found']);
    }
}
