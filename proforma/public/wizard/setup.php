<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once PF_ROOT . '/includes/config.php';
require_once PF_ROOT . '/includes/helpers.php';
require_once PF_ROOT . '/includes/auth.php';
require_once PF_ROOT . '/includes/db.php';

startSession();
requireAuth();

$user = currentUser();
$userId = (int)$user['id'];
$db = getDb();

// Require an active business
if (empty($_SESSION['pf_business_id'])) redirect('/dashboard.php');
$bid = (int)$_SESSION['pf_business_id'];
$biz = getBusinessById($bid, $userId);
if (!$biz) redirect('/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name   = trim(post('business_name'));
    $type   = post('business_type', 'yoga');
    $salary = postFloat('owner_salary_annual');
    $weeks  = postInt('weeks_per_year', 50);

    if ($name === '') $name = 'My Studio';
    if (!array_key_exists($type, BUSINESS_TYPES)) $type = 'yoga';
    if ($weeks < 1 || $weeks > 52) $weeks = 50;

    $stmt = $db->prepare(
        'UPDATE pf_businesses SET business_name=?, business_type=?, owner_salary_annual=?, weeks_per_year=?, updated_at=NOW()
         WHERE id=? AND user_id=?'
    );
    $stmt->execute([$name, $type, $salary, $weeks, $bid, $userId]);

    flashSuccess('Setup saved.');
    redirect('/wizard/expenses.php');
}

$stepStatus  = wizardStepStatus($bid, $biz['business_type']);
$currentStep = 1;
$pageTitle   = 'Step 1: Setup — ProForma';
include PF_ROOT . '/templates/header.php';
include PF_ROOT . '/templates/wizard_nav.php';
?>

<div class="max-w-xl">
    <h1 class="text-xl font-bold text-gray-900 mb-1">Step 1 — Business Setup</h1>
    <p class="text-sm text-gray-500 mb-6">Tell us the basics. You can change any of this later.</p>

    <form method="post" class="bg-white border border-gray-200 rounded-xl p-6 space-y-5">
        <?= csrf() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Business name</label>
            <input type="text" name="business_name" value="<?= h($biz['business_name']) ?>" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Business type</label>
            <select name="business_type"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?php foreach (BUSINESS_TYPES as $key => $bt): ?>
                <option value="<?= h($key) ?>" <?= $biz['business_type'] === $key ? 'selected' : '' ?>>
                    <?= h($bt['label']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Insurance billing features are only available for Therapy Practice.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Owner's desired annual salary</label>
            <div class="relative">
                <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                <input type="number" name="owner_salary_annual" value="<?= h($biz['owner_salary_annual']) ?>"
                       min="0" step="1000" placeholder="60000"
                       class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <p class="text-xs text-gray-400 mt-1">Treated as an operating expense the model must cover. Be honest with yourself here.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Operating weeks per year</label>
            <input type="number" name="weeks_per_year" value="<?= h($biz['weeks_per_year']) ?>"
                   min="1" max="52" step="1"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-gray-400 mt-1">50 weeks is typical — accounts for holidays and slow periods.</p>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                Next: Expenses &rarr;
            </button>
        </div>
    </form>
</div>

<?php include PF_ROOT . '/templates/footer.php'; ?>
