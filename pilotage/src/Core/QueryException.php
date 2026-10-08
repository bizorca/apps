<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/** Raised for malformed repository queries — illegal columns, bad LIMIT, and the like. */
final class QueryException extends \LogicException
{
}
