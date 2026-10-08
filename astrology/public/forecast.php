<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Check access
if (!userHasAccess($user['id'])) {
    setFlash('error', 'Please subscribe to access monthly forecasts.');
    header('Location: ' . url('/pricing.php'));
    exit;
}

// Get user's profile
$stmt = $db->prepare("SELECT * FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    setFlash('error', 'Please create your zodiac profile first.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

// Determine month
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthDate = $month . '-01';

// Load forecast
$stmt = $db->prepare("SELECT * FROM as_forecasts WHERE forecast_month = ? AND zodiac_animal = ?");
$stmt->execute([$monthDate, $profile['zodiac_animal']]);
$forecast = $stmt->fetch();

if (!$forecast) {
    setFlash('error', 'No forecast available for ' . date('F Y', strtotime($monthDate)) . ' yet.');
    header('Location: ' . url('/forecasts.php'));
    exit;
}

// Track view
$stmt = $db->prepare("INSERT IGNORE INTO as_user_forecasts (user_id, forecast_id, viewed_at) VALUES (?, ?, NOW())");
$stmt->execute([$user['id'], $forecast['id']]);

$data = json_decode($forecast['content_json'], true);
$animalInfo = getAnimalInfo($profile['zodiac_animal']);
$css = getElementCSS($profile['zodiac_element']);
$emoji = getAnimalEmoji($profile['zodiac_animal']);
$displayMonth = date('F Y', strtotime($monthDate));

// Get element-specific overlay
$elementKey = strtolower($profile['zodiac_element']);
$elementOverlay = $data['element_overlays'][$elementKey] ?? null;

// Calculate prev/next months
$prevMonth = date('Y-m', strtotime($monthDate . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthDate . ' +1 month'));

$pageTitle = $displayMonth . ' Forecast';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Month Navigation -->
    <div class="flex items-center justify-between mb-6">
        <a href="<?= url('/forecast.php') ?>?month=<?= h($prevMonth) ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">&larr; Previous Month</a>
        <h1 class="text-2xl font-bold text-gray-900"><?= $emoji ?> <?= h($displayMonth) ?></h1>
        <a href="<?= url('/forecast.php') ?>?month=<?= h($nextMonth) ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Next Month &rarr;</a>
    </div>

    <p class="text-center text-sm text-gray-500 mb-8">Forecast for <?= h($profile['zodiac_element']) ?> <?= h($profile['zodiac_animal']) ?></p>

    <!-- General Overview -->
    <?php if (!empty($data['general_overview'])): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-4">
        <h2 class="text-lg font-bold text-gray-900 mb-3">Overview</h2>
        <p class="text-gray-700 leading-relaxed"><?= h($data['general_overview']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Element-Specific Overlay -->
    <?php if ($elementOverlay): ?>
    <div class="<?= $css['bg'] ?> <?= $css['border'] ?> border rounded-xl p-6 mb-4">
        <h2 class="text-lg font-bold <?= $css['text'] ?> mb-3"><?= h($profile['zodiac_element']) ?> Element Influence</h2>
        <p class="text-gray-700 leading-relaxed"><?= h($elementOverlay) ?></p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <?php if (!empty($data['career'])): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-2">Career & Finance</h3>
            <p class="text-sm text-gray-700"><?= h($data['career']) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['relationships'])): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-2">Relationships</h3>
            <p class="text-sm text-gray-700"><?= h($data['relationships']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($data['health'])): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-4">
        <h3 class="font-bold text-gray-900 mb-2">Health & Wellness</h3>
        <p class="text-sm text-gray-700"><?= h($data['health']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Personal Consultation -->
    <div class="bg-gradient-to-r from-brand-50 to-amber-50 border border-brand-200 rounded-xl p-6 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <h3 class="font-bold text-gray-900 mb-1">Personalized Guidance for This Month</h3>
                <p class="text-sm text-gray-600">Want to navigate this month's energy with expert support? Jillian Ribbons offers remote East Asian Medicine sessions tailored to your unique constitution.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-shrink-0">
                <a href="https://joypointacupuncture.fullslate.com/services/413" target="_blank" rel="noopener" class="bg-brand-600 text-white px-5 py-2 rounded-lg hover:bg-brand-700 font-medium text-sm text-center transition">Book a Session</a>
                <a href="https://www.sacredflowhealingarts.com/services" target="_blank" rel="noopener" class="text-brand-600 hover:text-brand-700 text-xs font-medium text-center">Learn about Jillian &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Lucky Days -->
    <?php if (!empty($data['lucky_days']) && is_array($data['lucky_days'])): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-4">
        <h3 class="font-bold text-gray-900 mb-3">Key Dates</h3>
        <ul class="space-y-2">
            <?php foreach ($data['lucky_days'] as $day): ?>
            <li class="flex items-start gap-2 text-sm">
                <span class="text-brand-500 mt-0.5">&#x2605;</span>
                <span class="text-gray-700"><?= h($day) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Meditation Focus -->
    <?php if (!empty($data['meditation_focus'])): ?>
    <div class="bg-brand-50 border border-brand-200 rounded-xl p-6 mb-8">
        <h3 class="font-bold text-brand-800 mb-2">Meditation Focus</h3>
        <p class="text-brand-700 text-sm"><?= h($data['meditation_focus']) ?></p>
        <a href="<?= url('/meditations.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium mt-2 inline-block">Browse Meditations &rarr;</a>
    </div>
    <?php endif; ?>

    <div class="text-center">
        <a href="<?= url('/forecasts.php') ?>" class="text-brand-600 hover:text-brand-700 font-medium">&larr; All Forecasts</a>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
