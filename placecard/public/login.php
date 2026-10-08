<?php
/** Sign in is the shared tools account now; come back to the dashboard after. */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

header('Location: /account/login.php?next=' . rawurlencode(PC_BASE . '/dashboard.php'));
exit;
