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

// Filters
$from       = $_GET['from'] ?? date('Y-m-01');
$to         = $_GET['to']   ?? date('Y-m-d');
$account_id = (int)($_GET['account_id'] ?? 0);
$search     = trim($_GET['q'] ?? '');

$sql = "
    SELECT t.*, u.name as creator_name
    FROM tb_transactions t
    LEFT JOIN users u ON u.id = t.created_by
    WHERE t.company_id = ?
";
$params = [$company_id];

if ($from)       { $sql .= " AND t.date >= ?"; $params[] = $from; }
if ($to)         { $sql .= " AND t.date <= ?"; $params[] = $to; }
if ($account_id) {
    $sql .= " AND EXISTS (SELECT 1 FROM tb_transaction_lines tl WHERE tl.transaction_id = t.id AND tl.account_id = ?)";
    $params[] = $account_id;
}
if ($search) {
    $sql .= " AND (t.description LIKE ? OR t.reference LIKE ? OR t.memo LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}

$sql .= " ORDER BY t.date DESC, t.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$accounts = get_accounts($company_id);

$page_title = 'Transactions';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold text-gray-900">Transactions</h1>
    <div class="flex gap-2">
        <a href="import.php" class="border border-gray-200 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg hover:bg-gray-50">
            Import CSV
        </a>
        <a href="create.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
            + Add Transaction
        </a>
    </div>
</div>

<!-- Filters -->
<form method="get" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
        <input type="date" name="from" value="<?= h($from) ?>" class="rounded-lg border-gray-300 text-sm px-3 py-1.5 border">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
        <input type="date" name="to" value="<?= h($to) ?>" class="rounded-lg border-gray-300 text-sm px-3 py-1.5 border">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Account</label>
        <select name="account_id" class="rounded-lg border-gray-300 text-sm px-3 py-1.5 border">
            <option value="">All accounts</option>
            <?php foreach ($accounts as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $account_id === $a['id'] ? 'selected' : '' ?>><?= h($a['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Description, ref, memo..."
            class="rounded-lg border-gray-300 text-sm px-3 py-1.5 border w-48">
    </div>
    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-1.5 rounded-lg">
        Filter
    </button>
    <a href="index.php" class="text-sm text-gray-400 hover:underline py-1.5">Clear</a>
</form>

<!-- Transaction table -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <th class="px-4 py-2 text-left w-28">Date</th>
                <th class="px-4 py-2 text-left">Description</th>
                <th class="px-4 py-2 text-left hidden md:table-cell">From</th>
                <th class="px-4 py-2 text-left hidden md:table-cell">To</th>
                <th class="px-4 py-2 text-right">Amount</th>
                <th class="px-4 py-2 text-center w-20">Cleared</th>
                <th class="px-4 py-2 w-12"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php if (empty($transactions)): ?>
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                    No transactions found.
                    <a href="create.php" class="text-indigo-600 hover:underline">Add one.</a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($transactions as $tx):
                $parts  = simple_transaction_parts($tx['id']);
                $amount = $parts['amount'];
                $from_acct = $parts['from'];
                $to_acct   = $parts['to'];
                $is_expense = $to_acct && $to_acct['account_type'] === 'expense';
            ?>
            <tr class="hover:bg-gray-50 group <?= $tx['ai_categorized'] ? 'border-l-2 border-l-purple-200' : '' ?>">
                <td class="px-4 py-2.5 text-gray-500 font-mono text-xs"><?= date('M j, Y', strtotime($tx['date'])) ?></td>
                <td class="px-4 py-2.5">
                    <p class="font-medium text-gray-900"><?= h($tx['description']) ?></p>
                    <?php if ($tx['reference'] || $tx['memo']): ?>
                    <p class="text-xs text-gray-400"><?= h($tx['reference'] ?? '') ?> <?= h($tx['memo'] ?? '') ?></p>
                    <?php endif; ?>
                    <?php if ($tx['ai_categorized']): ?>
                    <span class="text-xs text-purple-500">&#10024; AI categorized</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-2.5 text-gray-500 hidden md:table-cell text-xs"><?= h($from_acct['account_name'] ?? '&mdash;') ?></td>
                <td class="px-4 py-2.5 text-gray-500 hidden md:table-cell text-xs"><?= h($to_acct['account_name'] ?? '&mdash;') ?></td>
                <td class="px-4 py-2.5 text-right font-semibold font-mono <?= $is_expense ? 'text-red-600' : 'text-green-600' ?>">
                    <?= $is_expense ? '-' : '+' ?><?= money($amount) ?>
                </td>
                <td class="px-4 py-2.5 text-center">
                    <?php if ($tx['is_reconciled']): ?>
                    <span class="inline-flex items-center justify-center w-5 h-5 bg-green-100 text-green-600 rounded-full text-xs">&#10003;</span>
                    <?php else: ?>
                    <span class="text-gray-200">&#9675;</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-2.5 text-right">
                    <a href="edit.php?id=<?= $tx['id'] ?>" class="text-gray-300 hover:text-indigo-600 text-xs opacity-0 group-hover:opacity-100">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
