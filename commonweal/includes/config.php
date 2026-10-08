<?php
// ============================================================
// Commonweal (CoopConvert) on tools.bizorca.com/commonweal
//
// Accounts are the shared tools account (private_html/includes); Commonweal
// keeps only its own role per person in cw_members. Reads that used to hit
// the old users table go through the cw_users view (shared users joined to
// cw_members), so the pages kept their queries. Tables are cw_-prefixed.
// ============================================================

declare(strict_types=1);

// The shared core sits beside this tool on the server and under private_html/ in the repo.
foreach ([CW_ROOT . '/../includes', CW_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

// Security headers (the old .htaccess set these; nginx ignores .htaccess).
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ── App constants ────────────────────────────────────────────
define('APP_NAME',        'CoopConvert');
define('BASE_PATH',       '/commonweal');
define('APP_URL',         (string) tl_env('CW_ORIGIN', 'https://tools.bizorca.com'));
define('MAIL_FROM',       (string) tl_env('CW_MAIL_FROM', 'noreply@bizorca.com'));
define('SMTP2GO_API_KEY', (string) tl_env('SMTP2GO_API_KEY', ''));

// ── PDO: the shared handle ───────────────────────────────────
function getDB(): PDO {
    return tl_db();
}

// ── Auth helpers ─────────────────────────────────────────────
function isLoggedIn(): bool {
    return tl_user() !== null;
}

/**
 * Signed in to the shared account AND a Commonweal member. A signed-in
 * account that has not joined goes to the join page; a coordinator awaiting
 * approval is held there too (the old login refused them outright).
 */
function requireLogin(): void {
    tl_require_login();
    $u = getCurrentUser();
    $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($self === 'register.php') {
        return;
    }
    if (!$u || ($u['role'] === 'coordinator' && (int)$u['coordinator_approved'] === 0 && !isAdmin())) {
        header('Location: ' . url('/register.php'));
        exit;
    }
}

function getCurrentUserId(): ?int {
    $u = tl_user();
    return $u ? (int)$u['id'] : null;
}

/** The signed-in person as the old users row looked: id, email, name, role, approval. Null if not a member. */
function getCurrentUser(): ?array {
    static $cache = [];
    $id = getCurrentUserId();
    if (!$id) return null;
    if (!array_key_exists($id, $cache)) {
        $stmt = getDB()->prepare('SELECT * FROM cw_users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: null;
        // The site owner is always a Commonweal admin, member row or not.
        if (!$row && !empty(tl_user()['is_admin'])) {
            $t = tl_user();
            $row = ['id' => (int)$t['id'], 'email' => $t['email'], 'name' => $t['name'], 'role' => 'admin',
                    'coordinator_approved' => 1, 'coordinator_why' => null, 'created_at' => $t['created_at']];
        }
        $cache[$id] = $row;
    }
    return $cache[$id];
}

// ── Role helpers ─────────────────────────────────────────────
// Admin used to be "email equals ADMIN_EMAIL". Now: the site owner
// (users.is_admin) or a cw_members row with role 'admin'.
function isAdmin(): bool {
    $t = tl_user();
    if (!$t) return false;
    if (!empty($t['is_admin'])) return true;
    $u = getCurrentUser();
    return $u && $u['role'] === 'admin';
}

function isCoordinator(): bool {
    $u = getCurrentUser();
    return $u && (in_array($u['role'], ['coordinator', 'admin'], true)) && ((int)$u['coordinator_approved'] === 1 || $u['role'] === 'admin');
}

function requireCoordinator(): void {
    if (!isCoordinator() && !isAdmin()) {
        setFlash('error', 'You do not have permission to access that page.');
        header('Location: ' . url('/dashboard.php'));
        exit;
    }
}

// ── URL helper ───────────────────────────────────────────────
function url(string $path): string {
    return BASE_PATH . $path;
}

// ── CSRF (session-backed; only form pages call it) ───────────
function generateCSRFToken(): string {
    tl_session(true);
    if (empty($_SESSION['cw_csrf'])) {
        $_SESSION['cw_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cw_csrf'];
}

function verifyCSRFToken(string $token): bool {
    if (!tl_session() || empty($_SESSION['cw_csrf'])) return false;
    return hash_equals($_SESSION['cw_csrf'], $token);
}

function csrfField(): string {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// ── Flash messages ────────────────────────────────────────────
function setFlash(string $type, string $message): void {
    tl_session(true);
    $_SESSION['cw_flash'] = ['type' => $type, 'message' => $message];
}

/** Read without minting a session for an anonymous visitor. */
function getFlash(): ?array {
    if (!tl_session() || empty($_SESSION['cw_flash'])) return null;
    $flash = $_SESSION['cw_flash'];
    unset($_SESSION['cw_flash']);
    return $flash;
}

// ── Output escaping ───────────────────────────────────────────
function h(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ── Case lookup ───────────────────────────────────────────────
/** The most recent case (with business data) for a given user, or null. */
function getUserCase(int $userId): ?array {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT cw_cases.*, cw_businesses.name AS business_name, cw_businesses.id AS business_id,
                    cw_businesses.industry, cw_businesses.employee_count, cw_businesses.annual_revenue_range,
                    cw_businesses.owner_timeline, cw_businesses.motivation, cw_businesses.business_type
             FROM cw_cases
             JOIN cw_businesses ON cw_businesses.id = cw_cases.business_id
             WHERE cw_businesses.user_id = ?
             ORDER BY cw_cases.created_at DESC, cw_cases.id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
    return $row ?: null;
}
