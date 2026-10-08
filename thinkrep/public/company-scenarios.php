<?php
/**
 * Custom Scenario Packs — company-scoped private scenarios.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/companies.php';
requireOnboarded();

$userId = getCurrentUserId();
$company = getUserCompany($userId);

if (!$company || !isCompanyManager($company['id'], $userId)) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/company-scenarios.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_pack') {
        $name = trim($_POST['pack_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (strlen($name) < 2) {
            setFlash('error', 'Pack name required.');
        } else {
            $stmt = $db->prepare("INSERT INTO tr_scenario_packs (company_id, name, description, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$company['id'], $name, $desc ?: null, $userId]);
            setFlash('success', 'Scenario pack created.');
        }
    } elseif ($action === 'add_scenario') {
        $packId = (int) ($_POST['pack_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $situation = trim($_POST['situation'] ?? '');
        $correctModelId = (int) ($_POST['correct_model_id'] ?? 0);
        $idealReasoning = trim($_POST['ideal_reasoning'] ?? '');
        $difficulty = $_POST['difficulty'] ?? 'intermediate';
        $distractorIds = array_map('intval', $_POST['distractor_model_ids'] ?? []);

        if (!$packId || !$title || strlen($situation) < 50 || !$correctModelId || !$idealReasoning) {
            setFlash('error', 'Please fill all required fields.');
        } else {
            // Verify pack belongs to company
            $stmt = $db->prepare("SELECT id FROM tr_scenario_packs WHERE id = ? AND company_id = ?");
            $stmt->execute([$packId, $company['id']]);
            if (!$stmt->fetch()) {
                setFlash('error', 'Invalid pack.');
            } else {
                // Create scenario
                $stmt = $db->prepare("
                    INSERT INTO tr_scenarios (title, situation, correct_model_id, ideal_reasoning, distractor_explanation, difficulty, is_active, pack_id)
                    VALUES (?, ?, ?, ?, '', ?, 1, ?)
                ");
                $stmt->execute([$title, $situation, $correctModelId, $idealReasoning, $difficulty, $packId]);
                $scenarioId = (int) $db->lastInsertId();

                // Create choices
                $order = 1;
                $stmt = $db->prepare("INSERT INTO tr_scenario_choices (scenario_id, model_id, is_correct, display_order) VALUES (?, ?, 1, ?)");
                $stmt->execute([$scenarioId, $correctModelId, $order++]);

                $stmt = $db->prepare("INSERT INTO tr_scenario_choices (scenario_id, model_id, is_correct, display_order) VALUES (?, ?, 0, ?)");
                foreach (array_slice($distractorIds, 0, 3) as $did) {
                    if ($did !== $correctModelId) {
                        $stmt->execute([$scenarioId, $did, $order++]);
                    }
                }

                setFlash('success', 'Scenario added to pack.');
            }
        }
    }

    header('Location: ' . url('/company-scenarios.php'));
    exit;
}

// Load packs with scenario counts
$stmt = $db->prepare("
    SELECT sp.*,
           (SELECT COUNT(*) FROM tr_scenarios WHERE pack_id = sp.id) AS scenario_count,
           u.name AS created_by_name
    FROM tr_scenario_packs sp
    JOIN users u ON u.id = sp.created_by
    WHERE sp.company_id = ?
    ORDER BY sp.created_at DESC, sp.id DESC
");
$stmt->execute([$company['id']]);
$packs = $stmt->fetchAll();

$models = $db->query("SELECT id, name FROM tr_mental_models ORDER BY display_order")->fetchAll();

$pageTitle = 'Custom Scenarios';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <a href="<?= url('/company.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 mb-4 inline-block">&larr; Company Dashboard</a>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Custom Scenario Packs</h1>

    <!-- Create Pack -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">New Scenario Pack</h2>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_pack">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pack Name</label>
                    <input type="text" name="pack_name" required placeholder="e.g., Sales Decision Training"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                    <input type="text" name="description" placeholder="Custom scenarios for the sales team"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                </div>
            </div>
            <button type="submit" class="bg-tr-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-tr-700 transition text-sm">Create Pack</button>
        </form>
    </div>

    <!-- Existing Packs -->
    <?php foreach ($packs as $pack): ?>
    <div class="bg-white rounded-lg shadow mb-6" x-data="{ expanded: false, showForm: false }">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900"><?= h($pack['name']) ?></h3>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $pack['scenario_count'] ?> scenarios &middot; by <?= h($pack['created_by_name']) ?>
                        <?php if ($pack['description']): ?>&middot; <?= h($pack['description']) ?><?php endif; ?>
                    </p>
                </div>
                <div class="flex space-x-2">
                    <button @click="expanded = !expanded" class="text-sm text-tr-600 hover:text-tr-700 font-medium">
                        <span x-text="expanded ? 'Hide' : 'View'"></span>
                    </button>
                    <button @click="showForm = !showForm" class="text-sm bg-tr-600 text-white px-3 py-1 rounded-lg hover:bg-tr-700 transition">
                        + Add Scenario
                    </button>
                </div>
            </div>

            <!-- Add Scenario Form -->
            <div x-show="showForm" x-cloak class="mt-4 border-t border-gray-100 pt-4">
                <form method="POST" class="space-y-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_scenario">
                    <input type="hidden" name="pack_id" value="<?= $pack['id'] ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" name="title" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scenario Situation</label>
                        <textarea name="situation" rows="4" required minlength="50" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm"></textarea>
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Correct Model</label>
                            <select name="correct_model_id" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm">
                                <option value="">Select...</option>
                                <?php foreach ($models as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= h($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                            <select name="difficulty" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm">
                                <option value="beginner">Beginner</option>
                                <option value="intermediate" selected>Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Distractors (select 2-3)</label>
                        <div class="grid grid-cols-2 gap-1">
                            <?php foreach ($models as $m): ?>
                            <label class="flex items-center space-x-1 text-sm">
                                <input type="checkbox" name="distractor_model_ids[]" value="<?= $m['id'] ?>" class="text-tr-600 rounded">
                                <span><?= h($m['name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ideal Reasoning</label>
                        <textarea name="ideal_reasoning" rows="3" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm"
                                  placeholder="KEY:phrase1|phrase2&#10;Full reasoning..."></textarea>
                    </div>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-green-700 transition">Add Scenario</button>
                </form>
            </div>
        </div>

        <!-- Scenario list -->
        <div x-show="expanded" x-cloak class="border-t border-gray-100">
            <?php
            $stmt = $db->prepare("SELECT s.id, s.title, s.difficulty, mm.name AS model_name FROM tr_scenarios s JOIN tr_mental_models mm ON mm.id = s.correct_model_id WHERE s.pack_id = ? ORDER BY s.id");
            $stmt->execute([$pack['id']]);
            $packScenarios = $stmt->fetchAll();
            ?>
            <?php if (empty($packScenarios)): ?>
            <p class="px-6 py-4 text-sm text-gray-500">No scenarios yet.</p>
            <?php else: ?>
            <div class="divide-y divide-gray-50">
                <?php foreach ($packScenarios as $ps): ?>
                <div class="px-6 py-3 flex items-center justify-between text-sm">
                    <div>
                        <span class="font-medium text-gray-900"><?= h($ps['title']) ?></span>
                        <span class="text-gray-400 ml-2"><?= h($ps['model_name']) ?></span>
                    </div>
                    <span class="text-xs text-gray-400"><?= h($ps['difficulty']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($packs)): ?>
    <div class="bg-gray-50 rounded-lg p-8 text-center text-gray-500">
        No scenario packs yet. Create one above to start adding company-specific training scenarios.
    </div>
    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
