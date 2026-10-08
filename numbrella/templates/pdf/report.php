<?php
// In scope: $report array (from renderReportPdf() in includes/pdf.php).
// DOMPDF requires inline CSS: no Tailwind, no external stylesheets, no remote fetches.

$valuation  = $report['valuation_json']  ? json_decode($report['valuation_json'],  true) : null;
$riskFlags  = $report['risk_flags_json'] ? json_decode($report['risk_flags_json'], true) : [];
$isPremium  = $report['tier'] === 'premium';
$addbacks   = !empty($report['addbacks_json']) ? (json_decode((string) $report['addbacks_json'], true) ?? []) : [];
$weights    = $valuation['summary']['weights'] ?? ['sde' => 0.60, 'revenue' => 0.25, 'dcf' => 0.15];
$pct        = static fn(float $w): string => (string) round($w * 100) . '%';

require_once NB_ROOT . '/includes/multiples.php';
$industry  = getMultiplesForIndustry($report['industry_key'])['name'];

$businessTypes = NB_BUSINESS_TYPES;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2937; line-height: 1.5; }
    h1 { font-size: 18px; font-weight: bold; color: #111827; margin-bottom: 4px; }
    h2 { font-size: 13px; font-weight: bold; color: #111827; margin-bottom: 6px; }
    h3 { font-size: 10px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
    p  { margin-bottom: 6px; }

    .page { padding: 36px 40px; }
    .section { margin-bottom: 24px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 18px; }
    .section-label { font-size: 9px; font-weight: bold; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }

    .cover-meta { font-size: 10px; color: #6b7280; margin-top: 6px; }
    .cover-range { font-size: 22px; font-weight: bold; color: #111827; margin-top: 12px; }
    .cover-mid   { font-size: 11px; color: #6b7280; margin-top: 3px; }

    .badge { display: inline-block; font-size: 8px; font-weight: bold; padding: 2px 8px; border-radius: 20px; border: 1px solid #e5e7eb; background: #f9fafb; color: #6b7280; }
    .badge-premium { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }

    .verdict-below  { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 5px 10px; border-radius: 4px; font-size: 10px; margin-top: 8px; }
    .verdict-within { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 5px 10px; border-radius: 4px; font-size: 10px; margin-top: 8px; }
    .verdict-above  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 5px 10px; border-radius: 4px; font-size: 10px; margin-top: 8px; }

    table { width: 100%; border-collapse: collapse; font-size: 10px; }
    th    { text-align: left; font-size: 9px; font-weight: bold; color: #6b7280; text-transform: uppercase; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }
    td    { padding: 5px 0; border-bottom: 1px solid #f3f4f6; color: #374151; }
    .tr { text-align: right; }

    .method-range { display: table; width: 100%; margin-top: 10px; }
    .method-col   { display: table-cell; width: 33%; text-align: center; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 4px; }
    .method-col.mid { background: #eef2ff; border-color: #a5b4fc; }
    .method-col-label { font-size: 8px; color: #6b7280; margin-bottom: 3px; }
    .method-col-value { font-size: 13px; font-weight: bold; color: #111827; }
    .method-col-sub   { font-size: 8px; color: #9ca3af; margin-top: 2px; }

    .calc-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px; margin-bottom: 12px; }
    .calc-row { display: table; width: 100%; padding: 2px 0; }
    .calc-left  { display: table-cell; color: #6b7280; }
    .calc-right { display: table-cell; text-align: right; font-weight: bold; color: #111827; }
    .calc-total { border-top: 1px solid #d1d5db; margin-top: 4px; padding-top: 4px; }
    .calc-total .calc-right { color: #4338ca; }

    .risk-card { margin-bottom: 8px; border-radius: 4px; padding: 8px 10px; }
    .risk-high   { background: #fef2f2; border: 1px solid #fecaca; }
    .risk-medium { background: #fffbeb; border: 1px solid #fde68a; }
    .risk-low    { background: #eff6ff; border: 1px solid #bfdbfe; }
    .risk-level  { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px; }
    .risk-high .risk-level   { color: #991b1b; }
    .risk-medium .risk-level { color: #92400e; }
    .risk-low .risk-level    { color: #1e40af; }
    .risk-title { font-weight: bold; font-size: 10px; color: #111827; margin-bottom: 3px; }
    .risk-body  { font-size: 9px; color: #374151; }

    .disclaimer { font-size: 8px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 8px; margin-top: 8px; }

    .page-break { page-break-before: always; }
    .warning { background: #fffbeb; color: #78350f; border: 1px solid #fde68a; padding: 6px 10px; border-radius: 4px; font-size: 9px; margin-top: 8px; }
</style>
</head>
<body>
<div class="page">

    <!-- Cover / Summary -->
    <div class="section">
        <div class="section-label">Numbrella Valuation Report</div>
        <h1><?= h($report['business_name'] ?: 'Business Valuation') ?></h1>
        <div class="cover-meta">
            <?= h($industry) ?>
            &bull; <?= h($businessTypes[$report['business_type']] ?? '') ?>
            &bull; <?= (int)$report['years_in_operation'] ?> years in operation
            <?php if ((int)$report['num_employees']): ?>
                &bull; <?= (int)$report['num_employees'] ?> employees
            <?php endif; ?>
            &bull; <?= h(date('F j, Y', strtotime(($report['completed_at'] ?? $report['created_at']) . ' UTC'))) ?>
            <?php if (NB_PAYMENTS_ENABLED): ?>&nbsp;<span class="badge <?= $isPremium ? 'badge-premium' : '' ?>"><?= $isPremium ? 'Premium' : 'Standard' ?></span><?php endif; ?>
        </div>

        <?php if ($valuation): ?>
        <div class="cover-range">
            <?= money((float)$valuation['summary']['consensus_low']) ?> &ndash; <?= money((float)$valuation['summary']['consensus_high']) ?>
        </div>
        <div class="cover-mid">Midpoint: <?= money((float)$valuation['summary']['consensus_mid']) ?> &bull; Weighted consensus across three methods</div>

        <?php if ($valuation['summary']['asking_verdict'] && $valuation['summary']['asking_price']): ?>
            <?php
            $av = $valuation['summary']['asking_verdict'];
            $verdictLabels = [
                'below'  => 'Asking price ' . money((float)$valuation['summary']['asking_price']) . ' — below range (buyer-favorable)',
                'within' => 'Asking price ' . money((float)$valuation['summary']['asking_price']) . ' — within estimated range',
                'above'  => 'Asking price ' . money((float)$valuation['summary']['asking_price']) . ' — above range (negotiate or walk away)',
            ];
            ?>
            <div class="verdict-<?= h($av) ?>"><?= h($verdictLabels[$av]) ?></div>
        <?php endif; ?>

        <?php foreach ($valuation['warnings'] ?? [] as $warning): ?>
            <div class="warning"><?= h($warning) ?></div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <?php if ($valuation): ?>

    <!-- Method 1: SDE -->
    <div class="section">
        <h2>Method 1 — SDE Multiple (Primary)</h2>
        <p style="color:#6b7280; font-size:9px; margin-bottom:10px;">Standard valuation basis for main-street acquisitions. Represents total economic benefit to a single working owner.</p>

        <?php $sde = $valuation['methods']['sde']; ?>
        <div class="calc-box">
            <h3>SDE Calculation (most recent year)</h3>
            <div class="calc-row">
                <span class="calc-left">Net profit (most recent year)</span>
                <span class="calc-right"><?= money((float)$report['net_profit_y3']) ?></span>
            </div>
            <div class="calc-row">
                <span class="calc-left">+ Owner wages deducted as an expense</span>
                <span class="calc-right"><?= money((float)$report['owner_salary']) ?></span>
            </div>
            <?php foreach ($addbacks as $ab): ?>
            <div class="calc-row">
                <span class="calc-left">+ Add-back: <?= h($ab['desc']) ?></span>
                <span class="calc-right"><?= money((float)$ab['amount']) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="calc-row calc-total">
                <span class="calc-left" style="font-weight:bold">= SDE (Year 3)</span>
                <span class="calc-right"><?= money((float)$sde['sde']) ?></span>
            </div>
            <div class="calc-row" style="margin-top:4px; font-size:9px;">
                <span class="calc-left" style="color:#9ca3af;"><?= count($sde['years_used'] ?? [1, 2, 3]) === 3 ? 'Weighted 3-year SDE (3×Y3 + 2×Y2 + 1×Y1 ÷ 6)' : 'Weighted SDE over the ' . count($sde['years_used']) . ' year(s) provided' ?></span>
                <span class="calc-right" style="color:#9ca3af; font-weight:normal"><?= money((float)$sde['weighted_sde']) ?></span>
            </div>
        </div>

        <div class="method-range">
            <div class="method-col" style="margin-right:4px">
                <div class="method-col-label">Conservative</div>
                <div class="method-col-value"><?= money((float)$sde['low']) ?></div>
                <div class="method-col-sub"><?= number_format($sde['multiple_floor'], 1) ?>× SDE</div>
            </div>
            <div class="method-col mid" style="margin-right:4px">
                <div class="method-col-label">Midpoint</div>
                <div class="method-col-value"><?= money((float)$sde['mid']) ?></div>
                <div class="method-col-sub"><?= number_format($sde['multiple_mid'], 1) ?>× SDE</div>
            </div>
            <div class="method-col">
                <div class="method-col-label">Optimistic</div>
                <div class="method-col-value"><?= money((float)$sde['high']) ?></div>
                <div class="method-col-sub"><?= number_format($sde['multiple_ceiling'], 1) ?>× SDE</div>
            </div>
        </div>
    </div>

    <!-- Method 2: Revenue -->
    <div class="section">
        <h2>Method 2 — Revenue Multiple</h2>
        <p style="color:#6b7280; font-size:9px; margin-bottom:10px;">Applied to most recent full-year revenue (<?= money((float)$valuation['methods']['revenue']['revenue']) ?>). Secondary method: it ignores profit, so it is a check on scale, not a price<?= !empty($valuation['summary']['no_earnings']) ? '; left out of the consensus because there are no earnings' : '' ?>.</p>

        <?php $rev = $valuation['methods']['revenue']; ?>
        <div class="method-range">
            <div class="method-col" style="margin-right:4px">
                <div class="method-col-label">Conservative</div>
                <div class="method-col-value"><?= money((float)$rev['low']) ?></div>
                <div class="method-col-sub"><?= number_format($rev['multiple_floor'], 2) ?>× revenue</div>
            </div>
            <div class="method-col mid" style="margin-right:4px">
                <div class="method-col-label">Midpoint</div>
                <div class="method-col-value"><?= money((float)$rev['mid']) ?></div>
                <div class="method-col-sub"><?= number_format($rev['multiple_mid'], 2) ?>× revenue</div>
            </div>
            <div class="method-col">
                <div class="method-col-label">Optimistic</div>
                <div class="method-col-value"><?= money((float)$rev['high']) ?></div>
                <div class="method-col-sub"><?= number_format($rev['multiple_ceiling'], 2) ?>× revenue</div>
            </div>
        </div>
    </div>

    <div class="page-break"></div>

    <!-- Method 3: DCF -->
    <div class="section">
        <h2>Method 3 — DCF <span style="font-size:9px; font-weight:normal; color:#92400e;">(Illustrative Only)</span></h2>
        <p style="color:#6b7280; font-size:9px; margin-bottom:6px;">Treat as a cross-check. Cash flow = weighted SDE (still includes the owner's pay; no capex or working capital deducted), so it runs high: roughly 3–4× SDE in any industry. Growth rate: <?= number_format((float)$valuation['methods']['dcf']['growth_rate'] * 100, 1) ?>%. Discount rate: 25%. Terminal multiple: 3.0×. Weight in consensus: <?= $pct($weights['dcf']) ?>.</p>

        <?php $dcf = $valuation['methods']['dcf']; ?>
        <table style="margin-bottom:10px">
            <thead>
                <tr>
                    <th>Year</th>
                    <th class="tr">Projected FCF</th>
                    <th class="tr">Present Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dcf['projection'] as $row): ?>
                <tr>
                    <td>Year <?= $row['year'] ?></td>
                    <td class="tr"><?= money((float)$row['fcf']) ?></td>
                    <td class="tr"><?= money((float)$row['pv']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td>Terminal value</td>
                    <td class="tr"><?= money((float)$dcf['terminal_value']) ?></td>
                    <td class="tr"><?= money((float)$dcf['terminal_pv']) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="method-range">
            <div class="method-col" style="margin-right:4px">
                <div class="method-col-label">Conservative (–20%)</div>
                <div class="method-col-value"><?= money((float)$dcf['low']) ?></div>
            </div>
            <div class="method-col mid" style="margin-right:4px">
                <div class="method-col-label">Base Case</div>
                <div class="method-col-value"><?= money((float)$dcf['mid']) ?></div>
            </div>
            <div class="method-col">
                <div class="method-col-label">Optimistic (+20%)</div>
                <div class="method-col-value"><?= money((float)$dcf['high']) ?></div>
            </div>
        </div>
    </div>

    <!-- Risk Flags -->
    <?php if (!empty($riskFlags)): ?>
    <div class="section">
        <h2>Risk Flags</h2>
        <?php foreach ($riskFlags as $flag): ?>
        <div class="risk-card risk-<?= h($flag['level']) ?>">
            <div class="risk-level"><?= h(ucfirst($flag['level'])) ?> Risk</div>
            <div class="risk-title"><?= h($flag['title']) ?></div>
            <div class="risk-body"><?= h($flag['body']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($isPremium && !empty($valuation['cash_flow_projection'])): ?>
    <!-- Premium: 3-Year Projection -->
    <div class="section">
        <h2>3-Year Cash Flow Projection</h2>
        <?php $cfp = $valuation['cash_flow_projection']; ?>
        <table>
            <thead><tr><th></th><th class="tr">Year 1</th><th class="tr">Year 2</th><th class="tr">Year 3</th></tr></thead>
            <tbody>
                <tr>
                    <td>Projected Revenue</td>
                    <?php foreach ($cfp['years'] as $y): ?><td class="tr"><?= money((float)$y['revenue']) ?></td><?php endforeach; ?>
                </tr>
                <tr>
                    <td>Projected SDE</td>
                    <?php foreach ($cfp['years'] as $y): ?><td class="tr" style="font-weight:bold;color:#4338ca"><?= money((float)$y['sde']) ?></td><?php endforeach; ?>
                </tr>
            </tbody>
        </table>
        <p style="font-size:8px;color:#9ca3af;margin-top:4px">From the most recent year at <?= number_format((float)$cfp['growth_rate'] * 100, 1) ?>%/year (historical trend, capped at ±5%)</p>
    </div>
    <?php endif; ?>

    <?php if ($isPremium && !empty($valuation['scenario_analysis'])): ?>
    <!-- Premium: Scenarios -->
    <div class="section">
        <h2>Scenario Analysis</h2>
        <table>
            <thead><tr><th>Price</th><th class="tr">SDE Multiple</th><th class="tr">Assessment</th></tr></thead>
            <tbody>
                <?php foreach ($valuation['scenario_analysis'] as $sc):
                    $adjLabel = $sc['adjustment'] == 0 ? '(midpoint)' : sprintf('(%+.0f%%)', $sc['adjustment'] * 100);
                    $vColor   = match($sc['verdict']) { 'Buyer-favorable' => '#166534', 'Seller-favorable' => '#991b1b', default => '#374151' };
                ?>
                <tr>
                    <td><?= money((float)$sc['price']) ?> <span style="color:#9ca3af;font-size:8px"><?= $adjLabel ?></span></td>
                    <td class="tr"><?= number_format((float)$sc['sde_multiple'], 2) ?>×</td>
                    <td class="tr" style="color:<?= $vColor ?>;font-weight:bold"><?= h($sc['verdict']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($isPremium && !empty($valuation['benchmark'])): $bm = $valuation['benchmark']; ?>
    <!-- Industry benchmark context -->
    <div class="section">
        <h2>Industry Benchmark Context</h2>
        <table>
            <tbody>
                <?php if ($bm['sde_margin'] !== null): ?>
                <tr><td>SDE margin (most recent SDE ÷ revenue)</td><td class="tr"><?= number_format($bm['sde_margin'] * 100, 1) ?>%</td></tr>
                <?php endif; ?>
                <tr><td><?= h($bm['industry']) ?> SDE multiple range</td><td class="tr"><?= number_format($bm['sde_range'][0], 1) ?>× – <?= number_format($bm['sde_range'][2], 1) ?>× (mid <?= number_format($bm['sde_range'][1], 1) ?>×)</td></tr>
                <tr><td><?= h($bm['industry']) ?> revenue multiple range</td><td class="tr"><?= number_format($bm['rev_range'][0], 2) ?>× – <?= number_format($bm['rev_range'][2], 2) ?>× (mid <?= number_format($bm['rev_range'][1], 2) ?>×)</td></tr>
                <?php if ($bm['implied_sde_multiple'] !== null): ?>
                <tr><td>Asking price ÷ weighted SDE</td><td class="tr"><?= number_format($bm['implied_sde_multiple'], 2) ?>× (<?= h($bm['implied_sde_position']) ?>)</td></tr>
                <?php endif; ?>
                <?php if ($bm['implied_rev_multiple'] !== null): ?>
                <tr><td>Asking price ÷ revenue</td><td class="tr"><?= number_format($bm['implied_rev_multiple'], 2) ?>× (<?= h($bm['implied_rev_position']) ?>)</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Methodology -->
    <div class="section">
        <h2>Methodology Notes</h2>
        <p><strong>SDE Multiple:</strong> Net profit, plus wages the owner took as an expense, plus add-backs (most recent year only): the total benefit to one working owner before paying anyone to replace them. Weighted 3× most recent year, 2× prior, 1× two years prior, over the years provided. Multiples are 2023 published ranges (IBBA Market Pulse Q4 2023; BizBuySell Insight Report 2023, as originally cited), not updated since.</p>
        <p><strong>Revenue Multiple:</strong> Most recent full-year revenue times the industry range. Ignores profit; a check on scale only.</p>
        <p><strong>DCF:</strong> Weighted SDE grown at the historical revenue trend (capped ±5%), discounted at 25%, terminal value 3.0× year-5 cash flow. Overstates value because SDE includes the owner's pay and nothing is reserved for capex or working capital.</p>
        <p><strong>Consensus:</strong> SDE <?= $pct($weights['sde']) ?>, Revenue <?= $pct($weights['revenue']) ?>, DCF <?= $pct($weights['dcf']) ?>, applied alike to low, midpoint and high. No positive weighted SDE means a consensus of $0.</p>
        <div class="disclaimer">This report is for informational purposes only and does not constitute a certified business appraisal, opinion of value, or investment advice. Figures are based solely on the information provided, which Numbrella has not verified. Engage a Certified Business Intermediary (CBI) or Certified Valuation Analyst (CVA) for a credentialed appraisal.</div>
    </div>

    <?php endif; // $valuation ?>

</div>
</body>
</html>
