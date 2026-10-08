<?php
/**
 * Placecard JSON API (the iOS app's backend), now at
 *
 *   https://tools.bizorca.com/placecard/api/?r=/events?city_id=chiang-mai
 *
 * This server has no rewrite to send /placecard/api/events here, so the route
 * rides in ?r=. A route's own query ("?city_id=...") may sit inside r; it is
 * folded back into $_GET. If Cloudways ever adds a try_files fallback to this
 * file, clean paths (/placecard/api/events) work too with no change here.
 *
 * Bearer tokens only: this endpoint never reads or sets a cookie.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require dirname(__DIR__) . '/_bootstrap.php';

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (is_string($_GET['r'] ?? null)) {
    $uri = $_GET['r'];
    if (($q = strpos($uri, '?')) !== false) {
        parse_str(substr($uri, $q + 1), $inner);
        $_GET += $inner;
        $uri = substr($uri, 0, $q);
    }
} else {
    $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $base = PC_BASE . '/api';
    if (str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    if (str_ends_with($uri, '/index.php')) {
        $uri = substr($uri, 0, -strlen('/index.php'));
    }
}
$uri = '/' . trim($uri, '/');
// Same as the original: an /api prefix inside the route is tolerated.
if (str_starts_with($uri, '/api')) {
    $uri = substr($uri, 4) ?: '/';
}

try {
    pc_route($method, $uri);
} catch (PcResponse $r) {
    http_response_code($r->status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($r->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    error_log('Placecard DB error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Throwable $e) {
    error_log('Placecard unhandled: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
