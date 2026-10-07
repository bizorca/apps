<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/*
 * TinyBooks' auth, now a layer over the shared tools account
 * (private_html/includes/auth.php). Sign-in, sign-out and passwords live at
 * /account/. TinyBooks keeps only what is its own: which companies each user
 * can open (tb_user_companies) and the company they are working in.
 *
 * Session keys are tb_-prefixed: one session is shared by every tool.
 */

/** Resume the shared session if there is one; with $create, start one. */
function auth_start(bool $create = false): bool {
    return tl_session($create);
}

/** The signed-in user in the shape TinyBooks' pages expect, or null. */
function auth_user(): ?array {
    $u = tl_user();
    if (!$u) return null;
    return [
        'id'       => (int)$u['id'],
        'email'    => $u['email'],
        'name'     => $u['name'],
        'is_admin' => (int)$u['is_admin'],
    ];
}

function auth_require(): array {
    tl_require_login();
    return auth_user();
}

/** Does this user belong to this company? Every company-scoped read hangs off this. */
function user_can_access_company(int $user_id, int $company_id): bool {
    $stmt = db()->prepare("SELECT 1 FROM tb_user_companies WHERE user_id = ? AND company_id = ?");
    $stmt->execute([$user_id, $company_id]);
    return (bool)$stmt->fetchColumn();
}

function user_company_role(int $user_id, int $company_id): ?string {
    $stmt = db()->prepare("SELECT role FROM tb_user_companies WHERE user_id = ? AND company_id = ?");
    $stmt->execute([$user_id, $company_id]);
    $role = $stmt->fetchColumn();
    return $role === false ? null : (string)$role;
}

/**
 * The company being worked in. The old login picked the user's first company;
 * with a shared login there is no TinyBooks sign-in moment, so the first page
 * view does it instead. Membership is re-checked on every request, so a
 * session can never keep a company its user was removed from.
 */
function current_company_id(): ?int {
    $user = auth_user();
    if (!$user) return null;

    $id = isset($_SESSION['tb_company_id']) ? (int)$_SESSION['tb_company_id'] : 0;
    if ($id && user_can_access_company($user['id'], $id)) {
        return $id;
    }
    unset($_SESSION['tb_company_id']);

    $first = user_companies($user['id']);
    if (!$first) return null;
    $_SESSION['tb_company_id'] = (int)$first[0]['id'];
    return (int)$first[0]['id'];
}

function current_company(): ?array {
    $id = current_company_id();
    if (!$id) return null;
    $stmt = db()->prepare("SELECT * FROM tb_companies WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function user_companies(int $user_id): array {
    $stmt = db()->prepare("
        SELECT c.*, uc.role
        FROM tb_companies c
        JOIN tb_user_companies uc ON uc.company_id = c.id
        WHERE uc.user_id = ?
        ORDER BY c.name
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/** Only these account ids may be posted to: they must belong to $company_id. */
function accounts_belong_to_company(int $company_id, int ...$account_ids): bool {
    $account_ids = array_values(array_unique(array_filter($account_ids)));
    if (!$account_ids) return false;
    $in   = implode(',', array_fill(0, count($account_ids), '?'));
    $stmt = db()->prepare("SELECT COUNT(*) FROM tb_accounts WHERE company_id = ? AND id IN ($in)");
    $stmt->execute(array_merge([$company_id], $account_ids));
    return (int)$stmt->fetchColumn() === count($account_ids);
}

function csrf_token(): string {
    auth_start(true);
    if (empty($_SESSION['tb_csrf_token']) || (time() - ($_SESSION['tb_csrf_time'] ?? 0)) > CSRF_TTL) {
        $_SESSION['tb_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['tb_csrf_time'] = time();
    }
    return $_SESSION['tb_csrf_token'];
}

function csrf_verify(): void {
    auth_start();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['tb_csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid request token.');
    }
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
