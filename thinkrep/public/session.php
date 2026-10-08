<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/scenarios.php';
require_once TR_ROOT . '/includes/scoring.php';
require_once TR_ROOT . '/includes/blindspots.php';
requireOnboarded();

$userId = getCurrentUserId();
$db = getDB();

// POST: score and save response
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/session.php'));
        exit;
    }

    $scenarioId = (int) ($_POST['scenario_id'] ?? 0);
    $chosenModelId = (int) ($_POST['chosen_model_id'] ?? 0);
    $reasoningText = trim($_POST['reasoning_text'] ?? '');
    $confidence = max(1, min(5, (int) ($_POST['confidence'] ?? 3)));
    $timeSpent = (int) ($_POST['time_spent_sec'] ?? 0);

    if (!$scenarioId || !$chosenModelId || !$reasoningText) {
        setFlash('error', 'Please complete all fields.');
        header('Location: ' . url('/session.php') . '?id=' . $scenarioId);
        exit;
    }

    // Load scenario for scoring
    $scenario = getScenarioWithChoices($scenarioId);
    if (!$scenario) {
        setFlash('error', 'Scenario not found.');
        header('Location: ' . url('/session.php'));
        exit;
    }

    // Score it
    $result = scoreResponse($scenario, $chosenModelId, $reasoningText);

    // Save response
    $stmt = $db->prepare("
        INSERT INTO tr_responses (user_id, scenario_id, chosen_model_id, reasoning_text, confidence, time_spent_sec, model_correct, reasoning_score, total_score, scoring_notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId,
        $scenarioId,
        $chosenModelId,
        $reasoningText,
        $confidence,
        $timeSpent ?: null,
        $result['model_correct'] ? 1 : 0,
        $result['reasoning_score'],
        $result['total_score'],
        json_encode($result['scoring_notes']),
    ]);

    $responseId = $db->lastInsertId();

    // Rebuild blind spot cache
    rebuildBlindspots($userId);

    header('Location: ' . url('/session-result.php') . '?id=' . $responseId);
    exit;
}

// GET: present a scenario
$scenarioId = (int) ($_GET['id'] ?? 0);

if ($scenarioId) {
    $scenario = getScenarioWithChoices($scenarioId);
} else {
    $scenario = getNextScenario($userId);
}

if (!$scenario) {
    setFlash('error', 'No scenarios available. Check back later!');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$pageTitle = 'Training Session';
require TR_ROOT . '/templates/header.php';
?>

<div x-data="{
    chosenModel: null,
    confidence: 3,
    startTime: Date.now(),
    submitted: false
}" class="max-w-3xl mx-auto">

    <div class="mb-6">
        <span class="inline-block bg-tr-100 text-tr-700 text-xs font-semibold px-2.5 py-1 rounded-full uppercase">
            <?= h($scenario['difficulty']) ?>
        </span>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-4"><?= h($scenario['title']) ?></h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="prose prose-gray max-w-none">
            <?php foreach (explode("\n\n", $scenario['situation']) as $para): ?>
                <p class="text-gray-700 mb-4 leading-relaxed"><?= h($para) ?></p>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="POST" @submit="submitted = true" class="space-y-6">
        <?= csrfField() ?>
        <input type="hidden" name="scenario_id" value="<?= $scenario['id'] ?>">
        <input type="hidden" name="time_spent_sec" :value="Math.round((Date.now() - startTime) / 1000)">

        <!-- Model Selection -->
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Which mental model best applies?</h2>
            <div class="grid gap-3">
                <?php foreach ($scenario['choices'] as $choice): ?>
                <label class="relative flex items-start p-4 bg-white rounded-lg shadow cursor-pointer border-2 transition"
                       :class="chosenModel == <?= $choice['model_id'] ?> ? 'border-tr-500 bg-tr-50' : 'border-transparent hover:border-gray-200'">
                    <input type="radio" name="chosen_model_id" value="<?= $choice['model_id'] ?>"
                           x-model.number="chosenModel" class="mt-1 text-tr-600 focus:ring-tr-500">
                    <div class="ml-3">
                        <span class="font-medium text-gray-900"><?= h($choice['model_name']) ?></span>
                        <p class="text-sm text-gray-500 mt-0.5"><?= h($choice['model_desc']) ?></p>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Confidence -->
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-3">How confident are you? <span class="text-sm font-normal text-gray-500">(1 = guessing, 5 = certain)</span></h2>
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
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Explain your reasoning</h2>
            <p class="text-sm text-gray-500 mb-2">Why does this mental model apply? Reference specifics from the scenario. Aim for 100+ characters.</p>
            <textarea name="reasoning_text" rows="6"
                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-4 py-3 border"
                      placeholder="Write your reasoning here..."
                      required></textarea>
        </div>

        <button type="submit" :disabled="!chosenModel || submitted"
                class="w-full bg-tr-600 text-white py-3 rounded-lg font-semibold hover:bg-tr-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-show="!submitted">Submit Answer</span>
            <span x-show="submitted" x-cloak>Scoring...</span>
        </button>
    </form>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
