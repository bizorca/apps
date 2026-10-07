<?php
declare(strict_types=1);

/**
 * Regression test for the calculation engine.
 *
 *   php tests/calc_test.php
 *
 * Every expected figure below is taken from the worked case studies in the
 * source framework document, which states that all figures agree across
 * instruments. That makes them the only independent check available on this
 * arithmetic — and seven of them are numbers the document records an AI
 * getting wrong, with confidence, on this same data. If a change here breaks
 * one of those, the change is wrong.
 */

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/calc.php';

$fail = 0;
function check(string $what, $got, $want, float $tol = 0.01) {
    global $fail;
    $ok = is_string($want) ? ($got === $want) : (abs((float)$got - (float)$want) <= $tol);
    if (!$ok) { $fail++; printf("  FAIL  %-42s got %s, want %s\n", $what, var_export($got, true), var_export($want, true)); }
    else      { printf("  ok    %-42s %s\n", $what, is_float($got) ? number_format($got, 2) : var_export($got, true)); }
}

echo "\n=== Case 1: Salal Pump & Septic — Health Check ===\n";
$hc = calcHealthCheck([
    'm1_cash_in' => 38500, 'm1_cash_out' => 41200,
    'm1_owner_draws' => 15000, 'm1_baseline_monthly' => 4500,
    'm2_insurance' => 540, 'm2_vehicle' => 650, 'm2_phone' => 290,
    'm2_rent' => 0, 'm2_other' => 125, 'm2_owner_pay' => 4500,
    'm2_cash_on_hand' => 9800,
    'm3_cash' => 9800, 'm3_ar_30' => 6200, 'm3_bills_due' => 11905, 'm3_ar_past_60' => 8300,
]);
check('M1 net 90-day cash flow', $hc['m1']['net'], -2700);
check('M1 reading',              $hc['m1']['reading'], READ_ACT);
check('M1 baseline owed (90d)',  $hc['m1']['baseline_90'], 13500);
check('M1 owner pay drawn in full', $hc['m1']['paid_in_full'] ? 'Yes' : 'No', 'Yes');
check('M2 monthly burn',         $hc['m2']['burn'], 6105);
check('M2 runway (months)',      $hc['m2']['runway'], 1.605, 0.005);
check('M2 reading',              $hc['m2']['reading'], READ_WATCH);
check('M3 available',            $hc['m3']['available'], 16000);
check('M3 liquidity ratio',      $hc['m3']['ratio'], 1.34, 0.005);
check('M3 reading',              $hc['m3']['reading'], READ_WATCH);
check('Overall (worst of three)', $hc['overall'], READ_ACT);

echo "\n=== Case 1: Salal Pump & Septic — EHR ===\n";
$e = calcEHR([
    'a1_revenue' => 180000, 'a2_operating_costs' => 95000, 'a4_tax_reserve_rate' => 22,
    'b1_billable' => 32, 'b2_travel' => 8, 'b3_quoting' => 6, 'b4_admin' => 4,
    'b5_equipment' => 3, 'b6_afterhours' => 2, 'b7_other' => 0, 'b9_weeks' => 50,
    'c2_quoted_rate' => 125, 'd1_target_takehome' => 80000,
]);
check('A3 net profit',        $e['a3'], 85000);
check('A5 tax set-aside',     $e['a5'], 18700);
check('A6 real take-home',    $e['a6'], 66300);
check('B8 hours per week',    $e['b8'], 55);
check('B10 annual hours',     $e['b10'], 2750);
check('B11 billable hours',   $e['b11'], 1600);
check('C1 effective hourly rate', $e['c1'], 24.11, 0.005);
check('C3 EHR as share of quote',  $e['c3'], 0.193, 0.001);
check('C4 revenue per billable hr', $e['c4'], 112.50);
check('Unbilled share of week',     $e['unbilledShare'], 23/55, 0.001);
check('D2 target net profit', $e['d2'], 102564.10, 0.5);
check('D3 required revenue',  $e['d3'], 197564.10, 0.5);
check('D4 floor rate',        $e['d4'], 123.48, 0.01);

echo "\n=== Case 1: Salal Pump & Septic — Capacity Audit ===\n";
$c = calcCapacity([
    'ceiling' => 45,
    'rows' => [
        ['activity'=>'Pumping and inspection','hours'=>24,'type'=>'D','only_me'=>0,'written'=>0],
        ['activity'=>'Diagnostics',           'hours'=>8, 'type'=>'D','only_me'=>1,'written'=>0],
        ['activity'=>'Driving',               'hours'=>8, 'type'=>'D','only_me'=>0,'written'=>0],
        ['activity'=>'Calls and quoting',     'hours'=>6, 'type'=>'S','only_me'=>1,'written'=>0],
        ['activity'=>'Invoicing',             'hours'=>4, 'type'=>'R','only_me'=>1,'written'=>1],
        ['activity'=>'Truck upkeep and parts','hours'=>3, 'type'=>'R','only_me'=>0,'written'=>0],
        ['activity'=>'After-hours calls',     'hours'=>2, 'type'=>'F','only_me'=>1,'written'=>0],
    ],
]);
check('Total hours per week',      $c['total'], 55);
check('"Only me" hours',           $c['only_me'], 20);
check('Hours over ceiling',        $c['over_ceiling'], 10);
check('"Only me" not written down', $c['only_me_unwritten'], 16);

echo "\n=== Priority quadrant sort ===\n";
check('exposure 3 / readiness 1 → advisor_work', quadrantFor(3,1), 'advisor_work');
check('exposure 3 / readiness 3 → do_now',       quadrantFor(3,3), 'do_now');
check('exposure 1 / readiness 1 → park',         quadrantFor(1,1), 'park');
check('exposure 2 / readiness 3 → quick_win',    quadrantFor(2,3), 'quick_win');
check('exposure 2 counts as low',                quadrantFor(2,1), 'park');

echo "\n=== Watch band: positive cash only because owner pay was skipped ===\n";
$w = calcCashFlow(['m1_cash_in'=>38500,'m1_cash_out'=>36000,'m1_owner_draws'=>6000,'m1_baseline_monthly'=>4500]);
check('net positive',        $w['net'], 2500);
check('reading is Watch',    $w['reading'], READ_WATCH);
check('shortfall flagged',   $w['shortfall'], 7500);

echo "\n=== Money parsing off a bank statement ===\n";
check('"$38,500.00"', num('$38,500.00'), 38500);
check('"(2,700)"',    num('(2,700)'),    -2700);
check('empty string', num(''),           0);

echo $fail === 0 ? "\nAll checks passed.\n\n" : "\n$fail CHECK(S) FAILED\n\n";
exit($fail === 0 ? 0 : 1);
