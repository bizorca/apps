<?php
/**
 * Settings and the shared database handle.
 *
 * Every tool uses the one MySQL database. Tool tables carry a short prefix
 * (pf_ for Proforma) and their own user_id foreign keys into the shared
 * `users` table.
 *
 * Secrets live in private_html/.env.php, which is never committed and never
 * deployed over. See .env.example.php for the keys.
 */

declare(strict_types=1);

function tl_env(string $key, mixed $default = null): mixed
{
    static $env = null;

    if ($env === null) {
        $file = TL_PRIVATE . '/.env.php';
        $env  = is_file($file) ? (array) require $file : [];
    }

    return $env[$key] ?? $default;
}

function tl_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        (string) tl_env('DB_HOST', 'localhost'),
        (string) tl_env('DB_NAME', 'bizorca_tools')
    );

    $pdo = new PDO($dsn, (string) tl_env('DB_USER', 'root'), (string) tl_env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Real prepared statements. Side effect worth knowing: a named
        // placeholder used twice in one statement throws HY093, and LIMIT ?
        // needs an int bound with PDO::PARAM_INT.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // UTC, so NOW() means what SQLite's datetime('now') meant before the port,
    // whatever the server's system zone is.
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}
