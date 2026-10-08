<?php
declare(strict_types=1);

require_once __DIR__ . '/api.php';

/*
 * Web session helpers, now over the shared tools account.
 *
 * The original kept a JWT and a cached copy of the user in $_SESSION and
 * logged in through its own pages. Here:
 *   - signing in/out is /account (login.php and logout.php just forward)
 *   - "a Placecard member" is a signed-in tools account with a pc_profiles row;
 *     a tools account without one is sent to register.php to join (first
 *     name, last name, 18+ age — the original registration fields)
 *   - currentUser() is read fresh from users/me on each request (the original
 *     served a session copy that went stale)
 *   - every POST must carry the CSRF token (the original had none)
 *   - session keys are pc_-prefixed; the session is shared by every tool
 */

function isLoggedIn(): bool
{
    $u = tl_user();
    return $u !== null && Profile::exists((int) $u['id']);
}

function pcCheckCsrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !tl_csrf_ok($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('That form had gone stale. Go back, reload the page and try again.');
    }
}

/** A signed-in tools account, member or not (register.php needs this). */
function requireAccount(): array
{
    $u = tl_require_login();
    pcCheckCsrf();
    return $u;
}

function requireAuth(): void
{
    $u = requireAccount();
    if (!Profile::exists((int) $u['id'])) {
        pcRedirect('/register');
    }
}

function requireGuest(): void
{
    if (isLoggedIn()) {
        $user = currentUser();
        if (empty($user['bio'])) {
            pcRedirect('/onboarding');
        } else {
            pcRedirect('/dashboard');
        }
    }
}

function currentUser(): array
{
    static $user = null;
    if ($user === null || ($GLOBALS['pc_user_dirty'] ?? false)) {
        $GLOBALS['pc_user_dirty'] = false;
        $resp = isLoggedIn() ? api('GET', 'users/me', [], getToken()) : [];
        $user = ($resp['success'] ?? false) ? $resp['data'] : [];
    }
    return $user;
}

/** The web pages pass this to api() as before; any non-null value means "as the signed-in user". */
function getToken(): string
{
    return 'session';
}

/** The original cached the user in the session; currentUser() now reads fresh. */
function refreshUser(array $user): void
{
    $GLOBALS['pc_user_dirty'] = true;
}

function setFlash(string $type, string $message): void
{
    tl_session(true);
    $_SESSION['pc_flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!tl_session() || !isset($_SESSION['pc_flash'])) return null;
    $flash = $_SESSION['pc_flash'];
    unset($_SESSION['pc_flash']);
    return $flash;
}

function pcCsrfField(): string
{
    return tl_csrf_field();
}
