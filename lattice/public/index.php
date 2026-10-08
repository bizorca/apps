<?php
require_once __DIR__ . '/_bootstrap.php';

// Subdomain root has no landing page of its own — send visitors where they belong.
redirect(is_logged_in() ? APP_URL . '/home.php' : '/account/login.php?next=' . rawurlencode(APP_URL . '/home.php'));
