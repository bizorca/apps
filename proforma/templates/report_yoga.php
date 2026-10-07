<?php
/**
 * Yoga/Gym/Dojo report template.
 * Expected in scope: $r (calculator report array), $biz, $actuals (array), $isShared (bool)
 */
$isShared = $isShared ?? false;
$actuals  = $actuals  ?? [];
$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>

<!-- Report header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900"><?= h($biz['business_name']) ?></h1>
        <p class="text-sm text-gray-500">Pro Forma Report &mdash; as of <?= date('F j, Y') ?></p>
    </div>
    <div class="flex items-center gap-3">
        <?php if (!$isShared): ?>
        <div class="no-print" id="share-area">
            <?php if (!empty($biz['share_token'])): ?>
            <div class="flex items-center gap-2">
                <input type="text" id="share-link-input" readonly
                       value="<?= h(APP_URL . '/share.php?token=' . $biz['share_token']) ?>"
                       class="text-xs border border-gray-200 rounded px-2 py-1.5 w-72 text-gray-600 bg-gray-50">
                <button onclick="copyShareLink()"
                        class="text-xs text-indigo-600 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors">
                    Copy
                </button>
                <form method="post" action="<?= PF_BASE ?>/wizard/report.php" class="inline">
                    <?= csrf() ?>
                    <input type="hidden" name="share_action" value="revoke">
                    <button type="submit" class="text-xs text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                        Revoke
                    </button>
                </form>
            </div>
            <script>
            function copyShareLink() {
                const input = document.getElementById('share-link-input');
                navigator.clipboard.writeText(input.value).then(() => {
                    const btn = input.nextElementSibling;
                    btn.textContent = 'Copied!';
                    setTimeout(() => btn.textContent = 'Copy', 2000);
                });
            }
            </script>
            <?php else: ?>
            <form method="post" action="<?= PF_BASE ?>/wizard/report.php">
                <?= csrf() ?>
                <input type="hidden" name="share_action" value="generate">
                <button type="submit"
                        class="text-sm text-indigo-600 border border-indigo-200 px-4 py-2 rounded-lg hover:bg-indigo-50 transition-colors">
                    Share report
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <button onclick="downloadCsv()" class="no-print text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">
            Download CSV
        </button>
        <button onclick="window.print()" class="no-print text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">
            Print / Save PDF
        </button>
    </div>
</div>

<!-- ============================================================
     ACTION FLAGS
============================================================ -->
<?php if (!empty($r['action_flags'])): ?>
<div class="mb-6 space-y-2">
    <?php foreach ($r['action_flags'] as $flag): ?>
    <div class="flex items-start gap-2 px-4 py-3 rounded-lg text-sm
        <?= $flag['type'] === 'danger' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-amber-50 text-amber-800 border border-amber-200' ?>">
        <span class="font-bold flex-shrink-0"><?= $flag['type'] === 'danger' ? '&#9888;' : '&#9432;' ?></span>
        <?= h($flag['message']) ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     WHAT-IF SCENARIO PLANNER
============================================================ -->
<?php
$wiDropInRev    = 0.0;
$wiClassPassRev = 0.0;
$wiSubRev       = 0.0;
$wiOtherRev     = 0.0;
foreach ($r['revenue_by_stream'] as $s) {
    switch ($s['stream_type']) {
        case 'drop_in':      $wiDropInRev    += (float)$s['revenue']; break;
        case 'class_pass':   $wiClassPassRev += (float)$s['revenue']; break;
        case 'subscription': $wiSubRev       += (float)$s['revenue']; break;
        default:             $wiOtherRev     += (float)$s['revenue']; break;
    }
}
$wiCurrentFill   = max(0.01, (float)$r['current_fill_rate']);
$wiOperatingCost = (float)$r['operating_cost'];
$wiOwnerSalary   = (float)$r['owner_salary_monthly'];
?>
<div class="no-print bg-indigo-50 border border-indigo-200 rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-indigo-900">What-If Scenario Planner</h2>
            <p class="text-xs text-indigo-500 mt-0.5">Drag sliders to see live P&amp;L impact. Your saved data is unchanged.</p>
        </div>
        <button onclick="resetWhatIf()"
                class="text-xs text-indigo-600 border border-indigo-300 bg-white px-3 py-1.5 rounded-lg hover:bg-indigo-100 transition-colors">
            Reset
        </button>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-5">
        <div>
            <div class="flex justify-between text-sm font-medium text-indigo-800 mb-1">
                <span>Fill Rate</span>
                <span id="wi-fill-val"><?= number_format($wiCurrentFill * 100, 0) ?>%</span>
            </div>
            <input type="range" id="wi-fill" min="5" max="100" step="1"
                   value="<?= number_format($wiCurrentFill * 100, 0) ?>"
                   oninput="updateWhatIf()"
                   class="w-full accent-indigo-600">
            <div class="flex justify-between text-xs text-indigo-400 mt-1">
                <span>5%</span>
                <span class="text-indigo-600 font-medium">Current: <?= number_format($wiCurrentFill * 100, 0) ?>%</span>
                <span>100%</span>
            </div>
        </div>

        <div>
            <div class="flex justify-between text-sm font-medium text-indigo-800 mb-1">
                <span>Drop-In Price</span>
                <span id="wi-dropin-val">+0%</span>
            </div>
            <input type="range" id="wi-dropin" min="-30" max="50" step="5" value="0"
                   oninput="updateWhatIf()"
                   class="w-full accent-indigo-600">
            <div class="flex justify-between text-xs text-indigo-400 mt-1">
                <span>-30%</span>
                <span class="text-indigo-600 font-medium">Base: <?= money($wiDropInRev) ?>/mo</span>
                <span>+50%</span>
            </div>
        </div>

        <div>
            <div class="flex justify-between text-sm font-medium text-indigo-800 mb-1">
                <span>Subscription Price</span>
                <span id="wi-sub-val">+0%</span>
            </div>
            <input type="range" id="wi-sub" min="-30" max="50" step="5" value="0"
                   oninput="updateWhatIf()"
                   class="w-full accent-indigo-600">
            <div class="flex justify-between text-xs text-indigo-400 mt-1">
                <span>-30%</span>
                <span class="text-indigo-600 font-medium">Base: <?= money($wiSubRev) ?>/mo</span>
                <span>+50%</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="text-center p-3 bg-white border border-indigo-100 rounded-lg">
            <div id="wi-gross" class="text-xl font-bold text-gray-800"><?= money($r['gross_revenue']) ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Gross Revenue</div>
        </div>
        <div class="text-center p-3 bg-white border border-indigo-100 rounded-lg">
            <div id="wi-net-before" class="text-xl font-bold <?= $r['net_before_owner'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= money($r['net_before_owner'], true) ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Net Before Owner Draw</div>
        </div>
        <div class="text-center p-3 bg-white border border-indigo-100 rounded-lg">
            <div id="wi-net" class="text-xl font-bold <?= $r['net_income'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= money($r['net_income'], true) ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Net After Owner Draw</div>
        </div>
    </div>
</div>

<script>
const wiBaseFill      = <?= json_encode($wiCurrentFill) ?>;
const wiBaseDropIn    = <?= json_encode($wiDropInRev) ?>;
const wiBaseClassPass = <?= json_encode($wiClassPassRev) ?>;
const wiBaseSub       = <?= json_encode($wiSubRev) ?>;
const wiBaseOther     = <?= json_encode($wiOtherRev) ?>;
const wiOpCost        = <?= json_encode($wiOperatingCost) ?>;
const wiOwnerSalary   = <?= json_encode($wiOwnerSalary) ?>;

function fmtMoney(n, signed = false) {
    const abs = Math.abs(n);
    const str = '$' + abs.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (signed && n < 0) return '-' + str;
    if (signed && n > 0) return '+' + str;
    return (n < 0 ? '-' : '') + str;
}

function updateWhatIf() {
    const newFill   = parseFloat(document.getElementById('wi-fill').value) / 100;
    const dropInPct = parseFloat(document.getElementById('wi-dropin').value);
    const subPct    = parseFloat(document.getElementById('wi-sub').value);

    document.getElementById('wi-fill-val').textContent   = Math.round(newFill * 100) + '%';
    document.getElementById('wi-dropin-val').textContent = (dropInPct >= 0 ? '+' : '') + dropInPct + '%';
    document.getElementById('wi-sub-val').textContent    = (subPct >= 0 ? '+' : '') + subPct + '%';

    const fillMult   = wiBaseFill > 0 ? newFill / wiBaseFill : 1;
    const dropInMult = (100 + dropInPct) / 100;
    const subMult    = (100 + subPct) / 100;

    const gross     = (wiBaseDropIn * fillMult * dropInMult)
                    + (wiBaseClassPass * fillMult)
                    + (wiBaseSub * subMult)
                    + wiBaseOther;
    const netBefore = gross - wiOpCost;
    const net       = netBefore - wiOwnerSalary;

    document.getElementById('wi-gross').textContent = fmtMoney(gross);

    const nbEl = document.getElementById('wi-net-before');
    nbEl.textContent = fmtMoney(netBefore, true);
    nbEl.className   = 'text-xl font-bold ' + (netBefore >= 0 ? 'text-green-600' : 'text-red-600');

    const netEl = document.getElementById('wi-net');
    netEl.textContent = fmtMoney(net, true);
    netEl.className   = 'text-xl font-bold ' + (net >= 0 ? 'text-green-600' : 'text-red-600');
}

function resetWhatIf() {
    document.getElementById('wi-fill').value   = Math.round(wiBaseFill * 100);
    document.getElementById('wi-dropin').value = 0;
    document.getElementById('wi-sub').value    = 0;
    updateWhatIf();
}
</script>

<script>
const reportData = <?= json_encode([
    'business_name'        => $biz['business_name'],
    'revenue_by_stream'    => $r['revenue_by_stream'],
    'expenses'             => $r['expenses'],
    'instructor_cost'      => $r['instructor_cost'],
    'operating_cost'       => $r['operating_cost'],
    'net_before_owner'     => $r['net_before_owner'],
    'owner_salary_monthly' => $r['owner_salary_monthly'],
    'net_income'           => $r['net_income'],
    'gross_revenue'        => $r['gross_revenue'],
    'break_even_fill_rate' => $r['break_even_fill_rate'],
    'current_fill_rate'    => $r['current_fill_rate'],
]) ?>;

function downloadCsv() {
    const d = reportData;
    const fmt = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const row = (...cols) => cols.map(fmt).join(',');
    const lines = [
        row('ProForma Report', d.business_name),
        row('Generated', new Date().toLocaleDateString()),
        row(''),
        row('MONTHLY P&L SUMMARY'),
        row('Category', 'Monthly ($)'),
        row('-- Revenue --', ''),
        ...d.revenue_by_stream.map(s => row(s.label, s.revenue.toFixed(2))),
        row('Gross Revenue', d.gross_revenue.toFixed(2)),
        row(''),
        row('-- Operating Costs --', ''),
        ...d.expenses.map(e => row(e.label, e.amount_monthly.toFixed(2))),
        row('Instructors', d.instructor_cost.toFixed(2)),
        row('Total Operating Costs', d.operating_cost.toFixed(2)),
        row(''),
        row('Net Before Owner Draw', d.net_before_owner.toFixed(2)),
        row("Owner's Draw", d.owner_salary_monthly.toFixed(2)),
        row('Net Income', d.net_income.toFixed(2)),
        row(''),
        row('ANNUAL PROJECTIONS'),
        row('Annual Gross Revenue', (d.gross_revenue * 12).toFixed(2)),
        row('Annual Net Income', (d.net_income * 12).toFixed(2)),
        row(''),
        row('BREAK-EVEN'),
        row('Break-even Fill Rate', (d.break_even_fill_rate * 100).toFixed(1) + '%'),
        row('Current Fill Rate', (d.current_fill_rate * 100).toFixed(1) + '%'),
    ];
    const csv  = lines.join('\n');
    const blob = new Blob([csv], {type: 'text/csv'});
    const a    = Object.assign(document.createElement('a'), {
        href:     URL.createObjectURL(blob),
        download: d.business_name.replace(/[^a-z0-9]/gi, '_') + '_proforma.csv',
    });
    a.click();
    URL.revokeObjectURL(a.href);
}
</script>

<!-- ============================================================
     SECTION 1: MONTHLY P&L SUMMARY
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Monthly P&L Summary</h2>

    <div class="grid grid-cols-2 gap-6">
        <div>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Revenue</h3>
            <?php foreach ($r['revenue_by_stream'] as $stream): ?>
            <div class="flex justify-between text-sm py-1 border-b border-gray-50">
                <span class="text-gray-700"><?= h($stream['label']) ?></span>
                <span class="font-medium"><?= money($stream['revenue']) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between text-sm font-bold py-2 mt-1 border-t border-gray-200">
                <span>Gross Revenue</span>
                <span class="text-green-700"><?= money($r['gross_revenue']) ?></span>
            </div>
        </div>

        <div>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Operating Costs</h3>
            <?php foreach ($r['expenses'] as $exp): ?>
            <div class="flex justify-between text-sm py-1 border-b border-gray-50">
                <span class="text-gray-700"><?= h($exp['label']) ?><?= $exp['is_variable'] ? ' <span class="text-xs text-gray-400">(var)</span>' : '' ?></span>
                <span><?= money($exp['amount_monthly']) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between text-sm py-1 border-b border-gray-50">
                <span class="text-gray-700">Instructors</span>
                <span><?= money($r['instructor_cost']) ?></span>
            </div>
            <div class="flex justify-between text-sm font-bold py-2 mt-1 border-t border-gray-200">
                <span>Total Operating Costs</span>
                <span class="text-gray-800"><?= money($r['operating_cost']) ?></span>
            </div>
        </div>
    </div>

    <div class="mt-4 p-4 rounded-xl text-center <?= $r['net_before_owner'] >= 0 ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?>">
        <div class="text-xs font-semibold uppercase tracking-wide <?= $r['net_before_owner'] >= 0 ? 'text-green-600' : 'text-red-600' ?> mb-1">
            Net Operating Income (before owner draw)
        </div>
        <div class="text-3xl font-bold <?= $r['net_before_owner'] >= 0 ? 'net-positive' : 'net-negative' ?>">
            <?= money($r['net_before_owner'], true) ?>
        </div>
    </div>

    <div class="mt-3 px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-xl flex items-center justify-between">
        <div>
            <div class="text-sm font-semibold text-indigo-800">Owner's Draw</div>
            <div class="text-xs text-indigo-500 mt-0.5">
                <?= money($r['owner_salary_monthly'] * 12) ?>/year target &mdash; drawn monthly from net income
            </div>
        </div>
        <div class="text-xl font-bold text-indigo-700"><?= money($r['owner_salary_monthly']) ?>/mo</div>
    </div>

    <div class="mt-3 p-4 rounded-xl text-center <?= $r['net_income'] >= 0 ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?>">
        <div class="text-xs font-semibold uppercase tracking-wide <?= $r['net_income'] >= 0 ? 'text-green-600' : 'text-red-600' ?> mb-1">
            Net Income After Owner Draw
        </div>
        <div class="text-3xl font-bold <?= $r['net_income'] >= 0 ? 'net-positive' : 'net-negative' ?>">
            <?= money($r['net_income'], true) ?>
        </div>
        <div class="text-xs text-gray-500 mt-1">Annual projection: <?= money($r['net_income'] * 12) ?></div>
    </div>
</div>

<!-- ============================================================
     SECTION 2: BREAK-EVEN ANALYSIS
============================================================ -->
<?php
$beWith    = $r['break_even_fill_rate'];
$beNoOwner = $r['break_even_fill_rate_no_owner'];
$curPos    = min(100, max(0, $r['current_fill_rate'] * 100));
?>
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-800">Break-Even Analysis</h2>
        <div class="no-print flex items-center gap-3 text-xs text-gray-600">
            <span class="font-medium">Include owner salary?</span>
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" name="be_mode" value="with_owner" checked onchange="updateBreakEven()"> Yes
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" name="be_mode" value="no_owner" onchange="updateBreakEven()"> No
            </label>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div id="be-rate" class="text-2xl font-bold <?= $beWith > $r['current_fill_rate'] ? 'text-red-600' : 'text-green-600' ?>">
                <?= pct($beWith) ?>
            </div>
            <div class="text-xs text-gray-500 mt-1">Break-even fill rate</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-indigo-600"><?= pct($r['current_fill_rate']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Your current fill rate</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <?php $gap = $beWith - $r['current_fill_rate']; ?>
            <div id="be-gap" class="text-2xl font-bold <?= $gap > 0 ? 'text-red-600' : 'text-green-600' ?>">
                <?= $gap > 0 ? '+' : '' ?><?= pct($gap) ?>
            </div>
            <div id="be-gap-label" class="text-xs text-gray-500 mt-1"><?= $gap > 0 ? 'Gap to break-even' : 'Buffer above break-even' ?></div>
        </div>
    </div>

    <div class="relative h-8 bg-gray-100 rounded-full overflow-visible mb-2">
        <div class="absolute inset-0 rounded-full overflow-hidden">
            <div class="h-full rounded-full <?= $r['net_income'] >= 0 ? 'bg-green-400' : 'bg-red-400' ?>"
                 style="width: <?= $curPos ?>%"></div>
        </div>
        <div id="be-marker" class="absolute top-0 bottom-0 w-0.5 bg-gray-800 z-10"
             style="left: <?= min(100, max(0, $beWith * 100)) ?>%">
            <div class="absolute -top-5 left-1 text-xs text-gray-600 whitespace-nowrap">B/E</div>
        </div>
    </div>
    <div class="flex justify-between text-xs text-gray-400 mb-4">
        <span>0%</span><span>50%</span><span>100%</span>
    </div>

    <div class="grid grid-cols-2 gap-4 text-sm mb-4">
        <div><span class="text-gray-500">Avg students/class:</span> <strong><?= number_format($r['avg_students_per_class'], 1) ?></strong></div>
        <div><span class="text-gray-500">Monthly classes:</span> <strong><?= number_format($r['monthly_classes'], 0) ?></strong></div>
        <div>
            <span class="text-gray-500">Blended revenue/visit:</span> <strong><?= money($r['blended_rev_per_visit']) ?></strong>
            <span class="ml-1 text-xs bg-blue-50 text-blue-600 border border-blue-100 px-1.5 py-0.5 rounded">Industry: $15–$25 yoga</span>
        </div>
        <div><span class="text-gray-500">Monthly student visits:</span> <strong><?= number_format($r['avg_students_per_class'] * $r['monthly_classes'], 0) ?></strong></div>
    </div>
    <div class="flex flex-wrap gap-2">
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">Benchmark: typical studio breaks even at 50–70% fill</span>
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">Healthy: 15%+ buffer above break-even</span>
    </div>
</div>

<script>
const beWithOwner = <?= json_encode($beWith) ?>;
const beNoOwner   = <?= json_encode($beNoOwner) ?>;
const currentFill = <?= json_encode($r['current_fill_rate']) ?>;

function updateBreakEven() {
    const mode = document.querySelector('[name="be_mode"]:checked').value;
    const be   = mode === 'with_owner' ? beWithOwner : beNoOwner;
    const gap  = be - currentFill;

    document.getElementById('be-rate').textContent = (be * 100).toFixed(1) + '%';
    document.getElementById('be-rate').className   = 'text-2xl font-bold ' + (be > currentFill ? 'text-red-600' : 'text-green-600');
    document.getElementById('be-gap').textContent  = (gap > 0 ? '+' : '') + (gap * 100).toFixed(1) + '%';
    document.getElementById('be-gap').className    = 'text-2xl font-bold ' + (gap > 0 ? 'text-red-600' : 'text-green-600');
    document.getElementById('be-gap-label').textContent = gap > 0 ? 'Gap to break-even' : 'Buffer above break-even';
    document.getElementById('be-marker').style.left = Math.min(100, Math.max(0, be * 100)) + '%';
}
</script>

<!-- ============================================================
     SECTION 3: PRICING SENSITIVITY TABLE
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Pricing Sensitivity</h2>
    <p class="text-xs text-gray-500 mb-4">Net income at various fill rates and across pricing scenarios (current / +10% / +20%).</p>
    <div class="overflow-x-auto">
        <table class="sensitivity-table w-full">
            <thead>
                <tr>
                    <th class="text-left">Fill Rate</th>
                    <th>Current Prices</th>
                    <th>Prices +10%</th>
                    <th>Prices +20%</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $scenariosByFill = [];
                foreach ($r['fill_scenarios'] as $sc) {
                    $fillKey = number_format($sc['fill_rate'] * 100, 0) . '%';
                    $scenariosByFill[$fillKey][number_format($sc['price_multiplier'], 1)] = $sc;
                }
                foreach ($scenariosByFill as $fillLabel => $mults):
                ?>
                <tr>
                    <td class="text-left font-medium text-gray-700"><?= h($fillLabel) ?></td>
                    <?php foreach (['1.0', '1.1', '1.2'] as $m):
                        $net = $mults[$m]['net_income'] ?? 0;
                    ?>
                    <td class="<?= $net >= 0 ? 'sensitivity-positive' : 'sensitivity-negative' ?>">
                        <?= money($net, true) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================
     SECTION 4: INSTRUCTOR PAY ANALYSIS
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Instructor Pay Analysis</h2>
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold <?= $r['instructor_cost_pct'] > 0.35 ? 'text-red-600' : ($r['instructor_cost_pct'] > 0.25 ? 'text-green-600' : 'text-amber-600') ?>">
                <?= pct($r['instructor_cost_pct']) ?>
            </div>
            <div class="text-xs text-gray-500 mt-1">Instructor % of revenue</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-gray-800"><?= money($r['instructor_cost']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Total monthly cost</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-indigo-600"><?= money($r['max_affordable_pay']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Max affordable $/class</div>
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        <span class="text-xs bg-amber-50 text-amber-700 border border-amber-100 px-2 py-1 rounded-full">&lt;25% — may be undervaluing instructors</span>
        <span class="text-xs bg-green-50 text-green-700 border border-green-100 px-2 py-1 rounded-full">25–35% — healthy range</span>
        <span class="text-xs bg-red-50 text-red-700 border border-red-100 px-2 py-1 rounded-full">&gt;35% — sustainability risk</span>
    </div>
    <p class="text-xs text-gray-400 mt-2">Source: Mindbody Business Intelligence / Yoga Alliance industry data.</p>

    <?php if (!empty($r['instructors'])): ?>
    <div class="mt-4">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 border-b border-gray-200">
                    <th class="text-left py-1">Instructor</th>
                    <th class="text-right py-1">Type</th>
                    <th class="text-right py-1">Pay type</th>
                    <th class="text-right py-1">Rate</th>
                    <th class="text-right py-1">Classes/wk</th>
                    <th class="text-right py-1">Monthly cost</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $wpm = (float)($biz['weeks_per_year'] ?? 50) / 12;
                foreach ($r['instructors'] as $inst):
                    $burden      = ($inst['worker_type'] ?? 'contractor') === 'employee' ? $wiOperatingCost : 1.0; // approximate
                    $monthlyCost = $inst['pay_type'] === 'flat'
                        ? (float)$inst['pay_per_class'] * (float)$inst['classes_per_week'] * $wpm
                        : null;
                ?>
                <tr class="border-b border-gray-50">
                    <td class="py-1.5"><?= h($inst['name']) ?></td>
                    <td class="text-right py-1.5 text-xs">
                        <span class="<?= ($inst['worker_type'] ?? 'contractor') === 'employee' ? 'text-blue-600' : 'text-gray-400' ?>">
                            <?= ($inst['worker_type'] ?? 'contractor') === 'employee' ? 'Employee' : 'Contractor' ?>
                        </span>
                    </td>
                    <td class="text-right py-1.5 capitalize"><?= h(str_replace('_', ' ', $inst['pay_type'])) ?></td>
                    <td class="text-right py-1.5">
                        <?php if ($inst['pay_type'] === 'flat'): ?>
                            <?= money($inst['pay_per_class']) ?>/class
                        <?php else: ?>
                            <?= pct((float)($inst['revenue_share_pct'] ?? 0)) ?> of rev
                        <?php endif; ?>
                    </td>
                    <td class="text-right py-1.5"><?= h($inst['classes_per_week']) ?></td>
                    <td class="text-right py-1.5 font-medium">
                        <?= $monthlyCost !== null ? money($monthlyCost) : '<em class="text-gray-400">varies</em>' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================================
     SECTION 5: INTRO OFFER FUNNEL
============================================================ -->
<?php if (!empty($r['intro_funnel'])): $funnel = $r['intro_funnel']; ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Intro Offer Funnel</h2>
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-gray-800"><?= number_format($funnel['new_leads_per_month']) ?></div>
            <div class="text-xs text-gray-500 mt-1">New intros/month</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-gray-800"><?= money($funnel['intro_revenue']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Intro offer revenue</div>
        </div>
        <div class="text-center p-4 bg-green-50 rounded-lg">
            <div class="text-2xl font-bold text-green-700"><?= money($funnel['total_mrr_lift']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Incremental MRR from conversions</div>
        </div>
    </div>
    <?php if (!empty($funnel['conversions'])): ?>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-xs text-gray-500 border-b border-gray-200">
                <th class="text-left py-1">Destination</th>
                <th class="text-right py-1">Conversion rate</th>
                <th class="text-right py-1">Converts/month</th>
                <th class="text-right py-1">Tier price</th>
                <th class="text-right py-1">MRR lift</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($funnel['conversions'] as $cv): ?>
            <tr class="border-b border-gray-50">
                <td class="py-1.5"><?= h($cv['destination']) ?></td>
                <td class="text-right py-1.5"><?= pct($cv['rate']) ?></td>
                <td class="text-right py-1.5"><?= number_format($cv['converts'], 1) ?></td>
                <td class="text-right py-1.5"><?= money($cv['tier_price']) ?>/mo</td>
                <td class="text-right py-1.5 font-medium text-green-700"><?= money($cv['monthly_lift']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     SECTION 6: ONLINE REVENUE BREAKDOWN
============================================================ -->
<?php
$onlineStreams = array_filter($r['revenue_by_stream'], fn($s) => in_array($s['stream_type'], ['online_live', 'online_recorded']));
if (!empty($onlineStreams)):
?>
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Online Revenue</h2>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-xs text-gray-500 border-b border-gray-200">
                <th class="text-left py-1">Stream</th>
                <th class="text-right py-1">Type</th>
                <th class="text-right py-1">Units/mo</th>
                <th class="text-right py-1">Price</th>
                <th class="text-right py-1">Revenue</th>
                <th class="text-right py-1">Instructor cost?</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($onlineStreams as $os): ?>
            <tr class="border-b border-gray-50">
                <td class="py-1.5"><?= h($os['label']) ?></td>
                <td class="text-right py-1.5"><?= $os['stream_type'] === 'online_live' ? 'Live' : 'Recorded' ?></td>
                <td class="text-right py-1.5"><?= number_format($os['units']) ?></td>
                <td class="text-right py-1.5"><?= money($os['price']) ?></td>
                <td class="text-right py-1.5 font-medium"><?= money($os['revenue']) ?></td>
                <td class="text-right py-1.5">
                    <?php if ($os['stream_type'] === 'online_recorded'): ?>
                    <span class="text-green-600 text-xs font-medium">None</span>
                    <?php else: ?>
                    <span class="text-gray-400 text-xs">Yes (see instructors)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============================================================
     SECTION 7: CLASS PASS DETAIL
============================================================ -->
<?php if ($r['class_pass_cash'] > 0): ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-2">Class Pass Cash vs. Earned Revenue</h2>
    <p class="text-xs text-gray-500 mb-4">This is a common blindspot — cash collected upfront vs. revenue actually earned through redemptions.</p>
    <div class="grid grid-cols-2 gap-4">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-gray-800"><?= money($r['class_pass_cash']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Cash collected (passes sold)</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-indigo-700"><?= money($r['class_pass_earned']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Earned revenue (80% redemption assumed)</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     SECTION 8: EXPENSE WATERFALL
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Expense Waterfall</h2>
    <p class="text-xs text-gray-500 mb-4">Each cost as a share of gross revenue.</p>
    <?php
    $gross = $r['gross_revenue'];
    $waterfallItems = [];
    foreach ($r['expenses'] as $exp) {
        $waterfallItems[] = ['label' => $exp['label'], 'amount' => (float)$exp['amount_monthly']];
    }
    $waterfallItems[] = ['label' => 'Instructors',  'amount' => $r['instructor_cost']];
    $waterfallItems[] = ['label' => 'Owner Salary', 'amount' => $r['owner_salary_monthly']];
    usort($waterfallItems, fn($a, $b) => $b['amount'] <=> $a['amount']);
    foreach ($waterfallItems as $item):
        $pctOfRevenue = $gross > 0 ? ($item['amount'] / $gross) * 100 : 0;
    ?>
    <div class="mb-3">
        <div class="flex justify-between text-sm mb-1">
            <span class="text-gray-700"><?= h($item['label']) ?></span>
            <span class="text-gray-600"><?= money($item['amount']) ?> <span class="text-xs text-gray-400">(<?= number_format($pctOfRevenue, 1) ?>%)</span></span>
        </div>
        <div class="h-4 bg-gray-100 rounded-full overflow-hidden">
            <div class="expense-bar-fill h-full rounded-full" style="width: <?= min(100, $pctOfRevenue) ?>%"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ============================================================
     SECTION 9: ANNUAL PROJECTION
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Annual Projection</h2>
    <p class="text-xs text-gray-400 mb-4 italic">Note: This is a straight 12&times; extrapolation. Seasonality modeling coming in a future update.</p>
    <div class="grid grid-cols-3 gap-4">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-green-700"><?= money($r['gross_revenue'] * 12) ?></div>
            <div class="text-xs text-gray-500 mt-1">Annual gross revenue</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-gray-800"><?= money($r['total_cost'] * 12) ?></div>
            <div class="text-xs text-gray-500 mt-1">Annual total costs</div>
        </div>
        <div class="text-center p-4 <?= $r['net_income'] >= 0 ? 'bg-green-50' : 'bg-red-50' ?> rounded-lg">
            <div class="text-2xl font-bold <?= $r['net_income'] >= 0 ? 'net-positive' : 'net-negative' ?>"><?= money($r['net_income'] * 12, true) ?></div>
            <div class="text-xs text-gray-500 mt-1">Annual net income</div>
        </div>
    </div>
</div>

<!-- ============================================================
     SECTION 10: ACTUAL VS. PROJECTED
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-800">Actual vs. Projected</h2>
            <p class="text-xs text-gray-500 mt-0.5">Track how real results compare to your model.</p>
        </div>
        <?php if (!$isShared): ?>
        <a href="<?= PF_BASE ?>/actuals.php" class="no-print text-sm text-indigo-600 border border-indigo-200 px-4 py-2 rounded-lg hover:bg-indigo-50 transition-colors">
            + Log actuals
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($actuals)): ?>
    <div class="text-center py-8 text-gray-400">
        <p class="text-sm mb-1">No actuals logged yet.</p>
        <?php if (!$isShared): ?>
        <p class="text-xs">Use <a href="<?= PF_BASE ?>/actuals.php" class="text-indigo-500 hover:underline">Log actuals</a> each month to track real vs. projected performance.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 border-b border-gray-200">
                    <th class="text-left py-2">Month</th>
                    <th class="text-right py-2">Proj. Revenue</th>
                    <th class="text-right py-2">Actual Revenue</th>
                    <th class="text-right py-2">Revenue Var.</th>
                    <th class="text-right py-2">Proj. Expenses</th>
                    <th class="text-right py-2">Actual Expenses</th>
                    <th class="text-right py-2">Expense Var.</th>
                    <th class="text-right py-2">Actual Visits</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actuals as $a):
                    $revVar  = (float)$a['gross_revenue']  - $r['gross_revenue'];
                    $expVar  = (float)$a['total_expenses'] - $r['operating_cost'];
                    $mLabel  = ($monthNames[$a['month'] - 1] ?? '?') . ' ' . $a['year'];
                ?>
                <tr class="border-b border-gray-50">
                    <td class="py-2 font-medium text-gray-700"><?= h($mLabel) ?></td>
                    <td class="text-right py-2 text-gray-500"><?= money($r['gross_revenue']) ?></td>
                    <td class="text-right py-2 font-medium"><?= money($a['gross_revenue']) ?></td>
                    <td class="text-right py-2 font-medium <?= $revVar >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= money($revVar, true) ?>
                    </td>
                    <td class="text-right py-2 text-gray-500"><?= money($r['operating_cost']) ?></td>
                    <td class="text-right py-2 font-medium"><?= money($a['total_expenses']) ?></td>
                    <td class="text-right py-2 font-medium <?= $expVar <= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= money($expVar, true) ?>
                    </td>
                    <td class="text-right py-2"><?= number_format($a['student_visits']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($actuals[0]['notes'])): ?>
    <p class="text-xs text-gray-400 mt-3 italic">Most recent note: <?= h($actuals[0]['notes']) ?></p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// ============================================================
// SECTION 11: LOCAL REVENUE OPPORTUNITIES
// (hidden on shared reports — competitive intelligence)
// ============================================================
if (!$isShared && isset($bid)):
    require_once PF_ROOT . '/includes/db.php';
    $marketProfile    = getMarketProfile($bid);
    $allOpportunities = !$marketProfile ? [] : getRecommendations($bid, false);
    $topOpportunities = array_slice($allOpportunities, 0, 3);
    if ($marketProfile && !empty($topOpportunities)):
?>
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6 no-print">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-800">Local Revenue Opportunities</h2>
            <p class="text-xs text-gray-500 mt-0.5">Strategies matched to your market size and nearby organizations.</p>
        </div>
        <a href="<?= PF_BASE ?>/localrev.php" class="text-sm text-indigo-600 border border-indigo-200 px-4 py-2 rounded-lg hover:bg-indigo-50 transition-colors">
            View all <?= count($allOpportunities) ?> opportunities &rarr;
        </a>
    </div>
    <div class="grid gap-3 sm:grid-cols-3">
        <?php
        $catColors = [
            'b2b'         => 'bg-indigo-50 text-indigo-700 border-indigo-100',
            'community'   => 'bg-green-50 text-green-700 border-green-100',
            'pricing'     => 'bg-amber-50 text-amber-700 border-amber-100',
            'partnership' => 'bg-purple-50 text-purple-700 border-purple-100',
        ];
        foreach ($topOpportunities as $opp):
            $cc = $catColors[$opp['category']] ?? 'bg-gray-50 text-gray-600 border-gray-100';
        ?>
        <div class="border border-dashed border-gray-200 rounded-lg p-4">
            <div class="flex items-start justify-between gap-2 mb-1">
                <p class="text-sm font-semibold text-gray-800 leading-snug"><?= h($opp['title']) ?></p>
                <span class="text-xs px-1.5 py-0.5 rounded border font-medium flex-shrink-0 <?= $cc ?>"><?= h(ucfirst($opp['category'])) ?></span>
            </div>
            <?php if (!empty($opp['estimated_monthly_revenue'])): ?>
            <p class="text-xs text-green-700 font-medium mb-1">Est. <?= h($opp['estimated_monthly_revenue']) ?>/mo</p>
            <?php endif; ?>
            <p class="text-xs text-gray-500 leading-relaxed line-clamp-2"><?= h(mb_substr($opp['description'], 0, 120)) ?>…</p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
    endif;
endif;
?>

<?php if (!$isShared): ?>
<div class="no-print flex gap-3 mt-2 mb-8">
    <a href="<?= PF_BASE ?>/wizard/setup.php" class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Edit setup</a>
    <a href="<?= PF_BASE ?>/wizard/expenses.php" class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Edit expenses</a>
    <a href="<?= PF_BASE ?>/wizard/classes.php" class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Edit classes</a>
    <a href="<?= PF_BASE ?>/wizard/revenue.php" class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Edit revenue</a>
    <a href="<?= PF_BASE ?>/wizard/instructors.php" class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Edit instructors</a>
    <a href="<?= PF_BASE ?>/actuals.php" class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Log actuals</a>
</div>
<?php else: ?>
<div class="mt-8 mb-6 text-center text-xs text-gray-400">
    Generated with <a href="<?= PF_BASE ?>/" class="text-indigo-500 hover:underline">ProForma</a> &mdash; pro forma modeling for small businesses
</div>
<?php endif; ?>
