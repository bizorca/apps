<?php
/**
 * Hindsight Reflection — revisit a past response and write updated reasoning.
 * Available for responses 30+ days old.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/scoring.php';
requireOnboarded();

$userId = getCurrentUserId();
$responseId = (int) ($_GET['id'] ?? 0);

if (!$responseId) {
    header('Location: ' . url('/journal.php'));
    exit;
}

$db = getDB();

// Load original response
$stmt = $db->prepare("
    SELECT r.*,
           s.title AS scenario_title, s.situation, s.ideal_reasoning, s.correct_model_id,
           cm.name AS chosen_model_name, am.name AS correct_model_name
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
    header('Location: ' . url('/journal.php'));
    exit;
}

// Check 30-day threshold
$daysAgo = (int) ((time() - strtotime($response['created_at'])) / 86400);
if ($daysAgo < 30) {
    setFlash('error', 'Hindsight reflections are available 30 days after the original response. This one is only ' . $daysAgo . ' days old.');
    header('Location: ' . url('/journal-entry.php') . '?id=' . $responseId);
    exit;
}

// Check if already reflected
$stmt = $db->prepare("SELECT * FROM tr_hindsight_reflections WHERE response_id = ?");
$stmt->execute([$responseId]);
$existing = $stmt->fetch();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/hindsight.php') . '?id=' . $responseId);
        exit;
    }

    $newReasoning = trim($_POST['new_reasoning'] ?? '');
    if (strlen($newReasoning) < 50) {
        setFlash('error', 'Reflection must be at least 50 characters.');
        header('Location: ' . url('/hindsight.php') . '?id=' . $responseId);
        exit;
    }

    // Score the new reasoning using the same scenario
    $scenario = [
        'correct_model_id' => $response['correct_model_id'],
        'ideal_reasoning' => $response['ideal_reasoning'],
        'situation' => $response['situation'],
    ];
    $result = scoreResponse($scenario, (int) $response['chosen_model_id'], $newReasoning);

    $stmt = $db->prepare("
        INSERT INTO tr_hindsight_reflections (response_id, user_id, new_reasoning, new_reasoning_score, scoring_notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $responseId,
        $userId,
        $newReasoning,
        $result['reasoning_score'],
        json_encode($result['scoring_notes']),
    ]);

    setFlash('success', 'Hindsight reflection saved.');
    header('Location: ' . url('/hindsight.php') . '?id=' . $responseId);
    exit;
}

// Parse ideal reasoning (skip KEY line)
$idealLines = explode("\n", $response['ideal_reasoning']);
$idealText = '';
foreach ($idealLines as $line) {
    if (strpos($line, 'KEY:') === 0) continue;
    $idealText .= $line . "\n";
}
$idealText = trim($idealText);

$originalNotes = json_decode($response['scoring_notes'] ?? '[]', true) ?: [];
$hindsightNotes = $existing ? (json_decode($existing['scoring_notes'] ?? '[]', true) ?: []) : [];

$pageTitle = 'Hindsight Reflection';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <a href="<?= url('/journal-entry.php') ?>?id=<?= $responseId ?>" class="text-sm text-tr-600 hover:text-tr-700 mb-4 inline-block">&larr; Back to Entry</a>

    <h1 class="text-2xl font-bold text-gray-900 mb-2">Hindsight Reflection</h1>
    <p class="text-gray-500 mb-6"><?= h($response['scenario_title']) ?> — originally answered <?= date('M j, Y', strtotime($response['created_at'])) ?> (<?= $daysAgo ?> days ago)</p>

    <!-- Scenario recap -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="font-semibold text-gray-900 mb-3">The Scenario</h2>
        <?php foreach (explode("\n\n", $response['situation']) as $para): ?>
            <p class="text-gray-700 mb-3 leading-relaxed"><?= h($para) ?></p>
        <?php endforeach; ?>
    </div>

    <!-- Side by side comparison -->
    <div class="grid md:grid-cols-2 gap-6 mb-6">
        <!-- Original -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-900">Original Reasoning</h2>
                <span class="text-sm font-mono font-bold <?= $response['reasoning_score'] >= 3 ? 'text-green-600' : 'text-yellow-600' ?>">
                    <?= $response['reasoning_score'] ?>/5 reasoning
                </span>
            </div>
            <div class="mb-3">
                <span class="text-sm <?= $response['model_correct'] ? 'text-green-600' : 'text-red-500' ?>">
                    <?= $response['model_correct'] ? '&#10003; Correct model' : '&#10007; Wrong model' ?>:
                </span>
                <span class="text-sm text-gray-600"><?= h($response['chosen_model_name']) ?></span>
            </div>
            <p class="text-gray-700 whitespace-pre-wrap text-sm"><?= h($response['reasoning_text']) ?></p>
        </div>

        <!-- Hindsight -->
        <div class="<?= $existing ? 'bg-white' : 'bg-tr-50 border-2 border-dashed border-tr-300' ?> rounded-lg shadow p-6">
            <?php if ($existing): ?>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-900">Hindsight Reasoning</h2>
                <span class="text-sm font-mono font-bold <?= $existing['new_reasoning_score'] >= 3 ? 'text-green-600' : 'text-yellow-600' ?>">
                    <?= $existing['new_reasoning_score'] ?>/5 reasoning
                </span>
            </div>
            <p class="text-gray-700 whitespace-pre-wrap text-sm"><?= h($existing['new_reasoning']) ?></p>

            <!-- Score comparison -->
            <?php
            $delta = (int) $existing['new_reasoning_score'] - (int) $response['reasoning_score'];
            ?>
            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500">Reasoning change:</span>
                    <?php if ($delta > 0): ?>
                    <span class="text-sm font-bold text-green-600">+<?= $delta ?> point<?= $delta > 1 ? 's' : '' ?></span>
                    <?php elseif ($delta < 0): ?>
                    <span class="text-sm font-bold text-red-600"><?= $delta ?> point<?= abs($delta) > 1 ? 's' : '' ?></span>
                    <?php else: ?>
                    <span class="text-sm font-bold text-gray-400">No change</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <h2 class="font-semibold text-tr-800 mb-3">Write Your Hindsight</h2>
            <p class="text-sm text-tr-600 mb-4">Knowing what you know now, how would you reason through this scenario differently? Your new reasoning will be scored alongside the original.</p>
            <form method="POST">
                <?= csrfField() ?>
                <textarea name="new_reasoning" rows="8" required minlength="50"
                          class="w-full border-tr-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm"
                          placeholder="With the benefit of hindsight, I would approach this differently because..."></textarea>
                <button type="submit" class="mt-3 w-full bg-tr-600 text-white py-2 rounded-lg font-semibold hover:bg-tr-700 transition text-sm">
                    Submit Reflection
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ideal reasoning -->
    <div class="bg-tr-50 border border-tr-200 rounded-lg p-6">
        <h2 class="font-semibold text-tr-800 mb-2">Ideal Reasoning</h2>
        <p class="text-tr-700 whitespace-pre-wrap"><?= h($idealText) ?></p>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
