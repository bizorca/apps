<?php
require_once __DIR__ . '/_bootstrap.php';
requireLogin();

$user = getCurrentUser();
if (empty($user['is_admin'])) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/admin-submissions.php'));
        exit;
    }

    $submissionId = (int) ($_POST['submission_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (!$submissionId || !in_array($action, ['approve', 'reject'])) {
        setFlash('error', 'Invalid action.');
        header('Location: ' . url('/admin-submissions.php'));
        exit;
    }

    if ($action === 'approve') {
        // Get submission details
        $stmt = $db->prepare("SELECT * FROM tr_scenario_submissions WHERE id = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch();

        if ($sub && $sub['status'] === 'pending') {
            // Create the actual scenario
            $stmt = $db->prepare("
                INSERT INTO tr_scenarios (title, situation, correct_model_id, ideal_reasoning, distractor_explanation, difficulty, role_tags, industry_tags)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sub['title'],
                $sub['situation'],
                $sub['correct_model_id'],
                $sub['ideal_reasoning'],
                $adminNotes ?: 'Community-submitted scenario.',
                $sub['difficulty'],
                $sub['role_tags'],
                $sub['industry_tags'],
            ]);
            $scenarioId = (int) $db->lastInsertId();

            // Create choices: correct model + distractors
            $distractorIds = json_decode($sub['distractor_model_ids'], true) ?: [];
            $order = 1;

            // Correct choice
            $stmt = $db->prepare("INSERT INTO tr_scenario_choices (scenario_id, model_id, is_correct, display_order) VALUES (?, ?, 1, ?)");
            $stmt->execute([$scenarioId, $sub['correct_model_id'], $order++]);

            // Distractor choices
            $stmt = $db->prepare("INSERT INTO tr_scenario_choices (scenario_id, model_id, is_correct, display_order) VALUES (?, ?, 0, ?)");
            foreach ($distractorIds as $did) {
                $stmt->execute([$scenarioId, $did, $order++]);
            }

            // Mark submission approved
            $stmt = $db->prepare("UPDATE tr_scenario_submissions SET status = 'approved', admin_notes = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->execute([$adminNotes, $submissionId]);

            setFlash('success', 'Scenario approved and added to the training pool.');
        }
    } else {
        // Reject
        $stmt = $db->prepare("UPDATE tr_scenario_submissions SET status = 'rejected', admin_notes = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$adminNotes, $submissionId]);
        setFlash('success', 'Scenario rejected.');
    }

    header('Location: ' . url('/admin-submissions.php'));
    exit;
}

// Load pending submissions
$filter = $_GET['status'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'rejected', 'all'])) $filter = 'pending';

$whereClause = $filter === 'all' ? '' : "WHERE ss.status = ?";
$params = $filter === 'all' ? [] : [$filter];

$stmt = $db->prepare("
    SELECT ss.*, mm.name AS model_name, u.name AS author_name, u.email AS author_email
    FROM tr_scenario_submissions ss
    JOIN tr_mental_models mm ON mm.id = ss.correct_model_id
    JOIN users u ON u.id = ss.user_id
    $whereClause
    ORDER BY ss.created_at DESC, ss.id DESC
");
$stmt->execute($params);
$submissions = $stmt->fetchAll();

$models = $db->query("SELECT id, name FROM tr_mental_models ORDER BY display_order")->fetchAll();
$modelMap = [];
foreach ($models as $m) $modelMap[$m['id']] = $m['name'];

$pageTitle = 'Review Submissions';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Review Scenario Submissions</h1>

    <!-- Filter tabs -->
    <div class="flex space-x-4 mb-6">
        <?php foreach (['pending', 'approved', 'rejected', 'all'] as $tab): ?>
        <a href="?status=<?= $tab ?>"
           class="text-sm font-medium px-3 py-1.5 rounded-full <?= $filter === $tab ? 'bg-tr-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
            <?= ucfirst($tab) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($submissions)): ?>
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">No <?= $filter ?> submissions.</div>
    <?php endif; ?>

    <?php foreach ($submissions as $sub): ?>
    <div class="bg-white rounded-lg shadow p-6 mb-4" x-data="{ expanded: false }">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <h3 class="font-semibold text-gray-900"><?= h($sub['title']) ?></h3>
                <p class="text-sm text-gray-500 mt-1">
                    by <?= h($sub['author_name'] ?: $sub['author_email']) ?>
                    &middot; <?= h($sub['model_name']) ?>
                    &middot; <?= h($sub['difficulty']) ?>
                    &middot; <?= date('M j, Y', strtotime($sub['created_at'])) ?>
                </p>
            </div>
            <button @click="expanded = !expanded" class="text-tr-600 text-sm font-medium ml-4">
                <span x-text="expanded ? 'Collapse' : 'Review'"></span>
            </button>
        </div>

        <div x-show="expanded" x-cloak class="mt-4 space-y-4 border-t border-gray-100 pt-4">
            <div>
                <h4 class="text-xs font-semibold text-gray-400 uppercase mb-1">Scenario</h4>
                <div class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($sub['situation']) ?></div>
            </div>

            <div>
                <h4 class="text-xs font-semibold text-gray-400 uppercase mb-1">Correct Model</h4>
                <p class="text-sm text-gray-700 font-medium"><?= h($sub['model_name']) ?></p>
            </div>

            <div>
                <h4 class="text-xs font-semibold text-gray-400 uppercase mb-1">Distractors</h4>
                <p class="text-sm text-gray-700">
                    <?php
                    $dids = json_decode($sub['distractor_model_ids'], true) ?: [];
                    echo h(implode(', ', array_map(fn($id) => $modelMap[$id] ?? "ID:$id", $dids)));
                    ?>
                </p>
            </div>

            <div>
                <h4 class="text-xs font-semibold text-gray-400 uppercase mb-1">Ideal Reasoning</h4>
                <div class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($sub['ideal_reasoning']) ?></div>
            </div>

            <?php if ($sub['status'] === 'pending'): ?>
            <form method="POST" class="border-t border-gray-100 pt-4 space-y-3">
                <?= csrfField() ?>
                <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Admin Notes</label>
                    <textarea name="admin_notes" rows="2"
                              class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm"
                              placeholder="Optional feedback for the author..."></textarea>
                </div>

                <div class="flex space-x-3">
                    <button type="submit" name="action" value="approve"
                            class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-green-700 transition">
                        Approve & Add
                    </button>
                    <button type="submit" name="action" value="reject"
                            class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-700 transition">
                        Reject
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
