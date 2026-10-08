<?php

namespace Bizorca\Pod\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    /** $path is the app path ('/forum/post/5'), from current_path(). */
    public function dispatch(string $method, string $path): void
    {
        // HEAD is answered like GET (PHP drops the body).
        $method = $method === 'HEAD' ? 'GET' : $method;

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            $pattern = preg_replace('/\{[a-z_]+\}/', '([^/]+)', $routePath);
            if ($routeMethod === $method && preg_match('#^' . $pattern . '$#', $path, $matches)) {
                array_shift($matches);
                $handler(...array_map('rawurldecode', $matches));
                return;
            }
        }

        http_response_code(404);
        render('error', ['code' => 404, 'message' => 'Page not found.']);
    }
}
