<?php
/**
 * The shared account. One login for every tool on tools.bizorca.com.
 *
 * Adapted from financialhypnosis.com's includes/auth.php, which has been
 * exercised in production; the comments that explain a bug it already hit are
 * kept because the bug would come back here too.
 *
 * The session is lazy: tl_user() returns null without starting a session when
 * no session cookie exists, so the landing page and anonymous visitors never
 * get a cookie just for reading. Only signing in, registering and CSRF tokens
 * on a form create one.
 *
 * URLs end in .php on purpose. This Cloudways app sends PHP straight from nginx
 * to PHP-FPM and never reads .htaccess, so there is no rewrite to hide them.
 */

declare(strict_types=1);

const TL_RESET_TTL = 3600;   // one hour, in seconds

function tl_session(bool $create = false): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    if (!$create && !isset($_COOKIE[session_name()])) {
        return false;
    }
    // Name and cookie params were set in bootstrap.php.
    session_start();

    return true;
}

/**
 * The signed-in user, or null. Never starts a session.
 *
 * Cached per request, and the cache has to be dropped whenever identity
 * changes. On financialhypnosis.com a stale "nobody" cached at the top of
 * register.php made the post-signup email silently send nothing.
 */
function tl_user(bool $forget = false): ?array
{
    static $cache = null;
    if ($forget) {
        $cache = null;
        return null;
    }
    if ($cache !== null) {
        return $cache ?: null;
    }

    if (!tl_session()) {
        $cache = false;
        return null;
    }

    $id = $_SESSION['tl_user_id'] ?? null;
    if (!$id) {
        $cache = false;
        return null;
    }

    $stmt = tl_db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    // A deleted account with a live session: drop the dangling id.
    if (!$row) {
        unset($_SESSION['tl_user_id']);
        $cache = false;
        return null;
    }

    $cache = $row;
    return $row;
}

function tl_logged_in(): bool { return tl_user() !== null; }

/** Send an unauthenticated visitor to sign in, and bring them back after. */
function tl_require_login(): array
{
    $user = tl_user();
    if ($user) {
        return $user;
    }
    header('Location: /account/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
}

function tl_require_admin(): array
{
    $user = tl_require_login();
    if (!$user['is_admin']) {
        http_response_code(403);
        exit('Not for you.');
    }
    return $user;
}

/**
 * A ?next= value safe to put in a Location header, or $default.
 *
 * Only a path on this site passes. '//host' is not the only escape: browsers
 * read a backslash as a forward slash, so '/\evil.example' leaves the site too.
 * Never back to an account page whose only job is signing in or out, or
 * "return to logout" signs someone out the moment they sign in.
 */
function tl_safe_next(?string $next, string $default = '/'): string
{
    $next = (string) $next;
    if ($next === '' || $next[0] !== '/'
        || (isset($next[1]) && ($next[1] === '/' || $next[1] === '\\'))
        || str_contains($next, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $next)
        || preg_match('~^/account/(login|register|logout|forgot|reset)(\.php)?([/?#]|$)~', $next)) {
        return $default;
    }
    return $next;
}

/** "?next=..." for links between the account pages, or ''. */
function tl_next_query(): string
{
    $next = tl_safe_next($_GET['next'] ?? null, '');
    return $next === '' ? '' : '?next=' . rawurlencode($next);
}

/* ─────────────────────────────────────────────────────────── registration ── */

/** @return array{0: bool, 1: string} [ok, message-or-empty] */
function tl_register(string $email, string $password, string $name): array
{
    $email = trim(strtolower($email));
    $name  = trim($name);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'That does not look like an email address.'];
    }
    if (strlen($password) < 10) {
        return [false, 'Use at least 10 characters. Length beats cleverness.'];
    }
    if ($name === '') {
        return [false, 'What should we call you?'];
    }

    $db   = tl_db();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return [false, 'There is already an account with that address. Try signing in.'];
    }

    $db->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)')
       ->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name]);

    tl_start_session_for((int) $db->lastInsertId());

    return [true, ''];
}

/* ──────────────────────────────────────────────────────────────── signing ── */

/** @return array{0: bool, 1: string} */
function tl_login(string $email, string $password): array
{
    $stmt = tl_db()->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([trim(strtolower($email))]);
    $row = $stmt->fetch();

    // One message for both failures: the form should not reveal which
    // addresses have accounts.
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return [false, 'That email and password do not match.'];
    }

    // Accounts imported from Proforma carry bcrypt cost-12 hashes; this quietly
    // moves them to the current default on their next sign-in.
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        tl_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }

    tl_start_session_for((int) $row['id']);

    return [true, ''];
}

function tl_start_session_for(int $userId): void
{
    tl_session(true);
    // New session id on privilege change, so a fixated one is worthless.
    session_regenerate_id(true);
    $_SESSION['tl_user_id'] = $userId;
    tl_user(true);
}

function tl_logout(): void
{
    if (!tl_session()) {
        return;
    }
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    session_destroy();
    tl_user(true);
}

/* ─────────────────────────────────────────────────────────── password reset ── */

/** A raw reset token to email, or null if there is no such account. */
function tl_reset_create(string $email): ?array
{
    $stmt = tl_db()->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([trim(strtolower($email))]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $token = bin2hex(random_bytes(32));

    // Expiry computed by the database, not PHP, so it shares a clock with the
    // `expires_at > NOW()` check. PHP and MySQL disagreed by seven hours on the
    // financialhypnosis.com server.
    tl_db()->prepare(
        'INSERT INTO password_resets (token_hash, user_id, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
    )->execute([hash('sha256', $token), $row['id'], TL_RESET_TTL]);

    return ['token' => $token, 'user' => $row];
}

/** @return array{0: bool, 1: string} */
function tl_reset_consume(string $token, string $password): array
{
    if (strlen($password) < 10) {
        return [false, 'Use at least 10 characters.'];
    }

    $db   = tl_db();
    $hash = hash('sha256', $token);
    $stmt = $db->prepare(
        'SELECT user_id FROM password_resets
          WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return [false, 'That link has expired or has already been used.'];
    }

    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
       ->execute([password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
    $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE token_hash = ?')
       ->execute([$hash]);

    return [true, ''];
}

/* ───────────────────────────────────────────────────────────────── csrf ───── */

/** Creates a session, so only call it on pages that are forms. */
function tl_csrf_token(): string
{
    tl_session(true);
    if (empty($_SESSION['tl_csrf'])) {
        $_SESSION['tl_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['tl_csrf'];
}

function tl_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . tl_h(tl_csrf_token()) . '">';
}

function tl_csrf_ok(?string $sent): bool
{
    return tl_session()
        && !empty($_SESSION['tl_csrf'])
        && is_string($sent)
        && hash_equals($_SESSION['tl_csrf'], $sent);
}

function tl_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
