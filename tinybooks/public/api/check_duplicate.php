<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';
require_once TB_ROOT . '/includes/functions.php';
require_once TB_ROOT . '/includes/haiku.php';

header('Content-Type: application/json');

$user    = auth_user();
$company = current_company();

if (!$user || !$company) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$description = trim($body['description'] ?? '');
$amount      = (float)($body['amount'] ?? 0);
$date        = $body['date'] ?? date('Y-m-d');

if (!$description || $amount <= 0) {
    echo json_encode(['is_duplicate' => false]);
    exit;
}

// Get recent transactions near this date
$from = date('Y-m-d', strtotime($date . ' -7 days'));
$to   = date('Y-m-d', strtotime($date . ' +1 days'));

$stmt = db()->prepare("
    SELECT t.id, t.date, t.description,
           COALESCE(MAX(tl.debit), MAX(tl.credit), 0) as amount
    FROM tb_transactions t
    JOIN tb_transaction_lines tl ON tl.transaction_id = t.id
    WHERE t.company_id = ? AND t.date BETWEEN ? AND ?
    GROUP BY t.id
    ORDER BY t.date DESC
    LIMIT 30
");
$stmt->execute([$company['id'], $from, $to]);
$recent = $stmt->fetchAll();

if (empty($recent)) {
    echo json_encode(['is_duplicate' => false]);
    exit;
}

$new_tx = ['date' => $date, 'description' => $description, 'amount' => $amount];
$result = ai_check_duplicate($new_tx, $recent);

echo json_encode($result ?? ['is_duplicate' => false]);
