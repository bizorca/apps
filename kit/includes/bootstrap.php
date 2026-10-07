<?php
declare(strict_types=1);

/**
 * Single entry point for every page (reached through public/_bootstrap.php).
 * Loads the app, sends the security headers, resumes the session if there is
 * one. The schema is no longer applied here: migrations/ does that, run by
 * private_html/bin/migrate.php on every deploy.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/palette.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/calc.php';
require_once __DIR__ . '/instruments.php';

// These were in public/.htaccess on SiteGround. This Cloudways app sends PHP
// straight from nginx to PHP-FPM and never reads .htaccess, so PHP sends them.
//
// Everything is served from this origin. The one inline <style> block is the
// palette; there is no inline or third-party script at all. Cloudflare Web
// Analytics appends a beacon at the edge on bizorca.com, and script-src 'self'
// blocks it on purpose: client financial data does not need a third-party
// beacon on the page. curl will not show the beacon; only a browser will.
// (Cache-Control: private, no-store comes from the shared core.)
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
}

startSession();

function render(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require dirname(__DIR__) . '/templates/' . $template . '.php';
}

function renderHeader(array $vars = []): void
{
    require_once dirname(__DIR__) . '/templates/partials.php';
    extract($vars, EXTR_SKIP);
    require dirname(__DIR__) . '/templates/header.php';
}

function renderFooter(): void
{
    require dirname(__DIR__) . '/templates/footer.php';
}
