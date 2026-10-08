<?php
/*
 * Bizorca Foundry, as a tool on tools.bizorca.com/foundry.
 *
 * FD_ROOT is set by public/_bootstrap.php (web) or by the bin/ scripts (CLI).
 * Account and database come from the shared core in private_html/includes:
 * one users table, one MySQL database, Foundry's tables prefixed fd_.
 */

declare(strict_types=1);

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([FD_ROOT . '/../includes', FD_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

const FD_BASE = '/foundry';   // where public/ is served

/*
 * ROUTING. This Cloudways app sends PHP straight from nginx to PHP-FPM with no
 * try_files fallback, so /foundry/admin is a 404 before PHP ever runs. Until
 * Cloudways support adds a rule that falls back to /foundry/index.php, every
 * route travels in the query string: /foundry/?r=/admin.
 *
 * Flip this to true once that rule exists (test /foundry/admin first).
 * Url::prefix() is the only thing that reads it: every link, form action and
 * redirect goes through u() / Url::to() / redirect().
 */
const FD_CLEAN_URLS = false;

/*
 * One clock for PHP and MySQL, as in Dispatch: Pacific time for both, so a
 * date PHP writes (date('Y-m-d H:i:s')) and a column MySQL defaults
 * (CURRENT_TIMESTAMP) mean the same moment. Database::pdo() applies the
 * matching offset to the MySQL session.
 */
const FD_TIMEZONE = 'America/Los_Angeles';
date_default_timezone_set(FD_TIMEZONE);

// PSR-4 for Bizorca\Consulting\ -> src/ (the original's namespace, kept).
spl_autoload_register(static function (string $class): void {
    $prefix = 'Bizorca\\Consulting\\';
    if (str_starts_with($class, $prefix)) {
        $file = FD_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require_once FD_ROOT . '/src/Core/helpers.php';
