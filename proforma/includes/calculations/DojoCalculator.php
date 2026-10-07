<?php
declare(strict_types=1);

namespace Calculations;

require_once __DIR__ . '/Calculator.php';

/** Stub — extend when building the dojo vertical */
class DojoCalculator extends Calculator {
    public function monthlyGrossRevenue(): float   { return 0.0; }
    public function monthlyInstructorCost(): float { return 0.0; }
    public function monthlyFixedOverhead(): float  { return 0.0; }
    public function monthlyVariableOverhead(): float { return 0.0; }
    public function breakEvenFillRate(): float     { return 0.0; }
    public function report(): array {
        return ['error' => 'Dojo calculator not yet implemented.'];
    }
}
