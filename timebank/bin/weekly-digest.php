<?php
/**
 * Weekly digest for every active community: new offers and requests from the
 * last seven days, to each member who has not switched the digest off.
 *
 *   php timebank/bin/weekly-digest.php [--dry-run]
 *
 * The original had the digest builder (Mailer::buildWeeklyDigest) but nothing
 * ever called it. Cloudways cron, Mondays 15:00 UTC (8am Pacific):
 *   0 15 * * 1 php /home/master/applications/qukjzcxeas/private_html/timebank/bin/weekly-digest.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('TM_ROOT', dirname(__DIR__));
require TM_ROOT . '/includes/config.php';

use TimeBank\Core\Mailer;

$dry = in_array('--dry-run', $argv, true);

$tenants = tl_db()->query('SELECT id, subdomain FROM tm_tenants WHERE is_active = 1 ORDER BY id')->fetchAll();
foreach ($tenants as $t) {
    $r = Mailer::buildWeeklyDigest((int) $t['id'], $dry);
    printf("%-20s recipients %d, sent %d, failed %d%s\n", $t['subdomain'], $r['recipients'], $r['sent'], $r['failed'], $dry ? ' (dry run)' : '');
}
