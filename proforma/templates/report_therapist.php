<?php
// Requires: $r (TherapistCalculator::report()), $biz, $bid
// Included from wizard/report.php
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900"><?= h($biz['business_name']) ?></h1>
        <p class="text-sm text-gray-500">Therapy Practice Pro Forma &mdash; <?= date('F j, Y') ?></p>
    </div>
    <button onclick="window.print()" class="no-print text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">
        Print / Save PDF
    </button>
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
     SECTION 1: MONTHLY P&L SUMMARY
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Monthly P&L Summary</h2>
    <div class="grid grid-cols-2 gap-6">

        <div>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Revenue</h3>
            <?php foreach ($r['revenue_by_code'] as $rc): ?>
            <div class="flex justify-between text-sm py-1 border-b border-gray-50">
                <span class="text-gray-700">
                    <span class="font-mono text-xs text-gray-400 mr-1"><?= h($rc['code']) ?></span>
                    <?= h($rc['description']) ?>
                    <span class="text-xs text-gray-400 ml-1">(<?= number_format($rc['sessions']) ?> sess)</span>
                </span>
                <span class="font-medium"><?= money($rc['blended_revenue']) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between text-sm font-bold py-2 mt-1 border-t border-gray-200">
                <span>Gross Revenue (blended)</span>
                <span class="text-green-700"><?= money($r['gross_revenue']) ?></span>
            </div>
        </div>

        <div>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Operating Costs</h3>
            <?php foreach ($r['expenses'] as $exp): ?>
            <div class="flex justify-between text-sm py-1 border-b border-gray-50">
                <span class="text-gray-700"><?= h($exp['label']) ?></span>
                <span><?= money($exp['amount_monthly']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if ($r['staff_cost'] > 0): ?>
            <div class="flex justify-between text-sm py-1 border-b border-gray-50">
                <span class="text-gray-700">Staff / Associate Providers</span>
                <span><?= money($r['staff_cost']) ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between text-sm font-bold py-2 mt-1 border-t border-gray-200">
                <span>Total Operating Costs</span>
                <span class="text-gray-800"><?= money($r['operating_cost']) ?></span>
            </div>
        </div>
    </div>

    <!-- Net before owner -->
    <div class="mt-4 p-4 rounded-xl text-center <?= $r['net_before_owner'] >= 0 ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?>">
        <div class="text-xs font-semibold uppercase tracking-wide <?= $r['net_before_owner'] >= 0 ? 'text-green-600' : 'text-red-600' ?> mb-1">
            Net Operating Income (before owner draw)
        </div>
        <div class="text-3xl font-bold <?= $r['net_before_owner'] >= 0 ? 'net-positive' : 'net-negative' ?>">
            <?= money($r['net_before_owner'], true) ?>
        </div>
    </div>

    <!-- Owner draw block -->
    <div class="mt-3 px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-xl flex items-center justify-between">
        <div>
            <div class="text-sm font-semibold text-indigo-800">Owner's Draw / Compensation</div>
            <div class="text-xs text-indigo-500 mt-0.5">
                <?= money($r['owner_salary_monthly'] * 12) ?>/year target &mdash; drawn from net operating income
            </div>
        </div>
        <div class="text-xl font-bold text-indigo-700"><?= money($r['owner_salary_monthly']) ?>/mo</div>
    </div>

    <!-- Final net income -->
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
     SECTION 2: INSURANCE vs. CASH-PAY COMPARISON
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-2">Insurance vs. Cash-Pay Comparison</h2>
    <p class="text-xs text-gray-500 mb-5">
        What would monthly revenue look like if every client paid the self-pay rate?
        This is the real cost of accepting insurance — not just administrative hassle, but dollars left on the table.
    </p>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-gray-800"><?= money($r['gross_revenue']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Current blended revenue</div>
        </div>
        <div class="text-center p-4 bg-green-50 rounded-lg">
            <div class="text-2xl font-bold text-green-700"><?= money($r['cash_pay_revenue']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Full cash-pay equivalent</div>
        </div>
        <div class="text-center p-4 bg-amber-50 rounded-lg">
            <div class="text-2xl font-bold text-amber-700"><?= money($r['insurance_discount']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Monthly insurance discount</div>
        </div>
    </div>

    <!-- Code-by-code comparison -->
    <table class="w-full text-sm">
        <thead>
            <tr class="text-xs text-gray-500 border-b border-gray-200">
                <th class="text-left py-1.5">CPT Code</th>
                <th class="text-left py-1.5">Description</th>
                <th class="text-right py-1.5">Sessions/mo</th>
                <th class="text-right py-1.5">Blended rate</th>
                <th class="text-right py-1.5">Cash rate</th>
                <th class="text-right py-1.5">Blended revenue</th>
                <th class="text-right py-1.5">Cash-pay revenue</th>
                <th class="text-right py-1.5">Monthly gap</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($r['revenue_by_code'] as $rc): ?>
            <tr class="border-b border-gray-50">
                <td class="py-1.5 font-mono text-xs text-gray-500"><?= h($rc['code']) ?></td>
                <td class="py-1.5 text-gray-700"><?= h($rc['description']) ?></td>
                <td class="py-1.5 text-right"><?= number_format($rc['sessions'], 0) ?></td>
                <td class="py-1.5 text-right"><?= money($rc['blended_rate']) ?></td>
                <td class="py-1.5 text-right"><?= money($rc['cash_rate']) ?></td>
                <td class="py-1.5 text-right font-medium"><?= money($rc['blended_revenue']) ?></td>
                <td class="py-1.5 text-right font-medium text-green-700"><?= money($rc['cash_revenue']) ?></td>
                <td class="py-1.5 text-right <?= $rc['monthly_gap'] > 0 ? 'text-amber-600' : 'text-gray-400' ?> font-medium">
                    <?= $rc['monthly_gap'] > 0 ? '-' . money($rc['monthly_gap']) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-gray-300 font-semibold text-sm">
                <td colspan="5" class="py-2 text-gray-700">Totals</td>
                <td class="py-2 text-right"><?= money($r['gross_revenue']) ?></td>
                <td class="py-2 text-right text-green-700"><?= money($r['cash_pay_revenue']) ?></td>
                <td class="py-2 text-right text-amber-600"><?= $r['insurance_discount'] > 0 ? '-' . money($r['insurance_discount']) : '—' ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-4 flex flex-wrap gap-2">
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">Industry avg: $111/session insurance vs. $159/session cash pay (Heard 2024)</span>
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">36% per-session revenue gap before collection rate differences</span>
    </div>

    <?php if ($r['cash_pay_revenue'] > $r['gross_revenue']): ?>
    <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-700">
        The practice leaves <?= money($r['insurance_discount'] * 12) ?>/year on the table by accepting insurance at current reimbursement rates.
        A hybrid model — dropping the lowest-paying panels while retaining cash-pay and high-reimbursing insurers — often closes most of that gap.
    </div>
    <?php endif; ?>
</div>

<!-- ============================================================
     SECTION 3: PAYER MIX ANALYSIS
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Payer Mix Analysis</h2>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-xs text-gray-500 border-b border-gray-200">
                <th class="text-left py-1.5">Payer</th>
                <th class="text-right py-1.5">% of clients</th>
                <th class="text-right py-1.5">Est. revenue contribution</th>
                <th class="text-right py-1.5">% of revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($r['revenue_by_payer'] as $rp): ?>
            <tr class="border-b border-gray-50">
                <td class="py-1.5 flex items-center gap-2">
                    <?= h($rp['payer_name']) ?>
                    <?php if ($rp['is_cash_pay']): ?>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">cash</span>
                    <?php endif; ?>
                </td>
                <td class="py-1.5 text-right"><?= pct($rp['client_pct']) ?></td>
                <td class="py-1.5 text-right font-medium"><?= money($rp['revenue']) ?></td>
                <td class="py-1.5 text-right text-gray-500">
                    <?= $r['gross_revenue'] > 0 ? pct($rp['revenue'] / $r['gross_revenue']) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="mt-4 text-xs text-gray-400 italic">
        27% of private practices have shifted to cash-pay only; a 60/40 insurance/cash hybrid is the most commonly cited sustainability model. (Heard 2024)
    </div>
</div>

<!-- ============================================================
     SECTION 4: BREAK-EVEN ANALYSIS
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-800">Break-Even Analysis</h2>
        <div class="no-print flex items-center gap-3 text-xs text-gray-600">
            <span class="font-medium">Include owner salary?</span>
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" name="be_mode_t" value="with_owner" checked onchange="updateBeTherapist()"> Yes
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" name="be_mode_t" value="no_owner" onchange="updateBeTherapist()"> No
            </label>
        </div>
    </div>
    <div class="grid grid-cols-4 gap-4 mb-5">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-gray-800"><?= number_format($r['total_sessions'], 0) ?></div>
            <div class="text-xs text-gray-500 mt-1">Current sessions/month</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div id="be-no-staff" class="text-2xl font-bold <?= $r['break_even_sessions'] > $r['total_sessions'] ? 'text-red-600' : 'text-green-600' ?>">
                <?= number_format(ceil($r['break_even_sessions']), 0) ?>
            </div>
            <div class="text-xs text-gray-500 mt-1" id="be-no-staff-label">Break-even (no staff)</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div id="be-with-staff" class="text-2xl font-bold <?= $r['break_even_with_staff'] > $r['total_sessions'] ? 'text-red-600' : 'text-amber-600' ?>">
                <?= number_format(ceil($r['break_even_with_staff']), 0) ?>
            </div>
            <div class="text-xs text-gray-500 mt-1">Break-even (with staff)</div>
        </div>
        <div class="text-center p-4 bg-indigo-50 rounded-lg">
            <div class="text-2xl font-bold text-indigo-700"><?= money($r['overall_blended_rate']) ?></div>
            <div class="text-xs text-gray-500 mt-1">Avg blended rate/session</div>
        </div>
    </div>

    <!-- Session volume scenarios -->
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Net Income at Different Session Volumes</h3>
    <table class="sensitivity-table w-full">
        <thead>
            <tr>
                <th class="text-left">Volume</th>
                <th>Net Income (insurance mix)</th>
                <th>Net Income (cash pay only)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($r['session_scenarios'] as $sc): ?>
            <tr>
                <td class="text-left font-medium text-gray-700"><?= h($sc['label']) ?></td>
                <td class="<?= $sc['net_insurance'] >= 0 ? 'sensitivity-positive' : 'sensitivity-negative' ?>">
                    <?= money($sc['net_insurance'], true) ?>
                </td>
                <td class="<?= $sc['net_cash'] >= 0 ? 'sensitivity-positive' : 'sensitivity-negative' ?>">
                    <?= money($sc['net_cash'], true) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="flex flex-wrap gap-2 mt-4">
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">Full caseload: 20–25 sessions/week per provider</span>
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">Solo break-even (overhead only): ~5–8 sess/week</span>
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">Solo break-even (incl. owner salary): ~15–20 sess/week</span>
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">Net collection rate: 95%+ with billing service / 80–85% in-house</span>
    </div>
    <p class="text-xs text-gray-400 mt-2">Sources: Heard 2024, Garrett Digital, Headway, Medical Billers and Coders.</p>
</div>

<!-- ============================================================
     SECTION 5: PROVIDER PRODUCTIVITY & STAFF AFFORDABILITY
============================================================ -->
<?php if (!empty($r['provider_productivity'])): ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-2">Provider Productivity &amp; Staff Affordability</h2>
    <div class="grid grid-cols-3 gap-4 mb-5">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold <?= $r['staff_cost_pct'] > 0.60 ? 'text-red-600' : ($r['staff_cost_pct'] > 0.45 ? 'text-amber-600' : 'text-green-600') ?>">
                <?= pct($r['staff_cost_pct']) ?>
            </div>
            <div class="text-xs text-gray-500 mt-1">Staff cost % of revenue</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-indigo-700"><?= money($r['max_affordable_hourly']) ?>/hr</div>
            <div class="text-xs text-gray-500 mt-1">Max affordable hourly rate</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-indigo-700"><?= money($r['max_affordable_per_sess']) ?>/sess</div>
            <div class="text-xs text-gray-500 mt-1">Max affordable per-session rate</div>
        </div>
    </div>
    <div class="flex flex-wrap gap-2 mb-3">
        <span class="text-xs bg-green-50 text-green-700 border border-green-100 px-2 py-1 rounded-full">&lt;45%: lean / sustainable</span>
        <span class="text-xs bg-amber-50 text-amber-700 border border-amber-100 px-2 py-1 rounded-full">45–60%: within range (W-2 therapist standard)</span>
        <span class="text-xs bg-red-50 text-red-700 border border-red-100 px-2 py-1 rounded-full">&gt;60%: insufficient margin for overhead + owner</span>
    </div>
    <p class="text-xs text-gray-400 mb-4">W-2 therapists typically cost 50–60% of their collections; 1099 contractors 60–70% (the premium offsets self-employment tax). Sources: Heard 2024, GrowingOurPractice, Solomon Advising.</p>

    <table class="w-full text-sm">
        <thead>
            <tr class="text-xs text-gray-500 border-b border-gray-200">
                <th class="text-left py-1.5">Provider</th>
                <th class="text-right py-1.5">Credential</th>
                <th class="text-right py-1.5">Sess/mo</th>
                <th class="text-right py-1.5">Pay type</th>
                <th class="text-right py-1.5">Monthly cost</th>
                <th class="text-right py-1.5">Rev generated</th>
                <th class="text-right py-1.5">Margin</th>
                <th class="text-right py-1.5">Margin %</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($r['provider_productivity'] as $pp): ?>
            <tr class="border-b border-gray-50">
                <td class="py-1.5 flex items-center gap-1.5">
                    <?= h($pp['name']) ?>
                    <?php if ($pp['is_owner']): ?>
                    <span class="text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded">owner</span>
                    <?php endif; ?>
                </td>
                <td class="py-1.5 text-right text-gray-500"><?= h($pp['credential']) ?></td>
                <td class="py-1.5 text-right"><?= number_format($pp['sessions_mo'], 0) ?></td>
                <td class="py-1.5 text-right text-gray-500 capitalize"><?= h(str_replace('_', ' ', $pp['pay_type'])) ?></td>
                <td class="py-1.5 text-right">
                    <?= $pp['is_owner'] ? '<em class="text-gray-400 text-xs">owner draw</em>' : money($pp['cost']) ?>
                </td>
                <td class="py-1.5 text-right font-medium"><?= money($pp['rev_generated']) ?></td>
                <td class="py-1.5 text-right <?= $pp['margin'] >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium">
                    <?= $pp['is_owner'] ? '—' : money($pp['margin'], true) ?>
                </td>
                <td class="py-1.5 text-right <?= $pp['margin_pct'] >= 0.40 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= $pp['is_owner'] ? '—' : pct($pp['margin_pct']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============================================================
     SECTION 6: EXPENSE WATERFALL
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Expense Waterfall</h2>
    <p class="text-xs text-gray-500 mb-4">Each cost as a share of gross revenue.</p>
    <?php
    $gross = $r['gross_revenue'];
    $wfItems = [];
    foreach ($r['expenses'] as $exp) {
        $wfItems[] = ['label' => $exp['label'], 'amount' => (float)$exp['amount_monthly']];
    }
    $wfItems[] = ['label' => 'Staff / Associates',   'amount' => $r['staff_cost']];
    $wfItems[] = ['label' => 'Owner Compensation',   'amount' => $r['owner_salary_monthly']];
    usort($wfItems, fn($a, $b) => $b['amount'] <=> $a['amount']);
    foreach ($wfItems as $item):
        if ($item['amount'] <= 0) continue;
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
     SECTION 7: ANNUAL PROJECTION
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Annual Projection</h2>
    <p class="text-xs text-gray-400 mb-4 italic">Straight 12× extrapolation. Seasonality and panel growth modeling available in a future update.</p>
    <div class="grid grid-cols-4 gap-4">
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-xl font-bold text-green-700"><?= money($r['gross_revenue'] * 12) ?></div>
            <div class="text-xs text-gray-500 mt-1">Annual revenue (blended)</div>
        </div>
        <div class="text-center p-4 bg-green-50 rounded-lg">
            <div class="text-xl font-bold text-green-700"><?= money($r['cash_pay_revenue'] * 12) ?></div>
            <div class="text-xs text-gray-500 mt-1">Annual revenue (cash pay)</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-xl font-bold text-gray-800"><?= money($r['total_cost'] * 12) ?></div>
            <div class="text-xs text-gray-500 mt-1">Annual total costs</div>
        </div>
        <div class="text-center p-4 <?= $r['net_income'] >= 0 ? 'bg-green-50' : 'bg-red-50' ?> rounded-lg">
            <div class="text-xl font-bold <?= $r['net_income'] >= 0 ? 'net-positive' : 'net-negative' ?>"><?= money($r['net_income'] * 12, true) ?></div>
            <div class="text-xs text-gray-500 mt-1">Annual net income</div>
        </div>
    </div>
</div>

<!-- ============================================================
     SECTION 8: HOW MANY CLIENTS DO I NEED?
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-2">Panel Size Calculator</h2>
    <p class="text-xs text-gray-500 mb-3">
        How many active clients do you need to sustain the practice? Based on your blended rate and session frequency assumptions.
    </p>
    <div class="flex flex-wrap gap-2 mb-4">
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">Full caseload benchmark: 20–25 sessions/week</span>
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">50% of solo practitioners see ≤15 clients/week (Heard 2024)</span>
        <span class="text-xs bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded-full">Utilization target: 65–75% of available hours billable</span>
    </div>
    <?php
    $blended = $r['overall_blended_rate'];
    $targets = [
        ['label' => 'Cover overhead only',          'cost' => $r['fixed_overhead'] + $r['variable_overhead']],
        ['label' => 'Cover overhead + owner draw',  'cost' => $r['fixed_overhead'] + $r['variable_overhead'] + $r['owner_salary_monthly']],
        ['label' => 'Cover all costs incl. staff',  'cost' => $r['total_cost']],
    ];
    foreach ([1, 2, 4] as $sessPerWeekPerClient):
        $sessPerMonthPerClient = $sessPerWeekPerClient * ($biz['weeks_per_year'] / 12);
    ?>
    <div class="mb-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-2"><?= $sessPerWeekPerClient === 1 ? 'Weekly clients (1 sess/wk)' : ($sessPerWeekPerClient === 2 ? 'Biweekly clients (2 sess/wk)' : 'Intensive clients (4 sess/wk)') ?></h3>
        <div class="grid grid-cols-3 gap-3">
            <?php foreach ($targets as $t): ?>
            <?php
            $sessNeeded    = $blended > 0 ? $t['cost'] / $blended : 0;
            $clientsNeeded = $sessPerMonthPerClient > 0 ? ceil($sessNeeded / $sessPerMonthPerClient) : 0;
            ?>
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <div class="text-xl font-bold text-indigo-700"><?= $clientsNeeded ?></div>
                <div class="text-xs text-gray-500 mt-0.5"><?= h($t['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="no-print flex gap-3 mt-2 mb-8">
    <a href="<?= PF_BASE ?>/wizard/setup.php"     class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Edit setup</a>
    <a href="<?= PF_BASE ?>/wizard/expenses.php"  class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Edit expenses</a>
    <a href="<?= PF_BASE ?>/wizard/insurance.php" class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Edit insurance matrix</a>
    <a href="<?= PF_BASE ?>/wizard/providers.php" class="text-sm border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">Edit providers</a>
</div>

<script>
const tBeWithOwner   = <?= (float)$r['break_even_sessions'] ?>;
const tBeNoOwner     = <?= (float)$r['break_even_sessions_no_owner'] ?>;
const tBeWithStaff   = <?= (float)$r['break_even_with_staff'] ?>;
const tTotalSessions = <?= (float)$r['total_sessions'] ?>;

function updateBeTherapist() {
    const mode    = document.querySelector('[name="be_mode_t"]:checked').value;
    const beValue = mode === 'with_owner' ? tBeWithOwner : tBeNoOwner;
    const el      = document.getElementById('be-no-staff');
    el.textContent = Math.ceil(beValue).toLocaleString();
    el.className   = 'text-2xl font-bold ' + (beValue > tTotalSessions ? 'text-red-600' : 'text-green-600');
    const label    = document.getElementById('be-no-staff-label');
    label.textContent = mode === 'with_owner' ? 'Break-even (no staff)' : 'Break-even (no owner, no staff)';
}
</script>
