<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TimeBank\Core\App;
use TimeBank\Core\Response;

// Security headers, moved here from the original .htaccess (this server
// ignores .htaccess). No CSP: pages load Tailwind and Alpine from CDNs and use
// inline scripts, so a policy would need 'unsafe-inline' to work.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

try {
    (new App())->run();
} catch (\Throwable $e) {
    error_log('TimeBank: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        Response::abort(500);
    }
}
