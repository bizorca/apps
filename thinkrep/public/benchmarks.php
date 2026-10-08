<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/benchmarks.php';
requireOnboarded();

$userId = getCurrentUserId();
$user = getCurrentUser();

// Rebuild benchmarks on demand (in production, do this via cron)
rebuildBenchmarks();

$comparison = getBenchmarkComparison($userId);

$roleLabels = [
    'founder' => 'Founders', 'product_manager' => 'Product Managers',
    'engineering_manager' => 'Engineering Managers', 'sales_director' => 'Sales Directors',
    'marketing_director' => 'Marketing Directors', 'hr_director' => 'HR Directors',
    'consultant' => 'Consultants', 'investor' => 'Investors',
    'data_analyst' => 'Data Analysts', 'operations' => 'Operations',
];
$industryLabels = [
    'saas' => 'SaaS', 'technology' => 'Technology', 'finance' => 'Finance',
    'fintech' => 'Fintech', 'healthcare' => 'Healthcare', 'ecommerce' => 'E-commerce',
    'consulting' => 'Consulting', 'professional_services' => 'Professional Services',
];

// Index personal stats by model_id
$personalByModel = [];
foreach ($comparison['personal'] as $p) {
    $personalByModel[$p['model_id']] = $p;
}

$pageTitle = 'Benchmarks';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Benchmarks</h1>
    <p class="text-gray-500 mb-6">See how you compare to other users by role and industry.</p>

    <?php if (empty($comparison['personal'])): ?>
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Complete some sessions first to see your benchmarks.
    </div>
    <?php else: ?>

    <!-- Your Stats vs Global -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">You vs. All Users</h2>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left bg-gray-50">
                    <th class="px-6 py-3 font-medium text-gray-500">Model</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Your Accuracy</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Global Avg</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Difference</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Your Reasoning</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Global Reasoning</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($comparison['global'] as $g):
                    $p = $personalByModel[$g['model_id']] ?? null;
                    $delta = $p ? round($p['avg_accuracy'] - $g['avg_accuracy']) : null;
                ?>
                <tr>
                    <td class="px-6 py-3 font-medium text-gray-900"><?= h($g['model_name']) ?></td>
                    <td class="px-6 py-3 text-center font-mono"><?= $p ? round($p['avg_accuracy']) . '%' : '-' ?></td>
                    <td class="px-6 py-3 text-center font-mono text-gray-500"><?= round($g['avg_accuracy']) ?>%</td>
                    <td class="px-6 py-3 text-center font-mono font-bold <?= $delta !== null ? ($delta >= 0 ? 'text-green-600' : 'text-red-600') : '' ?>">
                        <?= $delta !== null ? ($delta >= 0 ? '+' : '') . $delta . '%' : '-' ?>
                    </td>
                    <td class="px-6 py-3 text-center"><?= $p ? round($p['avg_reasoning'], 1) : '-' ?></td>
                    <td class="px-6 py-3 text-center text-gray-500"><?= round($g['avg_reasoning'], 1) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Role Benchmark -->
    <?php if (!empty($comparison['role'])): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">
                You vs. <?= h($roleLabels[$user['role_title']] ?? ucfirst($user['role_title'])) ?>
            </h2>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left bg-gray-50">
                    <th class="px-6 py-3 font-medium text-gray-500">Model</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">You</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Role Avg</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Diff</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($comparison['role'] as $r):
                    $p = $personalByModel[$r['model_id']] ?? null;
                    $delta = $p ? round($p['avg_accuracy'] - $r['avg_accuracy']) : null;
                ?>
                <tr>
                    <td class="px-6 py-3 font-medium text-gray-900"><?= h($r['model_name']) ?></td>
                    <td class="px-6 py-3 text-center font-mono"><?= $p ? round($p['avg_accuracy']) . '%' : '-' ?></td>
                    <td class="px-6 py-3 text-center font-mono text-gray-500"><?= round($r['avg_accuracy']) ?>% <span class="text-xs text-gray-400">(n=<?= $r['sample_size'] ?>)</span></td>
                    <td class="px-6 py-3 text-center font-mono font-bold <?= $delta !== null ? ($delta >= 0 ? 'text-green-600' : 'text-red-600') : '' ?>">
                        <?= $delta !== null ? ($delta >= 0 ? '+' : '') . $delta . '%' : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Industry Benchmark -->
    <?php if (!empty($comparison['industry'])): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">
                You vs. <?= h($industryLabels[$user['industry']] ?? ucfirst($user['industry'])) ?> Industry
            </h2>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left bg-gray-50">
                    <th class="px-6 py-3 font-medium text-gray-500">Model</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">You</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Industry Avg</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Diff</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($comparison['industry'] as $ind):
                    $p = $personalByModel[$ind['model_id']] ?? null;
                    $delta = $p ? round($p['avg_accuracy'] - $ind['avg_accuracy']) : null;
                ?>
                <tr>
                    <td class="px-6 py-3 font-medium text-gray-900"><?= h($ind['model_name']) ?></td>
                    <td class="px-6 py-3 text-center font-mono"><?= $p ? round($p['avg_accuracy']) . '%' : '-' ?></td>
                    <td class="px-6 py-3 text-center font-mono text-gray-500"><?= round($ind['avg_accuracy']) ?>% <span class="text-xs text-gray-400">(n=<?= $ind['sample_size'] ?>)</span></td>
                    <td class="px-6 py-3 text-center font-mono font-bold <?= $delta !== null ? ($delta >= 0 ? 'text-green-600' : 'text-red-600') : '' ?>">
                        <?= $delta !== null ? ($delta >= 0 ? '+' : '') . $delta . '%' : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
