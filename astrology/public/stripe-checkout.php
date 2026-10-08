<?php
// Billing is gone: everything is free during early access (see userHasAccess()).
require __DIR__ . '/_bootstrap.php';
header('Location: ' . url('/pricing.php'));
exit;
