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

if (!$description) {
    echo json_encode(['error' => 'No description']);
    exit;
}

$accounts = array_filter(
    get_accounts($company['id']),
    fn($a) => in_array($a['type'], ['income', 'expense'])
);

$result = ai_categorize_transaction($description, $amount, array_values($accounts));

if (!$result) {
    echo json_encode(['error' => 'AI unavailable']);
    exit;
}

// Add account name to the response
$account = get_account((int)$result['account_id']);
$result['account_name'] = $account['name'] ?? 'Unknown';

echo json_encode($result);
