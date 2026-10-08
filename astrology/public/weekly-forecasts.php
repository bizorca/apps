<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Get user's profile for context
$stmt = $db->prepare("SELECT * FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// Load available weeks (where at least a Chinese forecast exists for this user's animal, or any western)
$stmt = $db->query("
    SELECT DISTINCT week_start
    FROM as_weekly_forecasts
    ORDER BY week_start DESC
    LIMIT 26
");
$weeks = $stmt->fetchAll(PDO::FETCH_COLUMN);

$currentWeekStart = date('Y-m-d', strtotime('monday this week'));

$pageTitle = 'Weekly Forecasts';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Weekly Forecasts</h1>
    <p class="text-gray-500 text-sm mb-8">Chinese + Western astrology combined, updated each week for Premium members.</p>

    <?php if (empty($weeks)): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-8 text-center text-gray-500">
        No weekly forecasts available yet. Check back Monday.
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($weeks as $weekStart): ?>
        <?php
            $weekEnd = date('M j, Y', strtotime($weekStart . ' +6 days'));
            $weekLabel = date('M j', strtotime($weekStart)) . '–' . $weekEnd;
            $isCurrent = $weekStart === $currentWeekStart;
        ?>
        <a href="<?= url('/weekly-forecast.php') ?>?week=<?= h($weekStart) ?>"
           class="flex items-center justify-between bg-white border <?= $isCurrent ? 'border-brand-400 ring-1 ring-brand-300' : 'border-gray-200' ?> rounded-xl p-5 hover:border-brand-300 transition group">
            <div>
                <div class="font-semibold text-gray-900 group-hover:text-brand-700">
                    <?= h($weekLabel) ?>
                    <?php if ($isCurrent): ?>
                    <span class="ml-2 text-xs bg-brand-100 text-brand-700 px-2 py-0.5 rounded-full font-medium">Current</span>
                    <?php endif; ?>
                </div>
                <?php if ($profile): ?>
                <div class="text-xs text-gray-400 mt-0.5">
                    <?= h($profile['zodiac_element']) ?> <?= h($profile['zodiac_animal']) ?>
                    <?php if (!empty($profile['western_sign'])): ?>&middot; <?= h($profile['western_sign']) ?><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <span class="text-brand-500 text-sm font-medium group-hover:translate-x-1 transition-transform">&rarr;</span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
