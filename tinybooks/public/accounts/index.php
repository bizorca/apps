<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';

$user    = auth_require();
$company = current_company();
if (!$company) { header('Location: ' . APP_URL . '/companies/create.php'); exit; }

$company_id = $company['id'];
$today      = date('Y-m-d');

$accounts = db()->prepare("SELECT * FROM tb_accounts WHERE company_id = ? ORDER BY account_number, name");
$accounts->execute([$company_id]);
$accounts = $accounts->fetchAll();

$by_type = [];
foreach ($accounts as $a) $by_type[$a['type']][] = $a;

$type_labels = ['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expenses'];

$page_title = 'Chart of Accounts';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Chart of Accounts</h1>
    <a href="create.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
        + Add Account
    </a>
</div>

<?php foreach ($type_labels as $type => $label): ?>
<?php if (empty($by_type[$type])) continue; ?>
<div class="mb-6">
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2"><?= $label ?></h2>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-2 text-left w-24">#</th>
                    <th class="px-4 py-2 text-left">Account Name</th>
                    <th class="px-4 py-2 text-left hidden sm:table-cell">Subtype</th>
                    <th class="px-4 py-2 text-right">Balance</th>
                    <th class="px-4 py-2 text-right w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($by_type[$type] as $acct):
                    $bal = account_balance($acct['id']);
                ?>
                <tr class="hover:bg-gray-50 <?= $acct['is_active'] ? '' : 'opacity-50' ?>">
                    <td class="px-4 py-2 text-gray-400 font-mono"><?= h($acct['account_number'] ?? '') ?></td>
                    <td class="px-4 py-2 font-medium text-gray-900"><?= h($acct['name']) ?></td>
                    <td class="px-4 py-2 text-gray-400 hidden sm:table-cell"><?= h($acct['subtype'] ?? '') ?></td>
                    <td class="px-4 py-2 text-right font-mono <?= $bal < 0 ? 'text-red-500' : 'text-gray-700' ?>"><?= money($bal) ?></td>
                    <td class="px-4 py-2 text-right">
                        <a href="edit.php?id=<?= $acct['id'] ?>" class="text-gray-400 hover:text-indigo-600 text-xs">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php require TB_ROOT . '/includes/footer.php'; ?>
