<?php

declare(strict_types=1);

/**
 * The original Response class printed JSON and exit()ed. Here it throws
 * instead, carrying the same envelope, so the same controllers serve two
 * callers: public/api/index.php (prints it, for the iOS app) and the web
 * pages' api() helper (reads it in-process, no HTTP round trip). The envelope
 * — {success, message, data} / {success, message, errors} — is unchanged.
 */
final class PcResponse extends Exception
{
    public function __construct(public readonly int $status, public readonly array $body)
    {
        parent::__construct((string) ($body['message'] ?? ''), $status);
    }
}

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        throw new PcResponse($status, is_array($data) ? $data : ['data' => $data]);
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): never
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): never
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        self::json($body, $status);
    }

    public static function notFound(string $message = 'Not found'): never
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error($message, 403);
    }

    public static function methodNotAllowed(): never
    {
        self::error('Method not allowed', 405);
    }
}
