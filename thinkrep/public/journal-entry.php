<?php
require_once __DIR__ . '/_bootstrap.php';
requireOnboarded();

$userId = getCurrentUserId();
$responseId = (int) ($_GET['id'] ?? 0);

if (!$responseId) {
    header('Location: ' . url('/journal.php'));
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT r.*,
           s.title AS scenario_title, s.situation, s.ideal_reasoning, s.distractor_explanation, s.difficulty,
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
    setFlash('error', 'Entry not found.');
    header('Location: ' . url('/journal.php'));
    exit;
}

$notes = json_decode($response['scoring_notes'] ?? '[]', true) ?: [];

// Hindsight: check eligibility (30+ days old) and existing reflection
$daysAgo = (int) ((time() - strtotime($response['created_at'])) / 86400);
$hindsightEligible = ($daysAgo >= 30);

$hindsightReflection = null;
if ($hindsightEligible) {
    $stmt = $db->prepare("SELECT * FROM tr_hindsight_reflections WHERE response_id = ?");
    $stmt->execute([$responseId]);
    $hindsightReflection = $stmt->fetch() ?: null;
}

// Parse ideal reasoning (skip KEY line)
$idealLines = explode("\n", $response['ideal_reasoning']);
$idealText = '';
foreach ($idealLines as $line) {
    if (strpos($line, 'KEY:') === 0) continue;
    $idealText .= $line . "\n";
}
$idealText = trim($idealText);

$pageTitle = $response['scenario_title'];
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <a href="<?= url('/journal.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 mb-4 inline-block">&larr; Back to Journal</a>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-900"><?= h($response['scenario_title']) ?></h1>
        <span class="text-sm text-gray-400"><?= date('M j, Y g:ia', strtotime($response['created_at'])) ?></span>
    </div>

    <!-- Score -->
    <div class="bg-white rounded-lg shadow p-6 mb-6 text-center">
        <div class="text-5xl font-bold <?= $response['total_score'] >= 7 ? 'text-green-600' : ($response['total_score'] >= 4 ? 'text-yellow-600' : 'text-red-600') ?>">
            <?= $response['total_score'] ?>/10
        </div>
        <p class="text-gray-500 mt-2">
            Model: <?= $response['model_correct'] ? '<span class="text-green-600 font-semibold">Correct (+5)</span>' : '<span class="text-red-600 font-semibold">Incorrect (+0)</span>' ?>
            &middot; Reasoning: <?= $response['reasoning_score'] ?>/5
            &middot; Confidence: <?= $response['confidence'] ?>/5
        </p>
    </div>

    <!-- Scenario -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-3">The Scenario</h3>
        <div class="text-gray-700">
            <?php foreach (explode("\n\n", $response['situation']) as $para): ?>
                <p class="mb-3 leading-relaxed"><?= h($para) ?></p>
            <?php endforeach; ?>
        </div>
    </div>

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

    <!-- Scoring Breakdown -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-4">Scoring Breakdown</h3>
        <div class="space-y-3">
            <?php
            $criterionLabels = [
                'length' => 'Length & Effort',
                'key_concepts' => 'Key Concepts',
                'causal_language' => 'Causal Language',
                'tradeoff_awareness' => 'Tradeoff Awareness',
                'specificity' => 'Specificity',
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
                    <?= $note['criterion'] === 'cap' ? 'capped' : ((int)$note['score'] ? '+1' : '+0') ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$response['model_correct'] && $response['distractor_explanation']): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
        <h3 class="font-semibold text-yellow-800 mb-2">Why Other Answers Are Wrong</h3>
        <p class="text-yellow-700 whitespace-pre-wrap"><?= h($response['distractor_explanation']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Hindsight Reflection -->
    <?php if ($hindsightEligible): ?>
    <div class="<?= $hindsightReflection ? 'bg-purple-50 border border-purple-200' : 'bg-gray-50 border border-gray-200' ?> rounded-lg p-6 mb-6">
        <?php if ($hindsightReflection): ?>
        <h3 class="font-semibold text-purple-800 mb-2">Hindsight Reflection</h3>
        <p class="text-sm text-purple-600 mb-3">Written <?= date('M j, Y', strtotime($hindsightReflection['created_at'])) ?> — <?= $daysAgo ?> days after the original</p>
        <p class="text-purple-700 whitespace-pre-wrap text-sm"><?= h($hindsightReflection['new_reasoning']) ?></p>
        <div class="mt-3 flex items-center space-x-4 text-sm">
            <span class="text-gray-500">Original reasoning: <?= $response['reasoning_score'] ?>/5</span>
            <span class="text-purple-700 font-semibold">Hindsight reasoning: <?= $hindsightReflection['new_reasoning_score'] ?>/5</span>
            <?php $delta = (int)$hindsightReflection['new_reasoning_score'] - (int)$response['reasoning_score']; ?>
            <?php if ($delta > 0): ?>
            <span class="text-green-600 font-bold">+<?= $delta ?></span>
            <?php elseif ($delta < 0): ?>
            <span class="text-red-600 font-bold"><?= $delta ?></span>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-700">Hindsight Available</h3>
                <p class="text-sm text-gray-500 mt-1">This response is <?= $daysAgo ?> days old. Revisit it with fresh eyes and see how your thinking has evolved.</p>
            </div>
            <a href="<?= url('/hindsight.php') ?>?id=<?= $responseId ?>" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-purple-700 transition whitespace-nowrap">
                Write Reflection
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
