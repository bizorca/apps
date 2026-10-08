<?php
require_once __DIR__ . '/_bootstrap.php';
requireOnboarded();

$userId = getCurrentUserId();
$responseId = (int) ($_GET['id'] ?? 0);

if (!$responseId) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT r.*,
           s.title AS scenario_title, s.situation, s.ideal_reasoning, s.distractor_explanation,
           cm.name AS chosen_model_name, cm.slug AS chosen_model_slug,
           am.name AS correct_model_name, am.slug AS correct_model_slug
    FROM tr_responses r
    JOIN tr_scenarios s ON s.id = r.scenario_id
    JOIN tr_mental_models cm ON cm.id = r.chosen_model_id
    JOIN tr_mental_models am ON am.id = s.correct_model_id
    WHERE r.id = ? AND r.user_id = ?
");
$stmt->execute([$responseId, $userId]);
$response = $stmt->fetch();

if (!$response) {
    setFlash('error', 'Response not found.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$notes = json_decode($response['scoring_notes'] ?? '[]', true) ?: [];

// Parse ideal reasoning (skip KEY line)
$idealLines = explode("\n", $response['ideal_reasoning']);
$idealText = '';
foreach ($idealLines as $line) {
    if (strpos($line, 'KEY:') === 0) continue;
    $idealText .= $line . "\n";
}
$idealText = trim($idealText);

$pageTitle = 'Session Result';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <!-- Score Banner -->
    <div class="bg-white rounded-lg shadow p-6 mb-6 text-center">
        <div class="text-6xl font-bold <?= $response['total_score'] >= 7 ? 'text-green-600' : ($response['total_score'] >= 4 ? 'text-yellow-600' : 'text-red-600') ?>">
            <?= $response['total_score'] ?>/10
        </div>
        <p class="text-gray-500 mt-2">
            Model: <?= $response['model_correct'] ? '<span class="text-green-600 font-semibold">Correct (+5)</span>' : '<span class="text-red-600 font-semibold">Incorrect (+0)</span>' ?>
            &middot;
            Reasoning: <?= $response['reasoning_score'] ?>/5
        </p>
        <?php if ($response['time_spent_sec']): ?>
        <p class="text-sm text-gray-400 mt-1"><?= floor($response['time_spent_sec'] / 60) ?>m <?= $response['time_spent_sec'] % 60 ?>s</p>
        <?php endif; ?>
    </div>

    <h2 class="text-xl font-bold text-gray-900 mb-4"><?= h($response['scenario_title']) ?></h2>

    <!-- Model Answer -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-2">
            <?php if ($response['model_correct']): ?>
                <span class="text-green-600">&#10003;</span> You chose: <?= h($response['chosen_model_name']) ?>
            <?php else: ?>
                <span class="text-red-600">&#10007;</span> You chose: <?= h($response['chosen_model_name']) ?>
                <span class="text-gray-400 mx-2">&rarr;</span>
                Correct: <span class="text-green-600"><?= h($response['correct_model_name']) ?></span>
            <?php endif; ?>
        </h3>
    </div>

    <!-- Reasoning Breakdown -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-4">Reasoning Breakdown</h3>
        <div class="space-y-3">
            <?php
            $criterionLabels = [
                'length' => 'Length & Effort (100+ chars)',
                'key_concepts' => 'Key Concepts',
                'causal_language' => 'Causal Language',
                'tradeoff_awareness' => 'Tradeoff Awareness',
                'specificity' => 'Specificity (2+ details)',
                'cap' => 'Cap Applied',
            ];
            foreach ($notes as $note):
                $label = $criterionLabels[$note['criterion']] ?? $note['criterion'];
                $earned = (int) $note['score'];
            ?>
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-700"><?= h($label) ?></span>
                    <span class="text-xs text-gray-400 ml-2"><?= h($note['detail']) ?></span>
                </div>
                <span class="font-mono text-sm <?= $earned ? 'text-green-600' : 'text-gray-400' ?>">
                    <?= $note['criterion'] === 'cap' ? 'capped' : ($earned ? '+1' : '+0') ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Your Reasoning -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-2">Your Reasoning</h3>
        <p class="text-gray-700 whitespace-pre-wrap"><?= h($response['reasoning_text']) ?></p>
    </div>

    <!-- Ideal Reasoning -->
    <div class="bg-tr-50 border border-tr-200 rounded-lg p-6 mb-6">
        <h3 class="font-semibold text-tr-800 mb-2">Ideal Reasoning</h3>
        <p class="text-tr-700 whitespace-pre-wrap"><?= h($idealText) ?></p>
    </div>

    <?php if (!$response['model_correct'] && $response['distractor_explanation']): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
        <h3 class="font-semibold text-yellow-800 mb-2">Why Other Answers Are Wrong</h3>
        <p class="text-yellow-700 whitespace-pre-wrap"><?= h($response['distractor_explanation']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="flex items-center justify-between">
        <a href="<?= url('/session.php') ?>" class="bg-tr-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-tr-700 transition">
            Next Scenario
        </a>
        <a href="<?= url('/dashboard.php') ?>" class="text-gray-500 hover:text-gray-700 text-sm">
            Back to Dashboard
        </a>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
