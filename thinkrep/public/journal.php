<?php
require_once __DIR__ . '/_bootstrap.php';
requireOnboarded();

$userId = getCurrentUserId();
$db = getDB();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter by model
$modelFilter = (int) ($_GET['model'] ?? 0);

$where = "r.user_id = ?";
$params = [$userId];

if ($modelFilter) {
    $where .= " AND r.chosen_model_id = ?";
    $params[] = $modelFilter;
}

// Total count
$stmt = $db->prepare("SELECT COUNT(*) FROM tr_responses r WHERE $where");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch page
$stmt = $db->prepare("
    SELECT r.*, s.title AS scenario_title, mm.name AS chosen_model_name
    FROM tr_responses r
    JOIN tr_scenarios s ON s.id = r.scenario_id
    JOIN tr_mental_models mm ON mm.id = r.chosen_model_id
    WHERE $where
    ORDER BY r.created_at DESC, r.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$entries = $stmt->fetchAll();

// All models for filter dropdown
$models = $db->query("SELECT id, name FROM tr_mental_models ORDER BY display_order")->fetchAll();

$pageTitle = 'Journal';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Decision Journal</h1>
        <form method="GET" class="flex items-center space-x-2">
            <select name="model" onchange="this.form.submit()" class="border-gray-300 rounded-lg text-sm px-3 py-2 border">
                <option value="0">All Models</option>
                <?php foreach ($models as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $modelFilter == $m['id'] ? 'selected' : '' ?>><?= h($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($entries)): ?>
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        No journal entries yet. <a href="<?= url('/session.php') ?>" class="text-tr-600 hover:text-tr-700 font-medium">Start a session</a> to begin.
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow divide-y divide-gray-100">
        <?php foreach ($entries as $entry): ?>
        <a href="<?= url('/journal-entry.php') ?>?id=<?= $entry['id'] ?>" class="block px-6 py-4 hover:bg-gray-50 transition">
            <div class="flex items-center justify-between">
                <div>
                    <span class="font-medium text-gray-900"><?= h($entry['scenario_title']) ?></span>
                    <div class="text-sm text-gray-500 mt-1">
                        <?= h($entry['chosen_model_name']) ?>
                        <span class="mx-1">&middot;</span>
                        <?= date('M j, Y', strtotime($entry['created_at'])) ?>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-sm <?= $entry['model_correct'] ? 'text-green-600' : 'text-red-500' ?>">
                        <?= $entry['model_correct'] ? '&#10003;' : '&#10007;' ?>
                    </span>
                    <span class="font-mono font-bold text-lg <?= $entry['total_score'] >= 7 ? 'text-green-600' : ($entry['total_score'] >= 4 ? 'text-yellow-600' : 'text-red-600') ?>">
                        <?= $entry['total_score'] ?>/10
                    </span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center mt-6 space-x-2">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&model=<?= $modelFilter ?>" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">&laquo; Prev</a>
        <?php endif; ?>
        <span class="px-4 py-2 text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>&model=<?= $modelFilter ?>" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
