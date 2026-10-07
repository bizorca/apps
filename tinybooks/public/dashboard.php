<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';

$user    = auth_require();
$company = current_company();

if (!$company) {
    // No company selected — redirect to create one
    header('Location: companies/create.php');
    exit;
}

$company_id = $company['id'];
$today      = date('Y-m-d');
$month_start = date('Y-m-01');
$year_start  = date('Y-01-01');

// Quick summary numbers
$income_accounts  = get_accounts($company_id, 'income');
$expense_accounts = get_accounts($company_id, 'expense');

$income_ytd  = 0;
$expense_ytd = 0;
foreach ($income_accounts  as $a) $income_ytd  += account_balance($a['id'], $year_start, $today);
foreach ($expense_accounts as $a) $expense_ytd += account_balance($a['id'], $year_start, $today);
$net_ytd = $income_ytd - $expense_ytd;

// Month figures
$income_mtd  = 0;
$expense_mtd = 0;
foreach ($income_accounts  as $a) $income_mtd  += account_balance($a['id'], $month_start, $today);
foreach ($expense_accounts as $a) $expense_mtd += account_balance($a['id'], $month_start, $today);

// Recent transactions
$recent = get_transactions($company_id, ['limit' => 8]);

// Bank account balances
$bank_accounts = array_filter(get_accounts($company_id, 'asset'), fn($a) => in_array($a['subtype'], ['bank', 'cash']));

$page_title = 'Dashboard';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900"><?= h($company['name']) ?></h1>
        <p class="text-sm text-gray-500"><?= date('F j, Y') ?></p>
    </div>
    <a href="transactions/create.php"
        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        + Add Transaction
    </a>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Income (YTD)</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= money($income_ytd) ?></p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Expenses (YTD)</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= money($expense_ytd) ?></p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Net (YTD)</p>
        <p class="text-2xl font-bold mt-1 <?= $net_ytd >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= ($net_ytd < 0 ? '-' : '') . money($net_ytd) ?>
        </p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">This Month</p>
        <?php $net_mtd = $income_mtd - $expense_mtd; ?>
        <p class="text-2xl font-bold mt-1 <?= $net_mtd >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= ($net_mtd < 0 ? '-' : '') . money($net_mtd) ?>
        </p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Recent transactions -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Recent Transactions</h2>
            <a href="transactions/index.php" class="text-sm text-indigo-600 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php if (empty($recent)): ?>
            <div class="px-5 py-8 text-center text-gray-400 text-sm">
                No transactions yet. <a href="transactions/create.php" class="text-indigo-600 hover:underline">Add your first one.</a>
            </div>
            <?php else: ?>
            <?php foreach ($recent as $tx):
                $parts = simple_transaction_parts($tx['id']);
                $amount = $parts['amount'];
                $to_acct = $parts['to'];
                $is_expense = $to_acct && $to_acct['account_type'] === 'expense';
            ?>
            <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 group">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= h($tx['description']) ?></p>
                    <p class="text-xs text-gray-400">
                        <?= date('M j', strtotime($tx['date'])) ?>
                        <?php if ($to_acct): ?>&nbsp;&middot;&nbsp;<?= h($to_acct['account_name']) ?><?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-3 ml-4">
                    <span class="text-sm font-semibold <?= $is_expense ? 'text-red-600' : 'text-green-600' ?>">
                        <?= $is_expense ? '-' : '+' ?><?= money($amount) ?>
                    </span>
                    <a href="transactions/edit.php?id=<?= $tx['id'] ?>" class="text-gray-300 hover:text-indigo-600 opacity-0 group-hover:opacity-100 text-xs">Edit</a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bank balances -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-50">
            <h2 class="font-semibold text-gray-900">Account Balances</h2>
        </div>
        <div class="divide-y divide-gray-50">
            <?php if (empty($bank_accounts)): ?>
            <div class="px-5 py-4 text-sm text-gray-400">No bank accounts found.</div>
            <?php else: ?>
            <?php foreach ($bank_accounts as $acct):
                $bal = account_balance($acct['id']);
            ?>
            <div class="px-5 py-3 flex justify-between items-center">
                <div>
                    <p class="text-sm font-medium text-gray-900"><?= h($acct['name']) ?></p>
                    <p class="text-xs text-gray-400"><?= h($acct['account_number'] ?? '') ?></p>
                </div>
                <span class="text-sm font-semibold <?= $bal < 0 ? 'text-red-600' : 'text-gray-900' ?>">
                    <?= money($bal) ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="px-5 py-3 border-t border-gray-50">
            <a href="reconciliation/index.php" class="text-sm text-indigo-600 hover:underline">Reconcile accounts</a>
        </div>
    </div>

</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
