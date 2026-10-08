<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
require AS_ROOT . '/includes/western-astrology.php';
require AS_ROOT . '/includes/claude-api.php';
requireLogin();

if (!isAdmin()) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();
$animals = getAllAnimals();
$westernSigns = getAllWesternSigns();
$message = '';
$generated = [];

// Default to Monday of current week
$defaultWeekStart = date('Y-m-d', strtotime('monday this week'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/admin-weekly-forecasts.php'));
        exit;
    }

    $targetWeek = $_POST['week_start'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetWeek)) {
        setFlash('error', 'Invalid date format.');
        header('Location: ' . url('/admin-weekly-forecasts.php'));
        exit;
    }

    // Normalize to Monday
    $weekStart = date('Y-m-d', strtotime('monday', strtotime($targetWeek)));
    $weekEnd = date('Y-m-d', strtotime('sunday', strtotime($targetWeek)));
    $weekLabel = date('M j', strtotime($weekStart)) . ' – ' . date('M j, Y', strtotime($weekEnd));

    $generateType = $_POST['generate_type'] ?? 'both';

    // Chinese forecasts
    if (in_array($generateType, ['chinese', 'both'])) {
        foreach ($animals as $animal) {
            $stmt = $db->prepare("SELECT id FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'chinese' AND zodiac_key = ?");
            $stmt->execute([$weekStart, $animal]);
            if ($stmt->fetch()) {
                $generated[] = "Chinese / $animal: already exists";
                continue;
            }

            $content = generateWeeklyChineseForecast($animal, $weekLabel, $weekStart);
            if ($content) {
                $stmt = $db->prepare("INSERT INTO as_weekly_forecasts (week_start, forecast_type, zodiac_key, content_json) VALUES (?, 'chinese', ?, ?)");
                $stmt->execute([$weekStart, $animal, json_encode($content)]);
                $generated[] = "Chinese / $animal: generated";
            } else {
                $generated[] = "Chinese / $animal: FAILED";
            }

            sleep(1);
        }
    }

    // Western forecasts
    if (in_array($generateType, ['western', 'both'])) {
        foreach (array_keys($westernSigns) as $sign) {
            $stmt = $db->prepare("SELECT id FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'western' AND zodiac_key = ?");
            $stmt->execute([$weekStart, $sign]);
            if ($stmt->fetch()) {
                $generated[] = "Western / $sign: already exists";
                continue;
            }

            $content = generateWeeklyWesternForecast($sign, $weekLabel, $weekStart);
            if ($content) {
                $stmt = $db->prepare("INSERT INTO as_weekly_forecasts (week_start, forecast_type, zodiac_key, content_json) VALUES (?, 'western', ?, ?)");
                $stmt->execute([$weekStart, $sign, json_encode($content)]);
                $generated[] = "Western / $sign: generated";
            } else {
                $generated[] = "Western / $sign: FAILED";
            }

            sleep(1);
        }
    }

    $message = "Weekly forecast generation complete for $weekLabel.";
}

// Load existing weeks
$stmt = $db->query("
    SELECT week_start,
           SUM(forecast_type = 'chinese') as chinese_count,
           SUM(forecast_type = 'western') as western_count
    FROM as_weekly_forecasts
    GROUP BY week_start
    ORDER BY week_start DESC
    LIMIT 20
");
$existingWeeks = $stmt->fetchAll();

$pageTitle = 'Generate Weekly Forecasts';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Admin: Generate Weekly Forecasts</h1>

    <?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-medium"><?= h($message) ?></p>
        <?php if (!empty($generated)): ?>
        <ul class="mt-2 text-sm text-green-700 space-y-1 max-h-60 overflow-y-auto">
            <?php foreach ($generated as $g): ?>
            <li><?= h($g) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Generate Form -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-8">
        <h2 class="font-bold text-gray-900 mb-4">Generate New Weekly Forecasts</h2>
        <p class="text-sm text-gray-600 mb-4">Generates up to 24 forecasts (12 Chinese + 12 Western) for the selected week. Takes 3–5 minutes for a full run.</p>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div class="flex gap-4 items-end flex-wrap">
                <div class="flex-1 min-w-[180px]">
                    <label for="week_start" class="block text-sm text-gray-600 mb-1">Week (any day in the week)</label>
                    <input type="date" name="week_start" id="week_start" required
                           value="<?= h($defaultWeekStart) ?>"
                           class="w-full rounded-lg border-gray-300 px-4 py-2.5 border">
                    <p class="text-xs text-gray-400 mt-1">Will auto-normalize to Monday</p>
                </div>
                <div>
                    <label for="generate_type" class="block text-sm text-gray-600 mb-1">System</label>
                    <select name="generate_type" id="generate_type" class="rounded-lg border-gray-300 px-4 py-2.5 border">
                        <option value="both">Both (Chinese + Western)</option>
                        <option value="chinese">Chinese only</option>
                        <option value="western">Western only</option>
                    </select>
                </div>
                <button type="submit" class="bg-brand-600 text-white px-6 py-2.5 rounded-lg hover:bg-brand-700 font-medium"
                        onclick="this.textContent='Generating...'; this.disabled=true; this.form.submit();">
                    Generate
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Weeks -->
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <h2 class="font-bold text-gray-900 mb-4">Generated Weeks</h2>
        <?php if (empty($existingWeeks)): ?>
        <p class="text-gray-500 text-sm">No weekly forecasts generated yet.</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($existingWeeks as $w): ?>
            <?php
                $wEnd = date('M j, Y', strtotime($w['week_start'] . ' +6 days'));
                $wLabel = date('M j', strtotime($w['week_start'])) . ' – ' . $wEnd;
            ?>
            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                <span class="font-medium text-gray-900"><?= h($wLabel) ?></span>
                <div class="flex gap-3 text-sm">
                    <span class="<?= $w['chinese_count'] >= 12 ? 'text-green-600' : 'text-amber-600' ?>">
                        CN: <?= (int)$w['chinese_count'] ?>/12
                    </span>
                    <span class="<?= $w['western_count'] >= 12 ? 'text-green-600' : 'text-amber-600' ?>">
                        WE: <?= (int)$w['western_count'] ?>/12
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
