<?php
declare(strict_types=1);

/*
 * Numbrella, as a tool on tools.bizorca.com/numbrella.
 *
 * NB_ROOT is set by public/_bootstrap.php. Account and database come from the
 * shared core in private_html/includes: one users table, one MySQL database,
 * Numbrella's tables prefixed nb_. Keys come from private_html/.env.php.
 */

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([NB_ROOT . '/../includes', NB_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

define('APP_NAME', 'Numbrella');
define('NB_BASE',  '/numbrella');                              // URL prefix for every link and redirect
define('APP_URL',  'https://tools.bizorca.com' . NB_BASE);     // absolute base for share links

// Generated PDFs, one folder per user. Never under the web root.
define('NB_DATA', TL_PRIVATE . '/data/numbrella');

/*
 * Payments. Every report is free while this is false (the default): finishing
 * the wizard produces the full report, everything the old $99 Premium tier had.
 * Set NB_PAYMENTS_ENABLED => true in .env.php to bring back the $29 / $99
 * checkout (includes/payments.php, public/checkout.php, public/webhook.php).
 */
define('NB_PAYMENTS_ENABLED', (bool) tl_env('NB_PAYMENTS_ENABLED', false));
define('NB_PRICE_STANDARD',   (int) tl_env('NB_PRICE_STANDARD_CENTS', 2900));
define('NB_PRICE_PREMIUM',    (int) tl_env('NB_PRICE_PREMIUM_CENTS', 9900));

// nginx sends PHP straight to PHP-FPM here, so headers come from PHP, not .htaccess.
// No CSP: pages load Tailwind from its CDN and use inline scripts.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/** The shared MySQL handle. */
function getDB(): PDO
{
    return tl_db();
}

// ---------------------------------------------------------------------------
// Auth (the shared tools account)
// ---------------------------------------------------------------------------

/** @return array the signed-in user row; sends anyone else to sign in */
function requireLogin(): array
{
    return tl_require_login();
}

function getCurrentUser(): ?array
{
    return tl_user();
}

function getCurrentUserId(): ?int
{
    $u = tl_user();
    return $u ? (int) $u['id'] : null;
}

function isAdmin(): bool
{
    $u = tl_user();
    return $u !== null && (int) $u['is_admin'] === 1;
}

function requireAdmin(): array
{
    return tl_require_admin();
}

// ---------------------------------------------------------------------------
// CSRF + flash. Both live in the shared session, which only signed-in users
// have, so neither ever creates a cookie for an anonymous visitor.
// ---------------------------------------------------------------------------

function csrfField(): string
{
    return tl_csrf_field();
}

function verifyCSRFToken(?string $token): bool
{
    return tl_csrf_ok($token);
}

function setFlash(string $type, string $message): void
{
    if (tl_session()) {
        $_SESSION['nb_flash'] = ['type' => $type, 'message' => $message];
    }
}

function getFlash(): ?array
{
    if (!tl_session() || empty($_SESSION['nb_flash'])) {
        return null;
    }
    $flash = $_SESSION['nb_flash'];
    unset($_SESSION['nb_flash']);
    return $flash;
}

// ---------------------------------------------------------------------------
// Output + URLs
// ---------------------------------------------------------------------------

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** A site-relative URL under the tool's base path. */
function url(string $path = '/'): string
{
    return NB_BASE . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function notFound(): never
{
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:2rem">Report not found.</p>';
    exit;
}

/** Whole dollars, with the sign outside the dollar sign: -$12,000, not $-12,000. */
function money(float|int $amount): string
{
    $amount = round((float) $amount);
    return ($amount < 0 ? '-$' : '$') . number_format(abs($amount), 0);
}

/** A form amount like "350,000" or "$1,200.50" as a float; blank or junk is 0. */
function parseAmount(mixed $raw): float
{
    $clean = preg_replace('/[^0-9.\-]/', '', (string) $raw) ?? '';
    return is_numeric($clean) ? (float) $clean : 0.0;
}

const NB_BUSINESS_TYPES = [
    'sole_prop'   => 'Sole Proprietorship',
    'llc'         => 'LLC',
    's_corp'      => 'S-Corporation',
    'c_corp'      => 'C-Corporation',
    'partnership' => 'Partnership',
];

// ---------------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------------

/**
 * A report the signed-in user may see: their own, or any report for a site
 * admin. Null otherwise, so callers answer 404 without saying it exists.
 */
function findOwnReport(int $id): ?array
{
    $user = tl_user();
    if (!$user || $id <= 0) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT * FROM nb_reports WHERE id = ? AND (user_id = ? OR ? = 1)');
    $stmt->execute([$id, $user['id'], (int) $user['is_admin']]);
    return $stmt->fetch() ?: null;
}

/**
 * Run the valuation and mark the report complete. Used by the wizard when
 * payments are off (always the full report) and by the webhook when they are on.
 */
function finalizeReport(array $report, string $tier): void
{
    require_once NB_ROOT . '/includes/valuation.php';
    require_once NB_ROOT . '/includes/risk.php';

    $report['tier'] = $tier;
    $valuation = runValuationEngine($report);
    $riskFlags = computeRiskFlags($report, $valuation);

    getDB()->prepare(
        "UPDATE nb_reports
            SET status = 'complete', tier = ?, valuation_json = ?, risk_flags_json = ?,
                share_token = COALESCE(share_token, ?), completed_at = UTC_TIMESTAMP()
          WHERE id = ?"
    )->execute([
        $tier,
        json_encode($valuation, JSON_THROW_ON_ERROR),
        json_encode($riskFlags, JSON_THROW_ON_ERROR),
        bin2hex(random_bytes(32)),
        $report['id'],
    ]);

    // Inputs changed or the engine re-ran: any cached PDF is stale.
    require_once NB_ROOT . '/includes/pdf.php';
    deleteReportPdf((int) $report['user_id'], (int) $report['id']);
}
