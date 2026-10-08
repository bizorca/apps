<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Raised for routing and request failures that map to an HTTP status.
 *
 * Extends \Exception, NOT \RuntimeException, deliberately.
 *
 * Controllers routinely wrap service calls in
 * `catch (\InvalidArgumentException | \RuntimeException $e)` to turn a domain
 * error into a 422. When HttpException was a RuntimeException, those blocks
 * also caught the controller's OWN deliberate throws and rewrote every status
 * as 422 — a considered 403 or 404 silently became "unprocessable entity".
 * That was live: a coach trying to acknowledge a document on the client's
 * behalf returned 422 instead of 403.
 *
 * Keeping it off the RuntimeException branch means an explicit status always
 * survives to the front controller.
 */
final class HttpException extends \Exception
{
    private int $statusCode;

    public function __construct(int $statusCode, string $message = '')
    {
        $this->statusCode = $statusCode;
        parent::__construct($message !== '' ? $message : ('HTTP ' . $statusCode));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
