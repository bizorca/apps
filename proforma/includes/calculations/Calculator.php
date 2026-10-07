<?php
declare(strict_types=1);

namespace Calculations;

abstract class Calculator {
    protected array $business;
    protected array $expenses;
    protected array $schedules;
    protected array $streams;
    protected array $instructors;
    protected array $settings;

    public function __construct(
        array $business,
        array $expenses,
        array $schedules,
        array $streams,
        array $instructors,
        array $settings = []
    ) {
        $this->business    = $business;
        $this->expenses    = $expenses;
        $this->schedules   = $schedules;
        $this->streams     = $streams;
        $this->instructors = $instructors;
        $this->settings    = $settings;
    }

    /** Total gross revenue per month from all enabled streams */
    abstract public function monthlyGrossRevenue(): float;

    /** Total instructor/subcontractor cost per month (including employer burden for employees) */
    abstract public function monthlyInstructorCost(): float;

    /** Fixed overhead costs per month (excluding owner salary) */
    abstract public function monthlyFixedOverhead(): float;

    /** Variable overhead costs per month */
    abstract public function monthlyVariableOverhead(): float;

    /** Fill rate needed to break even */
    abstract public function breakEvenFillRate(): float;

    /** Full report data array */
    abstract public function report(): array;

    // ---------------------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------------------

    /** Owner's desired monthly salary (derived from annual) */
    protected function monthlyOwnerSalary(): float {
        return (float)$this->business['owner_salary_annual'] / 12;
    }

    /** Weeks per month based on annual operating weeks */
    protected function weeksPerMonth(): float {
        return (float)($this->business['weeks_per_year'] ?? 50) / 12;
    }

    /**
     * Employer burden multiplier for employees.
     * E.g. 1.0 + 0.0765 (FICA) + 0.03 (state) + 0.02 (WC) = 1.1265
     * Applied on top of the worker's base pay rate.
     */
    protected function employerBurdenMultiplier(): float {
        $fed   = (float)($this->settings['federal_payroll_tax_pct'] ?? 0.0765);
        $state = (float)($this->settings['state_payroll_tax_pct']   ?? 0.03);
        $wc    = (float)($this->settings['workers_comp_pct']        ?? 0.02);
        return 1.0 + $fed + $state + $wc;
    }

    /** Total monthly cost: overhead + instructor + owner draw */
    public function monthlyTotalCost(): float {
        return $this->monthlyFixedOverhead()
             + $this->monthlyVariableOverhead()
             + $this->monthlyInstructorCost()
             + $this->monthlyOwnerSalary();
    }

    /** Monthly operating cost — overhead + instructors, no owner draw */
    public function monthlyOperatingCost(): float {
        return $this->monthlyFixedOverhead()
             + $this->monthlyVariableOverhead()
             + $this->monthlyInstructorCost();
    }

    /** Net income before owner draw */
    public function monthlyNetBeforeOwner(): float {
        return $this->monthlyGrossRevenue() - $this->monthlyOperatingCost();
    }

    /** Net monthly income (after owner draw) */
    public function monthlyNetIncome(): float {
        return $this->monthlyGrossRevenue() - $this->monthlyTotalCost();
    }
}
