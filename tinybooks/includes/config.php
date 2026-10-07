<?php
/*
 * TinyBooks, as a tool on tools.bizorca.com/tinybooks.
 *
 * TB_ROOT is set by public/_bootstrap.php (or by a CLI script). The account and
 * database come from the shared core in private_html/includes: one users table,
 * one MySQL database, TinyBooks' tables prefixed tb_. The Anthropic key comes
 * from private_html/.env.php.
 */

if (!defined('TB_ROOT')) {
    define('TB_ROOT', dirname(__DIR__));
}

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([TB_ROOT . '/../includes', TB_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

if (!defined('APP_NAME')) {
    define('APP_NAME', 'TinyBooks');
    define('APP_URL', '/tinybooks');   // URL prefix for every absolute link and redirect
    define('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages');
    define('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001');

    // CSRF token lifetime in seconds
    define('CSRF_TTL', 3600);
}
