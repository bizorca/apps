<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Output escaping
// ---------------------------------------------------------------------------
function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Money formatting
// ---------------------------------------------------------------------------
function money(float $amount, bool $showSign = false): string {
    $formatted = '$' . number_format(abs($amount), 2);
    if ($showSign && $amount < 0) return '-' . $formatted;
    if ($showSign && $amount > 0) return '+' . $formatted;
    if ($amount < 0) return '-' . $formatted;
    return $formatted;
}

function moneyShort(float $amount): string {
    if (abs($amount) >= 1000) {
        return '$' . number_format($amount / 1000, 1) . 'k';
    }
    return money($amount);
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------
function csrf(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------
function flash(string $key, string $message = ''): string {
    // Reading needs no session if the visitor has none: don't mint a cookie
    // on every anonymous page view just to find there is nothing to show.
    if ($message === '' && !tl_session()) return '';
    if (session_status() === PHP_SESSION_NONE) session_start();
    if ($message !== '') {
        $_SESSION['flash'][$key] = $message;
        return '';
    }
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function flashError(string $message): void   { flash('error', $message); }
function flashSuccess(string $message): void { flash('success', $message); }

function renderFlash(): string {
    $error   = flash('error');
    $success = flash('success');
    $html    = '';
    if ($error)   $html .= '<div class="flash flash-error">'   . h($error)   . '</div>';
    if ($success) $html .= '<div class="flash flash-success">' . h($success) . '</div>';
    return $html;
}

// ---------------------------------------------------------------------------
// Redirect
// ---------------------------------------------------------------------------
function redirect(string $url): never {
    // Pages pass app-relative paths ('/dashboard.php'); the app lives under PF_BASE.
    if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_starts_with($url, PF_BASE . '/')) {
        $url = PF_BASE . $url;
    }
    header('Location: ' . $url);
    exit;
}

// ---------------------------------------------------------------------------
// Input helpers
// ---------------------------------------------------------------------------
function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

function postFloat(string $key, float $default = 0.0): float {
    return (float)($_POST[$key] ?? $default);
}

function postInt(string $key, int $default = 0): int {
    return (int)($_POST[$key] ?? $default);
}

// ---------------------------------------------------------------------------
// Percent formatting
// ---------------------------------------------------------------------------
function pct(float $value, int $decimals = 1): string {
    return number_format($value * 100, $decimals) . '%';
}
