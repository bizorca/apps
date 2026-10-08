<?php
require_once __DIR__ . '/_bootstrap.php';
requireOnboarded();

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    header('Location: ' . url('/models.php'));
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM tr_mental_models WHERE slug = ?");
$stmt->execute([$slug]);
$model = $stmt->fetch();

if (!$model) {
    setFlash('error', 'Model not found.');
    header('Location: ' . url('/models.php'));
    exit;
}

$userId = getCurrentUserId();

// User stats for this model from blindspot_cache
$stmt = $db->prepare("SELECT * FROM tr_blindspot_cache WHERE user_id = ? AND model_id = ?");
$stmt->execute([$userId, $model['id']]);
$stats = $stmt->fetch();

// Recent responses for scenarios involving this model
$stmt = $db->prepare("
    SELECT r.*, s.title AS scenario_title
    FROM tr_responses r
    JOIN tr_scenarios s ON s.id = r.scenario_id
    WHERE r.user_id = ? AND s.correct_model_id = ?
    ORDER BY r.created_at DESC, r.id DESC
    LIMIT 10
");
$stmt->execute([$userId, $model['id']]);
$recent = $stmt->fetchAll();

$pageTitle = $model['name'];
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <a href="<?= url('/models.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 mb-4 inline-block">&larr; All Models</a>

    <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= h($model['name']) ?></h1>
    <p class="text-lg text-gray-600 mb-6"><?= h($model['short_desc']) ?></p>

    <?php if ($stats): ?>
    <!-- User Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-tr-600"><?= $stats['times_presented'] ?></div>
            <div class="text-sm text-gray-500">Times Seen</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <?php $acc = $stats['times_presented'] > 0 ? round(($stats['times_correct'] / $stats['times_presented']) * 100) : 0; ?>
            <div class="text-2xl font-bold <?= $acc >= 80 ? 'text-green-600' : ($acc >= 50 ? 'text-yellow-600' : 'text-red-600') ?>"><?= $acc ?>%</div>
            <div class="text-sm text-gray-500">Accuracy</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-tr-600"><?= $stats['avg_reasoning'] ?></div>
            <div class="text-sm text-gray-500">Avg Reasoning</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold <?= $stats['overuse_count'] >= 3 ? 'text-orange-500' : 'text-gray-400' ?>"><?= $stats['overuse_count'] ?></div>
            <div class="text-sm text-gray-500">Overuse Count</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Description -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="font-semibold text-gray-900 mb-3">About This Model</h2>
        <div class="text-gray-700 space-y-3">
            <?php foreach (explode("\n\n", $model['full_desc']) as $para): ?>
                <p class="leading-relaxed"><?= h($para) ?></p>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Example -->
    <div class="bg-tr-50 border border-tr-200 rounded-lg p-6 mb-6">
        <h2 class="font-semibold text-tr-800 mb-2">Classic Example</h2>
        <p class="text-tr-700"><?= h($model['example']) ?></p>
    </div>

    <?php if ($model['counter_example']): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
        <h2 class="font-semibold text-yellow-800 mb-2">When It Does NOT Apply</h2>
        <p class="text-yellow-700"><?= h($model['counter_example']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Recent Responses -->
    <?php if (!empty($recent)): ?>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="font-semibold text-gray-900">Your History with This Model</h2>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($recent as $r): ?>
            <a href="<?= url('/journal-entry.php') ?>?id=<?= $r['id'] ?>" class="block px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium text-gray-900"><?= h($r['scenario_title']) ?></span>
                        <span class="text-xs text-gray-400 ml-2"><?= date('M j', strtotime($r['created_at'])) ?></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm <?= $r['model_correct'] ? 'text-green-600' : 'text-red-500' ?>"><?= $r['model_correct'] ? '&#10003;' : '&#10007;' ?></span>
                        <span class="font-mono text-sm font-bold"><?= $r['total_score'] ?>/10</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
