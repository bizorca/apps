<?php

declare(strict_types=1);

namespace Dispatch\Core;

class Middleware
{
    public static function run(string $name): void
    {
        match ($name) {
            'auth'   => Auth::requireAuth(),
            'admin'  => Auth::requireAdmin(),
            'sysop'  => Auth::requireSysOp(),
            default  => null,
        };
    }
}
