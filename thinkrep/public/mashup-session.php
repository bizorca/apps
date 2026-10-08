<?php
/**
 * Mashup Session — multi-model scenario training.
 * Users identify primary + secondary models and explain the interplay.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/scenarios.php';
require_once TR_ROOT . '/includes/mashup-scoring.php';
require_once TR_ROOT . '/includes/blindspots.php';
requireOnboarded();

$userId = getCurrentUserId();
$db = getDB();

// POST: score and save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/mashup-session.php'));
        exit;
    }

    $scenarioId = (int) ($_POST['scenario_id'] ?? 0);
    $primaryModelId = (int) ($_POST['primary_model_id'] ?? 0);
    $selectedIds = array_map('intval', $_POST['selected_model_ids'] ?? []);
    $reasoningText = trim($_POST['reasoning_text'] ?? '');
    $confidence = max(1, min(5, (int) ($_POST['confidence'] ?? 3)));
    $timeSpent = (int) ($_POST['time_spent_sec'] ?? 0);

    // Ensure primary is in selected
    if ($primaryModelId && !in_array($primaryModelId, $selectedIds)) {
        $selectedIds[] = $primaryModelId;
    }

    if (!$scenarioId || !$primaryModelId || count($selectedIds) < 2 || !$reasoningText) {
        setFlash('error', 'Please select a primary model, at least one secondary, and write your reasoning.');
        header('Location: ' . url('/mashup-session.php') . '?id=' . $scenarioId);
        exit;
    }

    $scenario = getScenarioWithChoices($scenarioId);
    if (!$scenario || !$scenario['is_mashup']) {
        setFlash('error', 'Scenario not found.');
        header('Location: ' . url('/mashup-session.php'));
        exit;
    }

    $result = scoreMashupResponse($scenario, $primaryModelId, $selectedIds, $reasoningText);

    $stmt = $db->prepare("
        INSERT INTO tr_mashup_responses
            (user_id, scenario_id, selected_model_ids, primary_model_id, reasoning_text, confidence, time_spent_sec,
             primary_correct, secondary_found, reasoning_score, total_score, scoring_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId, $scenarioId, json_encode($selectedIds), $primaryModelId,
        $reasoningText, $confidence, $timeSpent ?: null,
        $result['primary_correct'] ? 1 : 0,
        $result['secondary_found'] ? 1 : 0,
        $result['reasoning_score'], $result['total_score'],
        json_encode($result['scoring_notes']),
    ]);

    $mashupId = $db->lastInsertId();

    // Also create a regular response for streak/blindspot tracking (uses primary model)
    $stmt = $db->prepare("
        INSERT INTO tr_responses (user_id, scenario_id, chosen_model_id, reasoning_text, confidence, time_spent_sec,
                               model_correct, reasoning_score, total_score, scoring_notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId, $scenarioId, $primaryModelId, $reasoningText, $confidence, $timeSpent ?: null,
        $result['primary_correct'] ? 1 : 0, $result['reasoning_score'], $result['total_score'],
        json_encode($result['scoring_notes']),
    ]);

    rebuildBlindspots($userId);

    header('Location: ' . url('/mashup-result.php') . '?id=' . $mashupId);
    exit;
}

// GET: present a mashup scenario
$scenarioId = (int) ($_GET['id'] ?? 0);

if ($scenarioId) {
    $scenario = getScenarioWithChoices($scenarioId);
    if ($scenario && !$scenario['is_mashup']) $scenario = null;
} else {
    // Pick a random mashup scenario
    $stmt = $db->prepare("SELECT id FROM tr_scenarios WHERE is_active = 1 AND is_mashup = 1 ORDER BY RAND() LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    $scenario = $row ? getScenarioWithChoices($row['id']) : null;
}

if (!$scenario) {
    setFlash('error', 'No mashup scenarios available.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

// Load all models for selection
$models = $db->query("SELECT id, name, short_desc FROM tr_mental_models ORDER BY display_order")->fetchAll();

$pageTitle = 'Model Mashup';
require TR_ROOT . '/templates/header.php';
?>

<div x-data="{
    selectedModels: [],
    primaryModel: null,
    confidence: 3,
    startTime: Date.now(),
    submitted: false,
    toggleModel(id) {
        const idx = this.selectedModels.indexOf(id);
        if (idx === -1) {
            this.selectedModels.push(id);
        } else {
            this.selectedModels.splice(idx, 1);
            if (this.primaryModel === id) this.primaryModel = null;
        }
    },
    isSelected(id) { return this.selectedModels.includes(id); }
}" class="max-w-3xl mx-auto">

    <div class="mb-6 flex items-center space-x-3">
        <span class="inline-block bg-purple-100 text-purple-700 text-xs font-semibold px-2.5 py-1 rounded-full uppercase">
            Mashup
        </span>
        <span class="inline-block bg-tr-100 text-tr-700 text-xs font-semibold px-2.5 py-1 rounded-full uppercase">
            <?= h($scenario['difficulty']) ?>
        </span>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-4"><?= h($scenario['title']) ?></h1>

    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
        <p class="text-sm text-purple-700">
            <span class="font-semibold">Multi-model scenario:</span> Multiple mental models apply here.
            Select all that apply, mark which one is primary, and explain how they interact.
        </p>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <?php foreach (explode("\n\n", $scenario['situation']) as $para): ?>
            <p class="text-gray-700 mb-4 leading-relaxed"><?= h($para) ?></p>
        <?php endforeach; ?>
    </div>

    <form method="POST" @submit="submitted = true" class="space-y-6">
        <?= csrfField() ?>
        <input type="hidden" name="scenario_id" value="<?= $scenario['id'] ?>">
        <input type="hidden" name="time_spent_sec" :value="Math.round((Date.now() - startTime) / 1000)">

        <!-- Model Selection (multi-select) -->
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-1">Which mental models apply?</h2>
            <p class="text-sm text-gray-500 mb-3">Select 2 or more models, then mark one as the primary model.</p>

            <div class="grid gap-3">
                <?php foreach ($models as $m): ?>
                <div class="relative flex items-start p-4 bg-white rounded-lg shadow border-2 transition cursor-pointer"
                     :class="isSelected(<?= $m['id'] ?>) ? 'border-tr-500 bg-tr-50' : 'border-transparent hover:border-gray-200'"
                     @click="toggleModel(<?= $m['id'] ?>)">

                    <input type="checkbox" name="selected_model_ids[]" value="<?= $m['id'] ?>"
                           :checked="isSelected(<?= $m['id'] ?>)" class="sr-only">

                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-900"><?= h($m['name']) ?></span>
                            <template x-if="isSelected(<?= $m['id'] ?>)">
                                <label @click.stop class="flex items-center space-x-1 cursor-pointer">
                                    <input type="radio" name="primary_model_id" value="<?= $m['id'] ?>"
                                           x-model.number="primaryModel"
                                           class="text-purple-600 focus:ring-purple-500">
                                    <span class="text-xs font-semibold text-purple-600">Primary</span>
                                </label>
                            </template>
                        </div>
                        <p class="text-sm text-gray-500 mt-0.5"><?= h($m['short_desc']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Confidence -->
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Confidence <span class="text-sm font-normal text-gray-500">(1 = guessing, 5 = certain)</span></h2>
            <div class="flex items-center space-x-4">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <label class="flex flex-col items-center cursor-pointer">
                    <input type="radio" name="confidence" value="<?= $i ?>" x-model.number="confidence" class="sr-only">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold border-2 transition"
                          :class="confidence == <?= $i ?> ? 'bg-tr-600 text-white border-tr-600' : 'bg-white text-gray-500 border-gray-300 hover:border-tr-300'"><?= $i ?></span>
                </label>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Reasoning -->
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Explain the interplay</h2>
            <p class="text-sm text-gray-500 mb-2">Why is one model primary and the other secondary? How do they interact in this scenario? Aim for 150+ characters.</p>
            <textarea name="reasoning_text" rows="7"
                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-4 py-3 border"
                      placeholder="The primary model here is... because... The secondary model also applies because... They interact in that..."
                      required></textarea>
        </div>

        <button type="submit"
                :disabled="selectedModels.length < 2 || !primaryModel || submitted"
                class="w-full bg-purple-600 text-white py-3 rounded-lg font-semibold hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-show="!submitted">Submit Mashup</span>
            <span x-show="submitted" x-cloak>Scoring...</span>
        </button>
    </form>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
