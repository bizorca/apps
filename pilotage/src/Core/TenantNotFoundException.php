<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Raised when a host looks like a tenant but does not resolve to an active one.
 * Always a 404 to the visitor — never leak whether a slug exists.
 */
final class TenantNotFoundException extends \RuntimeException
{
}
