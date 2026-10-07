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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Input is in percent (e.g. 7.65) — store as decimal (0.0765)
    saveUserSettings($userId, [
        'federal_payroll_tax_pct' => (float)post('federal_payroll_tax_pct', '7.65') / 100,
        'state_payroll_tax_pct'   => (float)post('state_payroll_tax_pct',   '3.00') / 100,
        'workers_comp_pct'        => (float)post('workers_comp_pct',        '2.00') / 100,
    ]);

    flashSuccess('Settings saved.');
    redirect('/settings.php');
}

$s            = getUserSettings($userId);
$fedPct       = round($s['federal_payroll_tax_pct']  * 100, 4);
$statePct     = round($s['state_payroll_tax_pct']    * 100, 4);
$wcPct        = round($s['workers_comp_pct']         * 100, 4);
$totalBurden  = round(($s['federal_payroll_tax_pct'] + $s['state_payroll_tax_pct'] + $s['workers_comp_pct']) * 100, 2);

$pageTitle = 'Settings — ProForma';
include PF_ROOT . '/templates/header.php';
?>

<div class="max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Settings</h1>
    <p class="text-sm text-gray-500 mb-8">Configure tax and cost rates applied to employees. These affect instructor and provider cost calculations throughout the app.</p>

    <form method="post" class="space-y-6">
        <?= csrf() ?>

        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-1">Employer Payroll Costs</h2>
            <p class="text-xs text-gray-500 mb-5">
                Applied on top of the base wage for anyone marked as an <strong>Employee</strong>.
                Independent contractors are not affected. These rates are stacked additively —
                a $35/hr employee actually costs $<?= number_format(35 * (1 + $totalBurden / 100), 2) ?>/hr
                at your current settings (<?= $totalBurden ?>% total burden).
            </p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Federal Payroll Tax — Employer FICA
                        <span class="text-xs font-normal text-gray-400 ml-1">(Social Security 6.2% + Medicare 1.45%)</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="federal_payroll_tax_pct"
                               value="<?= h($fedPct) ?>"
                               min="0" max="25" step="0.01"
                               class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right">
                        <span class="text-sm text-gray-500">%</span>
                        <span class="text-xs text-gray-400">Default: 7.65%</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        State Payroll Tax
                        <span class="text-xs font-normal text-gray-400 ml-1">(state unemployment insurance + any state-specific taxes)</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="state_payroll_tax_pct"
                               value="<?= h($statePct) ?>"
                               min="0" max="20" step="0.01"
                               class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right">
                        <span class="text-sm text-gray-500">%</span>
                        <span class="text-xs text-gray-400">Default: 3.00% — varies by state and payroll history</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Workers' Compensation Insurance
                        <span class="text-xs font-normal text-gray-400 ml-1">(as % of gross wages — varies by occupation class code)</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="workers_comp_pct"
                               value="<?= h($wcPct) ?>"
                               min="0" max="30" step="0.01"
                               class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right">
                        <span class="text-sm text-gray-500">%</span>
                        <span class="text-xs text-gray-400">Default: 2.00% — yoga/fitness ~1.5–3%, therapy ~0.5–1.5%</span>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-4 border-t border-gray-100">
                <div class="text-sm text-gray-600">
                    Total employer burden:
                    <strong class="text-indigo-700" id="total-burden"><?= $totalBurden ?>%</strong>
                    above base pay
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-indigo-700 transition-colors text-sm">
                Save settings
            </button>
            <a href="<?= PF_BASE ?>/dashboard.php" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>
    </form>
</div>

<script>
function updateBurden() {
    const fed   = parseFloat(document.querySelector('[name="federal_payroll_tax_pct"]').value) || 0;
    const state = parseFloat(document.querySelector('[name="state_payroll_tax_pct"]').value)   || 0;
    const wc    = parseFloat(document.querySelector('[name="workers_comp_pct"]').value)         || 0;
    document.getElementById('total-burden').textContent = (fed + state + wc).toFixed(2) + '%';
}
document.querySelectorAll('input[type="number"]').forEach(i => i.addEventListener('input', updateBurden));
</script>

<?php include PF_ROOT . '/templates/footer.php'; ?>
