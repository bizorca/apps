<?php
/**
 * Read-only check of the Zoom Server-to-Server OAuth credentials (PD_ZOOM_*):
 * fetches an access token and GETs /users/me. Creates and changes nothing at
 * Zoom (the token is cached in pd_zoom_token_cache, as the app would).
 *
 *   php pod/bin/zoom-check.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('PD_ROOT', dirname(__DIR__));
require PD_ROOT . '/includes/config.php';

use Bizorca\Pod\Services\ZoomService;

if (!ZoomService::configured()) {
    fwrite(STDERR, "Zoom is not configured: set PD_ZOOM_ACCOUNT_ID, PD_ZOOM_CLIENT_ID and PD_ZOOM_CLIENT_SECRET in private_html/.env.php.\n");
    exit(1);
}

try {
    $me = (new ZoomService())->whoAmI();
    echo 'OK: token issued; meetings will be created as ' . ($me['email'] ?? '(unknown)') . ', account zone ' . ($me['timezone'] ?? '(unset)') . "\n";
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Zoom check failed: ' . $e->getMessage() . "\n");
    exit(1);
}
