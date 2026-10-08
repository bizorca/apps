<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Get user's zodiac animal
$stmt = $db->prepare("SELECT zodiac_animal, zodiac_element FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    setFlash('error', 'Please create your zodiac profile first.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

// Get all available forecasts for this animal
$stmt = $db->prepare("SELECT * FROM as_forecasts WHERE zodiac_animal = ? ORDER BY forecast_month DESC");
$stmt->execute([$profile['zodiac_animal']]);
$allForecasts = $stmt->fetchAll();

$emoji = getAnimalEmoji($profile['zodiac_animal']);

$pageTitle = 'Monthly Forecasts';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6"><?= $emoji ?> Monthly Forecasts for <?= h($profile['zodiac_animal']) ?></h1>

    <?php if (empty($allForecasts)): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-8 text-center">
        <p class="text-gray-500">No forecasts have been generated yet. Check back soon!</p>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($allForecasts as $f):
            $data = json_decode($f['content_json'], true);
            $monthDate = new DateTime($f['forecast_month']);
            $isCurrentMonth = $f['forecast_month'] === date('Y-m-01');
        ?>
        <a href="<?= url('/forecast.php') ?>?month=<?= h($monthDate->format('Y-m')) ?>"
           class="block bg-white border <?= $isCurrentMonth ? 'border-brand-300 ring-2 ring-brand-100' : 'border-gray-200' ?> rounded-xl p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">
                        <?= h($monthDate->format('F Y')) ?>
                        <?php if ($isCurrentMonth): ?>
                        <span class="text-xs font-normal bg-brand-100 text-brand-700 px-2 py-0.5 rounded-full ml-2">Current</span>
                        <?php endif; ?>
                    </h2>
                    <p class="text-sm text-gray-600 mt-1"><?= h(substr($data['general_overview'] ?? '', 0, 150)) ?>...</p>
                </div>
                <svg class="w-5 h-5 text-gray-400 flex-shrink-0 ml-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
