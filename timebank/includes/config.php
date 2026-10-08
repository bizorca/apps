<?php
/*
 * TimeBank, as a tool on tools.bizorca.com/timebank.
 *
 * TM_ROOT is set by public/_bootstrap.php (web) or by the bin/ scripts (CLI).
 * Account and database come from the shared core in private_html/includes:
 * one users table, one MySQL database, TimeBank's tables prefixed tm_.
 */

declare(strict_types=1);

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([TM_ROOT . '/../includes', TM_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

const TM_APP_NAME = 'TimeBank';
const TM_BASE     = '/timebank';   // where public/ is served

/*
 * ROUTING. This Cloudways app sends PHP straight from nginx to PHP-FPM with no
 * try_files fallback, so /timebank/demo/dashboard is a 404 before PHP runs.
 * Until Cloudways support adds a rule falling back to /timebank/index.php,
 * every route travels in the query string: /timebank/?r=/demo/dashboard.
 * The first segment of the route is the community (tenant) slug, exactly as
 * the first path segment was in the original.
 *
 * Flip to true once that rule exists (test /timebank/demo/dashboard first).
 * Url::prefix() is the only reader: every link, form action and redirect goes
 * through url() / Url::to() / Response::redirect().
 */
const TM_CLEAN_URLS = false;

// Absolute origin for links in email (the weekly digest runs from cron).
define('TM_ORIGIN', rtrim((string) tl_env('TM_ORIGIN', 'https://tools.bizorca.com'), '/'));

// Email. TimeBank sends HTML (templates and the weekly digest), so it keeps
// its own SMTP2GO sender rather than the text-only tl_mail(), with the shared
// account's key.
define('TM_MAIL_KEY', (string) tl_env('SMTP2GO_API_KEY', ''));
const TM_MAIL_FROM_ADDRESS = 'tools@bizorca.com';
const TM_MAIL_FROM_NAME    = 'TimeBank';

/*
 * Payments (community-fund donations). The original used global keys too; the
 * per-community key columns on tenants were never read and are not ported.
 * With no keys set the endpoints answer 503 and nothing is charged.
 */
define('TM_STRIPE_SECRET_KEY',     (string) tl_env('TM_STRIPE_SECRET_KEY', ''));
define('TM_STRIPE_WEBHOOK_SECRET', (string) tl_env('TM_STRIPE_WEBHOOK_SECRET', ''));
define('TM_PAYPAL_CLIENT_ID',      (string) tl_env('TM_PAYPAL_CLIENT_ID', ''));
define('TM_PAYPAL_CLIENT_SECRET',  (string) tl_env('TM_PAYPAL_CLIENT_SECRET', ''));
define('TM_PAYPAL_MODE',           (string) tl_env('TM_PAYPAL_MODE', 'sandbox'));   // sandbox | live

// Avatars and offer images live outside the web root and are served by the
// media route, only to members of the same community.
define('TM_UPLOADS', TL_PRIVATE . '/data/timebank');

// One clock: PHP in UTC to match tl_db()'s MySQL session (+00:00), so CURDATE()
// and date('Y-m-d') always agree. Inside a community, App switches both to the
// community's timezone setting (which the original stored but never applied).
date_default_timezone_set('UTC');

// PSR-4 for TimeBank\ -> src/. Replaces Composer: PHPMailer was never used
// and Stripe is called over its REST API (see PaymentController).
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'TimeBank\\')) {
        $file = TM_ROOT . '/src/' . str_replace('\\', '/', substr($class, 9)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require_once TM_ROOT . '/src/Helpers/functions.php';
