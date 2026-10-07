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
if (!$biz || $biz['business_type'] !== 'therapist') redirect('/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $db->prepare('DELETE FROM pf_staff_providers WHERE business_id = ?')->execute([$bid]);

    $names       = $_POST['name']              ?? [];
    $creds       = $_POST['credential']        ?? [];
    $workerTypes = $_POST['worker_type']       ?? [];
    $sessWks     = $_POST['sessions_per_week'] ?? [];
    $hrsWks      = $_POST['hours_per_week']    ?? [];
    $payTypes    = $_POST['pay_type']          ?? [];
    $payRates    = $_POST['pay_rate']          ?? [];
    $isOwners    = $_POST['is_owner']          ?? [];

    $stmt = $db->prepare(
        'INSERT INTO pf_staff_providers (business_id, name, credential, worker_type, sessions_per_week, hours_per_week, pay_type, pay_rate, is_owner)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    foreach ($names as $i => $name) {
        $name       = trim($name);
        if ($name === '') continue;
        $workerType = in_array($workerTypes[$i] ?? '', ['employee', 'contractor']) ? $workerTypes[$i] : 'contractor';
        $stmt->execute([
            $bid,
            $name,
            trim($creds[$i] ?? ''),
            $workerType,
            (float)($sessWks[$i] ?? 0),
            (float)($hrsWks[$i]  ?? 0),
            $payTypes[$i] ?? 'hourly',
            (float)($payRates[$i] ?? 0),
            isset($isOwners[$i]) ? 1 : 0,
        ]);
    }

    flashSuccess('Providers saved.');
    redirect('/wizard/report.php');
}

$providers     = getStaffProviders($bid);
$weeksPerMonth = $biz['weeks_per_year'] / 12;
$settings      = getUserSettings($userId);
$burdenPct     = ($settings['federal_payroll_tax_pct'] + $settings['state_payroll_tax_pct'] + $settings['workers_comp_pct']) * 100;
$burdenMult    = 1 + ($settings['federal_payroll_tax_pct'] + $settings['state_payroll_tax_pct'] + $settings['workers_comp_pct']);

// Load payer data to compute blended rate for the affordability preview
$payers    = getInsurancePayers($bid);
$codes     = getCptCodes($bid);
$rates     = getPayerRates($bid);

// Quick blended rate calc for display
$blendedRate = 0;
$totalSessions = 0;
if (!empty($codes) && !empty($payers)) {
    foreach ($codes as $c) {
        $sessionBlended = 0;
        foreach ($payers as $p) {
            $rate = 0;
            foreach ($rates as $r) {
                if ($r['payer_id'] == $p['id'] && $r['cpt_id'] == $c['id']) {
                    $rate = (float)$r['rate'];
                    break;
                }
            }
            $sessionBlended += $rate * (float)$p['client_pct'];
        }
        $blendedRate   += $sessionBlended * (float)$c['sessions_per_month'];
        $totalSessions += (float)$c['sessions_per_month'];
    }
    $blendedRate = $totalSessions > 0 ? $blendedRate / $totalSessions : 0;
}

$stepStatus  = wizardStepStatus($bid, 'therapist');
$currentStep = 4;
$pageTitle   = 'Step 4: Providers — ProForma';
include PF_ROOT . '/templates/header.php';
include PF_ROOT . '/templates/wizard_nav.php';

$credentials = ['LCSW', 'LMFT', 'LPC', 'LADC', 'PhD', 'PsyD', 'MD', 'APRN', 'MSW', 'Other'];

$defaultProviders = count($providers) > 0 ? $providers
    : (BUSINESS_TYPES['therapist']['provider_defaults'] ?? []);
?>

<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-xl font-bold text-gray-900">Step 4 — Providers &amp; Staff</h1>
        <?php if ($blendedRate > 0): ?>
        <div class="text-xs text-gray-500 text-right">
            Blended reimbursement rate: <strong class="text-indigo-700"><?= money($blendedRate) ?>/session</strong>
        </div>
        <?php endif; ?>
    </div>
    <p class="text-sm text-gray-500 mb-2">
        List every provider in the practice — including yourself. The report calculates what each person costs,
        what revenue they generate, and whether the math holds.
    </p>
    <p class="text-sm text-gray-500 mb-6">
        Employees add a <strong class="text-gray-700"><?= number_format($burdenPct, 2) ?>%</strong> employer burden
        (FICA + state payroll + workers comp) on top of base pay.
        Adjust rates in <a href="<?= PF_BASE ?>/settings.php" class="text-indigo-600 hover:underline">Settings</a>.
    </p>

    <form method="post">
        <?= csrf() ?>

        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-4">
            <div class="grid text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200 px-4 py-2 gap-2"
                 style="grid-template-columns: 2fr 100px 110px 90px 90px 100px 110px auto auto">
                <span>Name</span>
                <span>Credential</span>
                <span>Worker type</span>
                <span class="text-right">Sess/wk</span>
                <span class="text-right">Hrs/wk</span>
                <span>Pay type</span>
                <span class="text-right">Pay rate</span>
                <span class="text-center">Owner?</span>
                <span></span>
            </div>

            <div id="provider-rows">
                <?php foreach ($defaultProviders as $prov): ?>
                <?php $isEmployee = ($prov['worker_type'] ?? 'contractor') === 'employee'; ?>
                <div class="provider-row grid gap-2 items-center px-4 py-3 border-b border-gray-100 last:border-0"
                     style="grid-template-columns: 2fr 100px 110px 90px 90px 100px 110px auto auto">
                    <input type="text" name="name[]" value="<?= h($prov['name'] ?? '') ?>"
                           class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    <select name="credential[]"
                            class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <?php foreach ($credentials as $cr): ?>
                        <option value="<?= h($cr) ?>" <?= ($prov['credential'] ?? '') === $cr ? 'selected' : '' ?>><?= h($cr) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="worker_type[]" onchange="updateCosts()"
                            class="worker-type-select border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <option value="contractor" <?= !$isEmployee ? 'selected' : '' ?>>Contractor</option>
                        <option value="employee"   <?= $isEmployee  ? 'selected' : '' ?>>Employee</option>
                    </select>
                    <input type="number" name="sessions_per_week[]" value="<?= h($prov['sessions_per_week'] ?? 20) ?>"
                           min="0" step="0.5"
                           class="sessions-input border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full"
                           oninput="updateCosts()">
                    <input type="number" name="hours_per_week[]" value="<?= h($prov['hours_per_week'] ?? 25) ?>"
                           min="0" step="0.5"
                           class="hours-input border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full"
                           oninput="updateCosts()">
                    <select name="pay_type[]" onchange="togglePayLabel(this)"
                            class="pay-type-select border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <option value="hourly"     <?= ($prov['pay_type'] ?? 'hourly') === 'hourly'      ? 'selected' : '' ?>>Hourly</option>
                        <option value="per_session"<?= ($prov['pay_type'] ?? '') === 'per_session'       ? 'selected' : '' ?>>Per session</option>
                        <option value="salary"     <?= ($prov['pay_type'] ?? '') === 'salary'            ? 'selected' : '' ?>>Monthly salary</option>
                    </select>
                    <div class="relative">
                        <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
                        <input type="number" name="pay_rate[]" value="<?= h($prov['pay_rate'] ?? 0) ?>"
                               min="0" step="0.01"
                               class="pay-rate-input w-full border border-gray-200 rounded pl-5 pr-1 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               oninput="updateCosts()">
                    </div>
                    <label class="flex items-center justify-center cursor-pointer">
                        <input type="checkbox" name="is_owner[<?= uniqid() ?>]"
                               <?= !empty($prov['is_owner']) ? 'checked' : '' ?>
                               class="rounded border-gray-300 text-indigo-600">
                    </label>
                    <button type="button" onclick="removeRow(this)"
                            class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Live cost summary -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm text-gray-700 mb-4" id="cost-summary">
            &nbsp;
        </div>

        <p class="text-xs text-gray-400 mb-4">
            "Owner?" marks this provider as the practice owner — their compensation comes from the owner salary set in Step 1, not from provider pay.
            Revenue-share and production-based models can be entered as per-session pay.
        </p>

        <div class="flex items-center justify-between mb-6">
            <button type="button" onclick="addRow()"
                    class="text-sm text-indigo-600 border border-indigo-200 px-4 py-2 rounded-lg hover:bg-indigo-50">
                + Add provider
            </button>
        </div>

        <div class="flex items-center justify-between">
            <a href="<?= PF_BASE ?>/wizard/insurance.php" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <button type="submit" class="bg-green-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                Generate report &rarr;
            </button>
        </div>
    </form>
</div>

<script>
const weeksPerMonth  = <?= number_format($weeksPerMonth, 4) ?>;
const blendedPerSess = <?= number_format($blendedRate, 4) ?>;
const burdenMult     = <?= number_format($burdenMult, 6) ?>;

function togglePayLabel(select) { updateCosts(); }

function updateCosts() {
    let totalMonthlyCost = 0, totalSessions = 0, totalRevenue = 0;
    document.querySelectorAll('.provider-row').forEach(row => {
        const payType    = row.querySelector('.pay-type-select').value;
        const workerType = row.querySelector('.worker-type-select')?.value ?? 'contractor';
        const rate       = parseFloat(row.querySelector('.pay-rate-input').value) || 0;
        const hours      = parseFloat(row.querySelector('.hours-input').value)    || 0;
        const sess       = parseFloat(row.querySelector('.sessions-input').value) || 0;
        const mult       = workerType === 'employee' ? burdenMult : 1.0;

        let baseCost = 0;
        if (payType === 'hourly')           baseCost = rate * hours * weeksPerMonth;
        else if (payType === 'per_session') baseCost = rate * sess  * weeksPerMonth;
        else if (payType === 'salary')      baseCost = rate;

        totalMonthlyCost += baseCost * mult;
        totalSessions    += sess * weeksPerMonth;
        totalRevenue     += sess * weeksPerMonth * blendedPerSess;
    });

    const pct = totalRevenue > 0 ? (totalMonthlyCost / totalRevenue * 100).toFixed(1) : '—';
    const fmt = n => n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});

    document.getElementById('cost-summary').innerHTML =
        `Staff monthly cost (incl. burden): <strong>$${fmt(totalMonthlyCost)}</strong>
         &ensp;|&ensp; Sessions/month: <strong>${Math.round(totalSessions)}</strong>
         &ensp;|&ensp; Est. revenue generated: <strong>$${fmt(totalRevenue)}</strong>
         &ensp;|&ensp; Staff cost as % of revenue: <strong>${pct}%</strong>`;
}

function addRow() {
    const credOptions = <?= json_encode(array_map(fn($c) => "<option value=\"$c\">$c</option>", $credentials)) ?>.join('');
    const row = document.createElement('div');
    row.className = 'provider-row grid gap-2 items-center px-4 py-3 border-b border-gray-100 last:border-0';
    row.style.gridTemplateColumns = '2fr 100px 110px 90px 90px 100px 110px auto auto';
    row.innerHTML = `
        <input type="text" name="name[]" placeholder="Provider name"
               class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <select name="credential[]"
                class="border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
            ${credOptions}
        </select>
        <select name="worker_type[]" onchange="updateCosts()"
                class="worker-type-select border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <option value="contractor">Contractor</option>
            <option value="employee">Employee</option>
        </select>
        <input type="number" name="sessions_per_week[]" value="20" min="0" step="0.5"
               class="sessions-input border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full"
               oninput="updateCosts()">
        <input type="number" name="hours_per_week[]" value="25" min="0" step="0.5"
               class="hours-input border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full"
               oninput="updateCosts()">
        <select name="pay_type[]" onchange="togglePayLabel(this)"
                class="pay-type-select border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <option value="hourly">Hourly</option>
            <option value="per_session">Per session</option>
            <option value="salary">Monthly salary</option>
        </select>
        <div class="relative">
            <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
            <input type="number" name="pay_rate[]" value="0" min="0" step="0.01"
                   class="pay-rate-input w-full border border-gray-200 rounded pl-5 pr-1 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                   oninput="updateCosts()">
        </div>
        <label class="flex items-center justify-center cursor-pointer">
            <input type="checkbox" name="is_owner[new_${Date.now()}]"
                   class="rounded border-gray-300 text-indigo-600">
        </label>
        <button type="button" onclick="removeRow(this)"
                class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
    `;
    document.getElementById('provider-rows').appendChild(row);
    updateCosts();
}

function removeRow(btn) {
    btn.closest('.provider-row').remove();
    updateCosts();
}

updateCosts();
</script>

<?php include PF_ROOT . '/templates/footer.php'; ?>
