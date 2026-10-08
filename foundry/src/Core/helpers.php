<?php

use Bizorca\Consulting\Core\Database;
use Bizorca\Consulting\Core\Url;

function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $viewPath = FD_ROOT . '/src/Views/' . $view . '.php';
    if (!file_exists($viewPath)) {
        http_response_code(500);
        echo "View not found: " . h($view);
        return;
    }
    require $viewPath;
}

/** An app path ('/admin') as a link; see Url. Views write u('/admin'). */
function u(string $path = '/'): string
{
    return Url::to($path);
}

/**
 * Redirect. App paths ('/admin/...') go through Url::to(); anything else
 * (an absolute URL, /account/...) is used as given.
 */
function redirect(string $url): never
{
    if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_starts_with($url, '/account/')) {
        $url = Url::to($url);
    }
    header('Location: ' . $url);
    exit;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** The original's url() helper, kept for any caller: an absolute-from-root link. */
function url(string $path = ''): string
{
    return Url::to('/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return \Bizorca\Consulting\Auth\CSRF::field();
}

function current_user(): ?array
{
    return \Bizorca\Consulting\Auth\Session::user();
}

/**
 * Foundry admin: the site owner (users.is_admin) or a row in fd_admins.
 * The original mirrored login.bizorca.com's admin flag; on the shared site
 * that flag already means "site owner", so it carries over, and fd_admins
 * lets someone run Foundry without being a site-wide admin.
 */
function fd_user_is_admin(int $userId, bool $siteAdmin): bool
{
    if ($siteAdmin) {
        return true;
    }
    return (bool) Database::fetchOne('SELECT 1 AS x FROM fd_admins WHERE user_id = ?', [$userId]);
}

function is_admin(): bool
{
    $user = current_user();
    return $user && !empty($user['is_admin']);
}

/** Send a visitor to the shared sign-in and back to this page afterwards. */
function require_auth(): void
{
    tl_require_login();
}

function require_admin(): void
{
    require_auth();
    if (!is_admin()) {
        http_response_code(403);
        render('error', ['code' => 403, 'message' => 'Admin access required.']);
        exit;
    }
}

/**
 * SQL for a joined user's first and last name, split from the shared
 * account's one name the same way Session::user() does it.
 */
function fd_name_cols(string $alias = 'u'): string
{
    return "SUBSTRING_INDEX({$alias}.name, ' ', 1) AS first_name, "
         . "TRIM(SUBSTRING({$alias}.name, CHAR_LENGTH(SUBSTRING_INDEX({$alias}.name, ' ', 1)) + 1)) AS last_name";
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    return match(true) {
        $diff < 60     => 'just now',
        $diff < 3600   => floor($diff / 60) . 'm ago',
        $diff < 86400  => floor($diff / 3600) . 'h ago',
        $diff < 604800 => floor($diff / 86400) . 'd ago',
        default        => date('M j, Y', strtotime($datetime)),
    };
}
