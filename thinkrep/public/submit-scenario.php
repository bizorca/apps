<?php
require_once __DIR__ . '/_bootstrap.php';
requireOnboarded();

$userId = getCurrentUserId();
$db = getDB();

// Load all models for the dropdown
$models = $db->query("SELECT id, name FROM tr_mental_models ORDER BY display_order")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/submit-scenario.php'));
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $situation = trim($_POST['situation'] ?? '');
    $correctModelId = (int) ($_POST['correct_model_id'] ?? 0);
    $idealReasoning = trim($_POST['ideal_reasoning'] ?? '');
    $distractorIds = array_map('intval', $_POST['distractor_model_ids'] ?? []);
    $difficulty = $_POST['difficulty'] ?? 'intermediate';
    $roleTags = array_filter(array_map('trim', explode(',', $_POST['role_tags'] ?? '')));
    $industryTags = array_filter(array_map('trim', explode(',', $_POST['industry_tags'] ?? '')));

    // Validation
    $errors = [];
    if (strlen($title) < 5) $errors[] = 'Title must be at least 5 characters.';
    if (strlen($situation) < 100) $errors[] = 'Scenario situation must be at least 100 characters.';
    if (!$correctModelId) $errors[] = 'Please select the correct mental model.';
    if (strlen($idealReasoning) < 50) $errors[] = 'Ideal reasoning must be at least 50 characters.';
    if (count($distractorIds) < 2) $errors[] = 'Please select at least 2 distractor models.';
    if (in_array($correctModelId, $distractorIds)) $errors[] = 'Distractor models should not include the correct model.';
    if (!in_array($difficulty, ['beginner', 'intermediate', 'advanced'])) $difficulty = 'intermediate';

    if (!empty($errors)) {
        setFlash('error', implode(' ', $errors));
        header('Location: ' . url('/submit-scenario.php'));
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO tr_scenario_submissions
            (user_id, title, situation, correct_model_id, ideal_reasoning, distractor_model_ids, difficulty, role_tags, industry_tags)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $title,
        $situation,
        $correctModelId,
        $idealReasoning,
        json_encode($distractorIds),
        $difficulty,
        !empty($roleTags) ? json_encode($roleTags) : null,
        !empty($industryTags) ? json_encode($industryTags) : null,
    ]);

    setFlash('success', 'Scenario submitted for review. You\'ll be able to track its status on your submissions page.');
    header('Location: ' . url('/my-submissions.php'));
    exit;
}

$pageTitle = 'Submit a Scenario';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Submit a Scenario</h1>
    <p class="text-gray-600 mb-6">Describe a real-world decision scenario that tests a specific mental model. Approved submissions get added to the training pool for all users.</p>

    <form method="POST" class="bg-white rounded-lg shadow p-6 space-y-6" x-data="{ correctModel: 0 }">
        <?= csrfField() ?>

        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Scenario Title</label>
            <input type="text" name="title" id="title" required minlength="5" maxlength="200"
                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border"
                   placeholder="e.g., The Overbuilt Feature">
        </div>

        <div>
            <label for="situation" class="block text-sm font-medium text-gray-700 mb-1">The Scenario</label>
            <p class="text-xs text-gray-500 mb-2">Describe the situation in 2-4 paragraphs. Include specific details — numbers, names, stakes. Separate paragraphs with blank lines.</p>
            <textarea name="situation" id="situation" rows="8" required minlength="100"
                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border"
                      placeholder="A product team has been working on..."></textarea>
        </div>

        <div>
            <label for="correct_model_id" class="block text-sm font-medium text-gray-700 mb-1">Correct Mental Model</label>
            <select name="correct_model_id" id="correct_model_id" required x-model.number="correctModel"
                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                <option value="0">Select the correct model...</option>
                <?php foreach ($models as $m): ?>
                <option value="<?= $m['id'] ?>"><?= h($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Distractor Models</label>
            <p class="text-xs text-gray-500 mb-2">Select 2-3 models that are plausible but wrong answers. These become the multiple-choice options alongside the correct answer.</p>
            <div class="grid grid-cols-2 gap-2">
                <?php foreach ($models as $m): ?>
                <label class="flex items-center space-x-2 p-2 rounded border border-gray-200 hover:border-tr-300 cursor-pointer"
                       :class="correctModel == <?= $m['id'] ?> ? 'opacity-40 pointer-events-none' : ''">
                    <input type="checkbox" name="distractor_model_ids[]" value="<?= $m['id'] ?>"
                           class="text-tr-600 focus:ring-tr-500 rounded"
                           :disabled="correctModel == <?= $m['id'] ?>">
                    <span class="text-sm text-gray-700"><?= h($m['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <label for="ideal_reasoning" class="block text-sm font-medium text-gray-700 mb-1">Ideal Reasoning</label>
            <p class="text-xs text-gray-500 mb-2">Write the ideal reasoning a user should produce. Start the first line with KEY: followed by pipe-separated key phrases.</p>
            <textarea name="ideal_reasoning" id="ideal_reasoning" rows="5" required minlength="50"
                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border"
                      placeholder="KEY:past investment|future value|cut losses&#10;The rational approach here is to..."></textarea>
        </div>

        <div>
            <label for="difficulty" class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
            <select name="difficulty" id="difficulty"
                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                <option value="beginner">Beginner</option>
                <option value="intermediate" selected>Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="role_tags" class="block text-sm font-medium text-gray-700 mb-1">Role Tags <span class="text-gray-400">(optional)</span></label>
                <input type="text" name="role_tags" id="role_tags"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm"
                       placeholder="founder, product_manager">
            </div>
            <div>
                <label for="industry_tags" class="block text-sm font-medium text-gray-700 mb-1">Industry Tags <span class="text-gray-400">(optional)</span></label>
                <input type="text" name="industry_tags" id="industry_tags"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm"
                       placeholder="saas, ecommerce">
            </div>
        </div>

        <button type="submit" class="w-full bg-tr-600 text-white py-3 rounded-lg font-semibold hover:bg-tr-700 transition">
            Submit for Review
        </button>
    </form>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
