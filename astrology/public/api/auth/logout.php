<?php
/**
 * DELETE /api/auth/logout
 * Revokes the current API token.
 */
define('AS_API', true);
require dirname(__DIR__, 2) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonError('Method not allowed', 405);
}

// Extract token directly (don't call requireAPIAuth — token may already be expired)
$token = apiBearerToken();
if ($token !== null) {
    getDB()->prepare("DELETE FROM as_api_tokens WHERE token = ?")->execute([$token]);
}

jsonResponse(['ok' => true]);
