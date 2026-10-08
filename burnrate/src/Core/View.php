<?php

namespace App\Core;

class View
{
    private string $basePath;
    private array $shared = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function render(string $view, array $data = []): string
    {
        $data = array_merge($this->shared, $data);
        $file = $this->basePath . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("View not found: {$view} ({$file})");
        }

        extract($data);
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
