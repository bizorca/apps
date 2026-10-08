<?php
declare(strict_types=1);

/**
 * Risk flag generator. Pure function — no DB access.
 *
 * Returns array of ['level' => 'high|medium|low', 'title' => '...', 'body' => '...']
 * ordered high → medium → low.
 */
function computeRiskFlags(array $report, ?array $valuation = null): array
{
    $flags = [];

    // A blank year is NULL and reads as 0 here, which every check below guards with $r1 > 0.
    $r1 = (float)($report['revenue_y1'] ?? 0);
    $r3 = (float)($report['revenue_y3'] ?? 0);
    $p3 = (float)($report['net_profit_y3'] ?? 0);

    // ── HIGH severity ─────────────────────────────────────────────────────

    // No earnings at all
    if ($valuation && !empty($valuation['summary']['no_earnings'])) {
        $flags[] = [
            'level' => 'high',
            'title' => 'The Business Is Not Making Money',
            'body'  => "Even after adding back the owner's pay and the add-backs, the business shows no "
                     . "positive earnings across the years provided. You would be buying assets and a "
                     . "turnaround project, not a cash flow. Price it on what the equipment, inventory and "
                     . "lease are worth, and budget for the losses until your changes take hold.",
        ];
    }

    // Revenue trend
    if ($r1 > 0 && $r3 < ($r1 * 0.90)) {
        $decline = $r1 > 0 ? round((($r1 - $r3) / $r1) * 100) : 0;
        $flags[] = [
            'level' => 'high',
            'title' => 'Declining Revenue',
            'body'  => "Revenue has dropped approximately {$decline}% over the past three years. "
                     . "A sustained downtrend raises serious questions about competitive position and "
                     . "customer retention. Ask the seller directly: what caused the decline and what "
                     . "has been done to reverse it?",
        ];
    }

    // Customer concentration
    $concentration = (int)$report['customer_concentration_pct'];
    if ($concentration >= 30) {
        $flags[] = [
            'level' => 'high',
            'title' => 'Customer Concentration Risk',
            'body'  => "Approximately {$concentration}% of revenue comes from a single customer. "
                     . "Lenders typically require this to be under 20-25% for SBA financing. "
                     . "If that relationship ends, the business loses a third or more of its revenue overnight. "
                     . "Verify whether there is a contract, its term, and renewal history.",
        ];
    }

    // Key person risk
    if ($report['owner_works_full_time'] && !$report['has_key_employees']) {
        $flags[] = [
            'level' => 'high',
            'title' => 'Key Person Risk',
            'body'  => "The owner appears to be the sole operator with no key employees in place. "
                     . "This means the business may not survive — or at minimum will struggle — during "
                     . "a transition period. Ask about customer relationships: do they follow the owner "
                     . "personally, or is there an established process and client list? Transition support "
                     . "and an earnout provision are worth negotiating.",
        ];
    }

    // Asking price above valuation range
    if ($valuation && !empty($valuation['summary']['asking_verdict']) && empty($valuation['summary']['no_earnings'])) {
        if ($valuation['summary']['asking_verdict'] === 'above') {
            $ask   = money((float)$valuation['summary']['asking_price']);
            $high  = money((float)$valuation['summary']['consensus_high']);
            $flags[] = [
                'level' => 'high',
                'title' => 'Asking Price Above Estimated Range',
                'body'  => "The seller's asking price of {$ask} exceeds the upper end of this report's "
                         . "estimated range ({$high}). That doesn't mean the price is wrong — qualitative "
                         . "factors, synergies, or proprietary assets can justify a premium — but you "
                         . "should be able to articulate specifically what justifies the gap before signing.",
            ];
        }
    }

    // ── MEDIUM severity ───────────────────────────────────────────────────

    // No written contracts
    if (!$report['has_written_contracts']) {
        $flags[] = [
            'level'  => 'medium',
            'title'  => 'No Written Customer Contracts',
            'body'   => "Revenue appears to be based on informal customer relationships rather than "
                      . "signed contracts. This creates transition risk — customers may not feel obligated "
                      . "to continue once ownership changes. Ask for evidence of customer tenure and "
                      . "whether any customers have historically left after ownership changes.",
        ];
    }

    // Fewer than three years of numbers entered
    $yearsGiven = 1 + (int)($r1 > 0) + (int)((float)($report['revenue_y2'] ?? 0) > 0);
    if ($yearsGiven < 3 && (int)$report['years_in_operation'] >= 3) {
        $flags[] = [
            'level'  => 'medium',
            'title'  => 'Incomplete Financial History',
            'body'   => "The business has been operating for {$report['years_in_operation']} years, but only "
                      . "{$yearsGiven} " . ($yearsGiven === 1 ? 'year' : 'years') . " of financials went into this report. "
                      . "Ask for three years of tax returns and P&Ls; a seller who can't produce them is telling you something.",
        ];
    }

    // Short operating history
    if ((int)$report['years_in_operation'] < 3) {
        $years = (int)$report['years_in_operation'];
        $flags[] = [
            'level'  => 'medium',
            'title'  => 'Limited Operating History',
            'body'   => "The business has operated for approximately {$years} " . ($years === 1 ? 'year' : 'years') . ". "
                      . "Most SBA lenders require at least 2 years of tax returns, and a short track record "
                      . "makes valuation less reliable. The financial story may not yet reflect a normalized "
                      . "operating cycle.",
        ];
    }

    // Thin margins
    $margin = $r3 > 0 ? ($p3 / $r3) : 0;
    if ($r3 > 0 && $margin < 0.10) {
        $marginPct = round($margin * 100);
        $flags[]   = [
            'level'  => 'medium',
            'title'  => 'Thin Profit Margins',
            'body'   => "Net profit margin is approximately {$marginPct}% after any owner salary paid through payroll. "
                      . "Thin margins leave little cushion for debt service if you're using an SBA loan. "
                      . "Run a pro forma: what does cash flow look like after your projected loan payment?",
        ];
    }

    // Lease risk
    $leaseYears = (int)$report['lease_years_remaining'];
    if ($leaseYears > 0 && $leaseYears <= 2) {
        $flags[] = [
            'level'  => 'medium',
            'title'  => 'Lease Expiring Soon',
            'body'   => "The lease expires in approximately {$leaseYears} " . ($leaseYears === 1 ? 'year' : 'years') . ". "
                      . "A favorable lease at below-market rent is a real asset; losing it is a real liability. "
                      . "Request a landlord estoppel letter and confirm whether the lease is assignable. "
                      . "If the landlord won't commit to a renewal, factor that into your price.",
        ];
    }

    // Revenue flat (not declining, but not growing either — worth noting)
    if ($r1 > 0 && $r3 > 0 && abs($r3 - $r1) / $r1 < 0.05 && $concentration < 30) {
        // Only flag if no other revenue flags exist and business is over 3 years old
        $hasRevFlag = array_filter($flags, fn($f) => str_contains($f['title'], 'Revenue'));
        if (empty($hasRevFlag) && (int)$report['years_in_operation'] >= 3) {
            $flags[] = [
                'level'  => 'low',
                'title'  => 'Revenue Has Been Flat',
                'body'   => "Revenue has been roughly flat over the past three years. That's not a red flag "
                          . "on its own — a stable, profitable business can be exactly what you want — but "
                          . "understand whether the ceiling is structural (market saturation, owner bandwidth) "
                          . "or addressable with new ownership.",
            ];
        }
    }

    // Sort: high → medium → low
    usort($flags, function ($a, $b) {
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        return $order[$a['level']] <=> $order[$b['level']];
    });

    return $flags;
}
