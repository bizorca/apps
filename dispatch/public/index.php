<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Dispatch\Core\App;
use Dispatch\Core\View;

// Security headers. The original set none in PHP and relied on .htaccess for
// its deny rules; this server ignores .htaccess, and nothing private is under
// public/ any more. No CSP: the pages load Tailwind and Google Fonts from CDNs
// and use inline scripts, so a policy would need 'unsafe-inline' to work.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    $app = new App();
    $app->run();
} catch (\Throwable $e) {
    error_log('Dispatch: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!headers_sent()) {
        View::render('errors/500', ['title' => 'Server Error'], 'app');
    }
}
