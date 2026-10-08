<?php

/**
 * Calculate monthly payment for an amortizing loan.
 * Returns 0 if principal or rate is 0.
 */
function monthlyPayment(float $principal, float $annualRate, int $termYears): float {
    if ($principal <= 0) return 0.0;
    if ($annualRate <= 0) {
        // Simple division, no interest
        return $principal / ($termYears * 12);
    }
    $r = $annualRate / 12;
    $n = $termYears * 12;
    return $principal * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
}

/**
 * Generate a year-by-year cash flow summary table.
 * Returns array of years, each with: year, seller_note_payment, cdfi_payment, total_payment,
 * per_member_payment, seller_note_balance, cdfi_balance, interest_paid
 */
function buildCashFlowTable(
    float $sellerNoteAmount,
    float $sellerNoteRate,
    int   $sellerNoteTermYears,
    float $cdfiAmount,
    float $cdfiRate,
    int   $cdfiTermYears,
    int   $memberCount,
    int   $years = 5
): array {
    $table = [];
    $sellerBalance  = $sellerNoteAmount;
    $cdfiBalance    = $cdfiAmount;
    $sellerMonthly  = monthlyPayment($sellerNoteAmount, $sellerNoteRate, $sellerNoteTermYears);
    $cdfiMonthly    = monthlyPayment($cdfiAmount, $cdfiRate, $cdfiTermYears);

    for ($year = 1; $year <= $years; $year++) {
        $sellerInterestPaid  = 0.0;
        $cdfiInterestPaid    = 0.0;
        $sellerPrincipalPaid = 0.0;
        $cdfiPrincipalPaid   = 0.0;

        for ($month = 1; $month <= 12; $month++) {
            // Seller note
            if ($sellerBalance > 0 && $year <= $sellerNoteTermYears) {
                $sellerInterest      = $sellerBalance * ($sellerNoteRate / 12);
                $sellerPrincipal     = min($sellerMonthly - $sellerInterest, $sellerBalance);
                $sellerInterestPaid  += $sellerInterest;
                $sellerPrincipalPaid += $sellerPrincipal;
                $sellerBalance        = max(0, $sellerBalance - $sellerPrincipal);
            }
            // CDFI loan
            if ($cdfiBalance > 0 && $year <= $cdfiTermYears) {
                $cdfiInterest      = $cdfiBalance * ($cdfiRate / 12);
                $cdfiPrincipal     = min($cdfiMonthly - $cdfiInterest, $cdfiBalance);
                $cdfiInterestPaid  += $cdfiInterest;
                $cdfiPrincipalPaid += $cdfiPrincipal;
                $cdfiBalance        = max(0, $cdfiBalance - $cdfiPrincipal);
            }
        }

        $sellerAnnual     = ($year <= $sellerNoteTermYears) ? $sellerMonthly * 12 : 0;
        $cdfiAnnual       = ($year <= $cdfiTermYears)       ? $cdfiMonthly * 12   : 0;
        $totalAnnual      = $sellerAnnual + $cdfiAnnual;
        $perMemberAnnual  = $memberCount > 0 ? $totalAnnual / $memberCount : 0;

        $table[] = [
            'year'               => $year,
            'seller_note_annual' => $sellerAnnual,
            'cdfi_annual'        => $cdfiAnnual,
            'total_annual'       => $totalAnnual,
            'per_member_annual'  => $perMemberAnnual,
            'per_member_monthly' => $memberCount > 0
                ? ($sellerMonthly + $cdfiMonthly) / $memberCount
                : 0,
            'seller_note_balance' => $sellerBalance,
            'cdfi_balance'        => $cdfiBalance,
            'interest_paid'       => $sellerInterestPaid + $cdfiInterestPaid,
        ];
    }
    return $table;
}

function formatCurrency(float $amount): string {
    return '$' . number_format($amount, 0);
}

function formatCurrencyDecimal(float $amount): string {
    return '$' . number_format($amount, 2);
}
