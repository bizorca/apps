<?php
/*
 * Pod, as a tool on tools.bizorca.com/pod.
 *
 * PD_ROOT is set by public/_bootstrap.php (web) or by the bin/ scripts (CLI).
 * Account and database come from the shared core in private_html/includes:
 * one users table, one MySQL database, Pod's tables prefixed pd_.
 */

declare(strict_types=1);

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([PD_ROOT . '/../includes', PD_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        require_once $_dir . '/mailer.php';
        break;
    }
}
unset($_dir);

const PD_BASE = '/pod';   // where public/ is served

/*
 * ROUTING. This Cloudways app sends PHP straight from nginx to PHP-FPM with no
 * try_files fallback, so /pod/forum/post/5 is a 404 before PHP ever runs.
 * Until Cloudways support adds a rule that falls back to /pod/index.php, every
 * route travels in the query string: /pod/?r=/forum/post/5.
 *
 * Flip this to true once that rule exists (test /pod/forum first). url() is
 * the only thing that reads it: every link, form action and redirect goes
 * through it.
 */
const PD_CLEAN_URLS = false;

// Absolute origin for links in notification emails.
define('PD_ORIGIN', (string) tl_env('PD_ORIGIN', 'https://tools.bizorca.com'));

/*
 * One clock for PHP and MySQL. Event times are typed into the admin form as
 * Pacific wall-clock times and stored as such; the original compared them
 * with a UTC PHP clock, so "upcoming" and "past" flipped seven or eight hours
 * late. Database moves the MySQL session to the same zone as an offset.
 */
const PD_TIMEZONE = 'America/Los_Angeles';
date_default_timezone_set(PD_TIMEZONE);

// Zoom Server-to-Server OAuth (marketplace.zoom.us). Empty = Zoom is off and
// the admin "Create Zoom meeting" box reports it instead of calling Zoom.
define('PD_ZOOM_ACCOUNT_ID', (string) tl_env('PD_ZOOM_ACCOUNT_ID', ''));
define('PD_ZOOM_CLIENT_ID', (string) tl_env('PD_ZOOM_CLIENT_ID', ''));
define('PD_ZOOM_CLIENT_SECRET', (string) tl_env('PD_ZOOM_CLIENT_SECRET', ''));

// PSR-4 for Bizorca\Pod\ -> src/. Replaces Composer, which supplied nothing
// but this autoloader.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Bizorca\\Pod\\')) {
        $file = PD_ROOT . '/src/' . str_replace('\\', '/', substr($class, 12)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require_once PD_ROOT . '/src/Core/helpers.php';
