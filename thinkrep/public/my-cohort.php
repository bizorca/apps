<?php
/**
 * User-facing cohort progress page.
 * Shows the enrolled user their daily scenario and progress.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/cohorts.php';
requireOnboarded();

$userId = getCurrentUserId();
$progress = getUserCohortProgress($userId);

if (!$progress) {
    setFlash('error', 'You are not enrolled in any cohort.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$pageTitle = $progress['enrollment']['cohort_name'];
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= h($progress['enrollment']['cohort_name']) ?></h1>

    <!-- Progress Bar -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700">Day <?= $progress['current_day'] ?> of <?= $progress['enrollment']['duration_days'] ?></span>
            <span class="text-sm text-tr-600 font-bold"><?= $progress['progress_pct'] ?>% complete</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-3">
            <div class="h-3 rounded-full bg-tr-500 transition-all" style="width: <?= $progress['progress_pct'] ?>%"></div>
        </div>
        <p class="text-xs text-gray-400 mt-2"><?= $progress['completed'] ?> of <?= $progress['total'] ?> scenarios completed</p>
    </div>

    <?php if ($progress['enrollment']['completed_at']): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6 text-center">
        <div class="text-4xl mb-2">&#127942;</div>
        <h2 class="text-lg font-bold text-green-800">Cohort Complete!</h2>
        <p class="text-green-600 text-sm mt-1">You finished on <?= date('M j, Y', strtotime($progress['enrollment']['completed_at'])) ?>.</p>
    </div>
    <?php endif; ?>

    <!-- Scenario List -->
    <div class="bg-white rounded-lg shadow divide-y divide-gray-100">
        <?php foreach ($progress['scenarios'] as $s):
            $isMashup = $s['is_mashup'];
            $sessionUrl = $isMashup
                ? url('/mashup-session.php') . '?id=' . $s['scenario_id']
                : url('/session.php') . '?id=' . $s['scenario_id'];
        ?>
        <div class="px-6 py-4 flex items-center justify-between <?= !$s['available'] ? 'opacity-50' : '' ?>">
            <div class="flex items-center space-x-4">
                <span class="text-sm font-mono text-gray-400 w-12">Day <?= $s['day_number'] ?></span>
                <div>
                    <span class="text-sm font-medium text-gray-900"><?= h($s['scenario_title']) ?></span>
                    <div class="flex items-center space-x-2 mt-0.5">
                        <span class="text-xs text-gray-400"><?= h($s['difficulty']) ?></span>
                        <?php if ($isMashup): ?>
                        <span class="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">Mashup</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div>
                <?php if ($s['completed']): ?>
                <span class="text-green-600 text-sm font-semibold">&#10003; Done</span>
                <?php elseif ($s['available']): ?>
                <a href="<?= $sessionUrl ?>" class="bg-tr-600 text-white px-4 py-1.5 rounded-lg text-sm font-semibold hover:bg-tr-700 transition">Start</a>
                <?php else: ?>
                <span class="text-xs text-gray-400">Locked</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
