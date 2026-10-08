<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Plain PHP templates. No engine — house style is no build step and no
 * dependencies, and PHP is already a template language.
 *
 * Templates live in src/Views/ and receive $data extracted into scope. They
 * are responsible for escaping their own output with h(); nothing here does
 * it for them, because a template that auto-escapes teaches people to forget.
 */
final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $body = self::capture($template, $data);

        if ($layout === null) {
            return $body;
        }

        return self::capture($layout, $data + [
            'content' => $body,
            'title'   => $data['title'] ?? 'Pilotage',
        ]);
    }

    /** @param array<string,mixed> $data */
    private static function capture(string $template, array $data): string
    {
        $path = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $template) . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        // Keep $data itself available so templates can check isset() without
        // tripping over undefined-variable notices.
        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
