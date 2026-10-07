<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once PF_ROOT . '/includes/config.php';
require_once PF_ROOT . '/includes/helpers.php';
require_once PF_ROOT . '/includes/auth.php';
require_once PF_ROOT . '/includes/db.php';

startSession();
requireAuth();

$user   = currentUser();
$userId = (int)$user['id'];

if (empty($_SESSION['pf_business_id'])) redirect('/dashboard.php');
$bid = (int)$_SESSION['pf_business_id'];
$biz = getBusinessById($bid, $userId);
if (!$biz) redirect('/dashboard.php');

// Share token actions (must run before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_action'])) {
    verifyCsrf();
    if ($_POST['share_action'] === 'generate') {
        generateShareToken($bid, $userId);
    } elseif ($_POST['share_action'] === 'revoke') {
        revokeShareToken($bid, $userId);
    }
    redirect('/wizard/report.php');
}

// Reload biz after potential token update
$biz = getBusinessById($bid, $userId);

$expenses    = getExpenses($bid);
$schedules   = getClassSchedules($bid);
$streams     = getRevenueStreams($bid);
$instructors = getInstructors($bid);
$actuals     = getMonthlyActuals($bid);

$extra = ['settings' => getUserSettings($userId)];

if ($biz['business_type'] === 'therapist') {
    $extra['payers']    = getInsurancePayers($bid);
    $extra['codes']     = getCptCodes($bid);
    $extra['rates']     = getPayerRates($bid);
    $extra['providers'] = getStaffProviders($bid);
}

$calc = getCalculator($biz, $expenses, $schedules, $streams, $instructors, $extra);
$r    = $calc->report();

// Stub verticals return an error key — show a polished placeholder
if (isset($r['error'])) {
    $stepStatus  = wizardStepStatus($bid, $biz['business_type']);
    $currentStep = $biz['business_type'] === 'therapist' ? 5 : 6;
    $pageTitle   = 'Report — ProForma';
    include PF_ROOT . '/templates/header.php';
    include PF_ROOT . '/templates/wizard_nav.php';
    $typeLabel = BUSINESS_TYPES[$biz['business_type']]['label'] ?? $biz['business_type'];
    ?>
    <div class="max-w-xl mx-auto text-center py-16">
        <div class="text-5xl mb-6">&#128203;</div>
        <h2 class="text-xl font-bold text-gray-900 mb-2"><?= h($typeLabel) ?> reporting is coming soon.</h2>
        <p class="text-gray-500 text-sm mb-6">
            The <?= h($typeLabel) ?> calculator is on the roadmap. Your data is saved and the wizard is fully functional.
        </p>
        <a href="<?= PF_BASE ?>/dashboard.php" class="text-indigo-600 text-sm hover:underline">&larr; Back to dashboard</a>
    </div>
    <?php
    include PF_ROOT . '/templates/footer.php';
    exit;
}

$stepStatus  = wizardStepStatus($bid, $biz['business_type']);
$currentStep = $biz['business_type'] === 'therapist' ? 5 : 6;
$pageTitle   = 'Pro Forma Report — ' . $biz['business_name'];
$isShared    = false;

include PF_ROOT . '/templates/header.php';
include PF_ROOT . '/templates/wizard_nav.php';

if ($biz['business_type'] === 'therapist') {
    include PF_ROOT . '/templates/report_therapist.php';
    include PF_ROOT . '/templates/footer.php';
    exit;
}

include PF_ROOT . '/templates/report_yoga.php';
include PF_ROOT . '/templates/footer.php';
