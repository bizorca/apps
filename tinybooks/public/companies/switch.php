<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once TB_ROOT . '/includes/config.php';
require_once TB_ROOT . '/includes/db.php';
require_once TB_ROOT . '/includes/auth.php';

$user = auth_require();
csrf_verify();

$company_id = (int)($_POST['company_id'] ?? 0);

// Verify user has access to this company
$stmt = db()->prepare("SELECT 1 FROM tb_user_companies WHERE user_id = ? AND company_id = ?");
$stmt->execute([$user['id'], $company_id]);
if ($stmt->fetch()) {
    $_SESSION['tb_company_id'] = $company_id;
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? APP_URL . '/dashboard.php'));
exit;
