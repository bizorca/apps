<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Output escaping
// ---------------------------------------------------------------------------
function h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------------
// URLs
//
// Every link is built through url(), so moving the app is one constant:
// KIT_BASE, the /kit prefix on tools.bizorca.com.
// ---------------------------------------------------------------------------
function url(string $path = ''): string
{
    return KIT_BASE . '/' . ltrim($path, '/');
}

/**
 * Cache-busting asset URL: ?v=<filemtime>.
 *
 * Resolves against KIT_PUBLIC (Kit's own public folder, set by
 * _bootstrap.php), not DOCUMENT_ROOT. Under /kit on the shared site,
 * DOCUMENT_ROOT is the site's web root, so the file would never be found and
 * the version would pin at ?v=1, serving stale CSS after every deploy. That
 * same bug happened once already on SiteGround, with public/ vs public_html/.
 */
function asset(string $path): string
{
    $rel  = ltrim($path, '/');
    $file = (defined('KIT_PUBLIC') ? KIT_PUBLIC : KIT_ROOT . '/public') . '/' . $rel;
    $v    = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $v;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------
function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid CSRF token. Go back, reload the page, and try again.');
    }
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------
function flash(string $key, string $message = ''): string
{
    // Reading needs no session if the visitor has none: don't mint a cookie on
    // every anonymous page view just to find there is nothing to show.
    if ($message === '' && !tl_session()) return '';
    if (session_status() === PHP_SESSION_NONE) session_start();
    if ($message !== '') { $_SESSION['flash'][$key] = $message; return ''; }
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function flashError(string $m): void   { flash('error', $m); }
function flashSuccess(string $m): void { flash('success', $m); }

// ---------------------------------------------------------------------------
// Input
// ---------------------------------------------------------------------------
function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function postArray(string $key): array
{
    $v = $_POST[$key] ?? [];
    return is_array($v) ? $v : [];
}

/**
 * Money/number field to float.
 *
 * Advisors paste figures straight off a bank statement, so "$38,500.00" and
 * "(2,700)" both have to survive the trip. An empty string stays null-ish 0.0
 * rather than becoming a real zero the reading would then act on.
 */
function num(mixed $v, float $default = 0.0): float
{
    if (is_float($v) || is_int($v)) return (float) $v;
    if (!is_string($v)) return $default;
    $s = trim($v);
    if ($s === '') return $default;
    $neg = str_starts_with($s, '(') && str_ends_with($s, ')');
    $s = preg_replace('/[^0-9.\-]/', '', $s) ?? '';
    if ($s === '' || $s === '-' || $s === '.') return $default;
    $f = (float) $s;
    return $neg ? -abs($f) : $f;
}

function postNum(string $key, float $default = 0.0): float
{
    return num($_POST[$key] ?? null, $default);
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------
function money(float $amount, int $decimals = 2): string
{
    $s = '$' . number_format(abs($amount), $decimals);
    return $amount < 0 ? '-' . $s : $s;
}

function money0(float $amount): string { return money($amount, 0); }

function pct(float $ratio, int $decimals = 1): string
{
    return number_format($ratio * 100, $decimals) . '%';
}

function hours(float $h): string
{
    return rtrim(rtrim(number_format($h, 1), '0'), '.') . ' hrs';
}

function niceDate(?string $iso): string
{
    if (!$iso) return '';
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : (string) $iso;
}

// ---------------------------------------------------------------------------
// JSON blob accessors
//
// Worksheet answers are stored as one JSON document per assessment. The rows
// inside them are variable-length (SWOT entries, capacity lines, candidate
// issues), and six more tables to model that would buy nothing — nothing ever
// queries across worksheets.
// ---------------------------------------------------------------------------
function jget(array $data, string $key, mixed $default = ''): mixed
{
    return $data[$key] ?? $default;
}

function jrows(array $data, string $key): array
{
    $rows = $data[$key] ?? [];
    return is_array($rows) ? array_values($rows) : [];
}

/**
 * Read one key out of a posted array and validate it against an allow-list.
 *
 * Replaces the `in_array($_POST['x'][$k] ?? '', $allowed, true) ? $_POST['x'][$k] : ''`
 * shape, where the `??` guards only the test and the true branch still touches
 * the undefined key. Also keeps a posted value from ever reaching the database
 * unless it is one the app defined.
 */
function postPick(string $field, string $key, array $allowed, string $default = ''): string
{
    $v = $_POST[$field][$key] ?? null;
    if (!is_string($v)) return $default;
    $v = trim($v);
    return in_array($v, $allowed, true) ? $v : $default;
}

/** Same, for a positionally-indexed repeating row. */
function postPickRow(string $field, int $i, array $allowed, string $default = ''): string
{
    return postPick($field, (string) $i, $allowed, $default);
}

/**
 * Collapse parallel form arrays (name="row_hours[]", name="row_label[]") into
 * a list of row arrays, dropping rows where every field is blank.
 */
function collectRows(array $fields, ?callable $isEmpty = null): array
{
    $cols = [];
    $len  = 0;
    foreach ($fields as $out => $inputName) {
        $cols[$out] = postArray($inputName);
        $len = max($len, count($cols[$out]));
    }
    $rows = [];
    for ($i = 0; $i < $len; $i++) {
        $row = [];
        foreach ($cols as $out => $vals) {
            $v = $vals[$i] ?? '';
            $row[$out] = is_string($v) ? trim($v) : $v;
        }
        $blank = $isEmpty ? $isEmpty($row) : (implode('', array_map('strval', $row)) === '');
        if (!$blank) $rows[] = $row;
    }
    return $rows;
}
