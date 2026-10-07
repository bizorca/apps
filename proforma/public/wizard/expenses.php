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

    // Delete all existing rows for this business, then re-insert
    $db->prepare('DELETE FROM pf_expenses WHERE business_id = ?')->execute([$bid]);

    $categories = $_POST['category']     ?? [];
    $labels     = $_POST['label']        ?? [];
    $amounts    = $_POST['amount']       ?? [];
    $variables  = $_POST['is_variable']  ?? [];

    $stmt = $db->prepare(
        'INSERT INTO pf_expenses (business_id, category, label, amount_monthly, is_variable, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($categories as $i => $cat) {
        $label  = trim($labels[$i] ?? '');
        $amount = (float)($amounts[$i] ?? 0);
        if ($label === '' && $amount == 0) continue;
        $isVar  = isset($variables[$i]) ? 1 : 0;
        $stmt->execute([$bid, $cat, $label ?: 'Expense', $amount, $isVar, $i]);
    }

    flashSuccess('Expenses saved.');
    $next = $biz['business_type'] === 'therapist' ? '/wizard/insurance.php' : '/wizard/classes.php';
    redirect($next);
}

$expenses    = getExpenses($bid);
$stepStatus  = wizardStepStatus($bid, $biz['business_type']);
$currentStep = 2;
$pageTitle   = 'Step 2: Expenses — ProForma';
include PF_ROOT . '/templates/header.php';
include PF_ROOT . '/templates/wizard_nav.php';

$categoryOptions = [
    'rent'      => 'Rent / Lease',
    'utilities' => 'Utilities',
    'insurance' => 'Insurance',
    'software'  => 'Software / Subscriptions',
    'marketing' => 'Marketing / Advertising',
    'cleaning'  => 'Cleaning / Maintenance',
    'supplies'  => 'Supplies',
    'payroll'   => 'Payroll / HR',
    'other'     => 'Other',
];
?>

<div class="max-w-3xl">
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-xl font-bold text-gray-900">Step 2 — Monthly Overhead</h1>
        <span class="text-sm text-gray-500" id="monthly-total-label">Monthly total: <strong id="monthly-total">$0.00</strong></span>
    </div>
    <p class="text-sm text-gray-500 mb-6">These are your fixed costs — the floor your revenue has to clear before you make anything.</p>

    <form method="post">
        <?= csrf() ?>

        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-4">
            <div class="grid grid-cols-[1fr_2fr_140px_auto_auto] gap-0 text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200 px-4 py-2">
                <span>Category</span>
                <span>Label</span>
                <span class="text-right">Monthly amount</span>
                <span class="text-center">Variable?</span>
                <span></span>
            </div>

            <div id="expense-rows">
                <?php
                $rows = count($expenses) > 0 ? $expenses : (BUSINESS_TYPES[$biz['business_type']]['expense_defaults'] ?? []);
                foreach ($rows as $i => $row):
                ?>
                <div class="expense-row grid grid-cols-[1fr_2fr_140px_auto_auto] gap-0 items-center px-4 py-2 border-b border-gray-100 last:border-0">
                    <select name="category[]"
                            class="border border-gray-200 rounded px-2 py-1 text-sm mr-2 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <?php foreach ($categoryOptions as $val => $lbl): ?>
                        <option value="<?= h($val) ?>" <?= ($row['category'] ?? '') === $val ? 'selected' : '' ?>>
                            <?= h($lbl) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="label[]" value="<?= h($row['label'] ?? '') ?>"
                           placeholder="Description"
                           class="border border-gray-200 rounded px-2 py-1 text-sm mr-2 focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
                    <div class="relative mr-2">
                        <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
                        <input type="number" name="amount[]" value="<?= h($row['amount_monthly'] ?? 0) ?>"
                               min="0" step="1" placeholder="0"
                               class="amount-input w-full border border-gray-200 rounded pl-5 pr-2 py-1 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400">
                    </div>
                    <label class="flex items-center justify-center cursor-pointer" title="Scales with class volume">
                        <input type="checkbox" name="is_variable[<?= $i ?>]"
                               <?= !empty($row['is_variable']) ? 'checked' : '' ?>
                               class="rounded border-gray-300 text-indigo-600">
                    </label>
                    <button type="button" onclick="removeRow(this)"
                            class="ml-2 text-red-400 hover:text-red-600 text-lg leading-none px-1" title="Remove row">
                        &times;
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center justify-between mb-6">
            <button type="button" onclick="addRow()"
                    class="text-sm text-indigo-600 border border-indigo-200 px-4 py-2 rounded-lg hover:bg-indigo-50 transition-colors">
                + Add expense
            </button>
            <p class="text-xs text-gray-400">"Variable" expenses scale with class volume in the report.</p>
        </div>

        <div class="flex items-center justify-between">
            <a href="<?= PF_BASE ?>/wizard/setup.php" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                Next: Classes &rarr;
            </button>
        </div>
    </form>
</div>

<template id="expense-row-template">
    <div class="expense-row grid grid-cols-[1fr_2fr_140px_auto_auto] gap-0 items-center px-4 py-2 border-b border-gray-100 last:border-0">
        <select name="category[]"
                class="border border-gray-200 rounded px-2 py-1 text-sm mr-2 focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <?php foreach ($categoryOptions as $val => $lbl): ?>
            <option value="<?= h($val) ?>"><?= h($lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="label[]" placeholder="Description"
               class="border border-gray-200 rounded px-2 py-1 text-sm mr-2 focus:outline-none focus:ring-1 focus:ring-indigo-400 w-full">
        <div class="relative mr-2">
            <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
            <input type="number" name="amount[]" value="0" min="0" step="1"
                   class="amount-input w-full border border-gray-200 rounded pl-5 pr-2 py-1 text-sm text-right focus:outline-none focus:ring-1 focus:ring-indigo-400">
        </div>
        <label class="flex items-center justify-center cursor-pointer" title="Scales with class volume">
            <input type="checkbox" name="is_variable[]" class="rounded border-gray-300 text-indigo-600">
        </label>
        <button type="button" onclick="removeRow(this)"
                class="ml-2 text-red-400 hover:text-red-600 text-lg leading-none px-1" title="Remove row">
            &times;
        </button>
    </div>
</template>

<script>
function addRow() {
    const tpl = document.getElementById('expense-row-template');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('expense-rows').appendChild(clone);
    // Fix checkbox name indices
    reindexCheckboxes();
    bindAmountListeners();
    updateTotal();
}

function removeRow(btn) {
    btn.closest('.expense-row').remove();
    reindexCheckboxes();
    updateTotal();
}

function reindexCheckboxes() {
    document.querySelectorAll('#expense-rows .expense-row').forEach((row, i) => {
        const cb = row.querySelector('input[type=checkbox]');
        if (cb) cb.name = `is_variable[${i}]`;
    });
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('.amount-input').forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });
    document.getElementById('monthly-total').textContent =
        '$' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function bindAmountListeners() {
    document.querySelectorAll('.amount-input').forEach(inp => {
        inp.removeEventListener('input', updateTotal);
        inp.addEventListener('input', updateTotal);
    });
}

bindAmountListeners();
updateTotal();
</script>

<?php include PF_ROOT . '/templates/footer.php'; ?>
