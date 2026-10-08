<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/blindspots.php';
requireOnboarded();

$userId = getCurrentUserId();
$analysis = getBlindspots($userId);

$pageTitle = 'Blind Spots';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Blind Spot Analysis</h1>

    <?php if (empty($analysis['all'])): ?>
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Complete at least 3 sessions to see your blind spot analysis.
        <a href="<?= url('/session.php') ?>" class="text-tr-600 hover:text-tr-700 font-medium block mt-2">Start training</a>
    </div>
    <?php else: ?>

    <?php if (!empty($analysis['confidence_gaps'])): ?>
    <!-- Confidently Wrong -->
    <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-red-800 mb-3">Confidently Wrong</h2>
        <p class="text-sm text-red-600 mb-4">High confidence + low accuracy. These are your biggest blind spots.</p>
        <div class="space-y-2">
            <?php foreach ($analysis['confidence_gaps'] as $gap): ?>
            <div class="flex items-center justify-between bg-white rounded p-3">
                <a href="<?= url('/model-detail.php') ?>?slug=<?= h($gap['model_slug']) ?>" class="font-medium text-red-700 hover:text-red-900"><?= h($gap['model_name']) ?></a>
                <span class="text-sm text-red-600">
                    <?= round($gap['accuracy'] * 100) ?>% accuracy @ <?= $gap['avg_confidence'] ?>/5 confidence
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($analysis['weak_spots'])): ?>
    <!-- Weak Spots -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-yellow-800 mb-3">Weak Spots</h2>
        <p class="text-sm text-yellow-600 mb-4">Models where you score below 50% accuracy.</p>
        <div class="space-y-2">
            <?php foreach ($analysis['weak_spots'] as $weak): ?>
            <div class="flex items-center justify-between bg-white rounded p-3">
                <a href="<?= url('/model-detail.php') ?>?slug=<?= h($weak['model_slug']) ?>" class="font-medium text-yellow-700 hover:text-yellow-900"><?= h($weak['model_name']) ?></a>
                <span class="text-sm text-yellow-600">
                    <?= $weak['times_correct'] ?>/<?= $weak['times_presented'] ?> correct (<?= round($weak['accuracy'] * 100) ?>%)
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($analysis['overused'])): ?>
    <!-- Overused Models -->
    <div class="bg-orange-50 border border-orange-200 rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-orange-800 mb-3">Overused Models</h2>
        <p class="text-sm text-orange-600 mb-4">Models you default to even when they are not the right answer.</p>
        <div class="space-y-2">
            <?php foreach ($analysis['overused'] as $over): ?>
            <div class="flex items-center justify-between bg-white rounded p-3">
                <a href="<?= url('/model-detail.php') ?>?slug=<?= h($over['model_slug']) ?>" class="font-medium text-orange-700 hover:text-orange-900"><?= h($over['model_name']) ?></a>
                <span class="text-sm text-orange-600">
                    Incorrectly chosen <?= $over['overuse_count'] ?> times
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($analysis['strengths'])): ?>
    <!-- Strengths -->
    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-green-800 mb-3">Strengths</h2>
        <p class="text-sm text-green-600 mb-4">Models where you consistently perform well.</p>
        <div class="space-y-2">
            <?php foreach ($analysis['strengths'] as $strong): ?>
            <div class="flex items-center justify-between bg-white rounded p-3">
                <a href="<?= url('/model-detail.php') ?>?slug=<?= h($strong['model_slug']) ?>" class="font-medium text-green-700 hover:text-green-900"><?= h($strong['model_name']) ?></a>
                <span class="text-sm text-green-600">
                    <?= $strong['times_correct'] ?>/<?= $strong['times_presented'] ?> correct (<?= round($strong['accuracy'] * 100) ?>%)
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Full Breakdown -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">All Models</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left">
                        <th class="pb-3 font-medium text-gray-500">Model</th>
                        <th class="pb-3 font-medium text-gray-500 text-center">Presented</th>
                        <th class="pb-3 font-medium text-gray-500 text-center">Correct</th>
                        <th class="pb-3 font-medium text-gray-500 text-center">Accuracy</th>
                        <th class="pb-3 font-medium text-gray-500 text-center">Avg Reasoning</th>
                        <th class="pb-3 font-medium text-gray-500 text-center">Overused</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($analysis['all'] as $row): ?>
                    <tr>
                        <td class="py-3"><a href="<?= url('/model-detail.php') ?>?slug=<?= h($row['model_slug']) ?>" class="text-tr-600 hover:text-tr-700 font-medium"><?= h($row['model_name']) ?></a></td>
                        <td class="py-3 text-center"><?= $row['times_presented'] ?></td>
                        <td class="py-3 text-center"><?= $row['times_correct'] ?></td>
                        <td class="py-3 text-center"><?= $row['times_presented'] > 0 ? round(($row['times_correct'] / $row['times_presented']) * 100) . '%' : '-' ?></td>
                        <td class="py-3 text-center"><?= $row['avg_reasoning'] ?>/5</td>
                        <td class="py-3 text-center"><?= $row['overuse_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
