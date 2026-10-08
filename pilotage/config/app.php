<?php

declare(strict_types=1);

/**
 * Pilotage configuration on tools.bizorca.com.
 *
 * Every value comes from the shared server-only private_html/.env.php through
 * tl_env(), like every other tool on the site. Pilotage-specific keys are
 * PL_-prefixed so they cannot collide with another tool's; the database and
 * SMTP2GO key are the shared ones.
 *
 * pilotagehq.com, getpilotage.com and pilotage.cc are gone: links are built by
 * the helpers in src/Core/helpers.php from the request (or PL_ORIGIN for CLI
 * work), and mail sends from a bizorca.com address, the verified SMTP2GO
 * sending domain.
 */

$get = static function (string $key, ?string $default = null): ?string {
    if (function_exists('tl_env')) {
        $value = tl_env($key, null);
        if ($value !== null && $value !== '') {
            return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
    }
    $fromServer = getenv($key);
    return ($fromServer !== false && $fromServer !== '') ? $fromServer : $default;
};

$appEnv = $get('PL_ENV', 'production');
$origin = rtrim((string) $get('PL_ORIGIN', 'https://tools.bizorca.com'), '/');
$private = defined('TL_PRIVATE') ? TL_PRIVATE : dirname(__DIR__) . '/../private_html';

return [
    'app' => [
        'env'         => $appEnv,
        'debug'       => filter_var($get('PL_DEBUG', $appEnv === 'production' ? 'false' : 'true'), FILTER_VALIDATE_BOOL),
        'force_https' => $appEnv === 'production',
        'root'        => dirname(__DIR__),
        // Uploaded documents and anything else written at runtime. Outside the
        // web root, inside private_html/data/ which deploys never touch.
        'storage'     => rtrim((string) $get('PL_STORAGE', $private . '/data/pilotage'), '/'),
        'secret_key'  => $get('PL_SECRET_KEY', ''),
        'beta'        => $get('PL_BILLING_BETA', 'true'),
    ],
    'domains' => [
        // Display and reply-address host only. URLs come from pl_origin().
        'base' => (string) (parse_url($origin, PHP_URL_HOST) ?: 'tools.bizorca.com'),
    ],
    'db' => [
        // true: use the shared tools connection. Tests set false and point
        // at their own schema with the values below.
        'shared' => true,
        // Unused at runtime: Database::conn() hands back the shared tl_db()
        // handle. Kept for tests and tools that point at a scratch schema.
        'host' => $get('DB_HOST', '127.0.0.1'),
        'port' => (int) $get('DB_PORT', '3306'),
        'name' => $get('DB_NAME', 'bizorca_tools'),
        'user' => $get('DB_USER', 'root'),
        'pass' => $get('DB_PASS', ''),
    ],
    'intake' => [
        'house_tenant' => $get('PL_INTAKE_HOUSE_TENANT', 'bizorca'),
    ],
    'calendar' => [
        'google' => [
            'client_id'     => $get('PL_GOOGLE_CLIENT_ID', ''),
            'client_secret' => $get('PL_GOOGLE_CLIENT_SECRET', ''),
        ],
        'microsoft' => [
            'client_id'     => $get('PL_MICROSOFT_CLIENT_ID', ''),
            'client_secret' => $get('PL_MICROSOFT_CLIENT_SECRET', ''),
        ],
    ],
    'stripe' => [
        'secret_key'     => $get('PL_STRIPE_SECRET_KEY', ''),
        'webhook_secret' => $get('PL_STRIPE_WEBHOOK_SECRET', ''),
        'enforce'        => $get('PL_BILLING_ENFORCE', 'false'),
        'prices' => [
            'solo_month'     => $get('PL_STRIPE_PRICE_SOLO_MONTH', ''),
            'solo_year'      => $get('PL_STRIPE_PRICE_SOLO_YEAR', ''),
            'practice_month' => $get('PL_STRIPE_PRICE_PRACTICE_MONTH', ''),
            'practice_year'  => $get('PL_STRIPE_PRICE_PRACTICE_YEAR', ''),
            'firm_month'     => $get('PL_STRIPE_PRICE_FIRM_MONTH', ''),
            'firm_year'      => $get('PL_STRIPE_PRICE_FIRM_YEAR', ''),
        ],
    ],
    'mail' => [
        'api_key'   => $get('SMTP2GO_API_KEY', ''),
        'from_addr' => $get('PL_MAIL_FROM', 'pilotage@bizorca.com'),
        'from_name' => $get('PL_MAIL_FROM_NAME', 'Pilotage'),
    ],
];
