<?php
/**
 * Local development only (never deployed):
 *
 *   php -S 127.0.0.1:8080 dev-router.php
 *
 * Serves public_html/ as the site root and each tool's public/ under /<tool>/,
 * which is how deploy.sh lays them out on Cloudways.
 */

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$root = __DIR__ . '/public_html';

if (preg_match('~^/([a-z0-9-]+)(/.*)?$~', $path, $m) && is_dir(__DIR__ . "/{$m[1]}/public")) {
    $root = __DIR__ . "/{$m[1]}/public";
    $path = $m[2] ?? '/';
}

$file = $root . $path;
if (is_dir($file)) {
    foreach (['index.php', 'index.html'] as $index) {
        if (is_file(rtrim($file, '/') . '/' . $index)) {
            $file = rtrim($file, '/') . '/' . $index;
            break;
        }
    }
}
if (!is_file($file)) {
    http_response_code(404);
    exit('Not found');
}
if (str_ends_with($file, '.php')) {
    $_SERVER['SCRIPT_FILENAME'] = $file;
    chdir(dirname($file));
    require $file;
    return true;
}
$types = ['css' => 'text/css', 'js' => 'application/javascript', 'html' => 'text/html; charset=utf-8', 'svg' => 'image/svg+xml', 'png' => 'image/png'];
header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
readfile($file);
