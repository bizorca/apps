<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';

$user    = auth_require();
$company = current_company();
if (!$company) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM tb_transactions WHERE id = ? AND company_id = ?");
$stmt->execute([$id, $company['id']]);
$tx = $stmt->fetch();
if (!$tx) { header('Location: index.php'); exit; }

$lines    = get_transaction_lines($id);
$parts    = simple_transaction_parts($id);
$accounts = get_accounts($company['id']);
$is_double = $company['accounting_method'] === 'double';
$error = '';

// Lines that a reconciliation has touched cannot be deleted or rewritten: the
// reconciliation keeps pointing at them. The original rewrote them anyway and
// died on the foreign key. Here the header fields stay editable and anything
// that would change the lines is refused with a reason.
// Only a tick that is still on locks it: un-ticking in the reconciliation
// leaves an is_cleared = 0 row behind, which forget_unticked() clears away
// before the lines are rewritten or deleted.
$in_recon = db()->prepare("SELECT COUNT(*) FROM tb_reconciliation_items ri JOIN tb_transaction_lines tl ON tl.id = ri.transaction_line_id WHERE tl.transaction_id = ? AND ri.is_cleared = 1");
$in_recon->execute([$id]);
$in_recon = (int)$in_recon->fetchColumn() > 0;
$locked   = $in_recon || $tx['is_reconciled'];

function forget_unticked(int $tx_id): void {
    db()->prepare("DELETE ri FROM tb_reconciliation_items ri JOIN tb_transaction_lines tl ON tl.id = ri.transaction_line_id WHERE tl.transaction_id = ? AND ri.is_cleared = 0")
        ->execute([$tx_id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['delete'])) {
        if ($locked) {
            flash_set('error', 'This transaction has been checked off in a reconciliation. Un-check it there before deleting it.');
            header('Location: edit.php?id=' . $id); exit;
        }
        forget_unticked($id);
        db()->prepare("DELETE FROM tb_transactions WHERE id = ?")->execute([$id]);
        flash_set('success', 'Transaction deleted.');
        header('Location: index.php'); exit;
    }

    $date   = $_POST['date'] ?? $tx['date'];
    $desc   = trim($_POST['description'] ?? '');
    $ref    = trim($_POST['reference'] ?? '');
    $memo   = trim($_POST['memo'] ?? '');
    $amount = round((float)str_replace(',', '', $_POST['amount'] ?? '0'), 2);

    if ($is_double) {
        $from_id = (int)($_POST['from_account_id'] ?? 0);
        $to_id   = (int)($_POST['to_account_id'] ?? 0);
    } else {
        $bank_id     = (int)($_POST['bank_account_id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $tx_type     = $_POST['tx_type'] ?? 'expense';
    }

    if (!$date || !$desc || $amount <= 0) {
        $error = 'Date, description, and amount are required.';
    } elseif (!valid_date($date)) {
        $error = 'Enter the date as a real calendar date.';
    } elseif ($is_double && (!$from_id || !$to_id || $from_id === $to_id)) {
        $error = 'Select two different accounts.';
    } elseif (!$is_double && (!$bank_id || !$category_id)) {
        $error = 'Bank account and category required.';
    } elseif (!accounts_belong_to_company((int)$company['id'], ...($is_double ? [$from_id, $to_id] : [$bank_id, $category_id]))) {
        // The account lists only offer this company's accounts; anything else was hand-crafted.
        $error = 'Choose accounts from this company.';
    } elseif ($locked && !lines_unchanged($id, $is_double, $amount, $is_double ? [$from_id, $to_id] : [$bank_id, $category_id, $tx_type])) {
        $error = 'This transaction has been checked off in a reconciliation, so its amount and accounts are locked. You can still change the date, description, reference and memo, or un-check it in the reconciliation first.';
    } elseif ($locked) {
        db()->prepare("UPDATE tb_transactions SET date=?, description=?, reference=?, memo=? WHERE id=?")
            ->execute([$date, $desc, $ref ?: null, $memo ?: null, $id]);
        flash_set('success', 'Transaction updated.');
        header('Location: index.php'); exit;
    } else {
        $pdo = db();
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE tb_transactions SET date=?, description=?, reference=?, memo=? WHERE id=?")
            ->execute([$date, $desc, $ref ?: null, $memo ?: null, $id]);

        forget_unticked($id);
        $pdo->prepare("DELETE FROM tb_transaction_lines WHERE transaction_id = ?")->execute([$id]);

        if ($is_double) {
            $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, credit) VALUES (?,?,?)")
                ->execute([$id, $from_id, $amount]);
            $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, debit) VALUES (?,?,?)")
                ->execute([$id, $to_id, $amount]);
        } else {
            if ($tx_type === 'income') {
                $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, debit) VALUES (?,?,?)")
                    ->execute([$id, $bank_id, $amount]);
                $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, credit) VALUES (?,?,?)")
                    ->execute([$id, $category_id, $amount]);
            } else {
                $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, credit) VALUES (?,?,?)")
                    ->execute([$id, $bank_id, $amount]);
                $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, debit) VALUES (?,?,?)")
                    ->execute([$id, $category_id, $amount]);
            }
        }

        $pdo->commit();
        flash_set('success', 'Transaction updated.');
        header('Location: index.php'); exit;
    }
}

// Determine current values for form
$current_amount  = $parts['amount'] ?: 0;
$current_from_id = $parts['from']['account_id'] ?? '';
$current_to_id   = $parts['to']['account_id'] ?? '';

$bank_accounts    = array_filter($accounts, fn($a) => in_array($a['subtype'], ['bank', 'cash', 'credit_card']));
$income_accounts  = array_filter($accounts, fn($a) => $a['type'] === 'income');
$expense_accounts = array_filter($accounts, fn($a) => $a['type'] === 'expense');

// Determine single-entry type from existing lines
$se_type    = 'expense';
$se_bank_id = '';
$se_cat_id  = '';
if (!$is_double && $parts['from'] && $parts['to']) {
    $from_type = $parts['from']['account_type'];
    $to_type   = $parts['to']['account_type'];
    if ($to_type === 'income') {
        $se_type    = 'income';
        $se_bank_id = $parts['from']['account_id'];
        $se_cat_id  = $parts['to']['account_id'];
    } else {
        $se_type    = 'expense';
        $se_bank_id = $parts['from']['account_id'];
        $se_cat_id  = $parts['to']['account_id'];
    }
}

$page_title = 'Edit Transaction';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Transaction</h1>

    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-3 text-sm rounded mb-4"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6">
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="date" required value="<?= h($_POST['date'] ?? $tx['date']) ?>"
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                        <input type="number" name="amount" required step="0.01" min="0.01"
                            value="<?= h($_POST['amount'] ?? number_format($current_amount, 2)) ?>"
                            class="w-full rounded-lg border-gray-300 text-sm pl-7 pr-3 py-2 border">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input type="text" name="description" required value="<?= h($_POST['description'] ?? $tx['description']) ?>"
                    class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
            </div>

            <?php if ($is_double): ?>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Money came from</label>
                    <select name="from_account_id" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="">-- select --</option>
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($_POST['from_account_id'] ?? $current_from_id) == $a['id'] ? 'selected' : '' ?>>
                            <?= h($a['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Money went to</label>
                    <select name="to_account_id" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="">-- select --</option>
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($_POST['to_account_id'] ?? $current_to_id) == $a['id'] ? 'selected' : '' ?>>
                            <?= h($a['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php else: ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="tx_type" value="expense" <?= ($_POST['tx_type'] ?? $se_type) === 'expense' ? 'checked' : '' ?> class="text-indigo-600">
                        Expense
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="tx_type" value="income" <?= ($_POST['tx_type'] ?? $se_type) === 'income' ? 'checked' : '' ?> class="text-indigo-600">
                        Income
                    </label>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account</label>
                    <select name="bank_account_id" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="">-- select --</option>
                        <?php foreach ($bank_accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($_POST['bank_account_id'] ?? $se_bank_id) == $a['id'] ? 'selected' : '' ?>><?= h($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="">-- select --</option>
                        <optgroup label="Income">
                            <?php foreach ($income_accounts as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= ($_POST['category_id'] ?? $se_cat_id) == $a['id'] ? 'selected' : '' ?>><?= h($a['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Expenses">
                            <?php foreach ($expense_accounts as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= ($_POST['category_id'] ?? $se_cat_id) == $a['id'] ? 'selected' : '' ?>><?= h($a['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference #</label>
                    <input type="text" name="reference" value="<?= h($_POST['reference'] ?? $tx['reference'] ?? '') ?>"
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Memo</label>
                    <input type="text" name="memo" value="<?= h($_POST['memo'] ?? $tx['memo'] ?? '') ?>"
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                    Save Changes
                </button>
                <a href="index.php" class="text-sm text-gray-500 hover:underline px-3 py-2">Cancel</a>
                <?php if (!$locked): ?>
                <button type="submit" name="delete" value="1"
                    onclick="return confirm('Delete this transaction? This cannot be undone.')"
                    class="ml-auto text-red-500 hover:text-red-700 text-sm px-3 py-2">
                    Delete
                </button>
                <?php else: ?>
                <span class="ml-auto text-xs text-gray-400 py-2">Reconciled — cannot delete</span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
