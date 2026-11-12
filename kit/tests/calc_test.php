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

// ===========================================================================
// Phase 1 instruments (2026-11-11). Expected figures come from the worked
// examples in "Phase 1 - Instrument Spec.md". Figures the framework already
// states are reused exactly; figures marked † there are new fiction chosen to
// agree with them (see "Phase 1 - Decisions Log.md").
// ===========================================================================

echo "\n=== Salal — Allocation Sequence (2.1) ===\n";
$al = calcAllocation([
    'deposits_90' => 38500, 'tax_setaside_annual' => 18700, 'revenue_annual' => 180000,
    'baseline_monthly' => 4500, 'operating_annual' => 95000, 'profit_pct' => 0,
]);
check('B1 deposits per allocation', $al['b1'], 6416.67, 0.005);
check('B2 tax share of deposits',   $al['b2'], 0.1039, 0.0001);
check('B3 tax transfer',            $al['b3'], 666.62, 0.01);
check('B4 owner pay transfer',      $al['b4'], 2250);
check('B5 left for operating',      $al['b5'], 3500, 0.10);
check('B6 operating need',          $al['b6'], 3958.33, 0.005);
check('B7 gap',                     $al['b7'], -458.29, 0.10);
check('short share of B6',          $al['shortShare'], 0.116, 0.001);
check('reading',                    $al['reading'], READ_ACT);
$alw = calcAllocation(['deposits_90' => 38500, 'tax_setaside_annual' => 18700, 'revenue_annual' => 180000,
    'baseline_monthly' => 4500, 'operating_annual' => 86400]);   // B6 3,600: short 2.8%
check('short under 10% reads Watch', $alw['reading'], READ_WATCH);
$alp = calcAllocation(['deposits_90' => 38500, 'tax_setaside_annual' => 18700, 'revenue_annual' => 180000,
    'baseline_monthly' => 4500, 'operating_annual' => 95000, 'profit_pct' => 1]);
check('optional profit comes off before operating', $alp['b5'], 3500.05 - 64.17, 0.02);

echo "\n=== Salal — Collections Calendar (2.3) ===\n";
$inv = [
    ['client'=>'Homeowner, Sequim',            'amount'=>1850, 'days'=>12],
    ['client'=>'Pre-sale inspection, Port Angeles','amount'=>1400, 'days'=>9],
    ['client'=>'Pump diagnostics, Joyce',      'amount'=>1650, 'days'=>21],
    ['client'=>'Homeowner, Carlsborg',         'amount'=>1300, 'days'=>4],
    ['client'=>'Rental duplex, Port Angeles',  'amount'=>2100, 'days'=>74],
    ['client'=>'Homeowner, Agnew',             'amount'=>1450, 'days'=>68],
    ['client'=>'Homeowner, Sequim (2)',        'amount'=>1900, 'days'=>131],
    ['client'=>'Pump replacement, Joyce',      'amount'=>1650, 'days'=>104],
    ['client'=>'Cabin, Lake Sutherland',       'amount'=>1200, 'days'=>96],
];
$co = calcCollections(['invoices' => $inv, 'revenue_annual' => 180000, 'terms_days' => 15, 'target_past60' => 3000]);
check('current bucket (due in 30 days)', $co['buckets']['current'], 6200);
check('31–60 bucket',               $co['buckets']['d31_60'], 0);
check('past 60 days',               $co['past60'], 8300);
check('B1 total receivables',       $co['b1'], 14500);
check('average daily revenue',      $co['daily'], 493.15, 0.01);
check('B2 DSO (days)',              $co['b2'], 29.4, 0.05);
check('B3 share past 60',           $co['b3'], 0.572, 0.001);
check('reading',                    $co['reading'], READ_ACT);
check('five oldest: first is 131 days', $co['oldest'][0]['days_calc'], 131);
check('five oldest are the past-60 rows', array_sum(array_column($co['oldest'], 'amount_num')), 8300);
$cod = calcCollections(['as_of' => '2026-11-11', 'revenue_annual' => 180000,
    'invoices' => [['client'=>'x','amount'=>1000,'invoice_date'=>'2026-09-01','days'=>5]]]);
check('days from dates override typed days', $cod['rows'][0]['days_calc'], 71);
check('boundary: exactly 25% past 60 is Watch', calcCollections(['invoices'=>[
    ['amount'=>750,'days'=>10],['amount'=>250,'days'=>61]]])['reading'], READ_WATCH);

echo "\n=== Salal — Modular Task Library (3.1) ===\n";
$tl = calcTaskLibrary(['index' => [
    ['task'=>'Pumping and inspection','hours'=>24,'type'=>'D','only_me'=>0,'written'=>0],
    ['task'=>'Diagnostics',           'hours'=>8, 'type'=>'D','only_me'=>1,'written'=>0],
    ['task'=>'Driving',               'hours'=>8, 'type'=>'D','only_me'=>0,'written'=>0],
    ['task'=>'Calls and quoting',     'hours'=>6, 'type'=>'S','only_me'=>1,'written'=>0],
    ['task'=>'Invoicing',             'hours'=>4, 'type'=>'R','only_me'=>1,'written'=>1],
    ['task'=>'Truck upkeep and parts','hours'=>3, 'type'=>'R','only_me'=>0,'written'=>0],
    ['task'=>'After-hours calls',     'hours'=>2, 'type'=>'F','only_me'=>1,'written'=>0],
]]);
check('total hours',                $tl['total'], 55);
check('written hours',              $tl['written'], 4);
check('coverage',                   $tl['coverage'], 0.0727, 0.0005);
check('only-me not written (fragility)', $tl['fragile'], 16);
check('reading',                    $tl['reading'], READ_ACT);
check('index sorted: most fragile first', $tl['rows'][0]['task'], 'Diagnostics');
check('index sorted: written last', $tl['rows'][6]['task'], 'Invoicing');

echo "\n=== Salal — Service Agreement ceiling (3.2) ===\n";
$ce = calcCeiling(['a1_ceiling' => 45, 'a2_non_delivery' => 15, 'a4_hours_per_job' => 2.5,
                   'current_delivery_hours' => 40]);
check('A3 delivery hours available', $ce['a3'], 30);
check('A5 weekly job ceiling',      $ce['a5'], 12);
check('current jobs (40 ÷ 2.5)',    $ce['currentJobs'], 16);
check('reading',                    $ce['reading'], READ_ACT);
check('at the ceiling reads Watch', calcCeiling(['a1_ceiling'=>45,'a2_non_delivery'=>15,'a4_hours_per_job'=>2.5,'current_jobs'=>12])['reading'], READ_WATCH);
check('under 90% reads Healthy',    calcCeiling(['a1_ceiling'=>45,'a2_non_delivery'=>15,'a4_hours_per_job'=>2.5,'current_jobs'=>10])['reading'], READ_HEALTHY);

echo "\n=== Salal — Delegation Decision Matrix (3.3) ===\n";
$de = calcDelegation(['floor_rate' => 123.48, 'candidates' => [
    ['task'=>'Calls and quoting',          'hours'=>6, 'judgment'=>3, 'written'=>0, 'going_rate'=>25],
    ['task'=>'Truck upkeep and parts runs','hours'=>3, 'judgment'=>2, 'written'=>0, 'going_rate'=>95],
    ['task'=>'Invoicing and receivables follow-up','hours'=>4, 'judgment'=>1, 'written'=>1, 'going_rate'=>35],
]]);
check('top pick',                   $de['top']['task'], 'Invoicing and receivables follow-up');
check('invoicing weekly value',     $de['top']['value'], 353.92, 0.005);
check('truck upkeep weekly value',  $de['scored'][1]['value'], 85.44, 0.005);
check('judgment 3 stays with owner', $de['kept'][0]['task'], 'Calls and quoting');
$deu = calcDelegation(['floor_rate' => 123.48, 'candidates' => [['task'=>'Driving','hours'=>8,'judgment'=>'','going_rate'=>'']]]);
check('blank rate or judgment is not scored', count($deu['unscored']) . '/' . count($deu['scored']), '1/0');
check('nothing scored gives no reading', $deu['reading'], READ_UNKNOWN);

echo "\n=== Salal — The Offer, the Client, and the Channel (4.1) ===\n";
$of = calcOffer([
    'awareness' => 'yes', 'floor_rate' => 123.48,
    'revenue_annual' => 180000, 'top_client_revenue' => 14400,
    'tiers' => [
        ['name'=>'Pre-sale inspection package','price'=>450,'hours'=>3.5],
        ['name'=>'Annual maintenance plan',    'price'=>390,'hours'=>3],
    ],
    'clients' => [
        ['client'=>'Agent-referred pre-sale inspections','problem'=>3,'pays'=>3,'comms'=>2,'refers'=>3],
        ['client'=>'Property manager, rentals',          'problem'=>3,'pays'=>2,'comms'=>3,'refers'=>2],
        ['client'=>'One-time letter-surge homeowner',    'problem'=>2,'pays'=>1,'comms'=>2,'refers'=>1],
    ],
]);
check('channel',                    $of['channel'], 'Referral-led acquisition (Unit 4.3)');
check('inspection package rate',    $of['tiers'][0]['rate'], 128.57, 0.005);
check('inspection package reading', $of['tiers'][0]['reading'], READ_HEALTHY);
check('maintenance plan rate',      $of['tiers'][1]['rate'], 130.00);
check('concentration',              $of['conc'], 0.08);
check('concentration reading',      $of['conc_reading'], READ_HEALTHY);
check('fit: score 11 is Ideal',     $of['clients'][0]['band'], 'Ideal');
check('fit: score 6 is Poor',       $of['clients'][2]['band'], 'Poor');
check('overall reading',            $of['reading'], READ_HEALTHY);
check('tier below floor reads Act', calcOffer(['floor_rate'=>123.48,'tiers'=>[['name'=>'x','price'=>400,'hours'=>3.5]]])['tiers'][0]['reading'], READ_ACT);

echo "\n=== Driftwood — Micro-Workshop Framework (4.2) ===\n";
$ws = calcWorkshopRun(['runs' => [
    ['date'=>'Run 1','registered'=>14,'attended'=>9, 'bookings'=>2,'revenue'=>520],
    ['date'=>'Run 2','registered'=>18,'attended'=>12,'bookings'=>3,'revenue'=>780],
    ['date'=>'Run 3','registered'=>16,'attended'=>11,'bookings'=>3,'revenue'=>780],
]]);
check('run 1 show rate',            $ws['runs'][0]['show'], 0.643, 0.001);
check('run 1 conversion',           $ws['runs'][0]['conv'], 0.222, 0.001);
check('all runs show rate',         $ws['show'], 32/48, 0.001);
check('all runs conversion',        $ws['conv'], 0.25);
check('all runs revenue',           $ws['revenue'], 2080);
check('no reading by design',       $ws['reading'], READ_UNKNOWN);

echo "\n=== Salal — Three-Source Referral System (4.3) ===\n";
$rf = calcReferralShare(['new_clients_total' => 120, 'as_of' => '2026-11-11', 'sources' => [
    ['type'=>'pro',  'name'=>'Real estate agent 1', 'referrals'=>16, 'clients'=>14, 'last_contact'=>'2026-10-28'],
    ['type'=>'pro',  'name'=>'Real estate agent 2', 'referrals'=>10, 'clients'=>9,  'last_contact'=>'2026-10-28'],
    ['type'=>'pro',  'name'=>'Real estate agent 3', 'referrals'=>7,  'clients'=>6,  'last_contact'=>'2026-09-15'],
    ['type'=>'pro',  'name'=>'Home inspectors',     'referrals'=>0,  'clients'=>0,  'last_contact'=>''],
    ['type'=>'assoc','name'=>"County inspector list",'referrals'=>18, 'clients'=>18, 'last_contact'=>'2026-08-01'],
]]);
check('named-source clients',       $rf['named'], 47);
check('share from named sources',   $rf['share'], 0.392, 0.001);
check('reading',                    $rf['reading'], READ_WATCH);
check('sources overdue for a touch', $rf['overdue'], 2);
check('boundary: exactly 50% is Healthy', calcReferralShare(['new_clients_total'=>10,'sources'=>[['name'=>'a','clients'=>5]]])['reading'], READ_HEALTHY);

echo $fail === 0 ? "\nAll checks passed.\n\n" : "\n$fail CHECK(S) FAILED\n\n";
exit($fail === 0 ? 0 : 1);
