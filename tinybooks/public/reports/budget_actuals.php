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
$from       = "{$year}-01-01";
$to         = "{$year}-12-31";

// Handle budget save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_budget'])) {
    csrf_verify();
    // Only this company's income and expense accounts, months 1-12.
    $budgetable = array_map(fn($a) => (int)$a['id'], array_merge(get_accounts($company_id, 'income'), get_accounts($company_id, 'expense')));
    foreach ((array)($_POST['budget'] ?? []) as $account_id => $months) {
        if (!in_array((int)$account_id, $budgetable, true)) continue;
        foreach ((array)$months as $month => $amount) {
            $month = (int)$month;
            if ($month < 1 || $month > 12) continue;
            $amt = round((float)str_replace(',', '', (string)$amount), 2);
            if ($amt > 0) {
                db()->prepare("INSERT INTO tb_budgets (company_id, account_id, year, month, amount) VALUES (?,?,?,?,?) AS new
                               ON DUPLICATE KEY UPDATE amount = new.amount")
                    ->execute([$company_id, $account_id, $year, $month, $amt]);
            } else {
                db()->prepare("DELETE FROM tb_budgets WHERE company_id=? AND account_id=? AND year=? AND month=?")
                    ->execute([$company_id, $account_id, $year, $month]);
            }
        }
    }
    flash_set('success', 'Budget saved.');
    header('Location: ?year=' . $year); exit;
}

$income_accounts  = get_accounts($company_id, 'income');
$expense_accounts = get_accounts($company_id, 'expense');

// Get all budgets for this year
$budget_stmt = db()->prepare("SELECT account_id, month, amount FROM tb_budgets WHERE company_id=? AND year=?");
$budget_stmt->execute([$company_id, $year]);
$budgets = [];
foreach ($budget_stmt->fetchAll() as $b) {
    $budgets[$b['account_id']][$b['month']] = $b['amount'];
}

// Build actuals by month for each account
function monthly_actuals(int $account_id, int $year): array {
    $result = [];
    for ($m = 1; $m <= 12; $m++) {
        $from = sprintf('%04d-%02d-01', $year, $m);
        $to   = date('Y-m-t', strtotime($from));
        $result[$m] = account_balance($account_id, $from, $to);
    }
    return $result;
}

$months = range(1, 12);
$month_abbr = array_map(fn($m) => date('M', mktime(0,0,0,$m,1)), $months);

$page_title = 'Budget vs. Actuals';
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold text-gray-900">Budget vs. Actuals</h1>
    <form method="get" class="flex gap-2 items-center">
        <select name="year" class="rounded-lg border-gray-300 text-sm px-2 py-1.5 border" onchange="this.form.submit()">
            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
            <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<p class="text-sm text-gray-500 mb-4">Click any budget cell to edit. Actuals pull from your transactions automatically.</p>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="save_budget" value="1">

    <?php foreach ([['Income', $income_accounts, 'green'], ['Expenses', $expense_accounts, 'red']] as [$section_label, $section_accounts, $color]): ?>
    <?php if (empty($section_accounts)) continue; ?>

    <div class="mb-6 overflow-x-auto">
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2"><?= $section_label ?></h2>
        <table class="text-xs w-full border-collapse bg-white rounded-xl shadow-sm overflow-hidden">
            <thead>
                <tr class="bg-gray-50 text-gray-500 font-semibold">
                    <th class="px-3 py-2 text-left min-w-40">Account</th>
                    <?php foreach ($month_abbr as $abbr): ?>
                    <th class="px-1 py-2 text-center w-16"><?= $abbr ?></th>
                    <?php endforeach; ?>
                    <th class="px-2 py-2 text-right min-w-20">YTD Budget</th>
                    <th class="px-2 py-2 text-right min-w-20">YTD Actual</th>
                    <th class="px-2 py-2 text-right min-w-20">Variance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($section_accounts as $a):
                    $actuals    = monthly_actuals($a['id'], $year);
                    $ytd_budget = array_sum($budgets[$a['id']] ?? []);
                    $ytd_actual = array_sum($actuals);
                    $variance   = $ytd_actual - $ytd_budget;
                    $is_expense = $a['type'] === 'expense';
                    // For expenses: negative variance = under budget (good); positive = over (bad)
                    $var_good = $is_expense ? $variance <= 0 : $variance >= 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 font-medium text-gray-700 whitespace-nowrap"><?= h($a['name']) ?></td>
                    <?php for ($m = 1; $m <= 12; $m++):
                        $b = $budgets[$a['id']][$m] ?? 0;
                        $act = $actuals[$m];
                    ?>
                    <td class="px-1 py-1 text-center">
                        <div class="space-y-0.5">
                            <input type="number" name="budget[<?= $a['id'] ?>][<?= $m ?>]"
                                value="<?= $b > 0 ? number_format($b, 0) : '' ?>"
                                placeholder="—"
                                min="0" step="1"
                                class="w-14 text-center text-xs border-0 rounded bg-gray-50 hover:bg-indigo-50 focus:bg-white focus:ring-1 focus:ring-indigo-400 py-0.5 font-mono text-gray-600">
                            <?php if ($act != 0): ?>
                            <div class="font-mono text-<?= $act > 0 ? 'gray' : 'red' ?>-600 text-xs"><?= money($act) ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endfor; ?>
                    <td class="px-2 py-2 text-right font-mono text-gray-600"><?= $ytd_budget > 0 ? money($ytd_budget) : '—' ?></td>
                    <td class="px-2 py-2 text-right font-mono text-gray-700"><?= $ytd_actual > 0 ? money($ytd_actual) : '—' ?></td>
                    <td class="px-2 py-2 text-right font-mono <?= $ytd_budget > 0 ? ($var_good ? 'text-green-600' : 'text-red-600') : 'text-gray-300' ?>">
                        <?php if ($ytd_budget > 0): ?>
                        <?= ($variance > 0 ? '+' : '') . money(abs($variance)) ?>
                        <?php else: ?>&mdash;<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <div class="mt-4">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
            Save Budget
        </button>
        <p class="text-xs text-gray-400 mt-2">Top row = budget (editable). Bottom row = actuals (from transactions).</p>
    </div>
</form>

<?php require TB_ROOT . '/includes/footer.php'; ?>
