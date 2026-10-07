<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once PF_ROOT . '/includes/config.php';
require_once PF_ROOT . '/includes/helpers.php';
require_once PF_ROOT . '/includes/auth.php';
require_once PF_ROOT . '/includes/db.php';

startSession();
requireAuth();

$user   = currentUser();
$userId = (int)$user['id'];
$db     = getDb();

if (empty($_SESSION['pf_business_id'])) redirect('/dashboard.php');
$bid = (int)$_SESSION['pf_business_id'];
$biz = getBusinessById($bid, $userId);
if (!$biz) redirect('/dashboard.php');

// Default to current month/year
$defaultYear  = (int)date('Y');
$defaultMonth = (int)date('n');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (post('action') === 'delete' && !empty($_POST['actual_id'])) {
        deleteMonthlyActual((int)$_POST['actual_id'], $bid);
        flashSuccess('Entry deleted.');
        redirect('/actuals.php');
    }

    $year   = (int)postInt('year',   $defaultYear);
    $month  = (int)postInt('month',  $defaultMonth);
    $year   = max(2020, min(2040, $year));
    $month  = max(1, min(12, $month));

    saveMonthlyActual($bid, $year, $month, [
        'gross_revenue'  => postFloat('gross_revenue'),
        'student_visits' => postInt('student_visits'),
        'total_expenses' => postFloat('total_expenses'),
        'notes'          => post('notes', ''),
    ]);

    flashSuccess('Actuals saved for ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . '.');
    redirect('/actuals.php');
}

// Load existing actuals and the current model projections for comparison
$actuals = getMonthlyActuals($bid, 24);

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

$calc    = getCalculator($biz, $expenses, $schedules, $streams, $instructors, $extra);
$r       = $calc->report();
$projRev = $r['gross_revenue']  ?? 0;
$projExp = $r['operating_cost'] ?? 0;

// Build index of saved actuals by year-month for form pre-population
$actualsIndex = [];
foreach ($actuals as $a) {
    $actualsIndex[$a['year'] . '-' . $a['month']] = $a;
}

// Determine which month to pre-populate in the form
$editYear  = (int)post('year',  $defaultYear);
$editMonth = (int)post('month', $defaultMonth);
$existing  = $actualsIndex[$editYear . '-' . $editMonth] ?? null;

$monthNames = ['January','February','March','April','May','June',
               'July','August','September','October','November','December'];

$pageTitle = 'Log Actuals — ' . h($biz['business_name']);
include PF_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900">Log Monthly Actuals</h1>
        <p class="text-sm text-gray-500 mt-1">
            Record real numbers for <strong><?= h($biz['business_name']) ?></strong> each month.
            Compares against your current model projections (<?= money($projRev) ?>/mo revenue, <?= money($projExp) ?>/mo operating costs).
        </p>
    </div>

    <!-- Entry form -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-8">
        <form method="post" class="space-y-5">
            <?= csrf() ?>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <select name="month" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $editMonth ? 'selected' : '' ?>>
                            <?= $monthNames[$m - 1] ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <select name="year" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            onchange="this.form.submit()">
                        <?php for ($y = $defaultYear - 2; $y <= $defaultYear + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y === $editYear ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <?php if ($existing): ?>
            <div class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                Actuals already logged for <?= $monthNames[$editMonth - 1] ?> <?= $editYear ?>. Saving will overwrite.
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Gross Revenue
                        <span class="text-gray-400 font-normal text-xs ml-1">Projected: <?= money($projRev) ?></span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                        <input type="number" name="gross_revenue" min="0" step="0.01"
                               value="<?= h($existing['gross_revenue'] ?? '') ?>"
                               placeholder="<?= number_format($projRev, 2) ?>"
                               class="w-full border border-gray-300 rounded-lg pl-6 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Total Expenses
                        <span class="text-gray-400 font-normal text-xs ml-1">Projected: <?= money($projExp) ?></span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                        <input type="number" name="total_expenses" min="0" step="0.01"
                               value="<?= h($existing['total_expenses'] ?? '') ?>"
                               placeholder="<?= number_format($projExp, 2) ?>"
                               class="w-full border border-gray-300 rounded-lg pl-6 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Student Visits</label>
                    <input type="number" name="student_visits" min="0" step="1"
                           value="<?= h($existing['student_visits'] ?? '') ?>"
                           placeholder="e.g. 480"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" name="notes" maxlength="255"
                       value="<?= h($existing['notes'] ?? '') ?>"
                       placeholder="e.g. Holiday week, new instructor, promo ran..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors">
                    Save actuals
                </button>
                <a href="<?= PF_BASE ?>/wizard/report.php" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to report</a>
            </div>
        </form>
    </div>

    <!-- History table -->
    <?php if (!empty($actuals)): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <h2 class="text-base font-bold text-gray-800 mb-4">History</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-200">
                        <th class="text-left py-2">Month</th>
                        <th class="text-right py-2">Actual Revenue</th>
                        <th class="text-right py-2">Rev. Variance</th>
                        <th class="text-right py-2">Actual Expenses</th>
                        <th class="text-right py-2">Exp. Variance</th>
                        <th class="text-right py-2">Visits</th>
                        <th class="text-left py-2 pl-4">Notes</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actuals as $a):
                        $revVar = (float)$a['gross_revenue']  - $projRev;
                        $expVar = (float)$a['total_expenses'] - $projExp;
                        $mLabel = ($monthNames[$a['month'] - 1] ?? '?') . ' ' . $a['year'];
                    ?>
                    <tr class="border-b border-gray-50">
                        <td class="py-2 font-medium text-gray-700"><?= h($mLabel) ?></td>
                        <td class="text-right py-2"><?= money($a['gross_revenue']) ?></td>
                        <td class="text-right py-2 font-medium <?= $revVar >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= money($revVar, true) ?>
                        </td>
                        <td class="text-right py-2"><?= money($a['total_expenses']) ?></td>
                        <td class="text-right py-2 font-medium <?= $expVar <= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= money($expVar, true) ?>
                        </td>
                        <td class="text-right py-2"><?= number_format($a['student_visits']) ?></td>
                        <td class="py-2 pl-4 text-gray-400 text-xs"><?= h($a['notes']) ?></td>
                        <td class="py-2 pl-2">
                            <form method="post" class="inline"
                                  onsubmit="return confirm('Delete <?= h($mLabel) ?> entry?')">
                                <?= csrf() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="actual_id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600 transition-colors">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include PF_ROOT . '/templates/footer.php'; ?>
