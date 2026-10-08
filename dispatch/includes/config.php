<?php
/*
 * Dispatch, as a tool on tools.bizorca.com/dispatch.
 *
 * DP_ROOT is set by public/_bootstrap.php (web) or by the bin/ scripts (CLI).
 * Account and database come from the shared core in private_html/includes:
 * one users table, one MySQL database, Dispatch's tables prefixed dp_.
 */

declare(strict_types=1);

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([DP_ROOT . '/../includes', DP_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

const DP_APP_NAME = 'Dispatch';
const DP_BASE     = '/dispatch';   // where public/ is served

/*
 * ROUTING. This Cloudways app sends PHP straight from nginx to PHP-FPM with no
 * try_files fallback, so /dispatch/campaigns/5 is a 404 before PHP ever runs.
 * Until Cloudways support adds a rule that falls back to /dispatch/index.php,
 * every route travels in the query string: /dispatch/?r=/campaigns/5.
 *
 * Flip this to true once that rule exists (test /dispatch/campaigns first).
 * Url::prefix() is the only thing that reads it: every link, form action and
 * redirect goes through $_base / Url::to() / Response::redirect().
 */
const DP_CLEAN_URLS = false;

// Absolute origin for links in emails (the digest runs from cron, with no Host).
define('DP_ORIGIN', (string) tl_env('DP_ORIGIN', 'https://tools.bizorca.com'));

// Email: Dispatch sends HTML (the digest), so it keeps its own SMTP2GO sender
// rather than tl_mail(), using the shared account's key.
define('DP_MAIL_KEY', (string) tl_env('SMTP2GO_API_KEY', ''));
const DP_MAIL_FROM_ADDRESS = 'noreply@bizorca.com';
const DP_MAIL_FROM_NAME    = 'Dispatch';

/*
 * One clock for PHP and MySQL. The original ran PHP in UTC and MySQL in
 * America/Chicago, so "today" for an overdue check (CURDATE()) and "today" in
 * PHP (date('Y-m-d')) disagreed for several hours every night. Dispatch's users
 * are in the Pacific Northwest, so both now use Pacific time; Database applies
 * the matching offset to the MySQL session.
 */
const DP_TIMEZONE = 'America/Los_Angeles';
date_default_timezone_set(DP_TIMEZONE);

// Uploaded documents live outside the web root, in the server-only data dir.
define('DP_UPLOADS', TL_PRIVATE . '/data/dispatch/documents');

// PSR-4 for Dispatch\ -> src/. Replaces Composer, whose only other package
// was vlucas/phpdotenv (secrets now come from tl_env()).
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Dispatch\\')) {
        $file = DP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 9)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
