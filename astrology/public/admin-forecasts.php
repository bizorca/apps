<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
require AS_ROOT . '/includes/claude-api.php';
requireLogin();

if (!isAdmin()) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();
$animals = getAllAnimals();
$message = '';
$generated = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/admin-forecasts.php'));
        exit;
    }

    $targetMonth = $_POST['target_month'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
        setFlash('error', 'Invalid month format.');
        header('Location: ' . url('/admin-forecasts.php'));
        exit;
    }

    $monthDate = $targetMonth . '-01';
    $monthLabel = date('F Y', strtotime($monthDate));

    foreach ($animals as $animal) {
        // Check if already exists
        $stmt = $db->prepare("SELECT id FROM as_forecasts WHERE forecast_month = ? AND zodiac_animal = ?");
        $stmt->execute([$monthDate, $animal]);
        if ($stmt->fetch()) {
            $generated[] = "$animal: already exists";
            continue;
        }

        $content = generateMonthlyForecast($animal, $monthLabel, $monthDate);
        if ($content) {
            $stmt = $db->prepare("INSERT INTO as_forecasts (forecast_month, zodiac_animal, content_json) VALUES (?, ?, ?)");
            $stmt->execute([$monthDate, $animal, json_encode($content)]);
            $generated[] = "$animal: generated";
        } else {
            $generated[] = "$animal: FAILED";
        }

        sleep(1); // Rate limiting
    }

    $message = "Forecast generation complete for $monthLabel.";
}

// Load existing forecasts grouped by month
$stmt = $db->query("SELECT forecast_month, COUNT(*) as count FROM as_forecasts GROUP BY forecast_month ORDER BY forecast_month DESC");
$existingMonths = $stmt->fetchAll();

$pageTitle = 'Generate Forecasts';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Admin: Generate Monthly Forecasts</h1>

    <?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-medium"><?= h($message) ?></p>
        <?php if (!empty($generated)): ?>
        <ul class="mt-2 text-sm text-green-700 space-y-1">
            <?php foreach ($generated as $g): ?>
            <li><?= h($g) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Generate Form -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-8">
        <h2 class="font-bold text-gray-900 mb-4">Generate New Forecasts</h2>
        <p class="text-sm text-gray-600 mb-4">This will generate 12 forecasts (one per zodiac animal) for the selected month using the Claude API. Takes about 1-2 minutes.</p>
        <form method="POST" class="flex gap-3 items-end">
            <?= csrfField() ?>
            <div class="flex-1">
                <label for="target_month" class="block text-sm text-gray-600 mb-1">Month</label>
                <input type="month" name="target_month" id="target_month" required
                       value="<?= h(date('Y-m')) ?>"
                       class="w-full rounded-lg border-gray-300 px-4 py-2.5 border">
            </div>
            <button type="submit" class="bg-brand-600 text-white px-6 py-2.5 rounded-lg hover:bg-brand-700 font-medium"
                    onclick="this.textContent='Generating...'; this.disabled=true; this.form.submit();">
                Generate All 12
            </button>
        </form>
    </div>

    <!-- Existing Forecasts -->
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <h2 class="font-bold text-gray-900 mb-4">Generated Forecasts</h2>
        <?php if (empty($existingMonths)): ?>
        <p class="text-gray-500 text-sm">No forecasts generated yet.</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($existingMonths as $em): ?>
            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                <span class="font-medium text-gray-900"><?= h(date('F Y', strtotime($em['forecast_month']))) ?></span>
                <span class="text-sm <?= $em['count'] >= 12 ? 'text-green-600' : 'text-amber-600' ?>">
                    <?= h($em['count']) ?>/12 animals
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
