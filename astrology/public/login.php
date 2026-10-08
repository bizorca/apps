<?php
// Old address. Signing in now happens on the shared /account pages.
require __DIR__ . '/_bootstrap.php';
$r = (string) ($_GET['redirect'] ?? '');
header('Location: ' . authUrl('login', $r !== '' && $r[0] === '/' ? (str_starts_with($r, BASE_PATH . '/') ? $r : url($r)) : ''), true, 301);
exit;
