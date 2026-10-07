<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';
require_once TB_ROOT . '/includes/haiku.php';

$user    = auth_require();
$company = current_company();
if (!$company) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$company_id = $company['id'];
$is_double  = $company['accounting_method'] === 'double';
$accounts   = get_accounts($company_id);
$error      = '';
$ai_suggestion = null;
$duplicate_warning = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $date   = $_POST['date'] ?? date('Y-m-d');
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
        $tx_type     = $_POST['tx_type'] ?? 'expense'; // income or expense
    }

    if (!$date || !$desc || $amount <= 0) {
        $error = 'Date, description, and a positive amount are required.';
    } elseif (!valid_date($date)) {
        $error = 'Enter the date as a real calendar date.';
    } elseif ($is_double && (!$from_id || !$to_id || $from_id === $to_id)) {
        $error = 'Choose two different accounts — where money came from, and where it went.';
    } elseif (!$is_double && (!$bank_id || !$category_id)) {
        $error = 'Choose a bank account and a category.';
    } elseif (!accounts_belong_to_company((int)$company['id'], ...($is_double ? [$from_id, $to_id] : [$bank_id, $category_id]))) {
        // The account lists only offer this company's accounts; anything else was hand-crafted.
        $error = 'Choose accounts from this company.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();

        $pdo->prepare("INSERT INTO tb_transactions (company_id, date, description, reference, memo, created_by) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$company_id, $date, $desc, $ref ?: null, $memo ?: null, $user['id']]);
        $tx_id = (int)$pdo->lastInsertId();

        if ($is_double) {
            // from_account = credit side, to_account = debit side
            $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, credit) VALUES (?, ?, ?)")
                ->execute([$tx_id, $from_id, $amount]);
            $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, debit) VALUES (?, ?, ?)")
                ->execute([$tx_id, $to_id, $amount]);
        } else {
            // Single entry: determine debit/credit by transaction type
            if ($tx_type === 'income') {
                // Debit bank, credit income
                $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, debit) VALUES (?, ?, ?)")
                    ->execute([$tx_id, $bank_id, $amount]);
                $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, credit) VALUES (?, ?, ?)")
                    ->execute([$tx_id, $category_id, $amount]);
            } else {
                // Debit expense, credit bank
                $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, credit) VALUES (?, ?, ?)")
                    ->execute([$tx_id, $bank_id, $amount]);
                $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, debit) VALUES (?, ?, ?)")
                    ->execute([$tx_id, $category_id, $amount]);
            }
        }

        $pdo->commit();
        flash_set('success', 'Transaction saved.');
        header('Location: index.php');
        exit;
    }
}

// Pre-fill AI suggestion if description is provided (AJAX handled separately)
$bank_accounts    = array_filter($accounts, fn($a) => in_array($a['subtype'], ['bank', 'cash', 'credit_card']));
$income_accounts  = array_filter($accounts, fn($a) => $a['type'] === 'income');
$expense_accounts = array_filter($accounts, fn($a) => $a['type'] === 'expense');

$page_title = 'Add Transaction';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Add Transaction</h1>

    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-3 text-sm rounded mb-4"><?= h($error) ?></div>
    <?php endif; ?>

    <div id="duplicate-warning" class="hidden bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-3 text-sm rounded mb-4"></div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6">
        <form method="post" id="tx-form" class="space-y-4">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="date" required value="<?= h($_POST['date'] ?? date('Y-m-d')) ?>"
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                        <input type="number" name="amount" required step="0.01" min="0.01"
                            value="<?= h($_POST['amount'] ?? '') ?>"
                            class="w-full rounded-lg border-gray-300 text-sm pl-7 pr-3 py-2 border"
                            id="amount-input">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input type="text" name="description" required id="desc-input"
                    value="<?= h($_POST['description'] ?? '') ?>"
                    placeholder="What was this for?"
                    class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
            </div>

            <?php if ($is_double): ?>
            <!-- Double entry: from/to accounts -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Money came from
                        <span class="text-xs text-gray-400">(which account)</span>
                    </label>
                    <select name="from_account_id" id="from-account" required
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="">-- select --</option>
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($_POST['from_account_id'] ?? '') == $a['id'] ? 'selected' : '' ?>>
                            <?= h($a['name']) ?> <small>(<?= $a['type'] ?>)</small>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Money went to
                        <span class="text-xs text-gray-400">(which account)</span>
                    </label>
                    <select name="to_account_id" id="to-account" required
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="">-- select --</option>
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($_POST['to_account_id'] ?? '') == $a['id'] ? 'selected' : '' ?>>
                            <?= h($a['name']) ?> <small>(<?= $a['type'] ?>)</small>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="ai-hint" class="hidden text-xs text-purple-600 bg-purple-50 rounded p-2">
                &#10024; AI suggestion: <span id="ai-hint-text"></span>
                <button type="button" id="apply-ai" class="ml-2 underline">Apply</button>
            </div>

            <?php else: ?>
            <!-- Single entry -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="tx_type" value="expense" <?= ($_POST['tx_type'] ?? 'expense') === 'expense' ? 'checked' : '' ?>
                            class="text-indigo-600"> Expense (money out)
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="tx_type" value="income" <?= ($_POST['tx_type'] ?? '') === 'income' ? 'checked' : '' ?>
                            class="text-indigo-600"> Income (money in)
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank / Cash Account</label>
                    <select name="bank_account_id" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="">-- select --</option>
                        <?php foreach ($bank_accounts as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= h($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id" id="category-select" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="">-- select --</option>
                        <optgroup label="Income">
                            <?php foreach ($income_accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= h($a['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Expenses">
                            <?php foreach ($expense_accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= h($a['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference # <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="reference" value="<?= h($_POST['reference'] ?? '') ?>"
                        placeholder="Check #, invoice #..."
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Memo <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="memo" value="<?= h($_POST['memo'] ?? '') ?>"
                        class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                    Save Transaction
                </button>
                <a href="index.php" class="text-sm text-gray-500 hover:underline px-3 py-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// AI categorization on description blur
const descInput   = document.getElementById('desc-input');
const amountInput = document.getElementById('amount-input');
const aiHint      = document.getElementById('ai-hint');
const aiHintText  = document.getElementById('ai-hint-text');
let aiData        = null;

<?php if ($is_double): ?>
function fetchAI() {
    const desc   = descInput.value.trim();
    const amount = parseFloat(amountInput.value) || 0;
    if (desc.length < 3) return;

    fetch('<?= APP_URL ?>/api/categorize.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({description: desc, amount: amount})
    })
    .then(r => r.json())
    .then(data => {
        if (data.account_id && data.account_name) {
            aiData = data;
            aiHintText.textContent = data.account_name + ' (' + data.confidence + ' confidence) — ' + data.reason;
            aiHint.classList.remove('hidden');
        }
    })
    .catch(() => {});
}

descInput.addEventListener('blur', fetchAI);

document.getElementById('apply-ai')?.addEventListener('click', () => {
    if (!aiData) return;
    // Try to apply to "to" account (most common use case is expense categorization)
    const toSelect = document.getElementById('to-account');
    if (toSelect) {
        toSelect.value = aiData.account_id;
    }
    aiHint.classList.add('hidden');
});
<?php endif; ?>

// Duplicate check on amount change
function checkDuplicate() {
    const desc   = descInput.value.trim();
    const amount = amountInput.value;
    const date   = document.querySelector('[name=date]').value;
    if (!desc || !amount || !date) return;

    fetch('<?= APP_URL ?>/api/check_duplicate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({description: desc, amount: parseFloat(amount), date: date})
    })
    .then(r => r.json())
    .then(data => {
        const warn = document.getElementById('duplicate-warning');
        if (data.is_duplicate && data.confidence !== 'low') {
            warn.textContent = '⚠ Possible duplicate: ' + data.reason;
            warn.classList.remove('hidden');
        } else {
            warn.classList.add('hidden');
        }
    })
    .catch(() => {});
}

amountInput.addEventListener('blur', checkDuplicate);
</script>

<?php require TB_ROOT . '/includes/footer.php'; ?>
