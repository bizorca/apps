<?php
declare(strict_types=1);

/**
 * Advisor Field Kit, as a tool on tools.bizorca.com/kit.
 *
 * KIT_ROOT is set by public/_bootstrap.php (the CLI test sets nothing, so it
 * falls back to this folder's parent). Account and database come from the
 * shared core in private_html/includes: one users table, one MySQL database,
 * Kit's tables prefixed kit_. Bizorca SSO is gone; the shared account
 * replaced it.
 */

if (!defined('KIT_ROOT')) {
    define('KIT_ROOT', dirname(__DIR__));
}

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([KIT_ROOT . '/../includes', KIT_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

const APP_NAME    = 'Advisor Field Kit';
const APP_TAGLINE = 'Diagnose before prescribing';
const APP_DESC    = 'Assessment instruments for small business advisors working with micro-businesses and solo practitioners.';
const KIT_BASE    = '/kit';                                   // URL prefix for every link and redirect
const APP_ORIGIN  = 'https://tools.bizorca.com' . KIT_BASE;   // printed on the client packet

/** The shared MySQL handle, under the name Kit's accessors already use. */
function getDb(): PDO
{
    return tl_db();
}
