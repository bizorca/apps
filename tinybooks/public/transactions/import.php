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
$preview    = [];
$imported   = 0;

// Step 1: upload; Step 2: map columns; Step 3: import
$step   = (int)($_POST['step'] ?? 1);
// Which form was submitted. The original tested $step, which the upload
// handler changes to 2, so the import ran in the upload request itself with
// no column mapping and no bank account: every row was skipped, the upload
// discarded, and "Imported 0 transactions" shown. Branch on what was posted.
$posted = $step;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $posted === 1) {
    // File upload
    csrf_verify();
    if (empty($_FILES['csv']['tmp_name'])) {
        $error = 'Please select a CSV file.';
        $step = 1;
    } else {
        $tmp  = $_FILES['csv']['tmp_name'];
        $rows = [];
        if (($fh = fopen($tmp, 'r')) !== false) {
            $headers = fgetcsv($fh, null, ',', '"', '\\');
            while (($row = fgetcsv($fh, null, ',', '"', '\\')) !== false) {
                if (count($row) >= 2) $rows[] = $row;
            }
            fclose($fh);
        }
        if (empty($headers) || empty($rows)) {
            $error = 'Could not read CSV. Make sure it has headers and at least one data row.';
            $step = 1;
        } else {
            $_SESSION['tb_csv_headers'] = $headers;
            $_SESSION['tb_csv_rows']    = array_slice($rows, 0, 500); // max 500 rows
            $step = 2;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $posted === 2) {
    csrf_verify();
    $headers  = $_SESSION['tb_csv_headers'] ?? [];
    $rows     = $_SESSION['tb_csv_rows'] ?? [];
    $col_date = (int)$_POST['col_date'];
    $col_desc = (int)$_POST['col_desc'];
    $col_amt  = (int)$_POST['col_amt'];
    $col_ref  = isset($_POST['col_ref']) && $_POST['col_ref'] !== '' ? (int)$_POST['col_ref'] : null;
    $bank_id  = (int)$_POST['bank_account_id'];
    $negate   = !empty($_POST['negate_amount']);

    $skipped = 0;
    $pdo = db();

    // The bank account comes from the form: it must be one of this company's.
    if (!accounts_belong_to_company($company_id, $bank_id)) {
        $bank_id = 0;
    }

    $income_expense_accounts = array_filter($accounts, fn($a) => in_array($a['type'], ['income', 'expense']));

    foreach ($rows as $row) {
        $raw_date = trim($row[$col_date] ?? '');
        $raw_desc = trim($row[$col_desc] ?? '');
        $raw_amt  = str_replace(['$', ',', ' '], '', trim($row[$col_amt] ?? '0'));
        $raw_ref  = $col_ref !== null ? trim($row[$col_ref] ?? '') : '';

        // Parse amount
        $amount = round((float)$raw_amt, 2);
        if ($negate) $amount = -$amount;

        // Try to parse date
        $date = date('Y-m-d', strtotime($raw_date));
        if ($date === '1970-01-01') { $skipped++; continue; }

        if (!$raw_desc || $amount == 0) { $skipped++; continue; }

        // AI categorize
        $ai_result = ai_categorize_transaction($raw_desc, abs($amount), array_values($income_expense_accounts));
        $category_id = $ai_result['account_id'] ?? null;
        // Only accept a suggestion that is actually one of the offered accounts.
        if ($category_id && !in_array((int)$category_id, array_map(fn($a) => (int)$a['id'], $income_expense_accounts), true)) {
            $category_id = null;
        }
        $ai_flag = $category_id ? 1 : 0;

        if (!$category_id) {
            // Fall back to first expense account
            $fallback = current(array_filter($accounts, fn($a) => $a['type'] === 'expense'));
            $category_id = $fallback['id'] ?? null;
        }

        if (!$bank_id || !$category_id) { $skipped++; continue; }

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO tb_transactions (company_id, date, description, reference, ai_categorized, created_by) VALUES (?,?,?,?,?,?)")
            ->execute([$company_id, $date, $raw_desc, $raw_ref ?: null, $ai_flag, $user['id']]);
        $tx_id = (int)$pdo->lastInsertId();

        $abs = abs($amount);
        if ($amount < 0 || ($amount > 0 && !$is_double)) {
            // Expense: credit bank, debit category
            $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, credit) VALUES (?,?,?)")->execute([$tx_id, $bank_id, $abs]);
            $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, debit) VALUES (?,?,?)")->execute([$tx_id, $category_id, $abs]);
        } else {
            // Income: debit bank, credit category
            $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, debit) VALUES (?,?,?)")->execute([$tx_id, $bank_id, $abs]);
            $pdo->prepare("INSERT INTO tb_transaction_lines (transaction_id, account_id, credit) VALUES (?,?,?)")->execute([$tx_id, $category_id, $abs]);
        }
        $pdo->commit();
        $imported++;
    }

    unset($_SESSION['tb_csv_headers'], $_SESSION['tb_csv_rows']);
    flash_set('success', "Imported {$imported} transaction" . ($imported !== 1 ? 's' : '') . ($skipped ? " ({$skipped} skipped)" : '') . ". Review and fix categories as needed.");
    header('Location: index.php'); exit;
}

$headers = $_SESSION['tb_csv_headers'] ?? [];
$preview_rows = array_slice($_SESSION['tb_csv_rows'] ?? [], 0, 5);
$bank_accounts = array_filter($accounts, fn($a) => in_array($a['subtype'], ['bank', 'cash', 'credit_card']));

$page_title = 'Import CSV';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Import Transactions from CSV</h1>
    <p class="text-sm text-gray-500 mb-6">Export from your bank and drop it here. TinyBooks will try to categorize everything automatically — you can fix any that are wrong afterward.</p>

    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-3 text-sm rounded mb-4"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- Step 1: Upload -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6">
        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="step" value="1">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Select CSV file</label>
                <input type="file" name="csv" accept=".csv,text/csv" required
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="text-xs text-gray-400 mt-1">Standard bank CSV export. First row must be headers. Max 500 rows per import.</p>
            </div>

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                Upload &amp; Preview
            </button>
        </form>
    </div>

    <?php elseif ($step === 2 && !empty($headers)): ?>
    <!-- Step 2: Map columns -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-6 space-y-5">
        <div>
            <h2 class="font-medium text-gray-900 mb-2">Preview (first 5 rows)</h2>
            <div class="overflow-x-auto">
                <table class="text-xs border-collapse w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <?php foreach ($headers as $i => $h): ?>
                            <th class="border border-gray-200 px-2 py-1 text-left font-medium text-gray-600"><?= h($h) ?> <span class="text-gray-400">[<?= $i ?>]</span></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                            <td class="border border-gray-200 px-2 py-1 text-gray-700"><?= h($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="step" value="2">

            <p class="text-sm font-medium text-gray-700">Match your CSV columns:</p>

            <div class="grid grid-cols-2 gap-4">
                <?php
                $col_opts = fn($label) => array_map(fn($i, $h) => "<option value=\"{$i}\">" . h($h) . "</option>", array_keys($headers), $headers);
                $sel = fn($name, $label, $sel_idx = 0) => "
                    <div>
                        <label class='block text-sm font-medium text-gray-700 mb-1'>{$label}</label>
                        <select name='{$name}' required class='w-full rounded-lg border-gray-300 text-sm px-3 py-2 border'>
                            " . implode('', array_map(fn($i, $h) => "<option value='{$i}'" . ($i == $sel_idx ? ' selected' : '') . ">" . h($h) . "</option>", array_keys($headers), $headers)) . "
                        </select>
                    </div>
                ";
                ?>
                <?= $sel('col_date', 'Date column', 0) ?>
                <?= $sel('col_desc', 'Description column', 1) ?>
                <?= $sel('col_amt', 'Amount column', 2) ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference # column <span class="text-gray-400">(optional)</span></label>
                    <select name="col_ref" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                        <option value="">-- none --</option>
                        <?php foreach ($headers as $i => $h): ?>
                        <option value="<?= $i ?>"><?= h($h) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account <span class="text-red-400">*</span></label>
                <select name="bank_account_id" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
                    <option value="">-- which account is this statement for? --</option>
                    <?php foreach ($bank_accounts as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= h($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="negate_amount" id="negate" class="rounded border-gray-300 text-indigo-600">
                <label for="negate" class="text-sm text-gray-600">
                    Flip amount signs (some banks export expenses as positive numbers)
                </label>
            </div>

            <p class="text-xs text-gray-400">
                &#10024; Haiku AI will attempt to auto-categorize each transaction. Review the results in the transaction list and fix any misses.
            </p>

            <div class="flex gap-3">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                    Import <?= count($_SESSION['tb_csv_rows'] ?? []) ?> Transactions
                </button>
                <a href="import.php" class="text-sm text-gray-500 hover:underline px-3 py-2">Start over</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
