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
$db     = getDb();

if (empty($_SESSION['pf_business_id'])) redirect('/dashboard.php');
$bid = (int)$_SESSION['pf_business_id'];
$biz = getBusinessById($bid, $userId);
if (!$biz) redirect('/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $db->prepare('DELETE FROM pf_instructors WHERE business_id = ?')->execute([$bid]);

    $names       = $_POST['name']              ?? [];
    $workerTypes = $_POST['worker_type']       ?? [];
    $payTypes    = $_POST['pay_type']          ?? [];
    $flatPays    = $_POST['pay_per_class']     ?? [];
    $sharePcts   = $_POST['revenue_share_pct'] ?? [];
    $cpws        = $_POST['classes_per_week']  ?? [];

    $stmt = $db->prepare(
        'INSERT INTO pf_instructors (business_id, name, worker_type, pay_type, pay_per_class, revenue_share_pct, classes_per_week) VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($names as $i => $name) {
        $name       = trim($name);
        $workerType = in_array($workerTypes[$i] ?? '', ['employee', 'contractor']) ? $workerTypes[$i] : 'contractor';
        $payType    = $payTypes[$i] ?? 'flat';
        $flat       = (float)($flatPays[$i]  ?? 0);
        $share      = isset($sharePcts[$i]) && $sharePcts[$i] !== '' ? (float)$sharePcts[$i] / 100 : null;
        $cpw        = (float)($cpws[$i] ?? 0);
        if ($name === '' && $cpw === 0.0) continue;
        $stmt->execute([$bid, $name ?: 'Instructor', $workerType, $payType, $flat, $share, $cpw]);
    }

    flashSuccess('Instructors saved.');
    redirect('/wizard/report.php');
}

$instructors   = getInstructors($bid);
$settings      = getUserSettings($userId);
$weeksPerMonth = $biz['weeks_per_year'] / 12;
$burdenPct     = ($settings['federal_payroll_tax_pct'] + $settings['state_payroll_tax_pct'] + $settings['workers_comp_pct']) * 100;

$stepStatus  = wizardStepStatus($bid, $biz['business_type']);
$currentStep = 5;
$pageTitle   = 'Step 5: Instructors — ProForma';
include PF_ROOT . '/templates/header.php';
include PF_ROOT . '/templates/wizard_nav.php';
?>

<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-xl font-bold text-gray-900">Step 5 — Instructors</h1>
        <span class="text-sm text-gray-500">Monthly cost: <strong id="total-cost" class="text-gray-900">$0.00</strong></span>
    </div>
    <p class="text-sm text-gray-500 mb-6">
        Flat rate per class or revenue share. Employees incur an additional
        <strong class="text-gray-700"><?= number_format($burdenPct, 2) ?>%</strong> employer burden
        (FICA + state payroll + workers comp) on top of base pay.
        Adjust rates in <a href="<?= PF_BASE ?>/settings.php" class="text-indigo-600 hover:underline">Settings</a>.
    </p>

    <form method="post">
        <?= csrf() ?>

        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-4">
            <div class="text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200 px-4 py-2
                        grid gap-2"
                 style="grid-template-columns: 2fr 110px 1fr 120px 120px 100px auto">
                <span>Name</span>
                <span>Worker type</span>
                <span>Pay type</span>
                <span class="text-right">Flat $/class</span>
                <span class="text-right">Share %</span>
                <span class="text-right">Classes/wk</span>
                <span></span>
            </div>

            <div id="instructor-rows">
                <?php
                $rows = count($instructors) > 0 ? $instructors : [
                    ['name' => 'Instructor', 'worker_type' => 'contractor', 'pay_type' => 'flat', 'pay_per_class' => 35, 'revenue_share_pct' => null, 'classes_per_week' => 5]
                ];
                foreach ($rows as $inst):
                    $isShare      = ($inst['pay_type'] ?? 'flat') === 'revenue_share';
                    $isEmployee   = ($inst['worker_type'] ?? 'contractor') === 'employee';
                    $sharePct     = $inst['revenue_share_pct'] !== null ? round($inst['revenue_share_pct'] * 100, 1) : '';
                ?>
                <div class="instructor-row grid gap-2 items-center px-4 py-3 border-b border-gray-100 last:border-0"
                     style="grid-template-columns: 2fr 110px 1fr 120px 120px 100px auto">
                    <input type="text" name="name[]" value="<?= h($inst['name'] ?? 'Instructor') ?>"
                           class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    <select name="worker_type[]" onchange="updateTotal()"
                            class="worker-type-select border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <option value="contractor" <?= !$isEmployee ? 'selected' : '' ?>>Contractor</option>
                        <option value="employee"   <?= $isEmployee  ? 'selected' : '' ?>>Employee</option>
                    </select>
                    <select name="pay_type[]" onchange="togglePayType(this)"
                            class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <option value="flat"          <?= !$isShare ? 'selected' : '' ?>>Flat rate</option>
                        <option value="revenue_share" <?= $isShare  ? 'selected' : '' ?>>Rev share</option>
                    </select>
                    <div class="relative flat-pay-wrap <?= $isShare ? 'opacity-25' : '' ?>">
                        <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
                        <input type="number" name="pay_per_class[]" value="<?= h($inst['pay_per_class'] ?? 35) ?>"
                               min="0" step="0.01" <?= $isShare ? 'disabled' : '' ?>
                               class="flat-pay-input w-full border border-gray-200 rounded pl-5 pr-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               oninput="updateTotal()">
                    </div>
                    <div class="relative share-wrap <?= !$isShare ? 'opacity-25' : '' ?>">
                        <input type="number" name="revenue_share_pct[]" value="<?= h($sharePct) ?>"
                               min="0" max="100" step="0.1" <?= !$isShare ? 'disabled' : '' ?>
                               class="share-input w-full border border-gray-200 rounded px-2 pr-6 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               oninput="updateTotal()">
                        <span class="absolute right-2 top-1.5 text-gray-400 text-xs">%</span>
                    </div>
                    <input type="number" name="classes_per_week[]" value="<?= h($inst['classes_per_week'] ?? 5) ?>"
                           min="0" step="0.5"
                           class="cpw-input border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full"
                           oninput="updateTotal()">
                    <button type="button" onclick="removeRow(this)"
                            class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="text-xs text-gray-400 mb-4">
            Revenue share % is applied to per-class gross revenue in the report.
            "Employee" adds <?= number_format($burdenPct, 2) ?>% employer burden to the calculated cost.
        </p>

        <div class="flex items-center justify-between mb-6">
            <button type="button" onclick="addRow()"
                    class="text-sm text-indigo-600 border border-indigo-200 px-4 py-2 rounded-lg hover:bg-indigo-50 transition-colors">
                + Add instructor
            </button>
        </div>

        <div class="flex items-center justify-between">
            <a href="<?= PF_BASE ?>/wizard/revenue.php" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <button type="submit" class="bg-green-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                Generate report &rarr;
            </button>
        </div>
    </form>
</div>

<script>
const weeksPerMonth  = <?= number_format($weeksPerMonth, 4) ?>;
const burdenMultiplier = <?= number_format(1 + ($settings['federal_payroll_tax_pct'] + $settings['state_payroll_tax_pct'] + $settings['workers_comp_pct']), 6) ?>;

function togglePayType(select) {
    const row       = select.closest('.instructor-row');
    const flatWrap  = row.querySelector('.flat-pay-wrap');
    const shareWrap = row.querySelector('.share-wrap');
    const flatInp   = row.querySelector('.flat-pay-input');
    const shareInp  = row.querySelector('.share-input');

    if (select.value === 'revenue_share') {
        flatWrap.classList.add('opacity-25');
        flatInp.disabled = true;
        shareWrap.classList.remove('opacity-25');
        shareInp.disabled = false;
    } else {
        flatWrap.classList.remove('opacity-25');
        flatInp.disabled = false;
        shareWrap.classList.add('opacity-25');
        shareInp.disabled = true;
    }
    updateTotal();
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('.instructor-row').forEach(row => {
        const payType    = row.querySelector('[name="pay_type[]"]').value;
        const workerType = row.querySelector('.worker-type-select').value;
        const cpw        = parseFloat(row.querySelector('.cpw-input').value) || 0;
        const multiplier = workerType === 'employee' ? burdenMultiplier : 1.0;
        if (payType === 'flat') {
            const flat = parseFloat(row.querySelector('.flat-pay-input').value) || 0;
            total += flat * cpw * weeksPerMonth * multiplier;
        }
        // Revenue share can't be summed without live revenue data
    });
    document.getElementById('total-cost').textContent =
        '$' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function addRow() {
    const row = document.createElement('div');
    row.className = 'instructor-row grid gap-2 items-center px-4 py-3 border-b border-gray-100 last:border-0';
    row.style.gridTemplateColumns = '2fr 110px 1fr 120px 120px 100px auto';
    row.innerHTML = `
        <input type="text" name="name[]" placeholder="Instructor name"
               class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <select name="worker_type[]" onchange="updateTotal()"
                class="worker-type-select border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <option value="contractor">Contractor</option>
            <option value="employee">Employee</option>
        </select>
        <select name="pay_type[]" onchange="togglePayType(this)"
                class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <option value="flat">Flat rate</option>
            <option value="revenue_share">Rev share</option>
        </select>
        <div class="relative flat-pay-wrap">
            <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
            <input type="number" name="pay_per_class[]" value="35" min="0" step="0.01"
                   class="flat-pay-input w-full border border-gray-200 rounded pl-5 pr-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                   oninput="updateTotal()">
        </div>
        <div class="relative share-wrap opacity-25">
            <input type="number" name="revenue_share_pct[]" value="" min="0" max="100" step="0.1" disabled
                   class="share-input w-full border border-gray-200 rounded px-2 pr-6 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                   oninput="updateTotal()">
            <span class="absolute right-2 top-1.5 text-gray-400 text-xs">%</span>
        </div>
        <input type="number" name="classes_per_week[]" value="5" min="0" step="0.5"
               class="cpw-input border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full"
               oninput="updateTotal()">
        <button type="button" onclick="removeRow(this)"
                class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
    `;
    document.getElementById('instructor-rows').appendChild(row);
    updateTotal();
}

function removeRow(btn) {
    btn.closest('.instructor-row').remove();
    updateTotal();
}

updateTotal();
</script>

<?php include PF_ROOT . '/templates/footer.php'; ?>
