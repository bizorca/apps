<?php
/**
 * Serve a course thumbnail from private_html/data/lattice/uploads. Uploads are
 * kept out of the web root (the deploy's --delete would erase them, and nginx
 * executes any .php it finds there), so this is the only way they are read.
 */
require_once __DIR__ . '/_bootstrap.php';

$f    = basename((string)($_GET['f'] ?? ''));
$ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$type = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? null;
$file = LT_UPLOADS . '/' . $f;

if (!$type || !preg_match('/^thumb_[a-z0-9]+\.[a-z]+$/', $f) || !is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $type);
header('Cache-Control: public, max-age=86400');
header_remove('Pragma');
readfile($file);
