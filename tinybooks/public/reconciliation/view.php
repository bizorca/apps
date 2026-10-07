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

$rec_id  = (int)($_GET['id'] ?? 0);
$rec_stmt = db()->prepare("SELECT r.*, a.name as account_name, a.type as account_type FROM tb_reconciliations r JOIN tb_accounts a ON a.id = r.account_id WHERE r.id = ? AND r.company_id = ?");
$rec_stmt->execute([$rec_id, $company['id']]);
$rec = $rec_stmt->fetch();
if (!$rec) { header('Location: index.php'); exit; }

$is_complete = $rec['status'] === 'completed';

// Handle toggle cleared / finalize
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['toggle']) && !$is_complete) {
        $line_id = (int)$_POST['toggle'];
        // Only lines this reconciliation actually lists. The original took any
        // line id, so a crafted form could tick another company's transaction.
        $own = db()->prepare("SELECT 1 FROM tb_transaction_lines tl JOIN tb_transactions t ON t.id = tl.transaction_id WHERE tl.id = ? AND tl.account_id = ? AND t.company_id = ?");
        $own->execute([$line_id, $rec['account_id'], $company['id']]);
        if (!$own->fetchColumn()) { header('Location: view.php?id=' . $rec_id); exit; }
        // Upsert reconciliation item
        $existing = db()->prepare("SELECT id, is_cleared FROM tb_reconciliation_items WHERE reconciliation_id=? AND transaction_line_id=?");
        $existing->execute([$rec_id, $line_id]);
        $row = $existing->fetch();
        if ($row) {
            db()->prepare("UPDATE tb_reconciliation_items SET is_cleared=? WHERE id=?")->execute([$row['is_cleared'] ? 0 : 1, $row['id']]);
        } else {
            db()->prepare("INSERT INTO tb_reconciliation_items (reconciliation_id, transaction_line_id, is_cleared) VALUES (?,?,1)")->execute([$rec_id, $line_id]);
        }
        // Mark transaction as reconciled if cleared
        if (!$row || !$row['is_cleared']) {
            db()->prepare("UPDATE tb_transactions SET is_reconciled=1 WHERE id=(SELECT transaction_id FROM tb_transaction_lines WHERE id=?)")->execute([$line_id]);
        } else {
            db()->prepare("UPDATE tb_transactions SET is_reconciled=0 WHERE id=(SELECT transaction_id FROM tb_transaction_lines WHERE id=?)")->execute([$line_id]);
        }
        header('Location: view.php?id=' . $rec_id); exit;
    }

    if (isset($_POST['finalize'])) {
        db()->prepare("UPDATE tb_reconciliations SET status='completed' WHERE id=?")->execute([$rec_id]);
        flash_set('success', 'Reconciliation complete. Nice work.');
        header('Location: index.php'); exit;
    }
}

// Get all transaction lines for this account up to statement date
$lines_stmt = db()->prepare("
    SELECT tl.id as line_id, tl.debit, tl.credit, t.id as tx_id, t.date, t.description, t.reference,
           ri.is_cleared
    FROM tb_transaction_lines tl
    JOIN tb_transactions t ON t.id = tl.transaction_id
    LEFT JOIN tb_reconciliation_items ri ON ri.transaction_line_id = tl.id AND ri.reconciliation_id = ?
    WHERE tl.account_id = ? AND t.date <= ?
    ORDER BY t.date ASC, t.id ASC
");
$lines_stmt->execute([$rec_id, $rec['account_id'], $rec['statement_date']]);
$lines = $lines_stmt->fetchAll();

// Compute cleared balance
$cleared_balance  = 0;
$uncleared_lines  = [];
foreach ($lines as $line) {
    $net = $line['debit'] - $line['credit'];
    if ($line['is_cleared']) {
        $cleared_balance += $net;
    } else {
        $uncleared_lines[] = $line;
    }
}

$difference   = $rec['ending_balance'] - $cleared_balance;
$is_balanced  = abs($difference) < 0.01;

// AI help: analyze unmatched items
$ai_advice = null;
if (!$is_balanced && !$is_complete && isset($_GET['ai'])) {
    $unmatched_books = array_map(fn($l) => [
        'date'        => $l['date'],
        'description' => $l['description'],
        'amount'      => abs($l['debit'] - $l['credit']),
    ], array_slice($uncleared_lines, 0, 15));

    $ai_advice = ai_reconciliation_help([], $unmatched_books);
}

$page_title = 'Reconcile: ' . $rec['account_name'];
require TB_ROOT . '/includes/header.php';
require TB_ROOT . '/includes/nav.php';
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Reconcile: <?= h($rec['account_name']) ?></h1>
        <p class="text-sm text-gray-500">Statement date: <?= date('M j, Y', strtotime($rec['statement_date'])) ?></p>
    </div>
    <a href="index.php" class="text-sm text-gray-500 hover:underline">← Back</a>
</div>

<!-- Status bar -->
<div class="bg-white rounded-xl border <?= $is_balanced ? 'border-green-200' : 'border-yellow-200' ?> shadow-sm px-5 py-4 mb-6 grid grid-cols-4 gap-4 text-center">
    <div>
        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Statement Balance</p>
        <p class="font-bold text-lg font-mono text-gray-900"><?= money($rec['ending_balance']) ?></p>
    </div>
    <div>
        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Cleared Balance</p>
        <p class="font-bold text-lg font-mono text-gray-700"><?= money($cleared_balance) ?></p>
    </div>
    <div>
        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Difference</p>
        <p class="font-bold text-lg font-mono <?= $is_balanced ? 'text-green-600' : 'text-red-600' ?>"><?= money($difference) ?></p>
    </div>
    <div class="flex items-center justify-center">
        <?php if ($is_balanced && !$is_complete): ?>
        <form method="post">
            <?= csrf_field() ?>
            <button type="submit" name="finalize" value="1"
                class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                &#10003; Finish
            </button>
        </form>
        <?php elseif ($is_complete): ?>
        <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-800 text-sm font-medium">Completed</span>
        <?php else: ?>
        <a href="?id=<?= $rec_id ?>&ai=1" class="text-sm text-purple-600 hover:underline">
            &#10024; Ask AI for help
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($ai_advice): ?>
<div class="bg-purple-50 border border-purple-200 rounded-xl px-5 py-4 mb-4 text-sm text-purple-800">
    <p class="font-semibold mb-1">&#10024; AI Analysis</p>
    <p><?= nl2br(h($ai_advice)) ?></p>
</div>
<?php endif; ?>

<!-- Transaction list -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-3 bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
        Check off each transaction that appears on your bank statement
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                <th class="px-4 py-2 w-8"></th>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Description</th>
                <th class="px-4 py-2 text-left hidden sm:table-cell">Ref</th>
                <th class="px-4 py-2 text-right">Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($lines as $line):
                $net     = $line['debit'] - $line['credit'];
                $cleared = $line['is_cleared'];
            ?>
            <tr class="<?= $cleared ? 'bg-green-50 text-gray-400' : 'hover:bg-gray-50' ?> <?= $is_complete ? '' : 'cursor-pointer' ?>"
                <?php if (!$is_complete): ?>
                onclick="document.getElementById('toggle-<?= $line['line_id'] ?>').submit()"
                <?php endif; ?>>
                <td class="px-4 py-2.5 text-center">
                    <?php if (!$is_complete): ?>
                    <form method="post" id="toggle-<?= $line['line_id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="toggle" value="<?= $line['line_id'] ?>">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded border <?= $cleared ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300' ?>">
                            <?= $cleared ? '&#10003;' : '' ?>
                        </span>
                    </form>
                    <?php else: ?>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded <?= $cleared ? 'bg-green-500 text-white' : 'border border-gray-300' ?>">
                        <?= $cleared ? '&#10003;' : '' ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-2.5 font-mono text-xs"><?= date('M j, Y', strtotime($line['date'])) ?></td>
                <td class="px-4 py-2.5 <?= $cleared ? '' : 'text-gray-900 font-medium' ?>"><?= h($line['description']) ?></td>
                <td class="px-4 py-2.5 text-gray-400 hidden sm:table-cell text-xs"><?= h($line['reference'] ?? '') ?></td>
                <td class="px-4 py-2.5 text-right font-mono font-semibold <?= $net >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= ($net < 0 ? '-' : '+') ?><?= money(abs($net)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require TB_ROOT . '/includes/footer.php'; ?>
