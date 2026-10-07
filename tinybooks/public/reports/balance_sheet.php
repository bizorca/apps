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
$as_of      = $_GET['as_of'] ?? date('Y-m-d');

$asset_accounts     = get_accounts($company_id, 'asset');
$liability_accounts = get_accounts($company_id, 'liability');
$equity_accounts    = get_accounts($company_id, 'equity');
$income_accounts    = get_accounts($company_id, 'income');
$expense_accounts   = get_accounts($company_id, 'expense');

$total_assets      = 0;
$total_liabilities = 0;
$total_equity      = 0;

foreach ($asset_accounts     as &$a) { $a['balance'] = account_balance($a['id'], null, $as_of); $total_assets      += $a['balance']; }
foreach ($liability_accounts as &$a) { $a['balance'] = account_balance($a['id'], null, $as_of); $total_liabilities += $a['balance']; }
foreach ($equity_accounts    as &$a) { $a['balance'] = account_balance($a['id'], null, $as_of); $total_equity      += $a['balance']; }
unset($a);

// Net income through as_of date rolls into equity
$net_income = 0;
foreach ($income_accounts  as $a) $net_income += account_balance($a['id'], null, $as_of);
foreach ($expense_accounts as $a) $net_income -= account_balance($a['id'], null, $as_of);

$total_equity += $net_income;

$equity_label = $company['type'] === 'non_profit' ? 'Net Assets' : 'Equity';

function report_section(string $title, array $accounts, float $total, string $color = 'gray'): void {
    echo "<div class='bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-4'>
        <div class='bg-{$color}-50 px-5 py-3'><h2 class='font-semibold text-{$color}-800'>{$title}</h2></div>
        <table class='w-full text-sm'><tbody class='divide-y divide-gray-50'>";
    foreach ($accounts as $a) {
        if ($a['balance'] == 0) continue;
        $num = $a['account_number'] ? h($a['account_number']) . ' · ' : '';
        echo "<tr><td class='px-5 py-2.5 text-gray-700'>{$num}" . h($a['name']) . "</td>
              <td class='px-5 py-2.5 text-right font-mono text-gray-900'>" . money($a['balance']) . "</td></tr>";
    }
    echo "</tbody><tfoot><tr class='bg-{$color}-50'>
        <td class='px-5 py-3 font-semibold text-{$color}-800'>Total {$title}</td>
        <td class='px-5 py-3 text-right font-bold font-mono text-{$color}-800'>" . money($total) . "</td>
    </tr></tfoot></table></div>";
}

$page_title = 'Balance Sheet';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold text-gray-900">Balance Sheet</h1>
    <form method="get" class="flex gap-2 items-center">
        <label class="text-sm text-gray-500">As of</label>
        <input type="date" name="as_of" value="<?= h($as_of) ?>" class="rounded-lg border-gray-300 text-sm px-2 py-1.5 border">
        <button type="submit" class="bg-gray-100 text-gray-700 text-sm px-3 py-1.5 rounded-lg hover:bg-gray-200">Run</button>
    </form>
</div>

<p class="text-sm text-gray-500 mb-6"><?= h($company['name']) ?> &mdash; As of <?= date('M j, Y', strtotime($as_of)) ?></p>

<div class="max-w-2xl">

    <?php report_section('Assets', $asset_accounts, $total_assets, 'blue'); ?>
    <?php report_section('Liabilities', $liability_accounts, $total_liabilities, 'orange'); ?>

    <!-- Equity section including net income -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-4">
        <div class="bg-purple-50 px-5 py-3">
            <h2 class="font-semibold text-purple-800"><?= $equity_label ?></h2>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($equity_accounts as $a): ?>
                <?php if ($a['balance'] == 0) continue; ?>
                <tr>
                    <td class="px-5 py-2.5 text-gray-700"><?= h($a['name']) ?></td>
                    <td class="px-5 py-2.5 text-right font-mono text-gray-900"><?= money($a['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($net_income != 0): ?>
                <tr>
                    <td class="px-5 py-2.5 text-gray-700 italic">Current Period Net <?= $company['type'] === 'non_profit' ? 'Surplus' : 'Income' ?></td>
                    <td class="px-5 py-2.5 text-right font-mono <?= $net_income >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= money($net_income) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-purple-50">
                    <td class="px-5 py-3 font-semibold text-purple-800">Total <?= $equity_label ?></td>
                    <td class="px-5 py-3 text-right font-bold font-mono text-purple-800"><?= money($total_equity) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Accounting equation check -->
    <?php $balanced = abs(($total_assets) - ($total_liabilities + $total_equity)) < 0.01; ?>
    <div class="rounded-xl border px-5 py-4 <?= $balanced ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' ?>">
        <div class="flex justify-between items-center">
            <div>
                <p class="font-semibold <?= $balanced ? 'text-green-800' : 'text-red-800' ?>">
                    <?= $balanced ? '&#10003; Balanced' : '&#9888; Out of Balance' ?>
                </p>
                <p class="text-xs <?= $balanced ? 'text-green-600' : 'text-red-600' ?>">Assets = Liabilities + <?= $equity_label ?></p>
            </div>
            <div class="text-right">
                <p class="font-mono font-bold <?= $balanced ? 'text-green-800' : 'text-red-800' ?>"><?= money($total_assets) ?></p>
                <p class="text-xs <?= $balanced ? 'text-green-600' : 'text-red-600' ?>"><?= money($total_liabilities + $total_equity) ?></p>
            </div>
        </div>
    </div>

</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
