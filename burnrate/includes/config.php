<?php
/*
 * Burn Rate, as a tool on tools.bizorca.com/burnrate.
 *
 * BR_ROOT is set by public/_bootstrap.php (web) or by the bin/ scripts (CLI).
 * Account and database come from the shared core in private_html/includes:
 * one users table, one MySQL database, Burn Rate's tables prefixed br_.
 */

declare(strict_types=1);

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([BR_ROOT . '/../includes', BR_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

const BR_BASE = '/burnrate';   // where public/ is served

/*
 * ROUTING. This Cloudways app sends PHP straight from nginx to PHP-FPM with no
 * try_files fallback, so /burnrate/game is a 404 before PHP ever runs. Until
 * Cloudways support adds a rule that falls back to /burnrate/index.php, every
 * route travels in the query string: /burnrate/?r=/game.
 *
 * Flip this to true once that rule exists (test /burnrate/game first). url()
 * and App\Core\Url are the only readers: every link, form action and redirect
 * in the app goes through url().
 */
const BR_CLEAN_URLS = false;

// PSR-4 for App\ -> src/ (the original's namespace, kept so the code reads the
// same). Only Burn Rate's classes load in a Burn Rate request.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BR_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

/**
 * Every Burn Rate URL. The views call url('/game?tab=stocks') exactly as the
 * original did; in query mode that becomes /burnrate/?r=/game?tab=stocks and
 * App\Core\Url::path() folds the inner query back into $_GET.
 */
function url(string $path = '/'): string
{
    return App\Core\Url::to($path);
}
