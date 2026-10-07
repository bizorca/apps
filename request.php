<?php
/**
 * "Request a tool" form handler for tools.bizorca.com.
 *
 * The page itself is static, so there is no session to hang a CSRF token on.
 * Instead the POST must come from this host (Origin, else Referer), which is
 * what a token would prove for a logged-out form anyway. Bots are filtered by
 * a honeypot field, a minimum time on page, and a per-IP hourly cap.
 *
 * Every accepted request is appended to private_html/tool-requests.jsonl
 * BEFORE mail is attempted, so a missing or broken SMTP2GO key never loses
 * one. The key lives in private_html/.env.php, which returns an array:
 *
 *   <?php return ['SMTP2GO_API_KEY' => 'api-...'];
 *
 * private_html is a sibling of public_html on Cloudways, outside the web root.
 */

declare(strict_types=1);

const REQUEST_TO     = 'jassen@bizorca.com';
const REQUEST_FROM   = 'Bizorca Tools <tools@bizorca.com>';
const MIN_SECONDS    = 3;
const MAX_PER_HOUR   = 5;

function back(string $state): never
{
    header('Location: /?request=' . $state . '#request', true, 303);
    exit;
}

function private_dir(): string
{
    $dir = dirname(__DIR__) . '/private_html';
    if (!is_dir($dir)) {
        // Local dev: the repo root is the web root, so keep scratch data in tmp.
        $dir = sys_get_temp_dir() . '/bizorca-tools';
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function env(string $key): string
{
    static $env = null;
    if ($env === null) {
        $file = private_dir() . '/.env.php';
        $env  = is_file($file) ? (array) require $file : [];
    }
    return (string) ($env[$key] ?? '');
}

function clean(string $field, int $max): string
{
    $v = trim((string) ($_POST[$field] ?? ''));
    return mb_substr($v, 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /#request', true, 303);
    exit;
}

// Same-origin check in place of a CSRF token.
$host   = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$source = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
if ($host === '' || strtolower((string) parse_url($source, PHP_URL_HOST)) !== strtolower((string) parse_url('//' . $host, PHP_URL_HOST))) {
    http_response_code(403);
    exit('Forbidden');
}

// Bots: pretend it worked so they move on.
$stamp = (int) ($_POST['t'] ?? 0);
if (clean('website', 200) !== '' || ($stamp > 0 && time() - $stamp < MIN_SECONDS)) {
    back('sent');
}

$name  = clean('name', 100);
$email = clean('email', 200);
$role  = clean('role', 10);
$need  = clean('need', 4000);
$now   = clean('now', 500);
$roles = ['owner' => 'Business owner', 'coach' => 'Coach or advisor', 'other' => 'Something else'];

if ($name === '' || $need === '' || !isset($roles[$role]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back('invalid');
}

// Per-IP hourly cap. Cloudflare sits in front, so the real client is in CF-Connecting-IP.
$ip      = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$rlFile  = private_dir() . '/ratelimit.json';
$fh      = fopen($rlFile, 'c+');
if ($fh && flock($fh, LOCK_EX)) {
    $hits   = json_decode((string) stream_get_contents($fh), true) ?: [];
    $cutoff = time() - 3600;
    foreach ($hits as $k => $times) {
        $hits[$k] = array_values(array_filter((array) $times, fn($ts) => $ts > $cutoff));
        if (!$hits[$k]) {
            unset($hits[$k]);
        }
    }
    $key = hash('sha256', $ip);
    if (count($hits[$key] ?? []) >= MAX_PER_HOUR) {
        flock($fh, LOCK_UN);
        fclose($fh);
        back('limited');
    }
    $hits[$key][] = time();
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string) json_encode($hits));
    flock($fh, LOCK_UN);
    fclose($fh);
}

// Record first, mail second.
$record = [
    'at'    => date('c'),
    'name'  => $name,
    'email' => $email,
    'role'  => $roles[$role],
    'need'  => $need,
    'now'   => $now,
];
$saved = file_put_contents(private_dir() . '/tool-requests.jsonl', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

$text = "New tool request from tools.bizorca.com\n\n"
      . "Name:  {$name}\n"
      . "Email: {$email}\n"
      . "Role:  {$roles[$role]}\n\n"
      . "What the tool should do:\n{$need}\n\n"
      . "How they handle it now:\n" . ($now !== '' ? $now : '(not given)') . "\n";

$sent = false;
$key  = env('SMTP2GO_API_KEY');
if ($key !== '') {
    $ch = curl_init('https://api.smtp2go.com/v3/email/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'api_key'        => $key,
            'to'             => [REQUEST_TO],
            'sender'         => REQUEST_FROM,
            'subject'        => 'Tool request: ' . mb_substr(preg_replace('/\s+/', ' ', $need), 0, 60),
            'text_body'      => $text,
            'custom_headers' => [['header' => 'Reply-To', 'value' => str_replace(["\r", "\n"], '', $email)]],
        ]),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // Parse the response; SMTP2GO's JSON has no space after the colon.
    $json = is_string($body) ? json_decode($body, true) : null;
    $sent = $code === 200 && is_array($json) && (int) ($json['data']['succeeded'] ?? 0) > 0;
}

// Saved but not mailed is still a success for the visitor; it's in the log.
back($saved !== false || $sent ? 'sent' : 'error');
