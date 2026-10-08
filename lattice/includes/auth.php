<?php
/*
 * Lattice's auth, now a layer over the shared tools account
 * (private_html/includes/auth.php). Sign-in, registration, sign-out and
 * passwords live at /account/; Lattice's own login, register and logout pages
 * and its users table are gone.
 *
 * Lattice keeps one role of its own: course admin. It used to be users.is_admin
 * in Lattice's private users table. On the shared site users.is_admin means
 * "site owner" across every tool, so a Lattice course admin is a row in
 * lt_admins instead. A site admin is always a Lattice admin as well.
 *
 * Session keys are lt_-prefixed: one session is shared by every tool.
 */

function is_logged_in(): bool {
    return tl_logged_in();
}

function current_user(): ?array {
    $u = tl_user();
    if (!$u) return null;
    return [
        'id'         => (int)$u['id'],
        'name'       => $u['name'],
        'email'      => $u['email'],
        'is_admin'   => is_lattice_admin((int)$u['id'], (bool)$u['is_admin']) ? 1 : 0,
        'site_admin' => (int)$u['is_admin'],
        'created_at' => $u['created_at'],
    ];
}

function is_lattice_admin(int $user_id, bool $site_admin = false): bool {
    if ($site_admin) return true;
    return (bool)db_val('SELECT COUNT(*) FROM lt_admins WHERE user_id = ?', [$user_id]);
}

function is_admin(): bool {
    $u = current_user();
    return $u && (bool)$u['is_admin'];
}

function require_login(): void {
    tl_require_login();
}

function require_admin(): void {
    require_login();
    if (!is_admin()) redirect(APP_URL . '/home.php');
}

function redirect(string $url): never {
    header("Location: {$url}");
    exit;
}

function flash_set(string $key, string $msg): void {
    tl_session(true);
    $_SESSION['lt_flash'][$key] = $msg;
}

function flash_get(string $key): ?string {
    // Reading needs no session: never mint a cookie just to find nothing.
    if (!tl_session()) return null;
    $msg = $_SESSION['lt_flash'][$key] ?? null;
    unset($_SESSION['lt_flash'][$key]);
    return $msg;
}

/** Creates a session, so only call it on pages that render a form. */
function csrf_token(): string {
    tl_session(true);
    if (empty($_SESSION['lt_csrf'])) {
        $_SESSION['lt_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['lt_csrf'];
}

function csrf_ok(?string $sent): bool {
    return tl_session()
        && !empty($_SESSION['lt_csrf'])
        && is_string($sent)
        && hash_equals($_SESSION['lt_csrf'], $sent);
}

function verify_csrf(): void {
    if (!csrf_ok($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}
