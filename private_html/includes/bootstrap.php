<?php
/**
 * Shared core for every tool on tools.bizorca.com. First require on any page
 * that needs the account or the database:
 *
 *   require dirname(__DIR__) . '/private_html/includes/bootstrap.php';   // from public_html/
 *
 * The repo mirrors the server — public_html/ and private_html/ side by side —
 * so that one relative path is right both locally and on Cloudways.
 *
 * Everything shared is tl_-prefixed. Each tool keeps its own helpers (Proforma
 * has its own h(), csrf(), redirect()), and a shared unprefixed name would
 * collide with them.
 */

declare(strict_types=1);

const TL_PRIVATE = __DIR__ . '/..';

/**
 * One session for the whole site, so signing in once covers every tool.
 * Configured here, before anything can call session_start(), so that a tool's
 * own bare session_start() picks up the same name and cookie scope instead of
 * minting a separate PHPSESSID.
 */
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name('tools_session');
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                      || (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'),
    ]);
}

// Cloudways runs Varnish in front of this app and it cached the static landing
// page across a deploy. Anything that boots the core is per-visitor (accounts,
// tool pages), so tell every cache to keep its hands off.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: private, no-store');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
