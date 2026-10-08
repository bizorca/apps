<?php
/*
 * Lattice, as a tool on tools.bizorca.com/lattice.
 *
 * LT_ROOT is set by public/_bootstrap.php (or by a CLI script). The account and
 * database come from the shared core in private_html/includes: one users table,
 * one MySQL database, Lattice's tables prefixed lt_. Lattice has no secrets of
 * its own; the old config.php's database credentials are gone with it.
 */

if (!defined('LT_ROOT')) {
    define('LT_ROOT', dirname(__DIR__));
}

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([LT_ROOT . '/../includes', LT_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

define('APP_URL',  '/lattice');   // URL prefix for every link and redirect
define('APP_NAME', 'Lattice LMS');

// Scoring
define('PASS_THRESHOLD',         70.0);
define('MILESTONE_WEIGHT',       3);
define('MAX_CHALLENGE_ATTEMPTS', 2);   // declared by the original, never enforced (see CLAUDE.md)

// Course thumbnails live with the server-only data, not in the web root: the
// deploy's --delete would wipe them from public_html, and nginx would happily
// execute a .php that slipped into an uploads folder there. thumb.php serves them.
define('LT_UPLOADS', TL_PRIVATE . '/data/lattice/uploads');
