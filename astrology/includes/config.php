<?php
/*
 * Ba Zi Astrology ("Meridian"), as a tool on tools.bizorca.com/astrology.
 *
 * AS_ROOT is set by public/_bootstrap.php. Account and database come from the
 * shared core in private_html/includes: one users table, one MySQL database,
 * this tool's tables prefixed as_. Keys come from private_html/.env.php.
 *
 * Ported 2026-10-08 from the original at deprecated/astrology. What changed and
 * why is in ../CLAUDE.md; the function names below are the original's, so the
 * pages did not need rewriting.
 */

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([AS_ROOT . '/../includes', AS_ROOT . '/../private_html/includes'] as $_dir) {
    if (is_file($_dir . '/bootstrap.php')) {
        require_once $_dir . '/bootstrap.php';
        break;
    }
}
unset($_dir);

define('APP_NAME', 'Ba Zi Astrology');
define('BASE_PATH', '/astrology');                                         // every url() is under this
define('APP_URL', (string) tl_env('AS_ORIGIN', 'https://tools.bizorca.com')); // origin only; APP_URL . url('/x.php') is absolute
define('MAIL_FROM', (string) tl_env('MAIL_FROM', 'Bizorca Tools <tools@bizorca.com>'));

// Anthropic Claude API. The model id is what the live site ran with.
define('ANTHROPIC_API_KEY', (string) tl_env('ANTHROPIC_API_KEY', ''));
define('ANTHROPIC_MODEL', (string) tl_env('AS_ANTHROPIC_MODEL', 'claude-sonnet-5'));

// Who gets "request a spot" lead notifications. The original sent them to its
// ADMIN_EMAIL, which was also how it decided who was admin; admin is now
// users.is_admin or a row in as_admins (see isAdmin()).
define('ADMIN_EMAIL', (string) tl_env('AS_LEAD_EMAIL', 'jassen.bowman@gmail.com'));

/*
 * Sessions. The shared session is lazy: a visitor who only reads never gets a
 * cookie. Resume one if it exists; a form POST (anonymous profile, star seed
 * quiz, sign-in claims) is the moment a visitor has acted, so it may create one.
 * The JSON API never touches the session; it is bearer-token only.
 */
if (!defined('AS_API') && PHP_SAPI !== 'cli') {
    tl_session(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
}

// Security headers that used to come from .htaccess, which this server ignores.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * The shared handle, with the session clock the live site ran on. The original's
 * MySQL server was set to America/Chicago while PHP ran in UTC (both checked
 * 2026-10-08), so every date it displayed from a TIMESTAMP column was Chicago
 * time, and DATETIME columns it wrote (api token expiry, forecast generated_at)
 * hold Chicago wall time. tl_db() defaults to UTC; Astrology keeps Chicago so
 * its imported data and its pages mean what they meant. The offset is computed
 * per request, so it follows daylight saving without MySQL's zone tables.
 */
const AS_DB_TIMEZONE = 'America/Chicago';

function getDB(): PDO {
    static $ready = false;
    $db = tl_db();
    if (!$ready) {
        $offset = (new DateTime('now', new DateTimeZone(AS_DB_TIMEZONE)))->format('P');
        $db->exec("SET time_zone = '{$offset}'");
        $ready = true;
    }
    return $db;
}

/* ─────────────────────────────────────────────────────────────── auth ───── */

function isLoggedIn(): bool {
    return tl_user() !== null;
}

/**
 * Sign-in goes through the shared /account pages, then back through
 * auth-return.php, which claims anything the visitor did before signing in
 * (a star seed quiz, an anonymous profile) and then lands on $redirect.
 */
function authUrl(string $page, string $redirect = ''): string {
    $back = url('/auth-return.php') . ($redirect !== '' ? '?redirect=' . rawurlencode($redirect) : '');
    return '/account/' . $page . '.php?next=' . rawurlencode($back);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . authUrl('login', $_SERVER['REQUEST_URI'] ?? url('/dashboard.php')));
        exit;
    }
}

function url(string $path): string {
    return BASE_PATH . $path;
}

function getCurrentUserId(): ?int {
    $u = tl_user();
    return $u ? (int) $u['id'] : null;
}

/**
 * The shared users row plus Astrology's own per-person fields (as_members:
 * primary_profile_id, timezone), so pages that read $user['primary_profile_id']
 * work unchanged. The as_members row is created on first visit.
 */
function getCurrentUser(): ?array {
    $u = tl_user();
    if (!$u) return null;
    $id = (int) $u['id'];
    getDB()->prepare('INSERT IGNORE INTO as_members (user_id) VALUES (?)')->execute([$id]);
    $stmt = getDB()->prepare('SELECT primary_profile_id, timezone FROM as_members WHERE user_id = ?');
    $stmt->execute([$id]);
    $m = $stmt->fetch() ?: ['primary_profile_id' => null, 'timezone' => 'America/New_York'];
    return $u + $m;
}

function setPrimaryProfile(int $userId, int $profileId): void {
    getDB()->prepare(
        'INSERT INTO as_members (user_id, primary_profile_id) VALUES (?, ?) AS new
         ON DUPLICATE KEY UPDATE primary_profile_id = new.primary_profile_id'
    )->execute([$userId, $profileId]);
}

function isAstrologyAdmin(int $userId): bool {
    $stmt = getDB()->prepare('SELECT 1 FROM as_admins WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Remove one person's Astrology data. The shared account is untouched: it
 * belongs to every tool on the site.
 */
function removeAstrologyMember(int $userId): void {
    $db = getDB();
    $db->beginTransaction();
    $db->prepare("DELETE FROM as_readings WHERE user_id = ? OR profile_id IN (SELECT id FROM as_profiles WHERE user_id = ?)")->execute([$userId, $userId]);
    $db->prepare("DELETE FROM as_profiles WHERE user_id = ?")->execute([$userId]);
    foreach (['as_starseed_results', 'as_offer_interest', 'as_api_tokens', 'as_user_meditations', 'as_user_forecasts', 'as_admins', 'as_members'] as $t) {
        $db->prepare("DELETE FROM {$t} WHERE user_id = ?")->execute([$userId]);
    }
    $db->commit();
}

/** Site owner (users.is_admin) or an Astrology admin (as_admins). */
function isAdmin(): bool {
    $u = tl_user();
    if (!$u) return false;
    return !empty($u['is_admin']) || isAstrologyAdmin((int) $u['id']);
}

// Quiz-first funnel: visitors can take the star seed quiz before having an
// account; the scored result waits in $_SESSION['as_pending_starseed'] until they
// sign in, then this saves it to their account. Returns true if a pending
// result was claimed. The AI reading is generated later from the results page.
function claimPendingStarseed(int $userId): bool {
    $pending = $_SESSION['as_pending_starseed'] ?? null;
    if (!$pending || empty($pending['primary_lineage']) || empty($pending['secondary_lineage'])) {
        return false;
    }
    $db = getDB();
    $db->prepare("DELETE FROM as_starseed_results WHERE user_id = ?")->execute([$userId]);
    $db->prepare("INSERT INTO as_starseed_results (user_id, primary_lineage, secondary_lineage, scores_json, answers_json, reading_json) VALUES (?, ?, ?, ?, ?, NULL)")
       ->execute([
           $userId,
           $pending['primary_lineage'],
           $pending['secondary_lineage'],
           json_encode($pending['scores'] ?? []),
           json_encode($pending['answers'] ?? []),
       ]);
    unset($_SESSION['as_pending_starseed']);
    return true;
}

/**
 * Adopt the anonymous profile made before signing in. The original only did
 * this in its SSO callback, so a visitor who registered or logged in with a
 * password lost the chart they had just made.
 */
function claimPendingProfile(int $userId): bool {
    $profileId = (int) ($_SESSION['as_pending_profile_id'] ?? 0);
    if (!$profileId) return false;
    unset($_SESSION['as_pending_profile_id']);
    $db = getDB();
    // The id in this visitor's own session is the proof of ownership. Matching
    // session_id would never succeed: signing in regenerates the session id.
    $stmt = $db->prepare("UPDATE as_profiles SET user_id = ? WHERE id = ? AND user_id IS NULL");
    $stmt->execute([$userId, $profileId]);
    if ($stmt->rowCount() === 0) return false;
    $db->prepare("UPDATE as_readings SET user_id = ? WHERE profile_id = ? AND user_id IS NULL")->execute([$userId, $profileId]);
    $user = getCurrentUser();
    if ($user && empty($user['primary_profile_id'])) {
        setPrimaryProfile($userId, $profileId);
    }
    return true;
}

/* ─────────────────────────────────────────────────────────────── csrf ───── */

// A visitor reading an anonymous page has no session, and minting one just to
// hold a token would set a cookie on every landing-page view. So a form rendered
// without a session carries an empty token, and a POST that arrives with no
// prior session is accepted only from this site (Origin, else Referer).
function generateCSRFToken(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) return '';
    if (empty($_SESSION['as_csrf_token'])) {
        $_SESSION['as_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['as_csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    if (!empty($_SESSION['as_csrf_token'])) {
        return hash_equals($_SESSION['as_csrf_token'], $token);
    }
    $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
    $host   = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return $source !== '' && $host !== ''
        && strtolower((string) parse_url($source, PHP_URL_HOST)) === strtolower((string) parse_url('//' . $host, PHP_URL_HOST));
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

/* ────────────────────────────────────────────────────────────── flash ───── */

function setFlash(string $type, string $message): void {
    tl_session(true);
    $_SESSION['as_flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) return null;
    $flash = $_SESSION['as_flash'] ?? null;
    unset($_SESSION['as_flash']);
    return $flash;
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/* ─────────────────────────────────────────────────────── app settings ───── */

function getAppSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = getDB()->prepare("SELECT setting_value FROM as_app_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? $row['setting_value'] : $default;
    } catch (PDOException $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

function setAppSetting(string $key, string $value): void {
    getDB()->prepare(
        "INSERT INTO as_app_settings (setting_key, setting_value) VALUES (?, ?) AS new
         ON DUPLICATE KEY UPDATE setting_value = new.setting_value"
    )->execute([$key, $value]);
}

/* ─────────────────────────────────────────────────────────── access ───── */

// Everything is free during early access. These are the single seam a future
// paid plan would change. The original's SSO entitlement and Stripe plumbing are
// gone; there were no subscriptions in the live data.
function isBetaMode(): bool {
    return false;
}

function userHasAccess(?int $userId = null): bool {
    return true; // open access — all features free while in beta
}

function getEffectivePlan(?int $userId = null): string {
    return userHasAccess($userId) ? 'premium' : 'free';
}

function isInBetaGracePeriod(?int $userId = null): bool {
    return false;
}

function hasActiveSubscription(int $userId): bool {
    return false; // no billing on the platform; userHasAccess() is the gate
}
