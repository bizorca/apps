<?php
/*
 * ThinkRep, as a tool on tools.bizorca.com/thinkrep.
 *
 * TR_ROOT is set by public/_bootstrap.php. Account and database come from the
 * shared core (private_html/includes): one users table, one MySQL database,
 * ThinkRep's tables prefixed tr_. The helper names below are ThinkRep's
 * originals so the pages did not need rewriting; they now sit on the shared
 * account instead of Bizorca SSO.
 *
 * What ThinkRep used to keep on its own users row (role, industry,
 * onboarding date, timezone, its admin flag) lives in tr_profiles, one row
 * per shared user, created on first visit.
 */

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([TR_ROOT . '/../includes', TR_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

define('APP_NAME', 'Thinkrep');
define('BASE_PATH', '/thinkrep');                              // URL prefix for every link and redirect
define('APP_URL', 'https://tools.bizorca.com' . BASE_PATH);   // absolute base (invite links)

/** The shared MySQL handle, under ThinkRep's old name. */
function getDB(): PDO {
    return tl_db();
}

// Auth helpers ---------------------------------------------------------------

function isLoggedIn(): bool {
    return tl_user() !== null;
}

function requireLogin(): void {
    tl_require_login();
}

function requireOnboarded(): void {
    requireLogin();
    $user = getCurrentUser();
    if (empty($user['onboarded_at'])) {
        header('Location: ' . url('/onboarding.php'));
        exit;
    }
}

// URL helper - prepends BASE_PATH to all internal links
function url(string $path): string {
    return BASE_PATH . $path;
}

function getCurrentUserId(): ?int {
    $u = tl_user();
    return $u ? (int) $u['id'] : null;
}

/**
 * The signed-in user in the shape ThinkRep's pages expect: the shared account
 * (id, email, name) plus this tool's profile row. The profile row is created
 * on first use, so every signed-in user has one.
 */
function getCurrentUser(): ?array {
    static $cache = [];
    $u = tl_user();
    if (!$u) return null;
    $id = (int) $u['id'];
    if (isset($cache[$id])) return $cache[$id];

    $db = getDB();
    $db->prepare('INSERT IGNORE INTO tr_profiles (user_id) VALUES (?)')->execute([$id]);
    $stmt = $db->prepare('SELECT * FROM tr_profiles WHERE user_id = ?');
    $stmt->execute([$id]);
    $p = $stmt->fetch() ?: [];

    return $cache[$id] = [
        'id'           => $id,
        'email'        => $u['email'],
        'name'         => $u['name'],
        'role_title'   => $p['role_title'] ?? null,
        'industry'     => $p['industry'] ?? null,
        'onboarded_at' => $p['onboarded_at'] ?? null,
        'timezone'     => $p['timezone'] ?? 'America/New_York',
        'is_admin'     => isThinkrepAdmin($u, $p),
        'created_at'   => $u['created_at'],
    ];
}

/**
 * ThinkRep's admin (reviews scenario submissions) is either flagged in
 * tr_profiles or is the site owner (shared users.is_admin). The site-wide flag
 * counts because the only original admin was a placeholder address.
 */
function isThinkrepAdmin(array $sharedUser, array $profile): int {
    return (int) (!empty($profile['is_admin']) || !empty($sharedUser['is_admin']));
}

/** Upsert profile fields for a user (onboarding, settings). */
function saveProfile(int $userId, array $fields): void {
    $allowed = ['role_title', 'industry', 'timezone', 'onboarded_at'];
    $cols = array_values(array_intersect(array_keys($fields), $allowed));
    if (!$cols) return;
    $sql = 'INSERT INTO tr_profiles (user_id, ' . implode(', ', $cols) . ') VALUES (?' . str_repeat(', ?', count($cols)) . ') AS new
            ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = new.$c", $cols));
    $params = [$userId];
    foreach ($cols as $c) $params[] = $fields[$c];
    getDB()->prepare($sql)->execute($params);
}

// CSRF Protection -------------------------------------------------------------
// Session keys are tr_-prefixed: one session is shared by every tool.

function generateCSRFToken(): string {
    tl_session(true);
    if (empty($_SESSION['tr_csrf_token'])) {
        $_SESSION['tr_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['tr_csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    return tl_session() && isset($_SESSION['tr_csrf_token']) && hash_equals($_SESSION['tr_csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

// Flash messages --------------------------------------------------------------

function setFlash(string $type, string $message): void {
    tl_session(true);
    $_SESSION['tr_flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    // Reading needs no session if the visitor has none: no cookie for nothing.
    if (!tl_session()) return null;
    $flash = $_SESSION['tr_flash'] ?? null;
    unset($_SESSION['tr_flash']);
    return $flash;
}

// Sanitization
function h(?string $str): string {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

// Set PHP timezone to the logged-in user's preference
if (isLoggedIn()) {
    $_tz = getCurrentUser()['timezone'] ?? 'America/New_York';
    if (in_array($_tz, timezone_identifiers_list())) {
        date_default_timezone_set($_tz);
    }
    // The pages compare SQL dates (CURDATE(), DATE(created_at), NOW()) with
    // PHP's date('Y-m-d') in the user's zone: streaks, the daily challenge and
    // "today's" confidence log depend on the two agreeing. The shared
    // connection starts in UTC, so move this request's MySQL session to the
    // same offset PHP now uses.
    getDB()->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    unset($_tz);
}
