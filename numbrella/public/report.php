<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once NB_ROOT . '/includes/multiples.php';

$db = getDB();

// ── Access: share token (anyone, read-only, no cookie) or the signed-in owner/admin
$shareToken = (string) ($_GET['token'] ?? '');
$id         = (int) ($_GET['id'] ?? 0);
$isShared   = false;
$report     = null;

if ($shareToken !== '') {
    if (preg_match('/^[a-f0-9]{64}$/', $shareToken)) {
        $stmt = $db->prepare("SELECT * FROM nb_reports WHERE share_token = ? AND status = 'complete'");
        $stmt->execute([$shareToken]);
        $report   = $stmt->fetch() ?: null;
        $isShared = (bool) $report;
    }
} else {
    requireLogin();
    $report = findOwnReport($id);
}

if (!$report) {
    notFound();
}

if ($report['status'] === 'draft') {
    if (NB_PAYMENTS_ENABLED && !empty($_GET['paid'])) {
        // Back from Stripe before the webhook landed: wait for it.
        header('Refresh: 3');
        echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<p style="font-family:sans-serif;padding:2rem">Payment received. Finishing your report&hellip;</p>';
        exit;
    }
    redirect(NB_PAYMENTS_ENABLED ? '/checkout.php?id=' . (int) $report['id']
                                 : '/wizard.php?step=' . (int) $report['wizard_step'] . '&id=' . (int) $report['id']);
}

$valuation = $report['valuation_json']  ? json_decode((string) $report['valuation_json'],  true) : null;
$riskFlags = $report['risk_flags_json'] ? json_decode((string) $report['risk_flags_json'], true) : [];
$isPremium = $report['tier'] === 'premium';
$industry  = getMultiplesForIndustry($report['industry_key'])['name'];

$verdictConfig = [
    'below'  => ['label' => 'Below range — buyer-favorable',         'color' => 'bg-green-50 text-green-800 border-green-200'],
    'within' => ['label' => 'Within estimated range',                 'color' => 'bg-blue-50 text-blue-800 border-blue-200'],
    'above'  => ['label' => 'Above range — negotiate or walk away',   'color' => 'bg-red-50 text-red-800 border-red-200'],
];

$addbacks      = !empty($report['addbacks_json']) ? (json_decode((string) $report['addbacks_json'], true) ?? []) : [];
$businessTypes = NB_BUSINESS_TYPES;
$weights       = $valuation['summary']['weights'] ?? ['sde' => 0.60, 'revenue' => 0.25, 'dcf' => 0.15];
$pct           = static fn(float $w): string => (string) round($w * 100) . '%';
$completedAt   = strtotime(($report['completed_at'] ?? $report['created_at']) . ' UTC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($report['business_name'] ?: 'Valuation Report') ?> — <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<?php if (!$isShared): ?>
<?php include NB_ROOT . '/templates/header.php'; ?>
<?php endif; ?>

<main class="max-w-3xl mx-auto px-4 py-8">

    <!-- ── Actions bar ─────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-8 no-print">
        <div>
            <?php if (!$isShared): ?>
                <a href="<?= h(url('/dashboard.php')) ?>" class="text-sm text-gray-500 hover:text-gray-700">← My Reports</a>
            <?php else: ?>
                <span class="text-sm text-gray-400">Shared report &middot; <?= h(APP_NAME) ?></span>
            <?php endif; ?>
        </div>
        <?php if (!$isShared): ?>
        <div class="flex flex-wrap gap-3">
            <?php if (!NB_PAYMENTS_ENABLED && (int) $report['user_id'] === getCurrentUserId()): ?>
                <a href="<?= h(url('/wizard.php?step=1&id=' . (int) $report['id'])) ?>"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900 border border-gray-300 rounded-lg px-4 py-2 transition-colors">
                    Edit inputs
                </a>
            <?php endif; ?>
            <a href="<?= h(url('/report-pdf.php?id=' . (int) $report['id'])) ?>"
                class="text-sm font-medium text-gray-600 hover:text-gray-900 border border-gray-300 rounded-lg px-4 py-2 transition-colors">
                ↓ Download PDF
            </a>
            <?php if ($report['share_token']): ?>
                <button type="button" onclick="copyShareLink()" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 border border-indigo-200 rounded-lg px-4 py-2 transition-colors">
                    Copy share link
                </button>
                <input type="hidden" id="share_path" value="<?= h(url('/report.php?token=' . $report['share_token'])) ?>">
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Cover ───────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
        <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-1"><?= h(APP_NAME) ?> Valuation Report</p>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">
                    <?= h($report['business_name'] ?: 'Business Valuation') ?>
                </h1>
                <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-gray-500">
                    <span><?= h($industry) ?></span>
                    <span>&middot;</span>
                    <span><?= h($businessTypes[$report['business_type']] ?? $report['business_type']) ?></span>
                    <span>&middot;</span>
                    <span><?= h((int)$report['years_in_operation']) ?> years in operation</span>
                    <?php if ((int)$report['num_employees'] > 0): ?>
                        <span>&middot;</span>
                        <span><?= h($report['num_employees']) ?> <?= (int)$report['num_employees'] === 1 ? 'employee' : 'employees' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sm:text-right shrink-0">
                <?php if (NB_PAYMENTS_ENABLED): ?>
                <span class="text-xs font-semibold px-2 py-1 rounded-full <?= $isPremium ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' ?>">
                    <?= $isPremium ? 'Premium' : 'Standard' ?>
                </span>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-2"><?= h(date('F j, Y', $completedAt)) ?></p>
            </div>
        </div>

        <?php if ($valuation): ?>
        <!-- Executive Summary -->
        <div class="mt-6 pt-6 border-t border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Estimated Value Range</h2>
            <div class="flex items-baseline gap-3">
                <span class="text-3xl sm:text-4xl font-extrabold text-gray-900">
                    <?= money((float)$valuation['summary']['consensus_low']) ?>
                    <span class="text-2xl text-gray-400 font-semibold">–</span>
                    <?= money((float)$valuation['summary']['consensus_high']) ?>
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Midpoint: <strong><?= money((float)$valuation['summary']['consensus_mid']) ?></strong>
                &middot; Weighted consensus across three valuation methods.
            </p>

            <?php foreach ($valuation['warnings'] ?? [] as $warning): ?>
                <div class="mt-4 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-900"><?= h($warning) ?></div>
            <?php endforeach; ?>

            <?php if ($valuation['summary']['asking_verdict'] && $valuation['summary']['asking_price']): ?>
                <?php $vc = $verdictConfig[$valuation['summary']['asking_verdict']]; ?>
                <div class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium <?= $vc['color'] ?>">
                    Asking price <?= money((float)$valuation['summary']['asking_price']) ?>:
                    <?= h($vc['label']) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($valuation): ?>

    <!-- ── Method 1: SDE Multiple ─────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-1">Method 1 — Seller's Discretionary Earnings (SDE) Multiple</h2>
        <p class="text-sm text-gray-500 mb-6">Primary method for main-street business acquisitions. Adds back owner compensation and non-recurring expenses to compute the true earning power of the business.</p>

        <?php $sde = $valuation['methods']['sde']; ?>

        <!-- SDE calculation -->
        <div class="bg-gray-50 rounded-xl p-5 mb-6">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">SDE Calculation (most recent year)</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Net profit (most recent year)</span>
                    <span class="font-medium text-gray-900"><?= money((float)$report['net_profit_y3']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">+ Owner wages deducted as an expense</span>
                    <span class="font-medium text-gray-900"><?= money((float)$report['owner_salary']) ?></span>
                </div>
                <?php foreach ($addbacks as $ab): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">+ Add-back: <?= h($ab['desc']) ?></span>
                    <span class="font-medium text-gray-900"><?= money((float)$ab['amount']) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold">
                    <span class="text-gray-800">= SDE (Year 3)</span>
                    <span class="text-indigo-700"><?= money((float)$sde['sde']) ?></span>
                </div>
                <div class="flex justify-between text-xs text-gray-500 pt-1">
                    <span><?= count($sde['years_used'] ?? [1, 2, 3]) === 3 ? 'Weighted 3-year SDE (3×Y3 + 2×Y2 + 1×Y1 ÷ 6)' : 'Weighted SDE over the ' . count($sde['years_used']) . ' year(s) provided' ?></span>
                    <span class="font-medium"><?= money((float)$sde['weighted_sde']) ?></span>
                </div>
            </div>
        </div>

        <!-- Multiple table -->
        <div class="mb-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                <?= h($industry) ?> SDE Multiples (2023 published ranges)
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <?php
                $cols = [
                    ['label' => 'Conservative', 'mult' => $sde['multiple_floor'], 'val' => $sde['low'],  'color' => 'border-gray-200'],
                    ['label' => 'Midpoint',      'mult' => $sde['multiple_mid'],   'val' => $sde['mid'],  'color' => 'border-indigo-300 bg-indigo-50'],
                    ['label' => 'Optimistic',    'mult' => $sde['multiple_ceiling'],'val' => $sde['high'],'color' => 'border-gray-200'],
                ];
                foreach ($cols as $col): ?>
                <div class="rounded-xl border <?= $col['color'] ?> p-4 text-center">
                    <p class="text-xs text-gray-500 mb-1"><?= $col['label'] ?></p>
                    <p class="text-lg font-bold text-gray-900"><?= money((float)$col['val']) ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= number_format($col['mult'], 1) ?>× SDE</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── Method 2: Revenue Multiple ────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-1">Method 2 — Revenue Multiple</h2>
        <p class="text-sm text-gray-500 mb-6">Secondary method, applied to most recent full-year revenue. It ignores profit entirely, so it is a sanity check on size, not a price on its own<?= !empty($valuation['summary']['no_earnings']) ? ', and with no earnings it is left out of the consensus' : '' ?>.</p>

        <?php $rev = $valuation['methods']['revenue']; ?>

        <div class="bg-gray-50 rounded-xl p-5 mb-6 text-sm">
            <div class="flex justify-between font-semibold">
                <span class="text-gray-700">Most recent full-year revenue</span>
                <span class="text-indigo-700"><?= money((float)$rev['revenue']) ?></span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <?php
            $cols = [
                ['label' => 'Conservative', 'mult' => $rev['multiple_floor'], 'val' => $rev['low'],  'color' => 'border-gray-200'],
                ['label' => 'Midpoint',      'mult' => $rev['multiple_mid'],   'val' => $rev['mid'],  'color' => 'border-indigo-300 bg-indigo-50'],
                ['label' => 'Optimistic',    'mult' => $rev['multiple_ceiling'],'val' => $rev['high'],'color' => 'border-gray-200'],
            ];
            foreach ($cols as $col): ?>
            <div class="rounded-xl border <?= $col['color'] ?> p-4 text-center">
                <p class="text-xs text-gray-500 mb-1"><?= $col['label'] ?></p>
                <p class="text-lg font-bold text-gray-900"><?= money((float)$col['val']) ?></p>
                <p class="text-xs text-gray-400 mt-1"><?= number_format($col['mult'], 2) ?>× revenue</p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Method 3: DCF ──────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
        <div class="flex items-start justify-between gap-4 mb-1">
            <h2 class="text-lg font-bold text-gray-900">Method 3 — Discounted Cash Flow</h2>
            <span class="shrink-0 text-xs font-semibold px-2 py-1 rounded-full bg-yellow-50 text-yellow-700 border border-yellow-200">Illustrative</span>
        </div>
        <p class="text-sm text-gray-500 mb-2">Projects future cash flows and discounts them to present value. Treat as a sanity check — DCF output for small businesses is highly sensitive to the growth rate assumption.</p>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3 text-xs text-yellow-800 mb-6">
            Assumptions: cash flow = weighted SDE (still includes the owner's own pay; nothing deducted for equipment replacement or working capital); discount rate 25%; terminal value at 3.0× year-5 cash flow; growth rate capped at ±5%. Those assumptions put the DCF at roughly 3–4× SDE in any industry, which is why it carries only <?= $pct($weights['dcf']) ?> of the consensus.
        </div>

        <?php $dcf = $valuation['methods']['dcf']; ?>

        <!-- Projection table -->
        <div class="overflow-x-auto mb-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-semibold text-gray-500 pb-2 pr-4">Year</th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2 pr-4">Projected FCF</th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2">Present Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($dcf['projection'] as $row): ?>
                    <tr>
                        <td class="py-2 pr-4 text-gray-600">Year <?= $row['year'] ?></td>
                        <td class="py-2 pr-4 text-right text-gray-900"><?= money((float)$row['fcf']) ?></td>
                        <td class="py-2 text-right text-gray-600"><?= money((float)$row['pv']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="border-t border-gray-300">
                        <td class="py-2 pr-4 text-gray-600">Terminal value</td>
                        <td class="py-2 pr-4 text-right text-gray-900"><?= money((float)$dcf['terminal_value']) ?></td>
                        <td class="py-2 text-right text-gray-600"><?= money((float)$dcf['terminal_pv']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <?php
            $cols = [
                ['label' => 'Conservative (–20%)', 'val' => $dcf['low'],  'color' => 'border-gray-200'],
                ['label' => 'Base Case',             'val' => $dcf['mid'],  'color' => 'border-indigo-300 bg-indigo-50'],
                ['label' => 'Optimistic (+20%)',     'val' => $dcf['high'], 'color' => 'border-gray-200'],
            ];
            foreach ($cols as $col): ?>
            <div class="rounded-xl border <?= $col['color'] ?> p-4 text-center">
                <p class="text-xs text-gray-500 mb-1"><?= $col['label'] ?></p>
                <p class="text-lg font-bold text-gray-900"><?= money((float)$col['val']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-gray-400 mt-3">Growth rate applied: <?= number_format((float)$dcf['growth_rate'] * 100, 1) ?>% (derived from historical revenue trend, capped at ±5%).</p>
    </div>

    <!-- ── Risk Flags ─────────────────────────────────────────────────── -->
    <?php if (!empty($riskFlags)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-1">Risk Flags</h2>
        <p class="text-sm text-gray-500 mb-6">Issues worth investigating before you sign anything.</p>

        <div class="space-y-4">
            <?php foreach ($riskFlags as $flag):
                $levelConfig = [
                    'high'   => ['bg' => 'bg-red-50',    'border' => 'border-red-200',   'dot' => 'bg-red-500',   'text' => 'text-red-900',   'label' => 'High Risk'],
                    'medium' => ['bg' => 'bg-yellow-50', 'border' => 'border-yellow-200','dot' => 'bg-yellow-500','text' => 'text-yellow-900', 'label' => 'Medium Risk'],
                    'low'    => ['bg' => 'bg-blue-50',   'border' => 'border-blue-200',  'dot' => 'bg-blue-400',  'text' => 'text-blue-900',   'label' => 'Low'],
                ];
                $lc = $levelConfig[$flag['level']] ?? $levelConfig['medium'];
            ?>
            <div class="rounded-xl border <?= $lc['border'] ?> <?= $lc['bg'] ?> p-5">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full <?= $lc['dot'] ?>"></span>
                    <span class="text-xs font-semibold <?= $lc['text'] ?> uppercase tracking-wide"><?= $lc['label'] ?></span>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1"><?= h($flag['title']) ?></h3>
                <p class="text-sm text-gray-700 leading-relaxed"><?= h($flag['body']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Premium: Cash Flow Projection ─────────────────────────────── -->
    <?php if ($isPremium && !empty($valuation['cash_flow_projection'])): ?>
    <?php $cfp = $valuation['cash_flow_projection']; ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-1">3-Year Cash Flow Projection</h2>
        <p class="text-sm text-gray-500 mb-6">
            Projected revenue and SDE over three years, starting from the most recent year and assuming <?= number_format((float)$cfp['growth_rate'] * 100, 1) ?>% annual growth (historical trend, capped at ±5%).
        </p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-semibold text-gray-500 pb-2 pr-4"></th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2 pr-4">Year 1</th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2 pr-4">Year 2</th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2">Year 3</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr>
                        <td class="py-2 pr-4 text-gray-600">Projected Revenue</td>
                        <?php foreach ($cfp['years'] as $y): ?>
                        <td class="py-2 pr-4 text-right text-gray-900 font-medium"><?= money((float)$y['revenue']) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4 text-gray-600">Projected SDE</td>
                        <?php foreach ($cfp['years'] as $y): ?>
                        <td class="py-2 pr-4 text-right text-indigo-700 font-semibold"><?= money((float)$y['sde']) ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Premium: Scenario Analysis ────────────────────────────────── -->
    <?php if ($isPremium && !empty($valuation['scenario_analysis'])): ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-1">Scenario Analysis</h2>
        <p class="text-sm text-gray-500 mb-6">How does the deal look at different price points relative to the estimated midpoint?</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-semibold text-gray-500 pb-2 pr-4">Price</th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2 pr-4">SDE Multiple</th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2">Assessment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($valuation['scenario_analysis'] as $sc):
                        $rowColor = match($sc['verdict']) {
                            'Buyer-favorable' => 'text-green-700',
                            'Seller-favorable' => 'text-red-700',
                            default => 'text-gray-600',
                        };
                        $adjLabel = $sc['adjustment'] == 0 ? 'Midpoint (0%)' : sprintf('%+.0f%%', $sc['adjustment'] * 100);
                    ?>
                    <tr>
                        <td class="py-2 pr-4 font-medium text-gray-900"><?= money((float)$sc['price']) ?> <span class="text-xs font-normal text-gray-400"><?= $adjLabel ?></span></td>
                        <td class="py-2 pr-4 text-right text-gray-600"><?= number_format((float)$sc['sde_multiple'], 2) ?>×</td>
                        <td class="py-2 text-right font-medium <?= $rowColor ?>"><?= h($sc['verdict']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Industry benchmark context ─────────────────────────────── -->
    <?php if ($isPremium && !empty($valuation['benchmark'])): ?>
    <?php $bm = $valuation['benchmark']; ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-1">Industry Benchmark Context</h2>
        <p class="text-sm text-gray-500 mb-6">How this business, and its asking price, sit against <?= h($bm['industry']) ?> ranges.</p>
        <div class="space-y-3 text-sm">
            <?php if ($bm['sde_margin'] !== null): ?>
            <div class="flex justify-between gap-4">
                <span class="text-gray-600">SDE margin (most recent SDE ÷ revenue)</span>
                <span class="font-medium text-gray-900"><?= number_format($bm['sde_margin'] * 100, 1) ?>%</span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between gap-4">
                <span class="text-gray-600">Industry SDE multiple range</span>
                <span class="font-medium text-gray-900"><?= number_format($bm['sde_range'][0], 1) ?>× – <?= number_format($bm['sde_range'][2], 1) ?>× (mid <?= number_format($bm['sde_range'][1], 1) ?>×)</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-gray-600">Industry revenue multiple range</span>
                <span class="font-medium text-gray-900"><?= number_format($bm['rev_range'][0], 2) ?>× – <?= number_format($bm['rev_range'][2], 2) ?>× (mid <?= number_format($bm['rev_range'][1], 2) ?>×)</span>
            </div>
            <?php if ($bm['implied_sde_multiple'] !== null): ?>
            <div class="flex justify-between gap-4 border-t border-gray-100 pt-3">
                <span class="text-gray-600">Asking price as a multiple of weighted SDE</span>
                <span class="font-semibold text-gray-900"><?= number_format($bm['implied_sde_multiple'], 2) ?>× <span class="font-normal text-gray-500">(<?= h($bm['implied_sde_position']) ?>)</span></span>
            </div>
            <?php endif; ?>
            <?php if ($bm['implied_rev_multiple'] !== null): ?>
            <div class="flex justify-between gap-4">
                <span class="text-gray-600">Asking price as a multiple of revenue</span>
                <span class="font-semibold text-gray-900"><?= number_format($bm['implied_rev_multiple'], 2) ?>× <span class="font-normal text-gray-500">(<?= h($bm['implied_rev_position']) ?>)</span></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Methodology Notes ──────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Methodology Notes</h2>
        <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
            <p><strong class="text-gray-800">SDE Multiple:</strong> Seller's Discretionary Earnings is the usual valuation basis for main-street acquisitions under about $5M: net profit, plus any wages the owner took as an expense, plus personal or one-time costs a new owner wouldn't carry (add-backs, most recent year only). It is the total economic benefit to a single working owner, before paying anyone to replace them. This report weights the trailing years 3× most recent, 2× prior, 1× two years prior, over the years provided, to damp a single odd year. Multiples are 2023 published ranges (IBBA Market Pulse Q4 2023 and BizBuySell's 2023 Insight Report, as cited by the original methodology) and have not been updated since.</p>
            <p><strong class="text-gray-800">Revenue Multiple:</strong> Applied to most recent full-year revenue using the same industry ranges. It says nothing about profit, so treat it as a check on scale, never a price by itself.</p>
            <p><strong class="text-gray-800">DCF:</strong> Five years of weighted SDE grown at the historical revenue trend (capped at ±5%), discounted at 25%, plus a terminal value of 3.0× year-5 cash flow. Because SDE still contains the owner's pay and nothing is set aside for capex or working capital, this overstates what an investor would pay; read it as a ceiling-flavored cross-check.</p>
            <p><strong class="text-gray-800">Consensus Range:</strong> SDE <?= $pct($weights['sde']) ?>, revenue <?= $pct($weights['revenue']) ?>, DCF <?= $pct($weights['dcf']) ?>, applied the same way to the low, midpoint and high. A business with no positive weighted SDE gets a consensus of $0: its value is what its assets would fetch, which this report doesn't estimate.</p>
            <p class="text-xs text-gray-400 mt-4 pt-4 border-t border-gray-100">
                <strong>Disclaimer:</strong> This report is for informational purposes only and does not constitute a certified business appraisal, opinion of value, or investment advice. Figures are based solely on the information provided, which Numbrella has not verified. Engage a Certified Business Intermediary (CBI) or Certified Valuation Analyst (CVA) for a credentialed appraisal.
            </p>
        </div>
    </div>

    <?php endif; // $valuation ?>

</main>

<?php include NB_ROOT . '/templates/footer.php'; ?>

<script>
function copyShareLink() {
    const path = document.getElementById('share_path')?.value;
    if (!path) return;
    const url = location.origin + path;
    navigator.clipboard.writeText(url).then(() => {
        alert('Share link copied to clipboard.');
    });
}
</script>

</body>
</html>
