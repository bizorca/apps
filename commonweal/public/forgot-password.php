<?php
// Accounts are the shared tools account now; this page only forwards there.
require __DIR__ . '/_bootstrap.php';
header('Location: ' . '/account/forgot.php', true, 302);
exit;
