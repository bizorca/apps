<?php
/** Sign out is the shared tools account's (it signs out of every tool). */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

header('Location: /account/logout.php');
exit;
