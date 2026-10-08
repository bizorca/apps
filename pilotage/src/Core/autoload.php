<?php

declare(strict_types=1);

/**
 * PSR-4 autoloader for Bizorca\Pilotage\ => src/
 *
 * Hand-rolled on purpose. Pilotage has no Composer dependencies, and this
 * keeps deployment to "rsync the files" with no `composer install` step on
 * the server. House style: no build step.
 */

// The application runs in UTC end to end; Database pins MySQL to match. Any
// timezone conversion is a display concern and belongs in the view layer.
date_default_timezone_set('UTC');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Bizorca\\Pilotage\\';
    $baseDir = __DIR__ . '/../';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

require_once __DIR__ . '/helpers.php';
