<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';

$user    = auth_require();
$company = current_company();
if (!$company) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$company_id = $company['id'];
$year       = (int)($_GET['year'] ?? date('Y'));
$from       = $_GET['from'] ?? "{$year}-01-01";
$to         = $_GET['to']   ?? "{$year}-12-31";

$income_accounts  = get_accounts($company_id, 'income');
$expense_accounts = get_accounts($company_id, 'expense');

$total_income  = 0;
$total_expense = 0;

foreach ($income_accounts  as &$a) { $a['balance'] = account_balance($a['id'], $from, $to); $total_income  += $a['balance']; }
foreach ($expense_accounts as &$a) { $a['balance'] = account_balance($a['id'], $from, $to); $total_expense += $a['balance']; }
unset($a);

$net = $total_income - $total_expense;
$label = $company['type'] === 'non_profit' ? 'Net Surplus / (Deficit)' : 'Net Income / (Loss)';

$page_title = 'Profit & Loss';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold text-gray-900">
        <?= $company['type'] === 'non_profit' ? 'Statement of Activities' : 'Profit &amp; Loss' ?>
    </h1>
    <form method="get" class="flex gap-2 items-center">
        <input type="date" name="from" value="<?= h($from) ?>" class="rounded-lg border-gray-300 text-sm px-2 py-1.5 border">
        <span class="text-gray-400 text-sm">to</span>
        <input type="date" name="to" value="<?= h($to) ?>" class="rounded-lg border-gray-300 text-sm px-2 py-1.5 border">
        <button type="submit" class="bg-gray-100 text-gray-700 text-sm px-3 py-1.5 rounded-lg hover:bg-gray-200">Run</button>
    </form>
</div>

<p class="text-sm text-gray-500 mb-6"><?= h($company['name']) ?> &mdash; <?= date('M j, Y', strtotime($from)) ?> through <?= date('M j, Y', strtotime($to)) ?></p>

<div class="max-w-2xl space-y-6">

    <!-- Income -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="bg-green-50 px-5 py-3">
            <h2 class="font-semibold text-green-800">Income</h2>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($income_accounts as $a): ?>
                <?php if ($a['balance'] == 0) continue; ?>
                <tr>
                    <td class="px-5 py-2.5 text-gray-700"><?= h($a['account_number'] ? $a['account_number'] . ' · ' : '') ?><?= h($a['name']) ?></td>
                    <td class="px-5 py-2.5 text-right font-mono text-gray-900"><?= money($a['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-green-50">
                    <td class="px-5 py-3 font-semibold text-green-800">Total Income</td>
                    <td class="px-5 py-3 text-right font-bold font-mono text-green-800"><?= money($total_income) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Expenses -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="bg-red-50 px-5 py-3">
            <h2 class="font-semibold text-red-800">Expenses</h2>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($expense_accounts as $a): ?>
                <?php if ($a['balance'] == 0) continue; ?>
                <tr>
                    <td class="px-5 py-2.5 text-gray-700"><?= h($a['account_number'] ? $a['account_number'] . ' · ' : '') ?><?= h($a['name']) ?></td>
                    <td class="px-5 py-2.5 text-right font-mono text-gray-900"><?= money($a['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-red-50">
                    <td class="px-5 py-3 font-semibold text-red-800">Total Expenses</td>
                    <td class="px-5 py-3 text-right font-bold font-mono text-red-800"><?= money($total_expense) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Net -->
    <div class="bg-white rounded-xl border shadow-sm px-5 py-4 flex items-center justify-between <?= $net >= 0 ? 'border-green-200' : 'border-red-200' ?>">
        <span class="font-bold text-lg text-gray-900"><?= h($label) ?></span>
        <span class="font-bold text-xl font-mono <?= $net >= 0 ? 'text-green-700' : 'text-red-700' ?>">
            <?= ($net < 0 ? '(' : '') . money($net) . ($net < 0 ? ')' : '') ?>
        </span>
    </div>

</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
