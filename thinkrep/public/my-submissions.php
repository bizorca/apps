<?php
require_once __DIR__ . '/_bootstrap.php';
requireOnboarded();

$userId = getCurrentUserId();
$db = getDB();

$stmt = $db->prepare("
    SELECT ss.*, mm.name AS model_name
    FROM tr_scenario_submissions ss
    JOIN tr_mental_models mm ON mm.id = ss.correct_model_id
    WHERE ss.user_id = ?
    ORDER BY ss.created_at DESC, ss.id DESC
");
$stmt->execute([$userId]);
$submissions = $stmt->fetchAll();

$statusColors = [
    'pending' => 'bg-yellow-100 text-yellow-700',
    'approved' => 'bg-green-100 text-green-700',
    'rejected' => 'bg-red-100 text-red-700',
];

$pageTitle = 'My Submissions';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">My Submissions</h1>
        <a href="<?= url('/submit-scenario.php') ?>" class="bg-tr-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-tr-700 transition">
            Submit New
        </a>
    </div>

    <?php if (empty($submissions)): ?>
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        You haven't submitted any scenarios yet.
        <a href="<?= url('/submit-scenario.php') ?>" class="text-tr-600 hover:text-tr-700 font-medium block mt-2">Submit your first scenario</a>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow divide-y divide-gray-100">
        <?php foreach ($submissions as $sub): ?>
        <div class="px-6 py-4">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-medium text-gray-900"><?= h($sub['title']) ?></h3>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= h($sub['model_name']) ?> &middot; <?= h($sub['difficulty']) ?> &middot; <?= date('M j, Y', strtotime($sub['created_at'])) ?>
                    </p>
                </div>
                <span class="inline-block text-xs font-semibold px-2.5 py-1 rounded-full <?= $statusColors[$sub['status']] ?? '' ?>">
                    <?= ucfirst($sub['status']) ?>
                </span>
            </div>
            <?php if ($sub['admin_notes']): ?>
            <div class="mt-2 bg-gray-50 rounded p-3 text-sm text-gray-600">
                <span class="font-medium">Reviewer notes:</span> <?= h($sub['admin_notes']) ?>
            </div>
            <?php endif; ?>
            <p class="mt-2 text-sm text-gray-600 line-clamp-2"><?= h(substr($sub['situation'], 0, 200)) ?>...</p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
