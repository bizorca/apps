<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/mashup-scoring.php';
requireOnboarded();

$userId = getCurrentUserId();
$mashupId = (int) ($_GET['id'] ?? 0);

if (!$mashupId) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT mr.*,
           s.title AS scenario_title, s.situation, s.ideal_reasoning, s.distractor_explanation,
           pm.name AS primary_model_name
    FROM tr_mashup_responses mr
    JOIN tr_scenarios s ON s.id = mr.scenario_id
    JOIN tr_mental_models pm ON pm.id = mr.primary_model_id
    WHERE mr.id = ? AND mr.user_id = ?
");
$stmt->execute([$mashupId, $userId]);
$response = $stmt->fetch();

if (!$response) {
    setFlash('error', 'Response not found.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$notes = json_decode($response['scoring_notes'] ?? '[]', true) ?: [];
$selectedIds = json_decode($response['selected_model_ids'] ?? '[]', true) ?: [];

// Get names for selected models
$selectedNames = [];
if (!empty($selectedIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $stmt = $db->prepare("SELECT id, name FROM tr_mental_models WHERE id IN ($placeholders)");
    $stmt->execute($selectedIds);
    foreach ($stmt->fetchAll() as $row) {
        $selectedNames[$row['id']] = $row['name'];
    }
}

// Get correct models
$correctModels = getMashupCorrectModels($response['scenario_id']);

// Parse ideal reasoning
$idealLines = explode("\n", $response['ideal_reasoning']);
$idealText = '';
foreach ($idealLines as $line) {
    if (strpos($line, 'KEY:') === 0) continue;
    $idealText .= $line . "\n";
}
$idealText = trim($idealText);

$pageTitle = 'Mashup Result';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <!-- Score Banner -->
    <div class="bg-white rounded-lg shadow p-6 mb-6 text-center">
        <div class="text-6xl font-bold <?= $response['total_score'] >= 7 ? 'text-green-600' : ($response['total_score'] >= 4 ? 'text-yellow-600' : 'text-red-600') ?>">
            <?= $response['total_score'] ?>/10
        </div>
        <p class="text-gray-500 mt-2">
            Primary: <?= $response['primary_correct'] ? '<span class="text-green-600 font-semibold">Correct (+5)</span>' : '<span class="text-red-600 font-semibold">Incorrect (+0)</span>' ?>
            &middot;
            Secondary: <?= $response['secondary_found'] ? '<span class="text-green-600 font-semibold">Found (+2)</span>' : '<span class="text-red-600 font-semibold">Missed (+0)</span>' ?>
            &middot;
            Reasoning: <?= $response['reasoning_score'] ?>/3
        </p>
    </div>

    <h2 class="text-xl font-bold text-gray-900 mb-4"><?= h($response['scenario_title']) ?></h2>

    <!-- Your Selections -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-3">Your Model Selections</h3>
        <div class="space-y-2">
            <?php foreach ($selectedIds as $sid): ?>
            <div class="flex items-center justify-between p-2 rounded <?= $sid == $response['primary_model_id'] ? 'bg-purple-50' : 'bg-gray-50' ?>">
                <span class="text-sm font-medium text-gray-700"><?= h($selectedNames[$sid] ?? "Model #$sid") ?></span>
                <?php if ($sid == $response['primary_model_id']): ?>
                <span class="text-xs font-semibold text-purple-600">Your Primary</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Correct Models -->
    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
        <h3 class="font-semibold text-green-800 mb-3">Correct Models</h3>
        <div class="space-y-3">
            <?php foreach ($correctModels as $cm): ?>
            <div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $cm['rank_level'] === 'primary' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                        <?= ucfirst($cm['rank_level']) ?>
                    </span>
                    <span class="font-medium text-green-800"><?= h($cm['model_name']) ?></span>
                </div>
                <?php if ($cm['relevance_note']): ?>
                <p class="text-sm text-green-700 mt-1 ml-1"><?= h($cm['relevance_note']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Scoring Breakdown -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-4">Scoring Breakdown</h3>
        <div class="space-y-3">
            <?php
            $criterionLabels = [
                'primary_model' => 'Primary Model (5 pts)',
                'secondary_model' => 'Secondary Model (2 pts)',
                'interplay' => 'Interplay Language (1 pt)',
                'length' => 'Length (1 pt)',
                'specificity' => 'Specificity (1 pt)',
                'cap' => 'Cap Applied',
            ];
            foreach ($notes as $note):
                $label = $criterionLabels[$note['criterion']] ?? $note['criterion'];
            ?>
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-700"><?= h($label) ?></span>
                    <span class="text-xs text-gray-400 ml-2"><?= h($note['detail']) ?></span>
                </div>
                <span class="font-mono text-sm <?= (int)$note['score'] ? 'text-green-600' : 'text-gray-400' ?>">
                    <?php if ($note['criterion'] === 'cap'): ?>capped
                    <?php elseif ($note['criterion'] === 'primary_model'): ?>+<?= $note['score'] ?>
                    <?php elseif ($note['criterion'] === 'secondary_model'): ?>+<?= $note['score'] ?>
                    <?php else: ?>+<?= $note['score'] ?>
                    <?php endif; ?>
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

    <?php if ($response['distractor_explanation']): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
        <h3 class="font-semibold text-yellow-800 mb-2">Why Other Models Don't Fit</h3>
        <p class="text-yellow-700 whitespace-pre-wrap"><?= h($response['distractor_explanation']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="flex items-center justify-between">
        <a href="<?= url('/mashup-session.php') ?>" class="bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700 transition">
            Next Mashup
        </a>
        <a href="<?= url('/dashboard.php') ?>" class="text-gray-500 hover:text-gray-700 text-sm">
            Back to Dashboard
        </a>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
