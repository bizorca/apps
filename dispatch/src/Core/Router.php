<?php

declare(strict_types=1);

namespace Dispatch\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->routes[] = ['GET', $path, $handler, $middleware];
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->routes[] = ['POST', $path, $handler, $middleware];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = Url::path();

        foreach ($this->routes as [$routeMethod, $routePath, $handler, $middleware]) {
            $params = [];

            if ($routeMethod !== $method) continue;

            // Convert route path to regex
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';

            if (!preg_match($pattern, $uri, $matches)) continue;

            // Extract named params
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            // Run middleware
            foreach ($middleware as $mw) {
                Middleware::run($mw);
            }

            // Dispatch to controller
            [$controllerClass, $action] = $handler;
            $controller = new $controllerClass();
            $controller->$action($params);
            return;
        }

        // 404
        http_response_code(404);
        View::render('errors/404', ['title' => 'Page Not Found'], 'app');
    }
}
