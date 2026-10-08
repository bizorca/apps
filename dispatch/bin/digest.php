<?php
/**
 * Daily digest of upcoming Dispatch deadlines. Run once a day from cron:
 *
 *   php private_html/dispatch/bin/digest.php            send
 *   php private_html/dispatch/bin/digest.php --dry-run  list who would get one, send nothing
 *
 * Weekly-preference users are included on Mondays only (Pacific time, the
 * zone Dispatch runs in). Each user's remind_days_before sets the look-ahead.
 *
 * Deployed to private_html/, outside the web root; the SAPI guard is there so
 * it stays inert if it is ever copied somewhere served.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('DP_ROOT', dirname(__DIR__));
require DP_ROOT . '/includes/config.php';

use Dispatch\Services\NotificationService;

$dryRun      = in_array('--dry-run', $argv, true);
$isMonday    = (int) date('N') === 1;
$preferences = $isMonday ? ['daily', 'weekly'] : ['daily'];

$results = NotificationService::sendDailyDigests($preferences, $dryRun);

$sentCount = 0;
foreach ($results as $r) {
    $status = $dryRun ? 'DRY' : ($r['sent'] ? 'OK' : 'FAIL');
    echo "[{$status}] {$r['user']} — {$r['items']} item(s)\n";
    if ($r['sent']) $sentCount++;
}

echo 'Done. ' . count($results) . ' digest(s) processed, ' . ($dryRun ? '0 sent (dry run).' : $sentCount . ' sent.') . "\n";
