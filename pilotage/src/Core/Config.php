<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Configuration, loaded once from config/app.php which reads .env.
 *
 * .env lives on the server only and is never committed (house rule).
 */
final class Config
{
    /** @var array<string,mixed>|null */
    private static ?array $items = null;

    /** @param array<string,mixed> $items */
    public static function load(array $items): void
    {
        self::$items = $items;
    }

    /** Dot-path lookup: Config::get('db.host'). */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$items === null) {
            throw new \RuntimeException('Config::load() has not been called.');
        }

        $node = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * Override one value at runtime.
     *
     * A test seam. Config is otherwise read-only after load(), and it should
     * stay that way in application code — a setting that changes mid-request is
     * a setting nobody can reason about. Tests need it so they can exercise
     * "no encryption key configured" and "this provider is switched off"
     * without a developer's .env deciding the outcome.
     */
    public static function set(string $key, mixed $value): void
    {
        if (self::$items === null) {
            throw new \RuntimeException('Config::load() has not been called.');
        }

        $segments = explode('.', $key);
        $node = &self::$items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $node[$segment] = $value;
                break;
            }

            if (!isset($node[$segment]) || !is_array($node[$segment])) {
                $node[$segment] = [];
            }

            $node = &$node[$segment];
        }

        unset($node);
    }

    public static function isLoaded(): bool
    {
        return self::$items !== null;
    }

    /**
     * Minimal .env parser. No dependency, no build step — house style.
     * Supports KEY=value, quoted values, # comments, and blank lines.
     *
     * @return array<string,string>
     */
    public static function parseEnvFile(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $out = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip matching surrounding quotes, but leave interior ones alone
            // so passwords containing quotes survive.
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
