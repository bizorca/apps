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
$error      = '';

// Start a new reconciliation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $account_id    = (int)$_POST['account_id'];
    $stmt_date     = $_POST['statement_date'] ?? '';
    $ending_bal    = round((float)str_replace(',', '', $_POST['ending_balance'] ?? '0'), 2);

    if (!$account_id || !$stmt_date) {
        $error = 'Account and statement date required.';
    } elseif (!valid_date($stmt_date)) {
        $error = 'Enter the statement date as a real calendar date.';
    } elseif (!accounts_belong_to_company($company_id, $account_id)) {
        $error = 'Choose one of this company\'s accounts.';
    } else {
        // Close any previous in-progress reconciliation for this account
        db()->prepare("UPDATE tb_reconciliations SET status='abandoned' WHERE company_id=? AND account_id=? AND status='in_progress'")
            ->execute([$company_id, $account_id]);

        db()->prepare("INSERT INTO tb_reconciliations (company_id, account_id, statement_date, ending_balance) VALUES (?,?,?,?)")
            ->execute([$company_id, $account_id, $stmt_date, $ending_bal]);
        $rec_id = (int)db()->lastInsertId();

        header('Location: view.php?id=' . $rec_id);
        exit;
    }
}

// List recent reconciliations
$rec_stmt = db()->prepare("
    SELECT r.*, a.name as account_name
    FROM tb_reconciliations r
    JOIN tb_accounts a ON a.id = r.account_id
    WHERE r.company_id = ?
    ORDER BY r.created_at DESC, r.id DESC
    LIMIT 20
");
$rec_stmt->execute([$company_id]);
$reconciliations = $rec_stmt->fetchAll();

$bank_accounts = array_filter(get_accounts($company_id, 'asset'), fn($a) => in_array($a['subtype'], ['bank', 'cash', 'credit_card']));
// Also include liability credit card accounts
$cc_accounts = array_filter(get_accounts($company_id, 'liability'), fn($a) => $a['subtype'] === 'credit_card');
$reconcilable = array_merge(array_values($bank_accounts), array_values($cc_accounts));

$page_title = 'Reconcile';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Reconciliation</h1>
    <p class="text-sm text-gray-500 mb-6">
        Grab your bank statement, enter the ending balance, and check off each transaction that appears on the statement.
        When your cleared balance matches the statement, you're done — and every accountant in the world breathes a sigh of relief.
    </p>

    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-3 text-sm rounded mb-4"><?= h($error) ?></div>
    <?php endif; ?>

    <!-- Start new reconciliation -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">Start a New Reconciliation</h2>
        <form method="post" class="grid grid-cols-3 gap-4 items-end">
            <?= csrf_field() ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account</label>
                <select name="account_id" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                    <option value="">-- select --</option>
                    <?php foreach ($reconcilable as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= h($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statement Date</label>
                <input type="date" name="statement_date" required value="<?= date('Y-m-d') ?>"
                    class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ending Balance</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                    <input type="number" name="ending_balance" required step="0.01"
                        class="w-full rounded-lg border-gray-300 text-sm pl-7 pr-3 py-2 border">
                </div>
            </div>
            <div class="col-span-3">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                    Start Reconciling
                </button>
            </div>
        </form>
    </div>

    <!-- Recent reconciliations -->
    <?php if (!empty($reconciliations)): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-50">
            <h2 class="font-semibold text-gray-900">Recent Reconciliations</h2>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-2 text-left">Account</th>
                    <th class="px-4 py-2 text-left">Statement Date</th>
                    <th class="px-4 py-2 text-right">Ending Balance</th>
                    <th class="px-4 py-2 text-center">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($reconciliations as $r): ?>
                <tr>
                    <td class="px-4 py-2.5 font-medium text-gray-900"><?= h($r['account_name']) ?></td>
                    <td class="px-4 py-2.5 text-gray-500"><?= date('M j, Y', strtotime($r['statement_date'])) ?></td>
                    <td class="px-4 py-2.5 text-right font-mono text-gray-700"><?= money($r['ending_balance']) ?></td>
                    <td class="px-4 py-2.5 text-center">
                        <?php if ($r['status'] === 'completed'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Done</span>
                        <?php elseif ($r['status'] === 'in_progress'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">In progress</span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Abandoned</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        <?php if ($r['status'] === 'in_progress'): ?>
                        <a href="view.php?id=<?= $r['id'] ?>" class="text-indigo-600 hover:underline text-xs">Continue</a>
                        <?php elseif ($r['status'] === 'completed'): ?>
                        <a href="view.php?id=<?= $r['id'] ?>" class="text-gray-400 hover:underline text-xs">View</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
