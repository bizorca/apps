<?php
/**
 * The small set of Laravel helpers the original templates and controllers
 * leaned on, rebuilt without the framework: URLs and named routes, request
 * input, flash/old input/validation errors, CSRF, redirects, Carbon-style dates,
 * Str::, UUIDs, Markdown, rate limiting and views.
 *
 * Named after their Laravel counterparts (route(), old(), e(), back()) on
 * purpose, so the templates read the way the Blade did. They are global and
 * unprefixed, which is safe because only Fathom code loads in a Fathom request;
 * the shared core is all tl_-prefixed.
 */

declare(strict_types=1);

/* ───────────────────────────────────────────────────────────── URLs ────── */

/**
 * A Fathom URL for an app path like '/boards/5'. The single place that knows
 * the URL style; see FM_CLEAN_URLS in config.php.
 */
function fm_url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $qs   = $query ? http_build_query($query) : '';

    if (FM_CLEAN_URLS || $path === '/') {
        $url = FM_BASE . ($path === '/' ? '/' : $path);
        return $qs === '' ? $url : $url . '?' . $qs;
    }

    $url = FM_BASE . '/?r=' . $path;
    return $qs === '' ? $url : $url . '&' . $qs;
}

/** Absolute URL, for emails, QR codes and copy-paste invite links. */
function fm_absolute(string $url): string
{
    $https  = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
           || (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off');
    $host   = $_SERVER['HTTP_HOST'] ?? 'tools.bizorca.com';
    return ($https ? 'https' : 'http') . '://' . $host . $url;
}

/**
 * Hidden input that keeps a GET form on its route in query-string mode:
 * browsers drop the action's own query string when they submit a GET form.
 */
function fm_route_field(string $name, array $params = []): string
{
    if (FM_CLEAN_URLS) {
        return '';
    }
    return '<input type="hidden" name="r" value="' . e(fm_route_path($name, $params)) . '">';
}

/** The current app path, from ?r= or, with clean URLs, from the request URI. */
function fm_current_path(): string
{
    if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
        $p = $_GET['r'];
    } else {
        $p = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_starts_with($p, FM_BASE)) {
            $p = substr($p, strlen(FM_BASE));
        }
        if ($p === '' || $p === '/index.php') {
            $p = '/';
        }
    }
    $p = '/' . trim($p, '/');
    return $p;
}

/* ─────────────────────────────────────────────────────────── request ───── */

final class FmRequest
{
    private static ?array $json = null;

    public static function method(): string
    {
        $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($m === 'POST') {
            $spoof = strtoupper((string) ($_POST['_method'] ?? ''));
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoof;
            }
        }
        return $m;
    }

    /** POST body, JSON body (fetch from the board), then query string. */
    public static function all(): array
    {
        if (self::$json === null) {
            self::$json = [];
            if (str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
                $decoded = json_decode((string) file_get_contents('php://input'), true);
                self::$json = is_array($decoded) ? $decoded : [];
            }
        }
        $q = $_GET;
        unset($q['r']);
        return array_merge($q, self::$json, $_POST);
    }

    /**
     * Laravel's ConvertEmptyStringsToNull and TrimStrings, which every input
     * went through in the original: '  ' and '' both arrive as null.
     */
    public static function input(?string $key = null, mixed $default = null): mixed
    {
        $all = self::normalize(self::all());
        if ($key === null) {
            return $all;
        }
        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    private static function normalize(mixed $v): mixed
    {
        if (is_array($v)) {
            return array_map([self::class, 'normalize'], $v);
        }
        if (is_string($v)) {
            $v = trim($v);
            return $v === '' ? null : $v;
        }
        return $v;
    }

    public static function filled(string $key): bool
    {
        $v = self::input($key);
        return $v !== null && $v !== [];
    }

    public static function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'json');
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    }
}

/** request() -> object with get/filled/hasAny/routeIs; request('q') -> value. */
function request(?string $key = null, mixed $default = null): mixed
{
    if ($key !== null) {
        return FmRequest::input($key, $default);
    }
    return new class {
        public function get(string $k, mixed $d = null): mixed { return FmRequest::input($k, $d); }
        public function filled(string $k): bool { return FmRequest::filled($k); }
        public function hasAny(array $keys): bool
        {
            foreach ($keys as $k) {
                if (array_key_exists($k, FmRequest::all())) {
                    return true;
                }
            }
            return false;
        }
        public function routeIs(string $pattern): bool
        {
            $name = $GLOBALS['fm_route_name'] ?? '';
            return fnmatch($pattern, $name);
        }
    };
}

/* ─────────────────────────────────────── session, flash, errors, old ───── */

/** Pull last request's flash into this request, once, at dispatch. */
function fm_flash_boot(): void
{
    $GLOBALS['fm_flash_now'] = [];
    if (tl_session()) {
        $GLOBALS['fm_flash_now'] = $_SESSION['fm_flash'] ?? [];
        unset($_SESSION['fm_flash']);
    }
}

function flash(string $key, mixed $value): void
{
    tl_session(true);
    $_SESSION['fm_flash'][$key] = $value;
}

/** session('success') reads flash; session(['k' => v]) and session('k') for stored keys. */
function session(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $GLOBALS['fm_flash_now'] ?? [])) {
        return $GLOBALS['fm_flash_now'][$key];
    }
    return $default;
}

final class FmErrors
{
    /** @param array<string, string[]> $bag */
    public function __construct(private array $bag = []) {}
    public function any(): bool { return $this->bag !== []; }
    public function all(): array { return array_merge(...array_values($this->bag ?: [[]])); }
    public function first(?string $key = null): string
    {
        if ($key !== null) {
            return $this->bag[$key][0] ?? '';
        }
        return $this->all()[0] ?? '';
    }
    public function has(string $key): bool { return isset($this->bag[$key]); }
}

function errors(): FmErrors
{
    return new FmErrors((array) session('_errors', []));
}

/** old('field', $fallback): the value the visitor typed before a failed submit. */
function old(string $key, mixed $default = null): mixed
{
    $old = (array) session('_old', []);
    return array_key_exists($key, $old) ? $old[$key] : $default;
}

/* ──────────────────────────────────────────────────────────── csrf ─────── */

function csrf_token(): string
{
    return tl_csrf_token();
}

/** Laravel's @csrf, under Laravel's field name. */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

function fm_csrf_valid(): bool
{
    $sent = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    return tl_csrf_ok(is_string($sent) ? $sent : null);
}

/* ───────────────────────────────────────────────────── responses ───────── */

final class FmHalt extends RuntimeException {}

function redirect_to(string $url, int $status = 302): never
{
    header('Location: ' . $url, true, $status);
    throw new FmHalt();
}

/** Laravel's back(): the referring page on this site, or the fallback. */
function back_url(string $fallback = ''): string
{
    $ref  = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($ref !== '' && strtolower((string) parse_url($ref, PHP_URL_HOST)) === strtolower((string) parse_url('//' . $host, PHP_URL_HOST))) {
        $path = (string) parse_url($ref, PHP_URL_PATH);
        $q    = parse_url($ref, PHP_URL_QUERY);
        return $path . ($q ? '?' . $q : '');
    }
    return $fallback !== '' ? $fallback : fm_url('/');
}

function back(array $flash = []): never
{
    foreach ($flash as $k => $v) {
        flash($k, $v);
    }
    redirect_to(back_url(), 302);
}

function abort(int $status, string $message = ''): never
{
    throw new FmAbort($status, $message);
}

final class FmAbort extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message);
    }
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    throw new FmHalt();
}

/**
 * Render a template from templates/. Templates set $title and capture their
 * body, then pull in a layout from templates/layouts/, the way @extends did.
 */
function view(string $name, array $data = []): never
{
    echo fm_render($name, $data);
    throw new FmHalt();
}

function fm_render(string $name, array $data = []): string
{
    $file = FM_ROOT . '/templates/' . str_replace('.', '/', $name) . '.php';
    extract($data, EXTR_SKIP);
    $errors = errors();
    ob_start();
    try {
        require $file;
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    return (string) ob_get_clean();
}

/** Close a template body and render it inside a layout. */
function fm_layout(string $layout, string $title, string $content, array $extra = []): void
{
    $errors = errors();
    extract($extra, EXTR_SKIP);
    require FM_ROOT . '/templates/layouts/' . $layout . '.php';
}

/* ─────────────────────────────────────────────────────────── escaping ──── */

function e(mixed $value): string
{
    if ($value instanceof FmDate) {
        $value = (string) $value;
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
}

/* ─────────────────────────────────────────────────────────── dates ─────── */

/** Enough of Carbon for what the templates call: format, diffForHumans, isPast. */
final class FmDate implements Stringable, JsonSerializable
{
    public readonly DateTimeImmutable $dt;

    public function __construct(string|DateTimeInterface $value)
    {
        $utc = new DateTimeZone('UTC');
        $this->dt = $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)->setTimezone($utc)
            : new DateTimeImmutable($value, $utc);
    }

    public static function now(): self { return new self('now'); }

    public function format(string $f): string { return $this->dt->format($f); }
    public function isPast(): bool { return $this->dt < new DateTimeImmutable('now', new DateTimeZone('UTC')); }
    public function isFuture(): bool { return !$this->isPast(); }
    public function timestamp(): int { return $this->dt->getTimestamp(); }

    /** Whole days between this and now, unsigned. */
    public function daysFromNow(): float
    {
        return abs($this->dt->getTimestamp() - time()) / 86400;
    }

    /**
     * Carbon 3's wording, verified against the original's vendor copy:
     * calendar difference, largest unit, "N unit(s) ago" / "from now",
     * weeks between days and months, "0 seconds ago" for now.
     */
    public function diffForHumans(): string
    {
        $now  = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $diff = $now->diff($this->dt);
        $past = $diff->invert === 1;
        $units = [
            'year'   => $diff->y,
            'month'  => $diff->m,
            'week'   => intdiv($diff->d, 7),
            'day'    => $diff->d,
            'hour'   => $diff->h,
            'minute' => $diff->i,
            'second' => $diff->s,
        ];
        $n = 0;
        $unit = 'second';
        foreach ($units as $u => $v) {
            if ($v > 0) {
                $n = $v;
                $unit = $u;
                break;
            }
        }
        $label = $n . ' ' . $unit . ($n === 1 ? '' : 's');
        return $label . ($past || $n === 0 ? ' ago' : ' from now');
    }

    /** Laravel's model serialisation format. */
    public function jsonSerialize(): string { return $this->dt->format('Y-m-d\TH:i:s.u\Z'); }
    public function __toString(): string { return $this->dt->format('Y-m-d H:i:s'); }
}

/** now() as a DB string, UTC (tl_db() runs MySQL in UTC too). */
function now_sql(): string
{
    return gmdate('Y-m-d H:i:s');
}

/* ───────────────────────────────────────────────────────────── Str ─────── */

final class Str
{
    public static function limit(?string $value, int $limit = 100, string $end = '...'): string
    {
        $value = (string) $value;
        return mb_strwidth($value, 'UTF-8') <= $limit
            ? $value
            : rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    public static function plural(string $word, int|float $count = 2): string
    {
        return (int) $count === 1 ? $word : $word . 's';
    }

    public static function random(int $length = 16): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, 61)];
        }
        return $out;
    }

    public static function slug(string $title): string
    {
        $s = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title), '-'));
        return $s;
    }
}

/** Time-ordered UUID, the shape Laravel's HasUuids produced. */
function fm_uuid(): string
{
    $ms    = (int) floor(microtime(true) * 1000);
    $bytes = pack('J', $ms);
    $b     = substr($bytes, 2, 6) . random_bytes(10);
    $b[6]  = chr((ord($b[6]) & 0x0f) | 0x70);
    $b[8]  = chr((ord($b[8]) & 0x3f) | 0x80);
    $h     = bin2hex($b);
    return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
}

function fm_is_uuid(mixed $v): bool
{
    return is_string($v) && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);
}

/* ─────────────────────────────────────────────────────────── markdown ──── */

/**
 * Markdown to HTML with raw HTML escaped and unsafe links neutralised: the
 * original's CommonMark settings (html_input: strip, allow_unsafe_links: false)
 * via Parsedown's safe mode. Used for comments, as before, and now for card
 * descriptions too, which the original printed as raw HTML.
 */
function markdown(?string $text): string
{
    $p = new Parsedown();
    $p->setSafeMode(true);
    $p->setBreaksEnabled(false);
    return $p->text((string) $text);
}

/* ─────────────────────────────────────────────────────── rate limit ────── */

/** Laravel RateLimiter::tooManyAttempts / hit, kept in fm_rate_limits. */
function fm_too_many(string $key, int $max): bool
{
    $s = tl_db()->prepare('SELECT attempts FROM fm_rate_limits WHERE rate_key = ? AND reset_at > NOW()');
    $s->execute([$key]);
    return (int) $s->fetchColumn() >= $max;
}

function fm_hit(string $key, int $decaySeconds): void
{
    $db = tl_db();
    $db->prepare('DELETE FROM fm_rate_limits WHERE rate_key = ? AND reset_at <= NOW()')->execute([$key]);
    $db->prepare(
        'INSERT INTO fm_rate_limits (rate_key, attempts, reset_at)
         VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND)) AS new
         ON DUPLICATE KEY UPDATE attempts = fm_rate_limits.attempts + 1'
    )->execute([$key, $decaySeconds]);
}

/* ─────────────────────────────────────────────────────── validation ────── */

/**
 * The subset of Laravel validation the controllers used, with Laravel's
 * messages. Returns only the keys that were present (what validated() did).
 * On failure: errors and old input flash, then back(); JSON callers get 422.
 */
function validate(array $rules): array
{
    $in     = FmRequest::input();
    $out    = [];
    $errors = [];

    foreach ($rules as $field => $ruleString) {
        $list = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
        if (str_contains($field, '.*')) {
            $base = substr($field, 0, -2);
            foreach ((array) ($in[$base] ?? []) as $i => $v) {
                $err = fm_check($base . '.' . $i, $v, $list, $in);
                if ($err) {
                    $errors[$base . '.' . $i][] = $err;
                }
            }
            continue;
        }
        $present = array_key_exists($field, $in);
        $value   = $in[$field] ?? null;
        if (in_array('image', $list, true)) {
            $value   = ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? null : $_FILES[$field];
            $present = $value !== null;
        }
        $err = fm_check($field, $value, $list, $in);
        if ($err) {
            $errors[$field][] = $err;
        } elseif ($present) {
            $out[$field] = $value;
        }
    }

    if ($errors) {
        if (FmRequest::wantsJson()) {
            json_response(['message' => array_values($errors)[0][0], 'errors' => $errors], 422);
        }
        flash('_errors', $errors);
        $old = $_POST;
        unset($old['_token'], $old['_method'], $old['password']);
        flash('_old', $old);
        redirect_to(back_url(), 302);
    }
    return $out;
}

function fm_attr(string $field): string
{
    return str_replace('_', ' ', $field);
}

function fm_check(string $field, mixed $v, array $rules, array $all): ?string
{
    $a        = fm_attr($field);
    $nullable = in_array('nullable', $rules, true);
    $isInt    = in_array('integer', $rules, true);
    $isArray  = in_array('array', $rules, true);

    if ($v === null || $v === [] ) {
        if (in_array('required', $rules, true)) {
            return "The {$a} field is required.";
        }
        return null;
    }
    foreach ($rules as $r) {
        [$name, $arg] = array_pad(explode(':', $r, 2), 2, null);
        switch ($name) {
            case 'string':
                if (!is_string($v)) return "The {$a} field must be a string.";
                break;
            case 'array':
                if (!is_array($v)) return "The {$a} field must be an array.";
                break;
            case 'email':
                if (!is_string($v) || !filter_var($v, FILTER_VALIDATE_EMAIL)) return "The {$a} field must be a valid email address.";
                break;
            case 'uuid':
                if (!fm_is_uuid($v)) return "The {$a} field must be a valid UUID.";
                break;
            case 'boolean':
                if (!in_array($v, [true, false, 0, 1, '0', '1'], true)) return "The {$a} field must be true or false.";
                break;
            case 'integer':
                if (filter_var($v, FILTER_VALIDATE_INT) === false) return "The {$a} field must be an integer.";
                break;
            case 'date':
                if (!is_string($v) || strtotime($v) === false) return "The {$a} field must be a valid date.";
                break;
            case 'in':
                if (!in_array((string) $v, explode(',', (string) $arg), true)) return "The selected {$a} is invalid.";
                break;
            case 'image':
                $ok = is_array($v) && ($v['error'] ?? 1) === UPLOAD_ERR_OK
                    && in_array(@getimagesize($v['tmp_name'])[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
                if (!$ok) return "The {$a} field must be an image.";
                break;
            case 'max':
                if (in_array('image', $rules, true)) {
                    if (is_array($v) && ($v['size'] ?? 0) > (int) $arg * 1024) return "The {$a} field must not be greater than {$arg} kilobytes.";
                } elseif ($isInt) {
                    if ((int) $v > (int) $arg) return "The {$a} field must not be greater than {$arg}.";
                } elseif ($isArray || is_array($v)) {
                    if (count($v) > (int) $arg) return "The {$a} field must not have more than {$arg} items.";
                } elseif (mb_strlen((string) $v) > (int) $arg) {
                    return "The {$a} field must not be greater than {$arg} characters.";
                }
                break;
            case 'min':
                if ($isInt && (int) $v < (int) $arg) return "The {$a} field must be at least {$arg}.";
                if (!$isInt && is_string($v) && mb_strlen($v) < (int) $arg) return "The {$a} field must be at least {$arg} characters.";
                break;
        }
    }
    return null;
}

/** Checkbox/boolean input to 0/1. */
function fm_bool(mixed $v): int
{
    return in_array($v, [true, 1, '1', 'true', 'on'], true) ? 1 : 0;
}

/* ─────────────────────────────────────────────────────────── mail ──────── */

require_once TL_PRIVATE . '/includes/mailer.php';

function fm_mail(string $to, string $subject, string $text): bool
{
    return tl_mail($to, $subject, $text);
}
