<?php
declare(strict_types=1);

namespace Calculations;

require_once __DIR__ . '/Calculator.php';

class TherapistCalculator extends Calculator {

    private array $payers;     // rows from insurance_payers
    private array $codes;      // rows from cpt_codes
    private array $rateMatrix; // [payer_id][cpt_id] => rate (float)
    private array $providers;  // rows from staff_providers

    public function __construct(
        array $business,
        array $expenses,
        array $schedules,
        array $streams,
        array $instructors,
        array $extra = []
    ) {
        parent::__construct($business, $expenses, $schedules, $streams, $instructors, $extra['settings'] ?? []);
        $this->payers    = $extra['payers']    ?? [];
        $this->codes     = $extra['codes']     ?? [];
        $this->providers = $extra['providers'] ?? [];

        // Build rate matrix indexed by [payer_id][cpt_id]
        $this->rateMatrix = [];
        foreach ($extra['rates'] ?? [] as $r) {
            $this->rateMatrix[$r['payer_id']][$r['cpt_id']] = (float)$r['rate'];
        }
    }

    // ---------------------------------------------------------------------------
    // Core rate calculations
    // ---------------------------------------------------------------------------

    /**
     * Blended reimbursement rate for one CPT code — weighted average across
     * all payers by their client_pct share.
     */
    public function blendedRateForCode(array $code): float {
        $weighted   = 0.0;
        $totalShare = 0.0;
        foreach ($this->payers as $payer) {
            $share = (float)$payer['client_pct'];
            $rate  = $this->rateMatrix[$payer['id']][$code['id']] ?? 0.0;
            $weighted   += $rate * $share;
            $totalShare += $share;
        }
        return $totalShare > 0 ? $weighted / $totalShare : 0.0;
    }

    /** Weighted average blended rate across all codes and sessions */
    public function overallBlendedRate(): float {
        $totalRevenue  = 0.0;
        $totalSessions = 0.0;
        foreach ($this->codes as $code) {
            $sessions       = (float)$code['sessions_per_month'];
            $totalRevenue  += $sessions * $this->blendedRateForCode($code);
            $totalSessions += $sessions;
        }
        return $totalSessions > 0 ? $totalRevenue / $totalSessions : 0.0;
    }

    /** Cash-pay rate for one CPT code (from the payer flagged is_cash_pay=1) */
    public function cashPayRateForCode(array $code): float {
        foreach ($this->payers as $payer) {
            if ($payer['is_cash_pay']) {
                return $this->rateMatrix[$payer['id']][$code['id']] ?? 0.0;
            }
        }
        return 0.0;
    }

    // ---------------------------------------------------------------------------
    // Revenue
    // ---------------------------------------------------------------------------

    public function monthlyGrossRevenue(): float {
        $total = 0.0;
        foreach ($this->codes as $code) {
            $total += (float)$code['sessions_per_month'] * $this->blendedRateForCode($code);
        }
        return $total;
    }

    /** What total monthly revenue would be if 100% of clients were cash-pay */
    public function monthlyCashPayRevenue(): float {
        $total = 0.0;
        foreach ($this->codes as $code) {
            $total += (float)$code['sessions_per_month'] * $this->cashPayRateForCode($code);
        }
        return $total;
    }

    /** Revenue breakdown by CPT code — current model vs. cash pay */
    public function revenueByCode(): array {
        $results = [];
        foreach ($this->codes as $code) {
            $sessions     = (float)$code['sessions_per_month'];
            $blended      = $this->blendedRateForCode($code);
            $cash         = $this->cashPayRateForCode($code);
            $results[]    = [
                'code'           => $code['code'],
                'description'    => $code['description'],
                'sessions'       => $sessions,
                'blended_rate'   => $blended,
                'cash_rate'      => $cash,
                'blended_revenue'=> $sessions * $blended,
                'cash_revenue'   => $sessions * $cash,
                'gap_per_session'=> $cash - $blended,
                'monthly_gap'    => $sessions * ($cash - $blended),
            ];
        }
        return $results;
    }

    /** Revenue broken down by payer */
    public function revenueByPayer(): array {
        $results = [];
        foreach ($this->payers as $payer) {
            $revenue = 0.0;
            foreach ($this->codes as $code) {
                $rate     = $this->rateMatrix[$payer['id']][$code['id']] ?? 0.0;
                $revenue += $rate * (float)$code['sessions_per_month'] * (float)$payer['client_pct'];
            }
            $results[] = [
                'payer_name'  => $payer['payer_name'],
                'client_pct'  => (float)$payer['client_pct'],
                'is_cash_pay' => (bool)$payer['is_cash_pay'],
                'revenue'     => $revenue,
            ];
        }
        // Sort descending by revenue
        usort($results, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        return $results;
    }

    // ---------------------------------------------------------------------------
    // Costs
    // ---------------------------------------------------------------------------

    public function monthlyFixedOverhead(): float {
        $total = 0.0;
        foreach ($this->expenses as $e) {
            if (!$e['is_variable']) $total += (float)$e['amount_monthly'];
        }
        return $total;
    }

    public function monthlyVariableOverhead(): float {
        $total = 0.0;
        foreach ($this->expenses as $e) {
            if ($e['is_variable']) $total += (float)$e['amount_monthly'];
        }
        return $total;
    }

    public function monthlyInstructorCost(): float {
        return $this->monthlyStaffCost();
    }

    public function monthlyStaffCost(): float {
        $wpm    = $this->weeksPerMonth();
        $burden = $this->employerBurdenMultiplier();
        $total  = 0.0;
        foreach ($this->providers as $prov) {
            if ($prov['is_owner']) continue; // owner draw accounted for separately
            $baseCost = match($prov['pay_type']) {
                'hourly'      => (float)$prov['pay_rate'] * (float)$prov['hours_per_week']    * $wpm,
                'per_session' => (float)$prov['pay_rate'] * (float)$prov['sessions_per_week'] * $wpm,
                'salary'      => (float)$prov['pay_rate'],
                default       => 0.0,
            };
            $multiplier = ($prov['worker_type'] ?? 'contractor') === 'employee' ? $burden : 1.0;
            $total += $baseCost * $multiplier;
        }
        return $total;
    }

    // ---------------------------------------------------------------------------
    // Break-even and affordability
    // ---------------------------------------------------------------------------

    public function breakEvenFillRate(): float {
        // Therapist practices don't use fill rate — return 0 (not meaningful)
        return 0.0;
    }

    /** Sessions per month needed to break even (cover overhead + owner draw, no staff) */
    public function breakEvenSessions(): float {
        $blended   = $this->overallBlendedRate();
        $fixedCost = $this->monthlyFixedOverhead()
                   + $this->monthlyVariableOverhead()
                   + $this->monthlyOwnerSalary();
        if ($blended <= 0) return 0.0;
        return $fixedCost / $blended;
    }

    /** Sessions per month needed to break even — overhead only, no owner draw, no staff */
    public function breakEvenSessionsNoOwner(): float {
        $blended   = $this->overallBlendedRate();
        $fixedCost = $this->monthlyFixedOverhead() + $this->monthlyVariableOverhead();
        if ($blended <= 0) return 0.0;
        return $fixedCost / $blended;
    }

    /** Sessions per month needed to break even INCLUDING current staff cost */
    public function breakEvenSessionsWithStaff(): float {
        $blended   = $this->overallBlendedRate();
        $totalCost = $this->monthlyFixedOverhead()
                   + $this->monthlyVariableOverhead()
                   + $this->monthlyOwnerSalary()
                   + $this->monthlyStaffCost();
        if ($blended <= 0) return 0.0;
        return $totalCost / $blended;
    }

    /**
     * Maximum hourly rate the practice can afford to pay associate therapists
     * given current revenue and non-staff overhead.
     * Leaves a 10% margin.
     */
    public function maxAffordableHourlyRate(): float {
        $revenue     = $this->monthlyGrossRevenue();
        $overhead    = $this->monthlyFixedOverhead() + $this->monthlyVariableOverhead();
        $ownerDraw   = $this->monthlyOwnerSalary();
        $buffer      = $revenue * 0.10;
        $available   = $revenue - $overhead - $ownerDraw - $buffer;

        // Total non-owner clinical hours per month
        $wpm        = $this->weeksPerMonth();
        $totalHours = 0.0;
        foreach ($this->providers as $prov) {
            if (!$prov['is_owner']) $totalHours += (float)$prov['hours_per_week'] * $wpm;
        }
        if ($totalHours <= 0) return 0.0;
        return max(0.0, $available / $totalHours);
    }

    /**
     * Max affordable per-session rate given current session volume.
     */
    public function maxAffordablePerSessionRate(): float {
        $revenue     = $this->monthlyGrossRevenue();
        $overhead    = $this->monthlyFixedOverhead() + $this->monthlyVariableOverhead();
        $ownerDraw   = $this->monthlyOwnerSalary();
        $buffer      = $revenue * 0.10;
        $available   = $revenue - $overhead - $ownerDraw - $buffer;

        $wpm          = $this->weeksPerMonth();
        $totalSessions = 0.0;
        foreach ($this->providers as $prov) {
            if (!$prov['is_owner']) $totalSessions += (float)$prov['sessions_per_week'] * $wpm;
        }
        if ($totalSessions <= 0) return 0.0;
        return max(0.0, $available / $totalSessions);
    }

    /** Staff cost as % of gross revenue */
    public function staffCostPercent(): float {
        $rev = $this->monthlyGrossRevenue();
        return $rev > 0 ? $this->monthlyStaffCost() / $rev : 0.0;
    }

    /** Revenue each non-owner provider generates monthly */
    public function providerProductivity(): array {
        $wpm         = $this->weeksPerMonth();
        $blendedRate = $this->overallBlendedRate();
        $results     = [];
        foreach ($this->providers as $prov) {
            $sessPerMonth = (float)$prov['sessions_per_week'] * $wpm;
            $revGenerated = $sessPerMonth * $blendedRate;
            $cost         = match($prov['pay_type']) {
                'hourly'      => (float)$prov['pay_rate'] * (float)$prov['hours_per_week'] * $wpm,
                'per_session' => (float)$prov['pay_rate'] * $sessPerMonth,
                'salary'      => (float)$prov['pay_rate'],
                default       => 0.0,
            };
            $margin = $revGenerated - $cost;
            $results[] = [
                'name'          => $prov['name'],
                'credential'    => $prov['credential'],
                'is_owner'      => (bool)$prov['is_owner'],
                'pay_type'      => $prov['pay_type'],
                'pay_rate'      => (float)$prov['pay_rate'],
                'sessions_mo'   => $sessPerMonth,
                'hours_mo'      => (float)$prov['hours_per_week'] * $wpm,
                'rev_generated' => $revGenerated,
                'cost'          => $cost,
                'margin'        => $margin,
                'margin_pct'    => $revGenerated > 0 ? $margin / $revGenerated : 0.0,
            ];
        }
        return $results;
    }

    // ---------------------------------------------------------------------------
    // Fill-rate scenarios (adapted for session volume)
    // ---------------------------------------------------------------------------

    public function sessionVolumeScenarios(): array {
        $totalSessions = array_sum(array_column($this->codes, 'sessions_per_month'));
        $blended       = $this->overallBlendedRate();
        $cashBlended   = array_sum(array_map(
            fn($c) => (float)$c['sessions_per_month'] * $this->cashPayRateForCode($c),
            $this->codes
        )) / max(1, $totalSessions);

        $fixedCost = $this->monthlyFixedOverhead()
                   + $this->monthlyVariableOverhead()
                   + $this->monthlyStaffCost()
                   + $this->monthlyOwnerSalary();

        $scenarios = [];
        foreach ([0.60, 0.75, 0.85, 1.0, 1.15, 1.25] as $multiplier) {
            $sessions = $totalSessions * $multiplier;
            $net      = ($sessions * $blended) - $fixedCost;
            $netCash  = ($sessions * $cashBlended) - $fixedCost;
            $scenarios[] = [
                'label'        => number_format($sessions, 0) . ' sess/mo',
                'multiplier'   => $multiplier,
                'sessions'     => $sessions,
                'net_insurance'=> $net,
                'net_cash'     => $netCash,
            ];
        }
        return $scenarios;
    }

    // ---------------------------------------------------------------------------
    // Report
    // ---------------------------------------------------------------------------

    public function report(): array {
        $gross          = $this->monthlyGrossRevenue();
        $cashGross      = $this->monthlyCashPayRevenue();
        $staffCost      = $this->monthlyStaffCost();
        $fixed          = $this->monthlyFixedOverhead();
        $variable       = $this->monthlyVariableOverhead();
        $operatingCost  = $this->monthlyOperatingCost();
        $ownerDraw      = $this->monthlyOwnerSalary();
        $totalCost      = $this->monthlyTotalCost();
        $netBeforeOwner = $this->monthlyNetBeforeOwner();
        $net            = $this->monthlyNetIncome();
        $blended        = $this->overallBlendedRate();

        // Action flags
        $flags = [];
        if ($net < 0) {
            $flags[] = ['type' => 'danger', 'message' => 'The practice is running at a loss of ' . money(abs($net)) . '/month on the current payer mix.'];
        }
        $beNoStaff = $this->breakEvenSessions();
        $beSessions = array_sum(array_column($this->codes, 'sessions_per_month'));
        if ($beNoStaff > $beSessions) {
            $flags[] = ['type' => 'danger', 'message' => 'Current session volume (' . number_format($beSessions, 0) . '/mo) is below break-even (' . number_format($beNoStaff, 0) . ' sessions/mo needed).'];
        }
        if ($cashGross > $gross * 1.20) {
            $flags[] = ['type' => 'warning', 'message' => 'Going cash-pay would increase monthly revenue by ' . money($cashGross - $gross) . ' (' . pct(($cashGross - $gross) / max(1, $gross)) . '). Worth evaluating.'];
        }
        if ($this->staffCostPercent() > 0.60 && $gross > 0) {
            $flags[] = ['type' => 'warning', 'message' => 'Staff costs are ' . pct($this->staffCostPercent()) . ' of gross revenue — unusually high. Typical range is 40–55%.'];
        }

        return [
            'business_type'                 => 'therapist',
            'gross_revenue'                 => $gross,
            'cash_pay_revenue'              => $cashGross,
            'insurance_discount'            => $cashGross - $gross,
            'staff_cost'                    => $staffCost,
            'fixed_overhead'                => $fixed,
            'variable_overhead'             => $variable,
            'operating_cost'                => $operatingCost,
            'owner_salary_monthly'          => $ownerDraw,
            'total_cost'                    => $totalCost,
            'net_before_owner'              => $netBeforeOwner,
            'net_income'                    => $net,
            'net_cash_pay'                  => $cashGross - $totalCost,
            'overall_blended_rate'          => $blended,
            'break_even_sessions'           => $beNoStaff,
            'break_even_sessions_no_owner'  => $this->breakEvenSessionsNoOwner(),
            'break_even_with_staff'         => $this->breakEvenSessionsWithStaff(),
            'total_sessions'                => $beSessions,
            'max_affordable_hourly'   => $this->maxAffordableHourlyRate(),
            'max_affordable_per_sess' => $this->maxAffordablePerSessionRate(),
            'staff_cost_pct'          => $this->staffCostPercent(),
            'revenue_by_code'         => $this->revenueByCode(),
            'revenue_by_payer'        => $this->revenueByPayer(),
            'provider_productivity'   => $this->providerProductivity(),
            'session_scenarios'       => $this->sessionVolumeScenarios(),
            'expenses'                => $this->expenses,
            'payers'                  => $this->payers,
            'action_flags'            => $flags,
        ];
    }
}
