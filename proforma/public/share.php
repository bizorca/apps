<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once PF_ROOT . '/includes/config.php';
require_once PF_ROOT . '/includes/helpers.php';
require_once PF_ROOT . '/includes/auth.php';
require_once PF_ROOT . '/includes/db.php';

startSession();

$token = trim($_GET['token'] ?? '');
$biz   = $token !== '' ? getBusinessByToken($token) : null;

if (!$biz) {
    http_response_code(404);
    $pageTitle = 'Report Not Found — ProForma';
    include PF_ROOT . '/templates/header.php';
    ?>
    <div class="max-w-xl mx-auto text-center py-20">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Report not found</h2>
        <p class="text-gray-500 text-sm">This link may have expired or been revoked by the owner.</p>
    </div>
    <?php
    include PF_ROOT . '/templates/footer.php';
    exit;
}

$bid         = (int)$biz['id'];
$expenses    = getExpenses($bid);
$schedules   = getClassSchedules($bid);
$streams     = getRevenueStreams($bid);
$instructors = getInstructors($bid);
$actuals     = getMonthlyActuals($bid);

$extra = ['settings' => getUserSettings((int)$biz['user_id'])];

if ($biz['business_type'] === 'therapist') {
    $extra['payers']    = getInsurancePayers($bid);
    $extra['codes']     = getCptCodes($bid);
    $extra['rates']     = getPayerRates($bid);
    $extra['providers'] = getStaffProviders($bid);
}

$calc = getCalculator($biz, $expenses, $schedules, $streams, $instructors, $extra);
$r    = $calc->report();

if (isset($r['error'])) {
    http_response_code(404);
    $pageTitle = 'Report Unavailable — ProForma';
    include PF_ROOT . '/templates/header.php';
    ?>
    <div class="max-w-xl mx-auto text-center py-20">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Report unavailable</h2>
        <p class="text-gray-500 text-sm">This business type doesn't have a report yet.</p>
    </div>
    <?php
    include PF_ROOT . '/templates/footer.php';
    exit;
}

$isShared  = true;
$pageTitle = 'Pro Forma Report — ' . $biz['business_name'];

include PF_ROOT . '/templates/header.php';

if ($biz['business_type'] === 'therapist') {
    include PF_ROOT . '/templates/report_therapist.php';
    include PF_ROOT . '/templates/footer.php';
    exit;
}

include PF_ROOT . '/templates/report_yoga.php';
include PF_ROOT . '/templates/footer.php';
