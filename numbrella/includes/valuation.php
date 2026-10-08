<?php
declare(strict_types=1);

require_once __DIR__ . '/multiples.php';

/**
 * Core valuation engine. Pure functions, no DB access.
 *
 * All dollar values are floats (not cents). Y3 is the most recent full year,
 * Y1 two years before it.
 *
 * Changes from the standalone app (2026-10 port), each one a case where the
 * old engine produced a number a buyer could be misled by:
 *   - A year left blank is "not provided" and drops out of the weighted SDE and
 *     the growth rate. It used to count as a year of zero profit, which halved
 *     the value of a two-year-old business.
 *   - The growth rate uses the real span between the first provided year and Y3.
 *   - A business with no positive weighted SDE gets no earnings-based value:
 *     the consensus is $0 and the report says why. Before, the DCF went
 *     negative and the "estimated value range" ran from -$10,619 to $5,946.
 *   - DCF is floored at zero, so its low <= mid <= high again.
 *   - The consensus midpoint uses the same weights as the low and high
 *     (60% SDE, 25% revenue, 15% DCF). It used 50/25/25 for the midpoint only.
 *   - The cash-flow projection starts from the most recent year's SDE, not the
 *     three-year weighted average, so a declining business no longer shows an
 *     instant rebound in projected Year 1.
 */

const NB_ENGINE_VERSION = '2026-10';

/** Consensus weights, applied identically to low, mid and high. */
const NB_WEIGHTS = ['sde' => 0.60, 'revenue' => 0.25, 'dcf' => 0.15];

const NB_DCF_DISCOUNT_RATE = 0.25;
const NB_DCF_TERMINAL_MULT = 3.0;
const NB_DCF_YEARS         = 5;
const NB_GROWTH_CAP        = 0.05;

// ---------------------------------------------------------------------------
// Inputs
// ---------------------------------------------------------------------------

/** Sum of the add-backs, which apply to the most recent year only. */
function addbacksTotal(array $report): float
{
    $total = 0.0;
    if (!empty($report['addbacks_json'])) {
        foreach (json_decode((string) $report['addbacks_json'], true) ?? [] as $row) {
            $total += (float) ($row['amount'] ?? 0);
        }
    }
    return $total;
}

/**
 * The years with financials, as [year => ['revenue' => x, 'net' => y]].
 * A year counts as provided when its revenue was entered. Y3 always counts.
 */
function providedYears(array $report): array
{
    $years = [];
    foreach ([1, 2, 3] as $y) {
        $rev = $report['revenue_y' . $y] ?? null;
        if ($y === 3 || ($rev !== null && $rev !== '' && (float) $rev > 0)) {
            $years[$y] = [
                'revenue' => (float) ($rev ?? 0),
                'net'     => (float) ($report['net_profit_y' . $y] ?? 0),
            ];
        }
    }
    return $years;
}

/**
 * SDE for one year: net profit (after any owner salary run through payroll)
 * + that owner salary + add-backs (most recent year only).
 */
function yearSDE(array $report, int $year): float
{
    $sde = (float) ($report['net_profit_y' . $year] ?? 0) + (float) $report['owner_salary'];
    if ($year === 3) {
        $sde += addbacksTotal($report);
    }
    return $sde;
}

/** Most recent year's SDE. */
function computeSDE(array $report): float
{
    return yearSDE($report, 3);
}

/**
 * Weighted trailing SDE: 3×Y3 + 2×Y2 + 1×Y1, divided by the weights of the
 * years actually provided.
 */
function computeWeightedSDE(array $report): float
{
    $sum = 0.0;
    $w   = 0;
    foreach (array_keys(providedYears($report)) as $y) {
        $sum += $y * yearSDE($report, $y);
        $w   += $y;
    }
    return $w > 0 ? $sum / $w : 0.0;
}

/**
 * Annual revenue growth from the earliest provided year to Y3, capped at
 * ±5%. Zero when there is only one year.
 */
function revenueGrowthRate(array $report): float
{
    $years = providedYears($report);
    $first = min(array_keys($years));
    $r0    = $years[$first]['revenue'];
    $r3    = $years[3]['revenue'];
    if ($first === 3 || $r0 <= 0 || $r3 <= 0) {
        return 0.0;
    }
    $cagr = pow($r3 / $r0, 1 / (3 - $first)) - 1;
    return max(-NB_GROWTH_CAP, min(NB_GROWTH_CAP, $cagr));
}

// ---------------------------------------------------------------------------
// Method 1: SDE Multiple
// ---------------------------------------------------------------------------

function valueBySDE(array $report): array
{
    $m           = getMultiplesForIndustry($report['industry_key']);
    $weightedSDE = computeWeightedSDE($report);

    return [
        'method'           => 'sde',
        'label'            => 'SDE Multiple',
        'sde'              => computeSDE($report),
        'weighted_sde'     => $weightedSDE,
        'years_used'       => array_keys(providedYears($report)),
        'addbacks_total'   => addbacksTotal($report),
        'multiple_floor'   => $m['sde'][0],
        'multiple_mid'     => $m['sde'][1],
        'multiple_ceiling' => $m['sde'][2],
        'low'              => max(0.0, $weightedSDE * $m['sde'][0]),
        'mid'              => max(0.0, $weightedSDE * $m['sde'][1]),
        'high'             => max(0.0, $weightedSDE * $m['sde'][2]),
    ];
}

// ---------------------------------------------------------------------------
// Method 2: Revenue Multiple
// ---------------------------------------------------------------------------

function valueByRevenue(array $report): array
{
    $m       = getMultiplesForIndustry($report['industry_key']);
    $revenue = max(0.0, (float) ($report['revenue_y3'] ?? 0));

    return [
        'method'           => 'revenue',
        'label'            => 'Revenue Multiple',
        'revenue'          => $revenue,
        'multiple_floor'   => $m['rev'][0],
        'multiple_mid'     => $m['rev'][1],
        'multiple_ceiling' => $m['rev'][2],
        'low'              => $revenue * $m['rev'][0],
        'mid'              => $revenue * $m['rev'][1],
        'high'             => $revenue * $m['rev'][2],
    ];
}

// ---------------------------------------------------------------------------
// Method 3: DCF (illustrative only)
// ---------------------------------------------------------------------------

/**
 * Simplified DCF: five years of weighted SDE grown at the capped historical
 * rate, discounted at 25%, plus a 3.0× terminal value. Floored at zero.
 *
 * Known limitation, shown in the report: it discounts SDE, which still
 * includes the owner's own pay, with nothing taken out for capex or working
 * capital. That makes it worth roughly 3-4× SDE whatever the industry, above
 * most industries' SDE multiples. It carries 15% of the consensus for that reason.
 */
function valueByDCF(array $report): array
{
    $baseFCF    = max(0.0, computeWeightedSDE($report));
    $growthRate = revenueGrowthRate($report);

    $projection = [];
    $totalPV    = 0.0;
    for ($y = 1; $y <= NB_DCF_YEARS; $y++) {
        $fcf = $baseFCF * pow(1 + $growthRate, $y);
        $pv  = $fcf / pow(1 + NB_DCF_DISCOUNT_RATE, $y);
        $projection[] = ['year' => $y, 'fcf' => $fcf, 'pv' => $pv];
        $totalPV += $pv;
    }

    $terminalValue = $baseFCF * pow(1 + $growthRate, NB_DCF_YEARS) * NB_DCF_TERMINAL_MULT;
    $terminalPV    = $terminalValue / pow(1 + NB_DCF_DISCOUNT_RATE, NB_DCF_YEARS);
    $totalPV      += $terminalPV;

    return [
        'method'         => 'dcf',
        'label'          => 'Discounted Cash Flow',
        'base_fcf'       => $baseFCF,
        'growth_rate'    => $growthRate,
        'discount_rate'  => NB_DCF_DISCOUNT_RATE,
        'terminal_mult'  => NB_DCF_TERMINAL_MULT,
        'terminal_value' => $terminalValue,
        'terminal_pv'    => $terminalPV,
        'projection'     => $projection,
        'low'            => $totalPV * 0.80,
        'mid'            => $totalPV,
        'high'           => $totalPV * 1.20,
        'illustrative'   => true,
    ];
}

// ---------------------------------------------------------------------------
// Consensus summary
// ---------------------------------------------------------------------------

/**
 * Weighted consensus of the three methods (NB_WEIGHTS for low, mid and high
 * alike) plus the asking-price verdict. With no positive weighted SDE there is
 * no earnings value to weigh, so the consensus is zero and a warning says so;
 * a revenue multiple on a business that loses money is not a price.
 */
function summarizeValuation(array $sdeMeth, array $revMeth, array $dcfMeth, array $report): array
{
    $noEarnings = $sdeMeth['weighted_sde'] <= 0;

    $consensus = [];
    foreach (['low', 'mid', 'high'] as $k) {
        $consensus[$k] = $noEarnings ? 0.0
            : $sdeMeth[$k] * NB_WEIGHTS['sde'] + $revMeth[$k] * NB_WEIGHTS['revenue'] + $dcfMeth[$k] * NB_WEIGHTS['dcf'];
    }

    $result = [
        'consensus_low'  => $consensus['low'],
        'consensus_mid'  => $consensus['mid'],
        'consensus_high' => $consensus['high'],
        'weights'        => NB_WEIGHTS,
        'no_earnings'    => $noEarnings,
        'asking_price'   => null,
        'asking_verdict' => null,   // 'below', 'within', 'above'
    ];

    $askingPrice = (float) ($report['asking_price'] ?? 0);
    if ($askingPrice > 0) {
        $result['asking_price']   = $askingPrice;
        $result['asking_verdict'] = match (true) {
            $askingPrice < $consensus['low']  => 'below',
            $askingPrice > $consensus['high'] => 'above',
            default                           => 'within',
        };
    }

    return $result;
}

/** Plain-language caveats about the inputs, shown at the top of the report. */
function valuationWarnings(array $report, array $summary, array $sdeMeth, array $revMeth): array
{
    $warnings = [];
    if (!$summary['no_earnings']
        && $revMeth['mid'] * NB_WEIGHTS['revenue'] > $sdeMeth['mid'] * NB_WEIGHTS['sde']) {
        $warnings[] = 'Earnings are thin for a business with this much revenue, so most of this range comes from '
                    . 'the revenue multiple rather than from what the business earns. A revenue multiple assumes '
                    . 'normal margins for the industry; unless you can see how to restore them, lean on the SDE '
                    . 'figures and on what the assets are worth.';
    }
    if ($summary['no_earnings']) {
        $warnings[] = 'The business shows no positive seller\'s discretionary earnings across the years provided, '
                    . 'so there is no earnings-based value to estimate. What it is worth is roughly what its '
                    . 'assets would fetch (equipment, inventory, lease, customer list), which this report does '
                    . 'not compute. The revenue multiple below is shown for reference only and is not a price.';
    }
    $n = count(providedYears($report));
    if ($n < 3) {
        $warnings[] = 'Only ' . $n . ' year' . ($n === 1 ? ' of financials was' : 's of financials were') . ' provided, so the '
                    . 'weighted SDE and growth rate rest on ' . ($n === 1 ? 'a single year' : 'two years')
                    . '. Treat every figure here as less reliable than a three-year history would make it.';
    }
    return $warnings;
}

// ---------------------------------------------------------------------------
// Full report: 3-year cash flow projection
// ---------------------------------------------------------------------------

/**
 * Three years forward from the most recent year's revenue and SDE at the
 * capped historical growth rate.
 */
function buildCashFlowProjection(array $report): array
{
    $baseSDE    = computeSDE($report);
    $r3         = (float) ($report['revenue_y3'] ?? 0);
    $growthRate = revenueGrowthRate($report);
    // Growing a loss by a falling revenue rate would shrink the loss; hold it flat instead.
    $sdeGrowth  = $baseSDE > 0 ? $growthRate : 0.0;

    $years = [];
    for ($y = 1; $y <= 3; $y++) {
        $years[] = [
            'year'       => 'Year ' . $y,
            'revenue'    => $r3 * pow(1 + $growthRate, $y),
            'sde'        => $baseSDE * pow(1 + $sdeGrowth, $y),
            'growth_pct' => $growthRate,
        ];
    }

    return ['base_sde' => $baseSDE, 'growth_rate' => $growthRate, 'years' => $years];
}

// ---------------------------------------------------------------------------
// Full report: scenario analysis
// ---------------------------------------------------------------------------

/** The deal at -20%, -10%, midpoint, +10% and +20% of the consensus midpoint. */
function buildScenarioAnalysis(array $summary, array $report): array
{
    if ($summary['consensus_mid'] <= 0) {
        return [];
    }
    $baseSDE   = computeWeightedSDE($report);
    $scenarios = [];
    foreach ([-0.20, -0.10, 0.0, 0.10, 0.20] as $adj) {
        $price = $summary['consensus_mid'] * (1 + $adj);
        $scenarios[] = [
            'adjustment'   => $adj,
            'price'        => $price,
            'sde_multiple' => $baseSDE > 0 ? $price / $baseSDE : 0,
            'verdict'      => match (true) {
                $price < $summary['consensus_low']  => 'Buyer-favorable',
                $price > $summary['consensus_high'] => 'Seller-favorable',
                default                             => 'Fair range',
            },
        ];
    }
    return $scenarios;
}

// ---------------------------------------------------------------------------
// Full report: industry benchmark context
// ---------------------------------------------------------------------------

/**
 * Where this business and its asking price sit against the industry ranges:
 * the SDE margin, and the multiples the asking price implies. Promised on the
 * old $99 tier but never built before the port.
 */
function buildBenchmarkContext(array $report): array
{
    $m        = getMultiplesForIndustry($report['industry_key']);
    $wSDE     = computeWeightedSDE($report);
    $revenue  = (float) ($report['revenue_y3'] ?? 0);
    $asking   = (float) ($report['asking_price'] ?? 0);

    $position = static function (?float $x, array $range): ?string {
        if ($x === null) {
            return null;
        }
        return match (true) {
            $x < $range[0] => 'below the range',
            $x > $range[2] => 'above the range',
            $x < $range[1] => 'lower half of the range',
            default        => 'upper half of the range',
        };
    };

    $impliedSDE = ($asking > 0 && $wSDE > 0) ? $asking / $wSDE : null;
    $impliedRev = ($asking > 0 && $revenue > 0) ? $asking / $revenue : null;

    return [
        'industry'             => $m['name'],
        'sde_range'            => $m['sde'],
        'rev_range'            => $m['rev'],
        'sde_margin'           => $revenue > 0 ? computeSDE($report) / $revenue : null,
        'implied_sde_multiple' => $impliedSDE,
        'implied_sde_position' => $position($impliedSDE, $m['sde']),
        'implied_rev_multiple' => $impliedRev,
        'implied_rev_position' => $position($impliedRev, $m['rev']),
    ];
}

// ---------------------------------------------------------------------------
// Main entry point
// ---------------------------------------------------------------------------

/**
 * Run the full valuation engine on a report row. Returns the complete results
 * array for JSON storage. Tier 'premium' adds the projection, scenarios and
 * benchmark context; while payments are off every report is run as premium.
 */
function runValuationEngine(array $report): array
{
    $sde     = valueBySDE($report);
    $rev     = valueByRevenue($report);
    $dcf     = valueByDCF($report);
    $summary = summarizeValuation($sde, $rev, $dcf, $report);

    $result = [
        'engine'       => NB_ENGINE_VERSION,
        'generated_at' => gmdate('Y-m-d H:i:s'),
        'industry'     => getMultiplesForIndustry($report['industry_key'])['name'],
        'summary'      => $summary,
        'warnings'     => valuationWarnings($report, $summary, $sde, $rev),
        'methods'      => ['sde' => $sde, 'revenue' => $rev, 'dcf' => $dcf],
    ];

    if (($report['tier'] ?? 'premium') === 'premium') {
        $result['cash_flow_projection'] = buildCashFlowProjection($report);
        $result['scenario_analysis']    = buildScenarioAnalysis($summary, $report);
        $result['benchmark']            = buildBenchmarkContext($report);
    }

    return $result;
}
