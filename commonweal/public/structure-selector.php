<?php
require __DIR__ . '/_bootstrap.php';
require_once CW_ROOT . '/includes/structures.php';
requireLogin();

$user = getCurrentUser();
if (!in_array($user['role'], ['client'], true)) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$case = getUserCase((int)$user['id']);
if (!$case) {
    header('Location: ' . url('/intake.php'));
    exit;
}
if ($case['status'] === 'intake') {
    header('Location: ' . url('/intake.php'));
    exit;
}

$db = getDB();
$questions = getStructureQuestions();
$structures = STRUCTURES;

// Load existing assessment if any
$stmt = $db->prepare('SELECT * FROM cw_structure_assessments WHERE case_id = ? ORDER BY completed_at DESC LIMIT 1');
$stmt->execute([$case['id']]);
$existingAssessment = $stmt->fetch() ?: null;
$existingAnswers = $existingAssessment ? json_decode($existingAssessment['answers'], true) : [];

$errors = [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $answers = [];
        foreach ($questions as $q) {
            $answers[$q['id']] = $_POST['q_' . $q['id']] ?? '';
        }

        // Validate all answered
        foreach ($questions as $q) {
            if (empty($answers[$q['id']])) {
                $errors[] = 'Please answer all questions before continuing.';
                break;
            }
        }

        if (empty($errors)) {
            $scores      = scoreStructures($answers);
            $recommended = getRecommendedStructure($scores);
            $now         = date('Y-m-d H:i:s');

            if ($existingAssessment) {
                $stmt = $db->prepare(
                    'UPDATE cw_structure_assessments
                     SET answers=?, recommended_structure=?, structure_scores=?, completed_at=?
                     WHERE id=?'
                );
                $stmt->execute([
                    json_encode($answers), $recommended, json_encode($scores), $now,
                    $existingAssessment['id'],
                ]);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO cw_structure_assessments (case_id, answers, recommended_structure, structure_scores, completed_at)
                     VALUES (?,?,?,?,?)'
                );
                $stmt->execute([
                    $case['id'], json_encode($answers), $recommended, json_encode($scores), $now,
                ]);
            }

            // Advance case status
            if ($case['status'] === 'structure_selection') {
                $stmt = $db->prepare('UPDATE cw_cases SET status=\'modeling\', updated_at=? WHERE id=?');
                $stmt->execute([$now, $case['id']]);
            }

            // Reload case and redirect to results
            header('Location: ' . url('/structure-selector.php?completed=1'));
            exit;
        }
    }
    $existingAnswers = $answers ?? $existingAnswers;
}

$flash = getFlash();

// Results mode
$showResults = isset($_GET['completed']) && $existingAssessment;
$resultScores = null;
$recommended  = null;
if ($showResults) {
    $resultScores = json_decode($existingAssessment['structure_scores'], true) ?? [];
    $recommended  = $existingAssessment['recommended_structure'];
    arsort($resultScores);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Structure Selector — CoopConvert</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="bg-emerald-700 text-white px-6 py-4 flex items-center justify-between">
    <a href="<?= url('/dashboard.php') ?>" class="font-bold text-lg tracking-tight">CoopConvert</a>
    <div class="flex items-center gap-6 text-sm">
        <a href="<?= url('/dashboard.php') ?>" class="hover:text-teal-300">Dashboard</a>
        <span class="text-emerald-300"><?= h($user['name']) ?></span>
        <a href="<?= url('/logout.php') ?>" class="hover:text-teal-300">Sign out</a>
    </div>
</nav>

<div class="max-w-3xl mx-auto px-4 py-10">

    <?php if ($flash): ?>
    <div class="mb-6 px-4 py-3 rounded-md text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($showResults && $recommended): ?>

    <!-- RESULTS VIEW -->
    <div class="mb-4 flex items-center gap-2">
        <a href="<?= url('/structure-selector.php') ?>" class="text-emerald-600 text-sm hover:underline">&larr; Retake assessment</a>
    </div>

    <div class="bg-emerald-700 text-white rounded-xl p-6 mb-6">
        <p class="text-emerald-200 text-sm font-medium mb-1">Recommended structure</p>
        <h1 class="text-2xl font-bold mb-1"><?= h($structures[$recommended]['name']) ?></h1>
        <p class="text-emerald-100 text-sm"><?= h($structures[$recommended]['legal_basis']) ?></p>
    </div>

    <?php if ($structures[$recommended]['requires_specialist']): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6 flex gap-3">
        <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <div>
            <p class="font-semibold text-amber-800 text-sm">Specialist Required</p>
            <p class="text-amber-700 text-sm mt-0.5">ESOPs require specialized ERISA attorneys and a qualified trustee. This is not a DIY structure. Budget $40,000–$100,000+ for formation costs before proceeding.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-3">About this structure</h2>
        <p class="text-gray-700 text-sm leading-relaxed mb-4"><?= h($structures[$recommended]['description']) ?></p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-gray-500 text-xs uppercase font-semibold mb-1">Filing fee</p>
                <p class="font-medium text-gray-800"><?= h($structures[$recommended]['filing_fee']) ?></p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-gray-500 text-xs uppercase font-semibold mb-1">Typical timeline</p>
                <p class="font-medium text-gray-800"><?= h($structures[$recommended]['typical_timeline']) ?></p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-gray-500 text-xs uppercase font-semibold mb-1">Ideal for</p>
                <p class="font-medium text-gray-800 text-xs"><?= h($structures[$recommended]['ideal_for']) ?></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-3 text-emerald-700">Pros</h3>
            <ul class="space-y-1.5">
                <?php foreach ($structures[$recommended]['pros'] as $pro): ?>
                <li class="flex gap-2 text-sm text-gray-700">
                    <span class="text-emerald-500 mt-0.5">&#10003;</span><?= h($pro) ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-3 text-red-600">Cons</h3>
            <ul class="space-y-1.5">
                <?php foreach ($structures[$recommended]['cons'] as $con): ?>
                <li class="flex gap-2 text-sm text-gray-700">
                    <span class="text-red-400 mt-0.5">&#10007;</span><?= h($con) ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Score breakdown -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-4">Score breakdown — all structures</h2>
        <?php
        $maxScore = max($resultScores) ?: 1;
        foreach ($resultScores as $key => $score):
            $structure = $structures[$key] ?? null;
            if (!$structure) continue;
            $pct = round(($score / $maxScore) * 100);
        ?>
        <div class="mb-3">
            <div class="flex justify-between text-sm mb-1">
                <span class="font-medium text-gray-700"><?= h($structure['short']) ?></span>
                <span class="text-gray-500"><?= (int)$score ?> pts</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full <?= $key === $recommended ? 'bg-emerald-600' : 'bg-gray-300' ?>"
                     style="width: <?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Disclaimer -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-8">
        <p class="text-amber-800 text-sm font-semibold mb-1">Not legal advice</p>
        <p class="text-amber-700 text-sm">This is a recommendation tool, not legal advice. Have a Washington-licensed cooperative attorney review your situation before making any decisions about legal structure or filings.</p>
    </div>

    <div class="flex justify-end">
        <a href="<?= url('/deal-modeler.php') ?>"
           class="bg-emerald-700 hover:bg-emerald-600 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
            Model your deal &rarr;
        </a>
    </div>

    <?php else: ?>

    <!-- ASSESSMENT FORM -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Find your cooperative structure</h1>
        <p class="text-gray-500 text-sm">Answer all six questions. The tool scores each structure against your answers and recommends the best fit.</p>
    </div>

    <?php if ($errors): ?>
    <div class="mb-6 bg-red-50 border border-red-200 rounded-md px-4 py-3">
        <ul class="list-disc list-inside text-red-600 text-sm space-y-0.5">
            <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/structure-selector.php') ?>" class="space-y-6">
        <?= csrfField() ?>

        <?php foreach ($questions as $i => $q): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Question <?= $i + 1 ?> of <?= count($questions) ?></p>
            <h3 class="text-base font-semibold text-gray-900 mb-1"><?= h($q['question']) ?></h3>
            <?php if (!empty($q['help'])): ?>
            <p class="text-sm text-gray-500 mb-4"><?= h($q['help']) ?></p>
            <?php else: ?>
            <div class="mb-4"></div>
            <?php endif; ?>
            <div class="space-y-2">
                <?php foreach ($q['options'] as $opt): ?>
                <label class="flex items-start gap-3 cursor-pointer group">
                    <input type="radio"
                           name="q_<?= h($q['id']) ?>"
                           value="<?= h($opt['value']) ?>"
                           <?= ($existingAnswers[$q['id']] ?? '') === $opt['value'] ? 'checked' : '' ?>
                           class="mt-0.5 accent-emerald-600">
                    <span class="text-sm text-gray-700 group-hover:text-gray-900"><?= h($opt['label']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="flex justify-end pt-2">
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-600 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
                Get my recommendation &rarr;
            </button>
        </div>

    </form>

    <?php endif; ?>

</div>
</body>
</html>
