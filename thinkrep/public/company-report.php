<?php
/**
 * Manager Insight Report — printable team/org performance summary.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/companies.php';
require_once TR_ROOT . '/includes/reports.php';
requireOnboarded();

$userId = getCurrentUserId();
$company = getUserCompany($userId);

if (!$company || !isCompanyManager($company['id'], $userId)) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$days = max(7, min(90, (int) ($_GET['days'] ?? 30)));
$report = generateInsightReport($company['id'], null, $days);

$pageTitle = 'Insight Report';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6 no-print">
        <a href="<?= url('/company.php') ?>" class="text-sm text-tr-600 hover:text-tr-700">&larr; Company Dashboard</a>
        <div class="flex items-center space-x-3">
            <form method="GET" class="flex items-center space-x-2">
                <select name="days" onchange="this.form.submit()" class="text-sm border-gray-300 rounded-lg px-3 py-1.5 border">
                    <option value="7" <?= $days === 7 ? 'selected' : '' ?>>7 days</option>
                    <option value="14" <?= $days === 14 ? 'selected' : '' ?>>14 days</option>
                    <option value="30" <?= $days === 30 ? 'selected' : '' ?>>30 days</option>
                    <option value="60" <?= $days === 60 ? 'selected' : '' ?>>60 days</option>
                    <option value="90" <?= $days === 90 ? 'selected' : '' ?>>90 days</option>
                </select>
            </form>
            <button onclick="window.print()" class="text-sm bg-tr-600 text-white px-4 py-1.5 rounded-lg hover:bg-tr-700 transition">Print / PDF</button>
        </div>
    </div>

    <!-- Report Header -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h1 class="text-2xl font-bold text-gray-900"><?= h($company['name']) ?> — Insight Report</h1>
        <p class="text-gray-500 mt-1">Last <?= $days ?> days &middot; Generated <?= date('M j, Y') ?></p>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-tr-600"><?= (int) $report['overall']['total_sessions'] ?></div>
            <div class="text-xs text-gray-500">Sessions</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-tr-600"><?= (int) $report['overall']['active_users'] ?></div>
            <div class="text-xs text-gray-500">Active Users</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <?php $scoreDelta = $report['previous']['avg_score'] ? round($report['overall']['avg_score'] - $report['previous']['avg_score'], 1) : null; ?>
            <div class="text-2xl font-bold text-tr-600"><?= $report['overall']['avg_score'] ? round($report['overall']['avg_score'], 1) : '-' ?></div>
            <div class="text-xs text-gray-500">Avg Score
                <?php if ($scoreDelta !== null): ?>
                <span class="<?= $scoreDelta >= 0 ? 'text-green-600' : 'text-red-600' ?>">(<?= $scoreDelta >= 0 ? '+' : '' ?><?= $scoreDelta ?>)</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <?php $accDelta = $report['previous']['accuracy'] ? round($report['overall']['accuracy'] - $report['previous']['accuracy']) : null; ?>
            <div class="text-2xl font-bold text-tr-600"><?= $report['overall']['accuracy'] ? round($report['overall']['accuracy']) . '%' : '-' ?></div>
            <div class="text-xs text-gray-500">Accuracy
                <?php if ($accDelta !== null): ?>
                <span class="<?= $accDelta >= 0 ? 'text-green-600' : 'text-red-600' ?>">(<?= $accDelta >= 0 ? '+' : '' ?><?= $accDelta ?>%)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    <?php if (!empty($report['recommendations'])): ?>
    <div class="bg-tr-50 border border-tr-200 rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-tr-800 mb-3">Recommendations</h2>
        <ul class="space-y-2">
            <?php foreach ($report['recommendations'] as $rec): ?>
            <li class="text-sm text-tr-700"><?= $rec ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="grid md:grid-cols-2 gap-6 mb-6">
        <!-- Weakest Models -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <h2 class="text-sm font-semibold text-red-800 mb-3">Weakest Models</h2>
            <?php foreach ($report['weakest'] as $w): ?>
            <div class="flex items-center justify-between mb-2 text-sm">
                <span class="text-red-700"><?= h($w['model_name']) ?></span>
                <span class="text-red-600 font-mono"><?= round($w['accuracy']) ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Strongest Models -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
            <h2 class="text-sm font-semibold text-green-800 mb-3">Strongest Models</h2>
            <?php foreach ($report['strongest'] as $s): ?>
            <div class="flex items-center justify-between mb-2 text-sm">
                <span class="text-green-700"><?= h($s['model_name']) ?></span>
                <span class="text-green-600 font-mono"><?= round($s['accuracy']) ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Full Model Breakdown -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Model Breakdown</h2>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left bg-gray-50">
                    <th class="px-6 py-3 font-medium text-gray-500">Model</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Presented</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Correct</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Accuracy</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Avg Reasoning</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($report['model_breakdown'] as $row): ?>
                <tr>
                    <td class="px-6 py-3 font-medium text-gray-900"><?= h($row['model_name']) ?></td>
                    <td class="px-6 py-3 text-center"><?= $row['presentations'] ?></td>
                    <td class="px-6 py-3 text-center"><?= $row['correct'] ?></td>
                    <td class="px-6 py-3 text-center font-mono <?= $row['accuracy'] >= 70 ? 'text-green-600' : ($row['accuracy'] >= 40 ? 'text-yellow-600' : 'text-red-600') ?>"><?= round($row['accuracy']) ?>%</td>
                    <td class="px-6 py-3 text-center"><?= round($row['avg_reasoning'], 1) ?>/5</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Performers + Needs Attention -->
    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Top Performers</h2>
            <?php if (empty($report['top_performers'])): ?>
            <p class="text-sm text-gray-500">Not enough data.</p>
            <?php else: ?>
            <?php foreach ($report['top_performers'] as $i => $tp): ?>
            <div class="flex items-center justify-between mb-2 text-sm">
                <span class="text-gray-700"><?= $i + 1 ?>. <?= h($tp['name'] ?: $tp['email']) ?></span>
                <span class="font-mono text-green-600"><?= round($tp['avg_score'], 1) ?> avg / <?= round($tp['accuracy']) ?>%</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Needs Attention</h2>
            <?php if (empty($report['needs_attention'])): ?>
            <p class="text-sm text-gray-500">Everyone is performing well.</p>
            <?php else: ?>
            <?php foreach ($report['needs_attention'] as $na): ?>
            <div class="flex items-center justify-between mb-2 text-sm">
                <span class="text-gray-700"><?= h($na['name'] ?: $na['email']) ?></span>
                <span class="text-sm text-gray-500"><?= (int) $na['sessions'] ?> sessions / <?= round($na['accuracy']) ?>% acc</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
