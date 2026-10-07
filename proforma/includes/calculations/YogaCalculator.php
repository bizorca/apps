<?php
declare(strict_types=1);

namespace Calculations;

require_once __DIR__ . '/Calculator.php';

class YogaCalculator extends Calculator {

    // ---------------------------------------------------------------------------
    // Classes
    // ---------------------------------------------------------------------------

    /** Total classes per month across all schedule rows */
    public function monthlyClasses(): float {
        $wpm   = $this->weeksPerMonth();
        $total = 0.0;
        foreach ($this->schedules as $s) {
            $total += (float)$s['classes_per_week'] * $wpm;
        }
        return $total;
    }

    /** Total in-person classes per month */
    public function monthlyInPersonClasses(): float {
        $wpm   = $this->weeksPerMonth();
        $total = 0.0;
        foreach ($this->schedules as $s) {
            if ($s['class_type'] === 'in_person') {
                $total += (float)$s['classes_per_week'] * $wpm;
            }
        }
        return $total;
    }

    /** Weighted average students per in-person class */
    public function avgStudentsPerClass(): float {
        $wpm          = $this->weeksPerMonth();
        $totalVisits  = 0.0;
        $totalClasses = 0.0;
        foreach ($this->schedules as $s) {
            if ($s['class_type'] !== 'in_person') continue;
            $classes = (float)$s['classes_per_week'] * $wpm;
            $cap     = (float)($s['room_capacity'] ?? 20);
            $fill    = (float)($s['avg_fill_rate']  ?? 0.6);
            $totalVisits  += $classes * $cap * $fill;
            $totalClasses += $classes;
        }
        return $totalClasses > 0 ? $totalVisits / $totalClasses : 0;
    }

    /** Total projected in-person student visits per month */
    public function monthlyStudentVisits(): float {
        $wpm   = $this->weeksPerMonth();
        $total = 0.0;
        foreach ($this->schedules as $s) {
            if ($s['class_type'] !== 'in_person') continue;
            $classes = (float)$s['classes_per_week'] * $wpm;
            $cap     = (float)($s['room_capacity'] ?? 20);
            $fill    = (float)($s['avg_fill_rate']  ?? 0.6);
            $total  += $classes * $cap * $fill;
        }
        return $total;
    }

    /** Maximum capacity visits/month (at 100% fill) */
    public function maxMonthlyVisits(): float {
        $wpm   = $this->weeksPerMonth();
        $total = 0.0;
        foreach ($this->schedules as $s) {
            if ($s['class_type'] !== 'in_person') continue;
            $total += (float)$s['classes_per_week'] * $wpm * (float)($s['room_capacity'] ?? 20);
        }
        return $total;
    }

    // ---------------------------------------------------------------------------
    // Revenue
    // ---------------------------------------------------------------------------

    /** Total monthly revenue from all enabled streams */
    public function monthlyGrossRevenue(): float {
        $total = 0.0;
        foreach ($this->streams as $s) {
            if (!$s['is_enabled']) continue;
            if ($s['stream_type'] === 'intro_conversion') continue; // not direct revenue
            $total += $this->streamMonthlyRevenue($s);
        }
        return $total;
    }

    /** Revenue for a single stream */
    public function streamMonthlyRevenue(array $s): float {
        $price = (float)$s['price'];
        $units = (float)$s['estimated_monthly_units'];
        return match($s['stream_type']) {
            'drop_in'         => $price * $units,
            'class_pass'      => $price * $units,         // cash collected basis
            'subscription'    => $price * $units,
            'private'         => $price * $units,
            'online_live'     => $price * $units,
            'online_recorded' => $price * $units,
            'intro_offer'     => $price * $units,
            default           => 0.0,
        };
    }

    /** Class pass earned revenue (redemptions × effective per-class rate) */
    public function classPassEarnedRevenue(): float {
        $total = 0.0;
        foreach ($this->streams as $s) {
            if ($s['stream_type'] !== 'class_pass') continue;
            $units    = (int)($s['units_included'] ?? 0);
            $price    = (float)$s['price'];
            $sold     = (float)$s['estimated_monthly_units'];
            if ($units <= 0) continue;
            $effectiveRate = $price / $units;
            // Assume 80% redemption rate for earned revenue
            $redemptions   = $sold * $units * 0.80;
            $total        += $redemptions * $effectiveRate;
        }
        return $total;
    }

    /** Cash collected from passes sold this month */
    public function classPassCashCollected(): float {
        $total = 0.0;
        foreach ($this->streams as $s) {
            if ($s['stream_type'] !== 'class_pass') continue;
            $total += (float)$s['price'] * (float)$s['estimated_monthly_units'];
        }
        return $total;
    }

    /** Subscription MRR */
    public function subscriptionMRR(): float {
        $total = 0.0;
        foreach ($this->streams as $s) {
            if ($s['stream_type'] !== 'subscription') continue;
            $total += (float)$s['price'] * (float)$s['estimated_monthly_units'];
        }
        return $total;
    }

    /** Total subscribers across all tiers */
    public function totalSubscribers(): float {
        $total = 0.0;
        foreach ($this->streams as $s) {
            if ($s['stream_type'] !== 'subscription') continue;
            $total += (float)$s['estimated_monthly_units'];
        }
        return $total;
    }

    /**
     * Blended revenue per student visit.
     * Subscription revenue allocated across visits by treating each subscriber as
     * visiting on average 8 times/month (studio average; used if no class data).
     */
    public function blendedRevenuePerVisit(): float {
        $totalVisits = $this->monthlyStudentVisits();
        if ($totalVisits <= 0) return 0.0;

        // Non-subscription, non-pass streams contribute directly
        $directRevenue = 0.0;
        foreach ($this->streams as $s) {
            if (!$s['is_enabled']) continue;
            if (in_array($s['stream_type'], ['subscription', 'class_pass', 'intro_conversion'])) continue;
            $directRevenue += $this->streamMonthlyRevenue($s);
        }

        // Subscriptions: revenue ÷ (members × avg visits) to get $/visit
        $subRevenue = $this->subscriptionMRR();

        // Pass earned revenue per visit
        $passRevenue = $this->classPassEarnedRevenue();

        return ($directRevenue + $subRevenue + $passRevenue) / $totalVisits;
    }

    // ---------------------------------------------------------------------------
    // Costs
    // ---------------------------------------------------------------------------

    public function monthlyFixedOverhead(): float {
        $total = 0.0;
        foreach ($this->expenses as $e) {
            if (!$e['is_variable']) {
                $total += (float)$e['amount_monthly'];
            }
        }
        return $total;
    }

    public function monthlyVariableOverhead(): float {
        $total = 0.0;
        foreach ($this->expenses as $e) {
            if ($e['is_variable']) {
                $total += (float)$e['amount_monthly'];
            }
        }
        return $total;
    }

    public function monthlyFlatInstructorCost(): float {
        $wpm    = $this->weeksPerMonth();
        $burden = $this->employerBurdenMultiplier();
        $total  = 0.0;
        foreach ($this->instructors as $inst) {
            if ($inst['pay_type'] === 'flat') {
                $multiplier = ($inst['worker_type'] ?? 'contractor') === 'employee' ? $burden : 1.0;
                $total += (float)$inst['pay_per_class'] * (float)$inst['classes_per_week'] * $wpm * $multiplier;
            }
        }
        return $total;
    }

    public function monthlyRevenueShareInstructorCost(): float {
        $wpm         = $this->weeksPerMonth();
        $blended     = $this->blendedRevenuePerVisit();
        $avgStudents = $this->avgStudentsPerClass();
        $burden      = $this->employerBurdenMultiplier();
        $total       = 0.0;
        foreach ($this->instructors as $inst) {
            if ($inst['pay_type'] !== 'revenue_share') continue;
            $sharePct        = (float)($inst['revenue_share_pct'] ?? 0);
            $classes         = (float)$inst['classes_per_week'] * $wpm;
            $perClassRevenue = $avgStudents * $blended;
            $earnings        = $sharePct * $perClassRevenue * $classes;
            $multiplier      = ($inst['worker_type'] ?? 'contractor') === 'employee' ? $burden : 1.0;
            $total          += $earnings * $multiplier;
        }
        return $total;
    }

    public function monthlyInstructorCost(): float {
        return $this->monthlyFlatInstructorCost() + $this->monthlyRevenueShareInstructorCost();
    }

    // ---------------------------------------------------------------------------
    // Break-even
    // ---------------------------------------------------------------------------

    /**
     * Break-even fill rate: what % fill do you need to cover all costs?
     * Revenue = visits × blendedRate = classes × capacity × fill × rate
     * Total cost = fixed + variable + instructors + owner draw
     * Solve: fill = totalCost / (classes × capacity × blendedRate)
     */
    public function breakEvenFillRate(): float {
        $totalCost    = $this->monthlyFixedOverhead()
                      + $this->monthlyVariableOverhead()
                      + $this->monthlyFlatInstructorCost()
                      + $this->monthlyOwnerSalary();

        // Max visits at 100% fill
        $maxVisits    = $this->maxMonthlyVisits();
        $blended      = $this->blendedRevenuePerVisit();

        // Also include non-class revenue (subscriptions, online, private, recorded)
        $nonClassRevenue = 0.0;
        foreach ($this->streams as $s) {
            if (!$s['is_enabled']) continue;
            if (in_array($s['stream_type'], ['subscription', 'online_recorded', 'private', 'online_live', 'intro_offer'])) {
                $nonClassRevenue += $this->streamMonthlyRevenue($s);
            }
        }

        // Effective remaining cost that in-class revenue must cover
        $costToClassRevenue = $totalCost - $nonClassRevenue;
        if ($costToClassRevenue <= 0) return 0.0;
        if ($maxVisits <= 0 || $blended <= 0) return 1.0;

        $fill = $costToClassRevenue / ($maxVisits * $blended);
        return min(1.0, max(0.0, $fill));
    }

    /** Break-even fill rate excluding owner salary from the cost side */
    public function breakEvenFillRateNoOwner(): float {
        $totalCost    = $this->monthlyFixedOverhead()
                      + $this->monthlyVariableOverhead()
                      + $this->monthlyFlatInstructorCost();

        $maxVisits    = $this->maxMonthlyVisits();
        $blended      = $this->blendedRevenuePerVisit();

        $nonClassRevenue = 0.0;
        foreach ($this->streams as $s) {
            if (!$s['is_enabled']) continue;
            if (in_array($s['stream_type'], ['subscription', 'online_recorded', 'private', 'online_live', 'intro_offer'])) {
                $nonClassRevenue += $this->streamMonthlyRevenue($s);
            }
        }

        $costToClassRevenue = $totalCost - $nonClassRevenue;
        if ($costToClassRevenue <= 0) return 0.0;
        if ($maxVisits <= 0 || $blended <= 0) return 1.0;

        $fill = $costToClassRevenue / ($maxVisits * $blended);
        return min(1.0, max(0.0, $fill));
    }

    /**
     * Maximum affordable flat pay per class given current revenue and non-instructor costs.
     * Leaves a 10% buffer.
     */
    public function maxAffordableInstructorPayPerClass(): float {
        $revenue       = $this->monthlyGrossRevenue();
        $overhead      = $this->monthlyFixedOverhead() + $this->monthlyVariableOverhead();
        $ownerDraw     = $this->monthlyOwnerSalary();
        $buffer        = $revenue * 0.10;
        $available     = $revenue - $overhead - $ownerDraw - $buffer;
        $totalClasses  = $this->monthlyClasses();
        if ($totalClasses <= 0) return 0.0;
        return max(0.0, $available / $totalClasses);
    }

    /**
     * Net income at various fill rates (30%, 50%, 60%, 75%, 90%)
     * and optionally price multipliers.
     */
    public function fillRateScenarios(): array {
        $scenarios = [];
        $baseAvgStudents = $this->avgStudentsPerClass();
        $wpm             = $this->weeksPerMonth();
        $blendedRate     = $this->blendedRevenuePerVisit();
        $fixedCosts      = $this->monthlyFixedOverhead()
                         + $this->monthlyVariableOverhead()
                         + $this->monthlyFlatInstructorCost()
                         + $this->monthlyOwnerSalary();

        $nonClassRevenue = 0.0;
        foreach ($this->streams as $s) {
            if (!$s['is_enabled']) continue;
            if (in_array($s['stream_type'], ['subscription', 'online_recorded', 'private', 'online_live', 'intro_offer'])) {
                $nonClassRevenue += $this->streamMonthlyRevenue($s);
            }
        }

        $maxVisitsPerMonth = $this->maxMonthlyVisits();

        foreach ([0.30, 0.50, 0.60, 0.75, 0.90] as $fill) {
            foreach ([1.0, 1.1, 1.2] as $priceMultiplier) {
                $classRevenue = $maxVisitsPerMonth * $fill * $blendedRate * $priceMultiplier;
                $totalRevenue = $classRevenue + $nonClassRevenue * $priceMultiplier;
                $net          = $totalRevenue - $fixedCosts;
                $scenarios[]  = [
                    'fill_rate'        => $fill,
                    'price_multiplier' => $priceMultiplier,
                    'gross_revenue'    => $totalRevenue,
                    'total_cost'       => $fixedCosts,
                    'net_income'       => $net,
                ];
            }
        }
        return $scenarios;
    }

    /** Subscription health check: flag tiers where heavy users cost more than the price */
    public function subscriptionHealthFlags(): array {
        $flags            = [];
        $blended          = $this->blendedRevenuePerVisit();
        $avgStudentVisits = 8; // assumed avg visits/mo for unlimited member

        foreach ($this->streams as $s) {
            if ($s['stream_type'] !== 'subscription') continue;
            $costPerMember = $avgStudentVisits * $blended;
            $price         = (float)$s['price'];
            if ($costPerMember > $price) {
                $flags[] = [
                    'label'           => $s['label'],
                    'price'           => $price,
                    'cost_per_member' => $costPerMember,
                    'gap'             => $costPerMember - $price,
                ];
            }
        }
        return $flags;
    }

    /** Intro offer funnel projections */
    public function introConversionRevenueLift(): array {
        $intro = null;
        $convs = [];
        foreach ($this->streams as $s) {
            if ($s['stream_type'] === 'intro_offer')     $intro = $s;
            if ($s['stream_type'] === 'intro_conversion') $convs[] = $s;
        }
        if (!$intro) return [];

        $newLeads = (float)($intro['estimated_monthly_units'] ?? 0);
        $results  = [];
        $totalMRR = 0.0;

        foreach ($convs as $c) {
            $rate        = (float)($c['conversion_rate'] ?? 0);
            $converts    = $newLeads * $rate;
            // Find the matching subscription tier price by label
            $destLabel   = str_replace('Intro → ', '', $c['label'] ?? '');
            $tierPrice   = 0.0;
            foreach ($this->streams as $s) {
                if ($s['stream_type'] === 'subscription' && $s['label'] === $destLabel) {
                    $tierPrice = (float)$s['price'];
                    break;
                }
            }
            $monthlyLift = $converts * $tierPrice;
            $totalMRR   += $monthlyLift;
            $results[]   = [
                'destination'   => $destLabel,
                'rate'          => $rate,
                'converts'      => $converts,
                'tier_price'    => $tierPrice,
                'monthly_lift'  => $monthlyLift,
            ];
        }

        return [
            'new_leads_per_month' => $newLeads,
            'intro_price'         => (float)($intro['price'] ?? 0),
            'intro_revenue'       => (float)($intro['price'] ?? 0) * $newLeads,
            'conversions'         => $results,
            'total_mrr_lift'      => $totalMRR,
        ];
    }

    /** Instructor cost as a % of gross revenue */
    public function instructorCostPercent(): float {
        $rev = $this->monthlyGrossRevenue();
        if ($rev <= 0) return 0.0;
        return $this->monthlyInstructorCost() / $rev;
    }

    // ---------------------------------------------------------------------------
    // Main report
    // ---------------------------------------------------------------------------

    public function report(): array {
        $grossRevenue     = $this->monthlyGrossRevenue();
        $instructorCost   = $this->monthlyInstructorCost();
        $fixedOverhead    = $this->monthlyFixedOverhead();
        $variableOverhead = $this->monthlyVariableOverhead();
        $ownerSalary      = $this->monthlyOwnerSalary();
        $operatingCost    = $this->monthlyOperatingCost();
        $totalCost        = $this->monthlyTotalCost();
        $netBeforeOwner   = $this->monthlyNetBeforeOwner();
        $netIncome        = $this->monthlyNetIncome();
        $breakEvenFill    = $this->breakEvenFillRate();
        $breakEvenNoOwner = $this->breakEvenFillRateNoOwner();
        $currentFill      = $this->currentWeightedFillRate();

        // Revenue by stream type
        $revenueByStream = [];
        foreach ($this->streams as $s) {
            if (!$s['is_enabled'] || $s['stream_type'] === 'intro_conversion') continue;
            $rev = $this->streamMonthlyRevenue($s);
            $revenueByStream[] = [
                'label'       => $s['label'],
                'stream_type' => $s['stream_type'],
                'revenue'     => $rev,
                'units'       => $s['estimated_monthly_units'],
                'price'       => $s['price'],
            ];
        }

        // Action flags
        $flags = [];
        if ($netIncome < 0) {
            $flags[] = ['type' => 'danger', 'message' => 'You are currently operating at a loss of ' . money(abs($netIncome)) . '/month.'];
        }
        if ($breakEvenFill > 0.85) {
            $flags[] = ['type' => 'danger', 'message' => 'Break-even fill rate (' . pct($breakEvenFill) . ') is dangerously high. Most studios can\'t sustain above 80% consistently.'];
        } elseif ($breakEvenFill > 0.70) {
            $flags[] = ['type' => 'warning', 'message' => 'Break-even fill rate (' . pct($breakEvenFill) . ') is elevated. Pricing or overhead adjustment recommended.'];
        }
        if ($this->instructorCostPercent() > 0.35 && $grossRevenue > 0) {
            $flags[] = ['type' => 'warning', 'message' => 'Instructor pay is ' . pct($this->instructorCostPercent()) . ' of gross revenue. Industry healthy range is 25–35%.'];
        }
        foreach ($this->subscriptionHealthFlags() as $sf) {
            $flags[] = ['type' => 'warning', 'message' => "Subscription tier \"{$sf['label']}\" ({$sf['price']}/mo) may lose money on heavy users — estimated cost/member: " . money($sf['cost_per_member']) . '/mo.'];
        }

        return [
            'gross_revenue'                => $grossRevenue,
            'instructor_cost'              => $instructorCost,
            'fixed_overhead'               => $fixedOverhead,
            'variable_overhead'            => $variableOverhead,
            'operating_cost'               => $operatingCost,
            'owner_salary_monthly'         => $ownerSalary,
            'total_cost'                   => $totalCost,
            'net_before_owner'             => $netBeforeOwner,
            'net_income'                   => $netIncome,
            'break_even_fill_rate'         => $breakEvenFill,
            'break_even_fill_rate_no_owner'=> $breakEvenNoOwner,
            'current_fill_rate'            => $currentFill,
            'monthly_classes'         => $this->monthlyClasses(),
            'avg_students_per_class'  => $this->avgStudentsPerClass(),
            'blended_rev_per_visit'   => $this->blendedRevenuePerVisit(),
            'instructor_cost_pct'     => $this->instructorCostPercent(),
            'max_affordable_pay'      => $this->maxAffordableInstructorPayPerClass(),
            'revenue_by_stream'       => $revenueByStream,
            'expenses'                => $this->expenses,
            'instructors'             => $this->instructors,
            'fill_scenarios'          => $this->fillRateScenarios(),
            'subscription_flags'      => $this->subscriptionHealthFlags(),
            'intro_funnel'            => $this->introConversionRevenueLift(),
            'class_pass_cash'         => $this->classPassCashCollected(),
            'class_pass_earned'       => $this->classPassEarnedRevenue(),
            'subscription_mrr'        => $this->subscriptionMRR(),
            'total_subscribers'       => $this->totalSubscribers(),
            'action_flags'            => $flags,
        ];
    }

    /** Weighted average fill rate across in-person schedules */
    private function currentWeightedFillRate(): float {
        $totalCap    = 0.0;
        $totalFilled = 0.0;
        $wpm         = $this->weeksPerMonth();
        foreach ($this->schedules as $s) {
            if ($s['class_type'] !== 'in_person') continue;
            $classes      = (float)$s['classes_per_week'] * $wpm;
            $cap          = (float)($s['room_capacity'] ?? 20);
            $fill         = (float)($s['avg_fill_rate']  ?? 0.6);
            $totalCap    += $classes * $cap;
            $totalFilled += $classes * $cap * $fill;
        }
        return $totalCap > 0 ? $totalFilled / $totalCap : 0.0;
    }
}
