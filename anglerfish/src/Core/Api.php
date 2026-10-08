<?php

namespace Anglerfish\Core;

/**
 * Worker API plumbing (SPEC §13). Bearer-token auth, JSON in and out.
 * No session, no CSRF — the worker is a daemon, not a browser.
 */
final class Api
{
    /** Verify the bearer token, or halt with 401. */
    public static function authorize(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        $expected = (string) ($cfg['worker']['token'] ?? '');

        if ($expected === '') {
            self::fail(500, 'Worker token not configured on the server.');
        }

        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']   // Apache CGI strips it otherwise
            ?? '';

        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m) || !hash_equals($expected, $m[1])) {
            self::fail(401, 'Bad or missing worker token.');
        }
    }

    /** @return array<string,mixed> */
    public static function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            self::fail(400, 'Body must be a JSON object.');
        }
        return $data;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function fail(int $status, string $message): never
    {
        self::json(['ok' => false, 'error' => $message], $status);
    }
}
