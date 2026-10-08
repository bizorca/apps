<?php

use Anglerfish\Core\Session;

/** Escape for HTML output. Use on every echo of dynamic data. */
function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL for an app route: url('/library?q=x') -> /anglerfish/?r=/library&q=x.
 *
 * Routes ride in ?r= because this Cloudways app has no try_files fallback to
 * send /anglerfish/library to index.php. Relative to the host, so the same
 * links work on tools.bizorca.com and on a local dev server.
 */
function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    [$route, $query] = array_pad(explode('?', $path, 2), 2, '');
    $out = AF_BASE . '/';
    if ($route !== '/') {
        // Callers already rawurlencode() slugs; only escape what would end the
        // r= value early, so an encoded slug is not encoded twice.
        $out .= '?r=' . strtr($route, ['&' => '%26', '#' => '%23', '+' => '%2B', ' ' => '%20']);
    }
    if ($query !== '') {
        $out .= ($route !== '/' ? '&' : '?') . $query;
    }
    return $out;
}

/** The route this request asked for: ?r=, normalised, '/' when absent. */
function af_route(): string
{
    $r = $_GET['r'] ?? '/';
    $r = '/' . trim(is_string($r) ? $r : '/', '/');
    return $r === '/' ? '/' : rtrim($r, '/');
}

function redirect(string $path): never
{
    // Only paths inside the app: a posted "back" value can never send the
    // browser off the site.
    if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
        $path = '/dashboard';
    }
    header('Location: ' . url($path));
    exit;
}

/**
 * Absolute path for a stored "storage/..." path (assets.web_path,
 * books.cover_path). Those were written relative to the old app root; the files
 * now live in AF_STORAGE.
 */
function af_file(string $stored): string
{
    $stored = ltrim($stored, '/');
    if (str_starts_with($stored, 'storage/')) {
        $stored = substr($stored, 8);
    }
    return AF_STORAGE . '/' . $stored;
}

function csrf_token(): string
{
    return Session::csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

/** Flash a message for the next request (PRG pattern). */
function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

function old(string $key, string $default = ''): string
{
    return (string) ($_POST[$key] ?? $default);
}
