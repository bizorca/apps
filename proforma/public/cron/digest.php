<?php
/**
 * Monthly digest cron endpoint.
 *
 * Schedule in Cloudways → Application → Cron Job Management, e.g. on the 1st of each month at 8am:
 *   0 8 1 * * curl -s "https://tools.bizorca.com/proforma/cron/digest.php?secret=<PF_CRON_SECRET from .env.php>"
 *
 * Can also be triggered manually for testing — append &dry_run=1 to log output without sending.
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

require_once PF_ROOT . '/includes/config.php';
require_once PF_ROOT . '/includes/helpers.php';
require_once PF_ROOT . '/includes/db.php';
require_once PF_ROOT . '/includes/email.php';

// Gate on secret token
$secret = $_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
// An unset secret must lock the endpoint, not open it: hash_equals('', '') is true.
if (CRON_SECRET === '' || !hash_equals(CRON_SECRET, (string)$secret)) {
    http_response_code(403);
    exit('Forbidden');
}

$dryRun     = isset($_GET['dry_run']);
$monthLabel = date('F Y', strtotime('last month'));  // e.g. "May 2025"
$lastMonth  = (int)date('n', strtotime('last month'));
$lastYear   = (int)date('Y', strtotime('last month'));

$sent   = 0;
$errors = 0;
$log    = [];

foreach (getAllUsers() as $user) {
    $userId     = (int)$user['id'];
    $businesses = getUserBusinesses($userId);

    if (empty($businesses)) continue;

    $summaries = [];

    foreach ($businesses as $biz) {
        $bid = (int)$biz['id'];

        try {
            $expenses    = getExpenses($bid);
            $schedules   = getClassSchedules($bid);
            $streams     = getRevenueStreams($bid);
            $instructors = getInstructors($bid);
            $extra       = ['settings' => getUserSettings($userId)];

            if ($biz['business_type'] === 'therapist') {
                $extra['payers']    = getInsurancePayers($bid);
                $extra['codes']     = getCptCodes($bid);
                $extra['rates']     = getPayerRates($bid);
                $extra['providers'] = getStaffProviders($bid);
            }

            $calc   = getCalculator($biz, $expenses, $schedules, $streams, $instructors, $extra);
            $report = $calc->report();

            if (isset($report['error'])) continue; // skip stub verticals

            // Find last month's actuals for this business
            $db     = getDb();
            $stmt   = $db->prepare('SELECT * FROM pf_monthly_actuals WHERE business_id = ? AND year = ? AND month = ? LIMIT 1');
            $stmt->execute([$bid, $lastYear, $lastMonth]);
            $actual = $stmt->fetch() ?: null;

            $summaries[] = [
                'business'   => $biz,
                'report'     => $report,
                'lastActual' => $actual,
            ];
        } catch (\Throwable $e) {
            $log[] = "ERROR business {$bid}: " . $e->getMessage();
            $errors++;
            continue;
        }
    }

    if (empty($summaries)) continue;

    if ($dryRun) {
        $log[] = "DRY RUN — would send digest to {$user['email']} ({$monthLabel}, " . count($summaries) . " businesses)";
        $sent++;
        continue;
    }

    $ok = sendMonthlyDigest($user['email'], $summaries, $monthLabel);
    if ($ok) {
        $log[] = "Sent digest to {$user['email']} (" . count($summaries) . " businesses)";
        $sent++;
    } else {
        $log[] = "FAILED sending to {$user['email']}";
        $errors++;
    }
}

header('Content-Type: text/plain');
echo "ProForma digest — {$monthLabel}\n";
echo "Sent: {$sent}  Errors: {$errors}\n\n";
echo implode("\n", $log) . "\n";
