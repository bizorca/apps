<?php

declare(strict_types=1);

namespace TimeBank\Core;

class Router
{
    /** @var array<array{method: string, path: string, pattern: string, handler: array}> */
    private array $routes = [];

    public function addRoute(string $method, string $path, array $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $path,
            'pattern' => $this->pathToPattern($path),
            'handler' => $handler,
        ];
    }

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
                $this->callHandler($route['handler'], array_values($params));
                return;
            }
        }

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path)) {
                Response::abort(405, 'Method Not Allowed');
            }
        }

        Response::notFound();
    }

    /**
     * Route parameters are passed positionally and cast to each parameter's
     * declared type.
     *
     * The original passed the Request as the first argument AND the route
     * parameters as named arguments ($controller->show($request, id: '5')),
     * which PHP rejects ("Named parameter $id overwrites previous argument"):
     * every route with a {parameter} was a fatal error. Under strict_types a
     * string '5' would also have been refused by an int parameter.
     */
    private function callHandler(array $handler, array $params): void
    {
        [$class, $method] = $handler;

        if (!class_exists($class) || !method_exists($class, $method)) {
            error_log('TimeBank router: missing handler ' . $class . '::' . $method);
            Response::abort(500);
        }

        $ref  = new \ReflectionMethod($class, $method);
        $args = [];
        foreach ($ref->getParameters() as $i => $param) {
            if (!array_key_exists($i, $params)) {
                break;
            }
            $value = $params[$i];
            $type  = $param->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === 'int') {
                if (!ctype_digit((string) $value)) {
                    Response::notFound();
                }
                $value = (int) $value;
            }
            $args[] = $value;
        }

        $controller = new $class(new Request());
        $controller->$method(...$args);
    }

    /** /offers/{id}/edit -> #^/offers/(?P<id>[^/]+)/edit$# */
    private function pathToPattern(string $path): string
    {
        $escaped = preg_quote($path, '#');
        $pattern = preg_replace('/\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\}/', '(?P<$1>[^/]+)', $escaped);
        return '#^' . $pattern . '$#';
    }
}
