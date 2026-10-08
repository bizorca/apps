<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Raised when code that must be tenant-scoped runs without a tenant in scope.
 *
 * This is a programming error, not a user error. It should surface loudly in
 * development and as a 500 in production — never as an unscoped query.
 */
final class TenantScopeException extends \LogicException
{
}
