<?php
require_once __DIR__ . '/_bootstrap.php';
requireOnboarded();

$db = getDB();
$models = $db->query("SELECT * FROM tr_mental_models ORDER BY display_order")->fetchAll();

// Get user stats per model if logged in
$userId = getCurrentUserId();
$stats = [];
if ($userId) {
    $stmt = $db->prepare("
        SELECT bc.model_id, bc.times_presented, bc.times_correct, bc.avg_reasoning
        FROM tr_blindspot_cache bc
        WHERE bc.user_id = ?
    ");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['model_id']] = $row;
    }
}

$pageTitle = 'Mental Models';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">10 Mental Models</h1>

    <div class="grid gap-4">
        <?php foreach ($models as $model):
            $s = $stats[$model['id']] ?? null;
            $accuracy = ($s && $s['times_presented'] > 0) ? round(($s['times_correct'] / $s['times_presented']) * 100) : null;
        ?>
        <a href="<?= url('/model-detail.php') ?>?slug=<?= h($model['slug']) ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-900"><?= h($model['name']) ?></h2>
                    <p class="text-gray-600 mt-1"><?= h($model['short_desc']) ?></p>
                </div>
                <?php if ($accuracy !== null): ?>
                <div class="ml-4 text-center">
                    <div class="text-lg font-bold <?= $accuracy >= 80 ? 'text-green-600' : ($accuracy >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                        <?= $accuracy ?>%
                    </div>
                    <div class="text-xs text-gray-400"><?= $s['times_presented'] ?> seen</div>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
