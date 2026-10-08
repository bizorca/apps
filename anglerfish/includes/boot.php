<?php
/**
 * Anglerfish Press, as a tool on tools.bizorca.com/anglerfish.
 *
 * Loaded by public/index.php (web) and cli.php / bin/*.php (CLI), each of
 * which defines AF_ROOT first: the folder holding src/, templates/, config/.
 *
 *   repo       apps/anglerfish/
 *   Cloudways  private_html/anglerfish/
 *
 * Account and database come from the shared core in private_html/includes.
 * Anglerfish's tables are prefixed af_. Its files (covers, generated images,
 * batch logs, incoming pushes) live in private_html/data/anglerfish, which
 * deploy.sh never touches.
 */

declare(strict_types=1);

foreach ([AF_ROOT . '/../includes', AF_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

if (!defined('TL_PRIVATE')) {
    http_response_code(500);
    exit("Anglerfish: shared core not found. Check the deploy.\n");
}

const AF_BASE = '/anglerfish';   // where public/ is served

/*
 * Data that used to sit in the app's own storage/ folder. The database still
 * records paths as "storage/assets/asset-12.png"; af_file() maps that prefix
 * here, so no stored path had to be rewritten in the move.
 */
define('AF_STORAGE', rtrim((string) tl_env('AF_STORAGE', TL_PRIVATE . '/data/anglerfish'), '/'));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Anglerfish\\')) {
        return;
    }
    $path = AF_ROOT . '/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// Composer packages (the Anthropic SDK). Loaded conditionally on purpose: if a
// deploy lands without vendor/, composing should be the only thing that breaks.
if (is_file(AF_ROOT . '/vendor/autoload.php')) {
    require_once AF_ROOT . '/vendor/autoload.php';
}

require_once AF_ROOT . '/src/Core/helpers.php';
