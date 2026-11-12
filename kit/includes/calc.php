<?php
declare(strict_types=1);

/**
 * Advisor Field Kit calculation engine.
 *
 * Every function here is pure: worksheet inputs in, numbers and a reading out.
 * No I/O, no API calls, no model. That is deliberate. The source framework
 * catalogs seven errors an AI made on this exact case data, each stated with
 * confidence — wrong runway, aged receivables counted as cash, owner draws
 * treated as discretionary. Arithmetic this consequential is arithmetic the
 * app does itself, and shows.
 *
 * Each function returns a 'steps' array: [label, expression, value]. The
 * worksheet pages render it verbatim so the advisor can check the math against
 * a bank statement without trusting the app.
 */

const READ_HEALTHY = 'healthy';
const READ_WATCH   = 'watch';
const READ_ACT     = 'act';
const READ_UNKNOWN = 'unknown';

function readingLabel(string $r): string
{
    return match ($r) {
        READ_HEALTHY => 'Healthy',
        READ_WATCH   => 'Watch',
        READ_ACT     => 'Act',
        default      => 'No reading',
    };
}

/** Tailwind classes per reading. Kept here so the three metrics never drift. */
function readingClasses(string $r): string
{
    return match ($r) {
        READ_HEALTHY => 'bg-good-soft text-good border-good',
        READ_WATCH   => 'bg-warn-soft text-warn border-warn',
        READ_ACT     => 'bg-bad-soft  text-bad  border-bad',
        default      => 'bg-slate-100 text-muted border-hairline',
    };
}

/** Worst of a set of readings — drives the client-card summary. */
function worstReading(array $readings): string
{
    $rank = [READ_UNKNOWN => 0, READ_HEALTHY => 1, READ_WATCH => 2, READ_ACT => 3];
    $worst = READ_UNKNOWN;
    foreach ($readings as $r) {
        if (($rank[$r] ?? 0) > ($rank[$worst] ?? 0)) $worst = $r;
    }
    return $worst;
}

function step(string $label, string $expression, string $value): array
{
    return ['label' => $label, 'expression' => $expression, 'value' => $value];
}

// ===========================================================================
// Three-Metric Health Check (Unit 2.4)
//
// Worked from the last three bank statements, not the P&L and not the tax
// return. Read the three together, never one alone.
// ===========================================================================

/**
 * Metric 1 — trailing 90-day cash flow.
 *
 * Cash in excludes loans, transfers between accounts, and owner contributions
 * of personal money: none of those are the business earning anything. Cash out
 * includes owner draws, because the owner's pay is a cost, not a leftover.
 *
 * The Watch band exists for the case that otherwise reads as success: cash is
 * positive only because the owner skipped their own pay.
 */
function calcCashFlow(array $d): array
{
    $cashIn       = num($d['m1_cash_in'] ?? 0);
    $cashOut      = num($d['m1_cash_out'] ?? 0);
    $ownerDraws   = num($d['m1_owner_draws'] ?? 0);
    $baselineMo   = num($d['m1_baseline_monthly'] ?? 0);
    $baseline90   = $baselineMo * 3;
    $net          = $cashIn - $cashOut;
    $paidInFull   = $baseline90 > 0 && $ownerDraws + 0.005 >= $baseline90;
    $shortfall    = max(0.0, $baseline90 - $ownerDraws);

    $hasData = ($cashIn != 0.0 || $cashOut != 0.0);

    if (!$hasData) {
        $reading = READ_UNKNOWN;
    } elseif ($net < 0) {
        $reading = READ_ACT;
    } elseif ($baselineMo > 0 && !$paidInFull) {
        $reading = READ_WATCH;     // positive only because owner pay was cut
    } else {
        $reading = READ_HEALTHY;
    }

    $steps = [
        step('1a  Cash in (90 days)',  'deposits, excluding loans, transfers, and owner contributions', money($cashIn)),
        step('1b  Cash out (90 days)', 'every withdrawal, including owner draws', money($cashOut)),
        step('1c  Net 90-day cash flow', money($cashIn) . ' − ' . money($cashOut), money($net)),
    ];
    if ($baselineMo > 0) {
        $steps[] = step('Owner baseline pay owed (90 days)',
            money($baselineMo) . ' × 3', money($baseline90));
        $steps[] = step('Owner draws actually taken', 'from 1b', money($ownerDraws));
        $steps[] = step('Owner pay drawn in full?',
            $paidInFull ? 'draws ≥ baseline owed' : money($shortfall) . ' short of baseline',
            $paidInFull ? 'Yes' : 'No');
    }

    return [
        'cash_in' => $cashIn, 'cash_out' => $cashOut, 'net' => $net,
        'owner_draws' => $ownerDraws, 'baseline_monthly' => $baselineMo,
        'baseline_90' => $baseline90, 'paid_in_full' => $paidInFull,
        'shortfall' => $shortfall, 'reading' => $reading, 'steps' => $steps,
        'note' => match ($reading) {
            READ_ACT     => 'Cash shrank over the last 90 days.',
            READ_WATCH   => 'Cash is positive only because the owner did not take their full baseline pay. That is a subsidy, not a profit.',
            READ_HEALTHY => 'Cash grew, with the owner paid in full.',
            default      => 'Enter 90 days of deposits and withdrawals to get a reading.',
        },
    ];
}

/**
 * Metric 2 — fixed-cost burn and runway.
 *
 * Baseline owner pay is a line in the burn, not an afterthought. A runway
 * figure that assumes the owner works for nothing is not a runway figure.
 */
function calcRunway(array $d): array
{
    $lines = [
        'm2_insurance' => 'Insurance',
        'm2_vehicle'   => 'Vehicle and equipment payments',
        'm2_phone'     => 'Phone, software, subscriptions',
        'm2_rent'      => 'Rent, storage, shop space',
        'm2_other'     => 'Other fixed (accounting, licenses)',
        'm2_owner_pay' => 'Owner baseline pay',
    ];
    $items = [];
    $burn  = 0.0;
    foreach ($lines as $key => $label) {
        $v = num($d[$key] ?? 0);
        $items[] = ['label' => $label, 'amount' => $v];
        $burn   += $v;
    }
    $cashOnHand = num($d['m2_cash_on_hand'] ?? 0);
    $runway     = $burn > 0 ? $cashOnHand / $burn : 0.0;

    if ($burn <= 0)          $reading = READ_UNKNOWN;
    elseif ($runway >= 3.0)  $reading = READ_HEALTHY;
    elseif ($runway >= 1.0)  $reading = READ_WATCH;
    else                     $reading = READ_ACT;

    $steps = [
        step('Monthly fixed-cost burn', 'sum of costs that continue with no new work', money($burn)),
        step('Cash on hand', 'today', money($cashOnHand)),
        step('Runway in months', money($cashOnHand) . ' ÷ ' . money($burn),
             $burn > 0 ? number_format($runway, 1) . ' months' : '—'),
    ];

    return [
        'items' => $items, 'burn' => $burn, 'cash_on_hand' => $cashOnHand,
        'runway' => $runway, 'reading' => $reading, 'steps' => $steps,
        'owner_pay_included' => num($d['m2_owner_pay'] ?? 0) > 0,
        'note' => match ($reading) {
            READ_HEALTHY => 'Three months or more of fixed costs covered.',
            READ_WATCH   => 'Between one and three months. Thin, not critical.',
            READ_ACT     => 'Under one month of fixed costs covered.',
            default      => 'Enter the monthly fixed costs to get a reading.',
        },
    ];
}

/**
 * Metric 3 — quick liquidity.
 *
 * Receivables past 60 days are collected separately and never added to
 * available. Money that has already failed to arrive for two months is not
 * liquidity; counting it is how a business that cannot pay its bills reads
 * as solvent.
 */
function calcLiquidity(array $d): array
{
    $cash      = num($d['m3_cash'] ?? 0);
    $ar30      = num($d['m3_ar_30'] ?? 0);
    $bills     = num($d['m3_bills_due'] ?? 0);
    $arPast60  = num($d['m3_ar_past_60'] ?? 0);
    $available = $cash + $ar30;
    $ratio     = $bills > 0 ? $available / $bills : 0.0;

    if ($bills <= 0)        $reading = READ_UNKNOWN;
    elseif ($ratio >= 1.5)  $reading = READ_HEALTHY;
    elseif ($ratio >= 1.0)  $reading = READ_WATCH;
    else                    $reading = READ_ACT;

    $steps = [
        step('Cash on hand', '', money($cash)),
        step('Receivables likely collected in 30 days', 'excludes anything past 60 days', money($ar30)),
        step('Available', money($cash) . ' + ' . money($ar30), money($available)),
        step('Bills due in 30 days', 'including estimated tax payments and payroll', money($bills)),
        step('Liquidity ratio', money($available) . ' ÷ ' . money($bills),
             $bills > 0 ? number_format($ratio, 2) : '—'),
        step('Receivables past 60 days', 'listed separately, not counted as available', money($arPast60)),
    ];

    return [
        'cash' => $cash, 'ar_30' => $ar30, 'bills' => $bills,
        'ar_past_60' => $arPast60, 'available' => $available,
        'ratio' => $ratio, 'reading' => $reading, 'steps' => $steps,
        'note' => match ($reading) {
            READ_HEALTHY => 'The next 30 days are covered with margin.',
            READ_WATCH   => 'The next 30 days are covered only if receivables arrive on time.',
            READ_ACT     => 'The next 30 days cannot be paid from available funds.',
            default      => 'Enter bills due in the next 30 days to get a reading.',
        },
    ];
}

/** All three metrics plus the combined reading. */
function calcHealthCheck(array $d): array
{
    $m1 = calcCashFlow($d);
    $m2 = calcRunway($d);
    $m3 = calcLiquidity($d);
    return [
        'm1' => $m1, 'm2' => $m2, 'm3' => $m3,
        'overall' => worstReading([$m1['reading'], $m2['reading'], $m3['reading']]),
    ];
}

// ===========================================================================
// Effective Hourly Rate (Unit 2.2)
//
// Sections A–E of the worksheet. The arithmetic is the easy part; getting the
// owner to report the real hours is the skill. The interview prompts live on
// the form, not here.
// ===========================================================================
function calcEHR(array $d): array
{
    // Section A — real take-home
    $a1 = num($d['a1_revenue'] ?? 0);
    $a2 = num($d['a2_operating_costs'] ?? 0);
    $a3 = $a1 - $a2;
    $a4 = num($d['a4_tax_reserve_rate'] ?? 0) / 100.0;   // entered as a percent
    $a5 = $a3 * $a4;
    $a6 = $a3 - $a5;

    // Section B — every hour, billed or not
    $bKeys = ['b1_billable', 'b2_travel', 'b3_quoting', 'b4_admin',
              'b5_equipment', 'b6_afterhours', 'b7_other'];
    $b = [];
    foreach ($bKeys as $k) $b[$k] = num($d[$k] ?? 0);
    $b1  = $b['b1_billable'];
    $b8  = array_sum($b);
    $b9  = num($d['b9_weeks'] ?? 0);
    $b10 = $b8 * $b9;
    $b11 = $b1 * $b9;

    // Section C — the number
    $c1 = $b10 > 0 ? $a6 / $b10 : 0.0;
    $c2 = num($d['c2_quoted_rate'] ?? 0);
    $c3 = $c2 > 0 ? $c1 / $c2 : 0.0;
    $c4 = $b11 > 0 ? $a1 / $b11 : 0.0;
    $unbilledShare = $b8 > 0 ? ($b8 - $b1) / $b8 : 0.0;

    // Section D — the pricing floor
    $d1 = num($d['d1_target_takehome'] ?? 0);
    $d2 = $a4 < 1.0 ? $d1 / (1 - $a4) : 0.0;
    $d3 = $d2 + $a2;
    $d4 = $b11 > 0 ? $d3 / $b11 : 0.0;
    $gap = $d4 - $c4;

    $steps = [
        step('A3  Net profit',            money($a1) . ' − ' . money($a2), money($a3)),
        step('A5  Tax set-aside',         money($a3) . ' × ' . number_format($a4 * 100, 1) . '%', money($a5)),
        step('A6  Real take-home',        money($a3) . ' − ' . money($a5), money($a6)),
        step('B8  Total hours per week',  'B1 + B2 + B3 + B4 + B5 + B6 + B7', number_format($b8, 1)),
        step('B10 Total annual hours',    number_format($b8, 1) . ' × ' . number_format($b9, 0) . ' weeks', number_format($b10, 0)),
        step('B11 Annual billable hours', number_format($b1, 1) . ' × ' . number_format($b9, 0) . ' weeks', number_format($b11, 0)),
        step('C1  Effective hourly rate', money($a6) . ' ÷ ' . number_format($b10, 0) . ' hrs', money($c1)),
        step('C3  EHR as share of quoted rate', $c2 > 0 ? money($c1) . ' ÷ ' . money($c2) : '—', $c2 > 0 ? pct($c3) : '—'),
        step('C4  Revenue per billable hour', $b11 > 0 ? money($a1) . ' ÷ ' . number_format($b11, 0) . ' hrs' : '—', $b11 > 0 ? money($c4) : '—'),
        step('Unbilled share of the workweek', $b8 > 0 ? '(' . number_format($b8, 1) . ' − ' . number_format($b1, 1) . ') ÷ ' . number_format($b8, 1) : '—', $b8 > 0 ? pct($unbilledShare) : '—'),
        step('D2  Target net profit',     $a4 < 1.0 ? money($d1) . ' ÷ (1 − ' . number_format($a4 * 100, 1) . '%)' : '—', money($d2)),
        step('D3  Required revenue',      money($d2) . ' + ' . money($a2), money($d3)),
        step('D4  Floor rate per billable hour', $b11 > 0 ? money($d3) . ' ÷ ' . number_format($b11, 0) . ' hrs' : '—', $b11 > 0 ? money($d4) : '—'),
    ];

    return compact('a1','a2','a3','a4','a5','a6','b','b1','b8','b9','b10','b11',
                   'c1','c2','c3','c4','d1','d2','d3','d4','gap','unbilledShare','steps');
}

// ===========================================================================
// Capacity Audit and Handoff Test (Units 3.1 and 3.2)
// ===========================================================================

const CAPACITY_TYPES = [
    'D' => 'Deliver the work',
    'S' => 'Sell and quote',
    'R' => 'Run the business (admin, money)',
    'F' => 'Fix (rework, emergencies, chasing)',
];

const HANDOFF_STATES = [
    'written'  => 'Written down',
    'in_head'  => 'In my head',
    'missing'  => "Doesn't exist",
];

/** The ten standard handoff-test items, in worksheet order. */
function handoffItems(): array
{
    return [
        'How to deliver the core service, step by step',
        'How to price and quote a job',
        'Customer list, with history and open jobs',
        'Supplier, vendor, and subcontractor contacts',
        'Where logins are kept (the location only, never the passwords)',
        'License, permit, and insurance renewal dates',
        'The bookkeeping, billing, and bill-paying routine',
        'What to do when the usual things go wrong',
        'Who sends referrals, and how each is thanked',
    ];
}

function calcCapacity(array $d): array
{
    $rows    = jrows($d, 'rows');
    $ceiling = num($d['ceiling'] ?? 0);

    $total = 0.0; $onlyMe = 0.0; $onlyMeUnwritten = 0.0;
    $byType = array_fill_keys(array_keys(CAPACITY_TYPES), 0.0);

    foreach ($rows as $r) {
        $hrs      = num($r['hours'] ?? 0);
        $isOnlyMe = !empty($r['only_me']);
        $written  = !empty($r['written']);
        $type     = $r['type'] ?? 'D';

        $total += $hrs;
        if (isset($byType[$type])) $byType[$type] += $hrs;
        if ($isOnlyMe) {
            $onlyMe += $hrs;
            if (!$written) $onlyMeUnwritten += $hrs;
        }
    }

    $overCeiling = ($ceiling > 0) ? $total - $ceiling : 0.0;

    // Handoff test tally
    $handoff = jrows($d, 'handoff');
    $counts  = ['written' => 0, 'in_head' => 0, 'missing' => 0];
    foreach ($handoff as $item) {
        $s = $item['state'] ?? '';
        if (isset($counts[$s])) $counts[$s]++;
    }

    $steps = [
        step('Total hours per week',           'sum of the activity rows', number_format($total, 1)),
        step('Hours marked "only me"',         'activities nobody else can do today', number_format($onlyMe, 1)),
        step('Sustainable weekly ceiling',     "the owner's number", $ceiling > 0 ? number_format($ceiling, 1) : '—'),
        step('Hours over the ceiling',         $ceiling > 0 ? number_format($total, 1) . ' − ' . number_format($ceiling, 1) : '—',
             $ceiling > 0 ? number_format($overCeiling, 1) : '—'),
        step('"Only me" hours not written down', 'where the business is most fragile', number_format($onlyMeUnwritten, 1)),
    ];

    return [
        'total' => $total, 'only_me' => $onlyMe, 'ceiling' => $ceiling,
        'over_ceiling' => $overCeiling, 'only_me_unwritten' => $onlyMeUnwritten,
        'by_type' => $byType, 'handoff_counts' => $counts, 'steps' => $steps,
    ];
}

// ===========================================================================
// Priority & Next Action (7.5)
//
// The sort is mechanical: a score of 3 counts as high, 1 or 2 counts as low.
// ===========================================================================

const LAYERS = [
    'R'  => ['name' => 'Regulatory', 'order' => 1],
    'F'  => ['name' => 'Financial',  'order' => 2],
    'O'  => ['name' => 'Offer',      'order' => 3],
    'Op' => ['name' => 'Operator',   'order' => 4],
];

const QUADRANTS = [
    'do_now'       => ['name' => 'Do now',          'hint' => 'High exposure, high readiness. Act on it.'],
    'advisor_work' => ['name' => "Advisor's work",  'hint' => 'High exposure, low readiness. This is where the advisor earns their fee.'],
    'quick_win'    => ['name' => 'Quick win',       'hint' => "Low exposure, high readiness. Don't let it crowd out the priority."],
    'park'         => ['name' => 'Park it',         'hint' => 'Low exposure, low readiness. Revisit later.'],
];

function quadrantFor(int $exposure, int $readiness): string
{
    $highExposure = $exposure >= 3;
    $highReady    = $readiness >= 3;
    if ($highExposure && $highReady)  return 'do_now';
    if ($highExposure && !$highReady) return 'advisor_work';
    if (!$highExposure && $highReady) return 'quick_win';
    return 'park';
}

function calcPriority(array $d): array
{
    $issues = jrows($d, 'issues');
    $sorted = array_fill_keys(array_keys(QUADRANTS), []);

    foreach ($issues as $i => $issue) {
        $exposure  = max(1, min(3, (int) num($issue['exposure']  ?? 1, 1.0)));
        $readiness = max(1, min(3, (int) num($issue['readiness'] ?? 1, 1.0)));
        $q = quadrantFor($exposure, $readiness);
        $issue['exposure']  = $exposure;
        $issue['readiness'] = $readiness;
        $issue['quadrant']  = $q;
        $issue['index']     = $i;
        $sorted[$q][] = $issue;
    }

    // Within a quadrant, regulatory first — the layer order is the
    // prioritization rule, not a tiebreaker applied afterwards.
    foreach ($sorted as &$bucket) {
        usort($bucket, fn($a, $b) =>
            (LAYERS[$a['layer'] ?? 'Op']['order'] ?? 9) <=> (LAYERS[$b['layer'] ?? 'Op']['order'] ?? 9));
    }
    unset($bucket);

    $exposureItems = array_values(array_filter(
        jrows($d, 'exposure_items'),
        fn($r) => trim((string) ($r['item'] ?? '')) !== ''
    ));

    return [
        'issues' => $issues, 'sorted' => $sorted,
        'exposure_items' => $exposureItems,
        'open_exposure_count' => count(array_filter($exposureItems, fn($r) => ($r['status'] ?? 'open') !== 'resolved')),
    ];
}

/**
 * Layer-order check on the chosen priority.
 *
 * "Regulation before revenue" is the framework's hardest rule, and the failure
 * it guards against is specific: recommending a marketing push while an
 * unresolved regulatory exposure sits open. The app warns; it does not block.
 * The advisor may have a reason, and the judgment stays theirs.
 */
function priorityLayerWarning(array $calc, string $priorityLayer): string
{
    if ($priorityLayer === '' || $priorityLayer === 'R') return '';

    $openR = [];
    foreach ($calc['exposure_items'] as $item) {
        if (($item['status'] ?? 'open') !== 'resolved') $openR[] = $item['item'];
    }
    if (!$openR) {
        // No regulatory exposures logged, but a higher-layer issue may still
        // outrank the chosen priority among the candidate issues.
        $chosenOrder = LAYERS[$priorityLayer]['order'] ?? 9;
        foreach ($calc['issues'] as $issue) {
            $order = LAYERS[$issue['layer'] ?? 'Op']['order'] ?? 9;
            if ($order < $chosenOrder && (int) ($issue['exposure'] ?? 1) >= 3) {
                return 'A high-exposure ' . strtolower(LAYERS[$issue['layer']]['name'])
                     . '-layer issue is still on the candidate list: "' . $issue['issue']
                     . '". The four-layer order says it comes first. Say why it does not, or change the priority.';
            }
        }
        return '';
    }

    return 'There ' . (count($openR) === 1 ? 'is 1 unresolved regulatory exposure' : 'are ' . count($openR) . ' unresolved regulatory exposures')
         . ' on this assessment, and the chosen priority sits in the '
         . strtolower(LAYERS[$priorityLayer]['name']) . ' layer. Regulatory comes first because it is the only '
         . 'layer where one problem can end the business overnight. Resolve or refer those, or record why they wait.';
}

// ===========================================================================
// SWOT with the evidence rule (7.1)
// ===========================================================================

const SWOT_QUADRANTS = [
    'strengths'     => 'Strengths',
    'weaknesses'    => 'Weaknesses',
    'opportunities' => 'Opportunities',
    'threats'       => 'Threats',
];

const TOWS_PAIRS = [
    'so' => ['name' => 'SO — strength × opportunity', 'hint' => 'Use what works to capture what is available.'],
    'wo' => ['name' => 'WO — weakness × opportunity', 'hint' => 'Use an opening to fix a gap.'],
    'st' => ['name' => 'ST — strength × threat',      'hint' => 'Use what works to absorb a risk.'],
    'wt' => ['name' => 'WT — weakness × threat',      'hint' => 'Shrink the exposure where both meet.'],
];

/**
 * Tally SWOT entries and flag the ones failing the evidence rule.
 *
 * Every entry needs a number, an example, or a name. An entry without one is
 * an aspiration or an anxiety, and the instruction is to strike it — so the
 * app surfaces the count rather than silently accepting "we work hard".
 */
function calcSwot(array $d): array
{
    $counts = []; $unevidenced = []; $byLayer = array_fill_keys(array_keys(LAYERS), 0);
    $total = 0;

    foreach (array_keys(SWOT_QUADRANTS) as $q) {
        $rows = array_values(array_filter(
            jrows($d, $q),
            fn($r) => trim((string) ($r['entry'] ?? '')) !== ''
        ));
        $counts[$q] = count($rows);
        $total += count($rows);
        foreach ($rows as $r) {
            if (trim((string) ($r['evidence'] ?? '')) === '') {
                $unevidenced[] = ['quadrant' => $q, 'entry' => $r['entry']];
            }
            $layer = $r['layer'] ?? '';
            if (isset($byLayer[$layer])) $byLayer[$layer]++;
        }
    }

    return [
        'counts' => $counts, 'total' => $total,
        'unevidenced' => $unevidenced, 'by_layer' => $byLayer,
    ];
}

// ===========================================================================
// Phase 1 instruments (built 2026-11-11 from "Phase 1 - Instrument Spec.md")
//
// Every threshold below that the framework does not state is a STARTING POINT,
// chosen in the Phase 1 decisions log and labeled as such wherever it shows.
// Change the constant, then the test and the worked-example PDF, together.
// ===========================================================================

/** Starting-point thresholds. One place, so a page and a test can't disagree. */
const TH_ALLOC_WATCH_SHORT  = 0.10;  // short by under 10% of operating need = Watch
const TH_COLL_HEALTHY_PAST60 = 0.10; // share past 60 days under 10% = Healthy
const TH_COLL_WATCH_PAST60   = 0.25; // 10%–25% = Watch; over 25% = Act
const TH_TASK_HEALTHY = 0.60;        // coverage 60%+ = Healthy
const TH_TASK_WATCH   = 0.30;        // 30% to under 60% = Watch; under 30% = Act
const TH_CEILING_WATCH = 0.90;       // current jobs at 90%+ of the ceiling = Watch
const TH_REF_HEALTHY = 0.50;         // named sources 50%+ of new clients = Healthy
const TH_REF_WATCH   = 0.25;         // 25% to under 50% = Watch; under 25% = Act
const TH_CONC_WATCH  = 0.25;         // top client 25%–30% of revenue = Watch
const TH_CONC_ACT    = 0.30;         // over 30% = Act (framework: "roughly 25–30%")
const FIT_IDEAL      = 10;           // fit score 10–12 = Ideal
const FIT_ACCEPTABLE = 7;            // 7–9 = Acceptable; 4–6 = Poor

/** Two decimals of percent for a step value. */
function pct2(float $ratio): string { return pct($ratio, 1); }

// ---------------------------------------------------------------------------
// The Allocation Sequence (Unit 2.1, Financial)
//
// Tax reserve and baseline owner pay come off the top of every deposit on a
// fixed schedule; operating spend has to fit inside what remains. The
// multi-account structure derives from Profit First (Mike Michalowicz).
//
// The gap is never closed by skipping owner pay. That is the whole instrument:
// owner pay is a transfer that happens first, so a shortfall shows up where it
// belongs, in operating, and points at price, collections, or costs.
// ---------------------------------------------------------------------------
function calcAllocation(array $d): array
{
    $deposits90 = num($d['deposits_90'] ?? 0);
    $taxAnnual  = num($d['tax_setaside_annual'] ?? 0);
    $revenue    = num($d['revenue_annual'] ?? 0);
    $baselineMo = num($d['baseline_monthly'] ?? 0);
    $opAnnual   = num($d['operating_annual'] ?? 0);
    $profitPct  = num($d['profit_pct'] ?? 0) / 100.0;   // optional; 0 by default

    $b1 = $deposits90 / 6;                                // two allocations a month × 3 months
    $b2 = $revenue > 0 ? $taxAnnual / $revenue : 0.0;
    $b3 = $b1 * $b2;
    $b4 = $baselineMo / 2;
    $profit = $b1 * $profitPct;
    $b5 = $b1 - $b3 - $b4 - $profit;
    $b6 = $opAnnual / 24;
    $b7 = $b5 - $b6;
    $shortShare = ($b7 < 0 && $b6 > 0) ? -$b7 / $b6 : 0.0;

    if ($deposits90 <= 0 || $b6 <= 0) $reading = READ_UNKNOWN;
    elseif ($b7 >= 0)                 $reading = READ_HEALTHY;
    elseif ($shortShare < TH_ALLOC_WATCH_SHORT) $reading = READ_WATCH;
    else                              $reading = READ_ACT;

    $steps = [
        step('B1  Average deposits per allocation', money($deposits90) . ' ÷ 6', money($b1)),
        step('B2  Tax reserve share of deposits',
             $revenue > 0 ? money($taxAnnual) . ' ÷ ' . money($revenue) : '—', $revenue > 0 ? pct2($b2) : '—'),
        step('B3  Tax transfer per allocation', money($b1) . ' × ' . pct2($b2), money($b3)),
        step('B4  Owner pay transfer per allocation', money($baselineMo) . ' ÷ 2', money($b4)),
    ];
    if ($profitPct > 0) {
        $steps[] = step('Profit transfer per allocation (optional)', money($b1) . ' × ' . pct2($profitPct), money($profit));
        $steps[] = step('B5  Left for operating', money($b1) . ' − ' . money($b3) . ' − ' . money($b4) . ' − ' . money($profit), money($b5));
    } else {
        $steps[] = step('B5  Left for operating', money($b1) . ' − ' . money($b3) . ' − ' . money($b4), money($b5));
    }
    $steps[] = step('B6  Operating need per allocation', money($opAnnual) . ' ÷ 24', money($b6));
    $steps[] = step('B7  Gap', money($b5) . ' − ' . money($b6), money($b7));
    if ($b7 < 0 && $b6 > 0) {
        $steps[] = step('Short, as a share of operating need', money(-$b7) . ' ÷ ' . money($b6), pct2($shortShare));
    }

    return compact('b1', 'b2', 'b3', 'b4', 'profit', 'b5', 'b6', 'b7', 'shortShare', 'reading', 'steps') + [
        'profit_pct' => $profitPct,
        'note' => match ($reading) {
            READ_HEALTHY => 'Taxes and baseline owner pay come off the top, and operating still fits inside what remains.',
            READ_WATCH   => 'Operating runs slightly short once taxes and owner pay come off the top. Watch it, and fix it through price, collections, or costs.',
            READ_ACT     => 'The business cannot fund the owner\'s baseline pay and its taxes from current deposits. The fix is price, collections, or costs, never skipping pay. Go to the EHR Calculator and the Collections Calendar.',
            default      => 'Enter 90 days of deposits and annual operating costs to get a reading.',
        },
    ];
}

// ---------------------------------------------------------------------------
// The Collections Calendar (Unit 2.3, Financial)
//
// Receivables are bucketed by age. Past 60 days is reported on its own and is
// the reading, because that is the money the Health Check already refuses to
// count as available. Days outstanding come from the invoice date and an
// "as of" date when both exist; otherwise from the typed figure.
// ---------------------------------------------------------------------------

const AGING_BUCKETS = [
    'current' => 'Current (0–30 days)',
    'd31_60'  => '31–60 days',
    'd61_90'  => '61–90 days',
    'd90p'    => '90+ days',
];

function agingBucket(float $days): string
{
    if ($days <= 30) return 'current';
    if ($days <= 60) return 'd31_60';
    if ($days <= 90) return 'd61_90';
    return 'd90p';
}

/** Whole days between two Y-m-d dates, or null when either is not a date. */
function daysBetween(string $from, string $to): ?int
{
    $a = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $b = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    if (!$a || !$b) return null;
    return (int) $a->diff($b)->format('%r%a');
}

function calcCollections(array $d): array
{
    $asOf    = trim((string) ($d['as_of'] ?? ''));
    $revenue = num($d['revenue_annual'] ?? 0);
    $terms   = num($d['terms_days'] ?? 0);

    $buckets = array_fill_keys(array_keys(AGING_BUCKETS), 0.0);
    $rows = [];
    foreach (jrows($d, 'invoices') as $r) {
        $amount = num($r['amount'] ?? 0);
        $days   = daysBetween((string) ($r['invoice_date'] ?? ''), $asOf);
        $days   = $days ?? (int) num($r['days'] ?? 0);
        $bucket = agingBucket((float) $days);
        $buckets[$bucket] += $amount;
        $rows[] = $r + ['days_calc' => $days, 'bucket' => $bucket, 'amount_num' => $amount];
    }

    $b1 = array_sum($buckets);
    $daily = $revenue > 0 ? $revenue / 365 : 0.0;
    $b2 = $daily > 0 ? $b1 / $daily : 0.0;
    $past60 = $buckets['d61_90'] + $buckets['d90p'];
    $b3 = $b1 > 0 ? $past60 / $b1 : 0.0;

    if ($b1 <= 0)                          $reading = READ_UNKNOWN;
    elseif ($b3 < TH_COLL_HEALTHY_PAST60)  $reading = READ_HEALTHY;
    elseif ($b3 <= TH_COLL_WATCH_PAST60)   $reading = READ_WATCH;
    else                                   $reading = READ_ACT;

    // The five oldest open accounts: the "this week" list.
    $oldest = $rows;
    usort($oldest, fn($x, $y) => $y['days_calc'] <=> $x['days_calc']);
    $oldest = array_slice($oldest, 0, 5);

    $target = num($d['target_past60'] ?? 0);

    $steps = [];
    foreach (AGING_BUCKETS as $k => $label) $steps[] = step($label, 'sum of open invoices in this bucket', money($buckets[$k]));
    $steps[] = step('B1  Total receivables', 'sum of the four buckets', money($b1));
    $steps[] = step('Average daily revenue', $revenue > 0 ? money($revenue) . ' ÷ 365' : '—', $revenue > 0 ? money($daily) : '—');
    $steps[] = step('B2  Days sales outstanding', $daily > 0 ? money($b1) . ' ÷ ' . money($daily) : '—',
                    $daily > 0 ? number_format($b2, 1) . ' days' : '—');
    if ($terms > 0 && $daily > 0) {
        $steps[] = step('DSO compared with stated terms', number_format($b2, 1) . ' − ' . number_format($terms, 0) . ' days',
                        number_format($b2 - $terms, 1) . ' days ' . ($b2 > $terms ? 'slower than terms' : 'inside terms'));
    }
    $steps[] = step('Past 60 days', money($buckets['d61_90']) . ' + ' . money($buckets['d90p']), money($past60));
    $steps[] = step('B3  Share past 60 days', $b1 > 0 ? money($past60) . ' ÷ ' . money($b1) : '—', $b1 > 0 ? pct2($b3) : '—');

    return [
        'buckets' => $buckets, 'rows' => $rows, 'oldest' => $oldest,
        'b1' => $b1, 'daily' => $daily, 'b2' => $b2, 'past60' => $past60, 'b3' => $b3,
        'terms' => $terms, 'target' => $target, 'reading' => $reading, 'steps' => $steps,
        'note' => match ($reading) {
            READ_HEALTHY => 'Under 10% of receivables is past 60 days.',
            READ_WATCH   => 'Between 10% and 25% of receivables is past 60 days. Work the sequence before it ages further.',
            READ_ACT     => 'More than a quarter of receivables is past 60 days. That money is not available cash, and the sequence starts this week.',
            default      => 'Enter the open invoices to get a reading.',
        },
    ];
}

// ---------------------------------------------------------------------------
// The Modular Task Library (Unit 3.1, Operator)
//
// After Gerber's franchise prototype (The E-Myth Revisited), adapted for solo
// practice. The index is the Capacity Audit's activities sorted by fragility:
// "only me" and not written first, then unwritten, then written; hours
// descending inside each group.
// ---------------------------------------------------------------------------
function calcTaskLibrary(array $d): array
{
    $rows = [];
    $total = 0.0; $written = 0.0; $fragile = 0.0;
    foreach (jrows($d, 'index') as $i => $r) {
        $hrs = num($r['hours'] ?? 0);
        $only = !empty($r['only_me']);
        $wr = !empty($r['written']);
        $total += $hrs;
        if ($wr) $written += $hrs;
        if ($only && !$wr) $fragile += $hrs;
        $group = ($only && !$wr) ? 0 : (!$wr ? 1 : 2);
        $rows[] = $r + ['hours_num' => $hrs, 'group' => $group, 'pos' => $i];
    }
    usort($rows, fn($a, $b) => [$a['group'], -$a['hours_num'], $a['pos']] <=> [$b['group'], -$b['hours_num'], $b['pos']]);

    $coverage = $total > 0 ? $written / $total : 0.0;
    if ($total <= 0)                     $reading = READ_UNKNOWN;
    elseif ($coverage >= TH_TASK_HEALTHY) $reading = READ_HEALTHY;
    elseif ($coverage >= TH_TASK_WATCH)  $reading = READ_WATCH;
    else                                 $reading = READ_ACT;

    $steps = [
        step('Total hours per week', 'sum of the index rows', number_format($total, 1)),
        step('Hours in written entries', 'rows marked Written', number_format($written, 1)),
        step('Coverage', $total > 0 ? number_format($written, 1) . ' ÷ ' . number_format($total, 1) : '—', $total > 0 ? pct2($coverage) : '—'),
        step('"Only me" hours not written down', 'the fragility number', number_format($fragile, 1)),
    ];

    return [
        'rows' => $rows, 'total' => $total, 'written' => $written, 'coverage' => $coverage,
        'fragile' => $fragile, 'entries' => array_values(array_filter(jrows($d, 'entries'),
            fn($e) => trim((string) ($e['name'] ?? '')) !== '')),
        'reading' => $reading, 'steps' => $steps,
        'note' => match ($reading) {
            READ_HEALTHY => 'Most of the week runs on written protocols.',
            READ_WATCH   => 'Some of the week is written down. Keep going, fragile tasks first.',
            READ_ACT     => 'Under 30% of the week is written down. The business lives in the owner\'s head. Write the most fragile task first.',
            default      => 'Add the activities (or prefill them from the Capacity Audit) to get a reading.',
        },
    ];
}

// ---------------------------------------------------------------------------
// The Service Agreement: workload ceiling (Unit 3.2, Operator)
// ---------------------------------------------------------------------------
function calcCeiling(array $d): array
{
    $a1 = num($d['a1_ceiling'] ?? 0);
    $a2 = num($d['a2_non_delivery'] ?? 0);
    $a3 = $a1 - $a2;
    $a4 = num($d['a4_hours_per_job'] ?? 0);
    $a5 = ($a4 > 0 && $a3 > 0) ? (int) floor($a3 / $a4 + 1e-9) : 0;

    $currentJobs  = num($d['current_jobs'] ?? 0);
    $currentHours = num($d['current_delivery_hours'] ?? 0);
    $derived = false;
    if ($currentJobs <= 0 && $currentHours > 0 && $a4 > 0) {
        $currentJobs = $currentHours / $a4;
        $derived = true;
    }

    if ($a5 <= 0 || $currentJobs <= 0)              $reading = READ_UNKNOWN;
    elseif ($currentJobs > $a5)                     $reading = READ_ACT;
    elseif ($currentJobs >= $a5 * TH_CEILING_WATCH) $reading = READ_WATCH;
    else                                            $reading = READ_HEALTHY;

    $steps = [
        step('A1  Sustainable weekly hours', 'Capacity Audit ceiling', number_format($a1, 1)),
        step('A2  Fixed non-delivery hours', 'selling, running the business, fixing', number_format($a2, 1)),
        step('A3  Delivery hours available', number_format($a1, 1) . ' − ' . number_format($a2, 1), number_format($a3, 1)),
        step('A4  Average hours per job', 'including travel and setup', number_format($a4, 2)),
        step('A5  Weekly job ceiling', $a4 > 0 ? number_format($a3, 1) . ' ÷ ' . number_format($a4, 2) . ', rounded down' : '—', $a4 > 0 ? (string) $a5 : '—'),
    ];
    if ($derived) {
        $steps[] = step('Current jobs per week', number_format($currentHours, 1) . ' delivery hrs ÷ ' . number_format($a4, 2), number_format($currentJobs, 1));
    } else {
        $steps[] = step('Current jobs per week', "the owner's count", number_format($currentJobs, 1));
    }
    if ($a5 > 0 && $currentJobs > 0) {
        $steps[] = step('Over (+) or under (−) the ceiling', number_format($currentJobs, 1) . ' − ' . $a5, number_format($currentJobs - $a5, 1));
    }

    return compact('a1', 'a2', 'a3', 'a4', 'a5', 'currentJobs', 'derived', 'reading', 'steps') + [
        'note' => match ($reading) {
            READ_HEALTHY => 'Current volume sits below the ceiling, with room for growth to land.',
            READ_WATCH   => 'Current volume is within 10% of the ceiling. The next good month fills it.',
            READ_ACT     => 'Current volume is over the ceiling. Growth has no room to land; the agreement sets the limit in writing.',
            default      => 'Enter the ceiling, non-delivery hours, average job length, and current volume to get a reading.',
        },
    ];
}

// ---------------------------------------------------------------------------
// The Delegation Decision Matrix (Unit 3.3, Operator)
//
// Weekly value = hours × (owner's floor rate − going rate), for tasks with
// judgment 1 or 2. Judgment-3 work stays with the owner for now and is shown,
// not dropped, so the owner sees why it isn't the pick.
// ---------------------------------------------------------------------------
function calcDelegation(array $d): array
{
    $floor = num($d['floor_rate'] ?? 0);
    $scored = [];
    $kept = [];
    $unscored = [];
    foreach (jrows($d, 'candidates') as $i => $r) {
        if (trim((string) ($r['task'] ?? '')) === '') continue;
        // Not scored until the advisor enters both a judgment and a going rate:
        // a blank rate read as $0 would make every task look like free money.
        if (trim((string) ($r['going_rate'] ?? '')) === '' || trim((string) ($r['judgment'] ?? '')) === '') {
            $unscored[] = $r;
            continue;
        }
        $hrs  = num($r['hours'] ?? 0);
        $rate = num($r['going_rate'] ?? 0);
        $j    = max(1, min(3, (int) num($r['judgment'] ?? 1, 1.0)));
        $row  = $r + ['hours_num' => $hrs, 'rate_num' => $rate, 'judgment_num' => $j, 'pos' => $i];
        if ($j >= 3) {
            $row['value'] = null;
            $kept[] = $row;
            continue;
        }
        $row['value'] = $hrs * ($floor - $rate);
        $scored[] = $row;
    }
    usort($scored, fn($a, $b) => [-$a['value'], $a['pos']] <=> [-$b['value'], $b['pos']]);
    $top = $scored[0] ?? null;

    $steps = [step('B1  Owner\'s floor rate', 'EHR D4', $floor > 0 ? money($floor) : '—')];
    foreach ($scored as $r) {
        $steps[] = step('B2  ' . $r['task'],
            number_format($r['hours_num'], 1) . ' × (' . money($floor) . ' − ' . money($r['rate_num']) . ')',
            money($r['value']) . ' a week' . ($r['value'] < 0 ? ' (costs more than it frees)' : ''));
    }
    foreach ($kept as $r) {
        $steps[] = step($r['task'], 'judgment 3', 'stays with the owner for now');
    }

    $reading = ($floor <= 0 || !$scored) ? READ_UNKNOWN
             : (($top['value'] ?? 0) > 0 ? READ_HEALTHY : READ_WATCH);

    return [
        'floor' => $floor, 'scored' => $scored, 'kept' => $kept, 'unscored' => $unscored, 'top' => $top,
        'reading' => $reading, 'steps' => $steps,
        'note' => $top === null
            ? 'Enter the floor rate and at least one candidate task with judgment 1 or 2.'
            : ($top['value'] > 0
                ? 'First delegation: ' . $top['task'] . ', worth ' . money($top['value']) . ' a week of the owner\'s floor-rate time, unless the owner names a reason.'
                : 'No candidate frees more than it costs at the going rates entered. Recheck the rates, or the floor.'),
    ];
}

// ---------------------------------------------------------------------------
// The Offer, the Client, and the Channel (Unit 4.1, Offer)
// ---------------------------------------------------------------------------

const FIT_CRITERIA = [
    'problem'  => 'The problem matches what the business does best',
    'pays'     => 'Pays at or above the floor',
    'comms'    => 'Communication and expectations',
    'refers'   => 'Refers others',
];

function fitBand(int $score): string
{
    if ($score >= FIT_IDEAL) return 'Ideal';
    if ($score >= FIT_ACCEPTABLE) return 'Acceptable';
    return 'Poor';
}

function calcOffer(array $d): array
{
    $floor = num($d['floor_rate'] ?? 0);

    // Fixed-scope tiers: price ÷ hours to deliver against the floor.
    $tiers = [];
    $tierReadings = [];
    $steps = [step('Floor rate', 'EHR D4', $floor > 0 ? money($floor) : '—')];
    foreach (jrows($d, 'tiers') as $t) {
        if (trim((string) ($t['name'] ?? '')) === '') continue;
        $price = num($t['price'] ?? 0);
        $hrs   = num($t['hours'] ?? 0);
        $rate  = $hrs > 0 ? $price / $hrs : 0.0;
        $r = ($hrs <= 0 || $floor <= 0) ? READ_UNKNOWN : ($rate + 0.005 >= $floor ? READ_HEALTHY : READ_ACT);
        $tiers[] = $t + ['rate' => $rate, 'reading' => $r];
        $tierReadings[] = $r;
        $steps[] = step($t['name'] . ': price per delivery hour',
            $hrs > 0 ? money($price) . ' ÷ ' . number_format($hrs, 2) . ' hrs' : '—',
            $hrs > 0 ? money($rate) . ($floor > 0 ? ($r === READ_HEALTHY ? ' (at or above floor)' : ' (below floor)') : '') : '—');
    }

    // Client alignment: four criteria, 1–3 each. Urgency is a note, never a score.
    $clients = [];
    $bands = ['Ideal' => 0, 'Acceptable' => 0, 'Poor' => 0];
    foreach (jrows($d, 'clients') as $c) {
        if (trim((string) ($c['client'] ?? '')) === '') continue;
        $score = 0;
        foreach (array_keys(FIT_CRITERIA) as $k) $score += max(1, min(3, (int) num($c[$k] ?? 1, 1.0)));
        $band = fitBand($score);
        $bands[$band]++;
        $clients[] = $c + ['score' => $score, 'band' => $band];
    }

    // Customer concentration.
    $topRev = num($d['top_client_revenue'] ?? 0);
    $rev    = num($d['revenue_annual'] ?? 0);
    $conc   = $rev > 0 ? $topRev / $rev : 0.0;
    if ($rev <= 0 || $topRev <= 0)  $concReading = READ_UNKNOWN;
    elseif ($conc > TH_CONC_ACT)    $concReading = READ_ACT;
    elseif ($conc >= TH_CONC_WATCH) $concReading = READ_WATCH;
    else                            $concReading = READ_HEALTHY;
    if ($rev > 0 && $topRev > 0) {
        $steps[] = step('Customer concentration', money($topRev) . ' ÷ ' . money($rev), pct2($conc));
    }

    $awareness = (string) ($d['awareness'] ?? '');
    $channel = match ($awareness) {
        'no'  => 'Workshop-led acquisition (Unit 4.2)',
        'yes' => 'Referral-led acquisition (Unit 4.3)',
        default => '',
    };

    return [
        'floor' => $floor, 'tiers' => $tiers, 'clients' => $clients, 'bands' => $bands,
        'conc' => $conc, 'conc_reading' => $concReading, 'channel' => $channel,
        'reading' => worstReading(array_merge($tierReadings, [$concReading])),
        'steps' => $steps,
    ];
}

// ---------------------------------------------------------------------------
// The Micro-Workshop Framework (Unit 4.2, Offer)
//
// No Healthy/Watch/Act: the spec tracks runs over time rather than judging one,
// and there is no source for a conversion benchmark. Inventing one would break
// the framework's "never invent a benchmark" rule.
// ---------------------------------------------------------------------------
function calcWorkshopRun(array $d): array
{
    $runs = [];
    $tReg = 0.0; $tAtt = 0.0; $tBook = 0.0; $tRev = 0.0;
    $steps = [];
    foreach (jrows($d, 'runs') as $r) {
        $reg  = num($r['registered'] ?? 0);
        $att  = num($r['attended'] ?? 0);
        $book = num($r['bookings'] ?? 0);
        $rev  = num($r['revenue'] ?? 0);
        if ($reg <= 0 && $att <= 0) continue;
        $show = $reg > 0 ? $att / $reg : 0.0;
        $conv = $att > 0 ? $book / $att : 0.0;
        $label = trim((string) ($r['date'] ?? '')) !== '' ? (string) $r['date'] : 'Run ' . (count($runs) + 1);
        $runs[] = $r + ['show' => $show, 'conv' => $conv, 'label' => $label];
        $tReg += $reg; $tAtt += $att; $tBook += $book; $tRev += $rev;
        $steps[] = step($label . ': show rate', number_format($att, 0) . ' ÷ ' . number_format($reg, 0), $reg > 0 ? pct2($show) : '—');
        $steps[] = step($label . ': conversion', number_format($book, 0) . ' ÷ ' . number_format($att, 0), $att > 0 ? pct2($conv) : '—');
    }
    $show = $tReg > 0 ? $tAtt / $tReg : 0.0;
    $conv = $tAtt > 0 ? $tBook / $tAtt : 0.0;
    if ($runs) {
        $steps[] = step('All runs: show rate', number_format($tAtt, 0) . ' ÷ ' . number_format($tReg, 0), pct2($show));
        $steps[] = step('All runs: conversion', number_format($tBook, 0) . ' ÷ ' . number_format($tAtt, 0), pct2($conv));
        $steps[] = step('All runs: revenue from bookings', 'sum', money($tRev));
    }
    return [
        'runs' => $runs, 'registered' => $tReg, 'attended' => $tAtt, 'bookings' => $tBook,
        'revenue' => $tRev, 'show' => $show, 'conv' => $conv,
        'reading' => READ_UNKNOWN, 'steps' => $steps,
    ];
}

// ---------------------------------------------------------------------------
// The Three-Source Referral System (Unit 4.3, Offer)
// ---------------------------------------------------------------------------

const REFERRAL_SOURCE_TYPES = [
    'pro'   => 'Professionals who see the problem first',
    'emp'   => 'Employers, through a concierge benefit',
    'assoc' => 'Trade and community associations',
];

function calcReferralShare(array $d): array
{
    $asOf = trim((string) ($d['as_of'] ?? ''));
    $totalNew = num($d['new_clients_total'] ?? 0);
    $byType = array_fill_keys(array_keys(REFERRAL_SOURCE_TYPES), ['referrals' => 0.0, 'clients' => 0.0]);
    $sources = [];
    $overdue = 0;
    $named = 0.0; $refs = 0.0;
    foreach (jrows($d, 'sources') as $s) {
        if (trim((string) ($s['name'] ?? '')) === '') continue;
        $type = isset($byType[$s['type'] ?? '']) ? $s['type'] : 'pro';
        $r = num($s['referrals'] ?? 0);
        $c = num($s['clients'] ?? 0);
        $byType[$type]['referrals'] += $r;
        $byType[$type]['clients']   += $c;
        $named += $c; $refs += $r;
        $since = daysBetween((string) ($s['last_contact'] ?? ''), $asOf);
        $late = $since !== null && $since > 31;
        if ($late) $overdue++;
        $sources[] = $s + ['type' => $type, 'days_since' => $since, 'overdue' => $late];
    }
    $share = $totalNew > 0 ? $named / $totalNew : 0.0;

    if ($totalNew <= 0)             $reading = READ_UNKNOWN;
    elseif ($share >= TH_REF_HEALTHY) $reading = READ_HEALTHY;
    elseif ($share >= TH_REF_WATCH) $reading = READ_WATCH;
    else                            $reading = READ_ACT;

    $steps = [];
    foreach (REFERRAL_SOURCE_TYPES as $k => $label) {
        $steps[] = step($label, number_format($byType[$k]['referrals'], 0) . ' referrals', number_format($byType[$k]['clients'], 0) . ' new clients');
    }
    $steps[] = step('New clients from named sources', 'sum of the source rows', number_format($named, 0));
    $steps[] = step('New clients this quarter, all sources', "the owner's count", number_format($totalNew, 0));
    $steps[] = step('Share from named sources', $totalNew > 0 ? number_format($named, 0) . ' ÷ ' . number_format($totalNew, 0) : '—', $totalNew > 0 ? pct2($share) : '—');
    if ($asOf !== '') {
        $steps[] = step('Sources overdue for a monthly touch', 'last contact more than 31 days before ' . $asOf, (string) $overdue);
    }

    return [
        'sources' => $sources, 'by_type' => $byType, 'named' => $named, 'referrals' => $refs,
        'total_new' => $totalNew, 'share' => $share, 'overdue' => $overdue,
        'reading' => $reading, 'steps' => $steps,
        'note' => match ($reading) {
            READ_HEALTHY => 'Named sources produce half or more of new clients.',
            READ_WATCH   => 'Named sources produce between a quarter and half of new clients. The ask and the thank-you are where to work.',
            READ_ACT     => 'Under a quarter of new clients come from named sources. The business is waiting to be found.',
            default      => 'Enter new clients this quarter and the source rows to get a reading.',
        },
    ];
}
