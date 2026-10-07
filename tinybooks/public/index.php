<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TB_ROOT . '/includes/auth.php';

// Signed in: straight to the books. Not signed in: the shared account's sign-in,
// which brings them back here. A first-timer with no company lands on
// companies/create.php from the dashboard, which replaces the old setup wizard.
tl_require_login();
header('Location: ' . APP_URL . '/dashboard.php');
exit;
