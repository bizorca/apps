<?php
/**
 * Fathom, as a tool on tools.bizorca.com/fathom.
 *
 * A vanilla-PHP rewrite of the Laravel 11 app that ran at fathom.bizorca.com.
 * FM_ROOT is set by public/_bootstrap.php. Account and database come from the
 * shared core in private_html/includes: one users table, one MySQL database,
 * Fathom's tables prefixed fm_.
 */

declare(strict_types=1);

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([FM_ROOT . '/../includes', FM_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

const FM_BASE = '/fathom';

/*
 * URL style. This Cloudways app sends PHP straight from nginx to PHP-FPM and
 * cannot rewrite /fathom/boards/5 to index.php, so routes travel in the query
 * string: /fathom/?r=/boards/5. Every URL is built by fm_url() from this one
 * constant. If Cloudways ever adds `try_files $uri $uri/ /fathom/index.php?$args`
 * for this app, set it to true and every link becomes /fathom/boards/5; the
 * router already accepts both forms.
 */
const FM_CLEAN_URLS = false;

/** Where uploads and exports live: outside the web root, never served directly. */
define('FM_DATA', TL_PRIVATE . '/data/fathom');

const FM_MAGIC_LINK_MINUTES = 15;

// "Support Fathom" pay-what-you-want link; was SUPPORT_URL in the Laravel .env.
define('FM_SUPPORT_URL', (string) tl_env('FM_SUPPORT_URL', '#'));

require_once FM_ROOT . '/includes/vendor/autoload.php';
require_once FM_ROOT . '/includes/helpers.php';
require_once FM_ROOT . '/includes/models.php';
require_once FM_ROOT . '/includes/onboarding.php';
require_once FM_ROOT . '/includes/routes.php';
