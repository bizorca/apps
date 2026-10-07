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
