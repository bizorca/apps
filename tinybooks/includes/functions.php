<?php
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money(float $amount, string $currency = 'USD'): string {
    return '$' . number_format(abs($amount), 2);
}

function flash_set(string $type, string $message): void {
    auth_start(true);
    $_SESSION['tb_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array {
    // No session yet means nothing to show; don't mint a cookie to find that out.
    if (!auth_start()) return null;
    $flash = $_SESSION['tb_flash'] ?? null;
    unset($_SESSION['tb_flash']);
    return $flash;
}

function flash_html(): string {
    $flash = flash_get();
    if (!$flash) return '';
    $colors = [
        'success' => 'bg-green-50 border-green-400 text-green-800',
        'error'   => 'bg-red-50 border-red-400 text-red-800',
        'info'    => 'bg-blue-50 border-blue-400 text-blue-800',
    ];
    $cls = $colors[$flash['type']] ?? $colors['info'];
    return '<div class="border-l-4 p-4 mb-4 ' . $cls . '">' . h($flash['message']) . '</div>';
}

/** A real Y-m-d calendar date. MySQL's DATE column rejects anything else; SQLite stored it as-is. */
function valid_date(?string $d): bool {
    if (!is_string($d) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

function account_types(): array {
    return [
        'asset'     => 'Asset',
        'liability' => 'Liability',
        'equity'    => 'Equity',
        'income'    => 'Income',
        'expense'   => 'Expense',
    ];
}

function get_accounts(int $company_id, ?string $type = null): array {
    $sql = "SELECT * FROM tb_accounts WHERE company_id = ? AND is_active = 1";
    $params = [$company_id];
    if ($type) {
        $sql .= " AND type = ?";
        $params[] = $type;
    }
    $sql .= " ORDER BY account_number, name";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_account(int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM tb_accounts WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function account_balance(int $account_id, ?string $from = null, ?string $to = null): float {
    $account = get_account($account_id);
    if (!$account) return 0;

    $sql = "
        SELECT COALESCE(SUM(tl.debit), 0) as total_debit,
               COALESCE(SUM(tl.credit), 0) as total_credit
        FROM tb_transaction_lines tl
        JOIN tb_transactions t ON t.id = tl.transaction_id
        WHERE tl.account_id = ?
    ";
    $params = [$account_id];
    if ($from) { $sql .= " AND t.date >= ?"; $params[] = $from; }
    if ($to)   { $sql .= " AND t.date <= ?"; $params[] = $to; }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    // Normal balance: assets/expenses have debit balance, liabilities/equity/income have credit balance
    $debit_normal = in_array($account['type'], ['asset', 'expense']);
    if ($debit_normal) {
        return $row['total_debit'] - $row['total_credit'];
    } else {
        return $row['total_credit'] - $row['total_debit'];
    }
}

function get_transactions(int $company_id, array $filters = []): array {
    $sql = "
        SELECT t.*,
               u.name as creator_name
        FROM tb_transactions t
        LEFT JOIN users u ON u.id = t.created_by
        WHERE t.company_id = ?
    ";
    $params = [$company_id];

    if (!empty($filters['from'])) { $sql .= " AND t.date >= ?"; $params[] = $filters['from']; }
    if (!empty($filters['to']))   { $sql .= " AND t.date <= ?"; $params[] = $filters['to']; }
    if (!empty($filters['account_id'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM tb_transaction_lines tl WHERE tl.transaction_id = t.id AND tl.account_id = ?)";
        $params[] = $filters['account_id'];
    }

    $sql .= " ORDER BY t.date DESC, t.id DESC";

    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . (int)$filters['limit'];
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_transaction_lines(int $transaction_id): array {
    $stmt = db()->prepare("
        SELECT tl.*, a.name as account_name, a.type as account_type, a.account_number
        FROM tb_transaction_lines tl
        JOIN tb_accounts a ON a.id = tl.account_id
        WHERE tl.transaction_id = ?
    ");
    $stmt->execute([$transaction_id]);
    return $stmt->fetchAll();
}

// Returns the "from" (credit) and "to" (debit) accounts for a simple transaction
function simple_transaction_parts(int $transaction_id): array {
    $lines = get_transaction_lines($transaction_id);
    $from = null;
    $to   = null;
    $amount = 0;
    foreach ($lines as $line) {
        if ($line['credit'] > 0) { $from = $line; $amount = $line['credit']; }
        if ($line['debit'] > 0)  { $to   = $line; }
    }
    return ['from' => $from, 'to' => $to, 'amount' => $amount];
}

/**
 * Would saving these values leave the transaction's lines exactly as they are?
 * Double entry: [from (credit), to (debit)]. Single entry: [bank, category, 'income'|'expense'].
 */
function lines_unchanged(int $transaction_id, bool $is_double, float $amount, array $sel): bool {
    $want = [];
    if ($is_double) {
        [$from, $to] = $sel;
        $want = [[(int)$from, 0.0, $amount], [(int)$to, $amount, 0.0]];
    } else {
        [$bank, $cat, $type] = $sel;
        $want = $type === 'income'
            ? [[(int)$bank, $amount, 0.0], [(int)$cat, 0.0, $amount]]
            : [[(int)$bank, 0.0, $amount], [(int)$cat, $amount, 0.0]];
    }
    $have = array_map(fn($l) => [(int)$l['account_id'], round((float)$l['debit'], 2), round((float)$l['credit'], 2)],
                      get_transaction_lines($transaction_id));
    $norm = function (array $rows): array { sort($rows); return $rows; };
    return $norm($have) == $norm(array_map(fn($r) => [$r[0], round($r[1], 2), round($r[2], 2)], $want));
}

function fiscal_months(int $fiscal_start): array {
    $months = [];
    for ($i = 0; $i < 12; $i++) {
        $m = (($fiscal_start - 1 + $i) % 12) + 1;
        $months[] = $m;
    }
    return $months;
}

function month_name(int $m): string {
    return date('F', mktime(0, 0, 0, $m, 1));
}
