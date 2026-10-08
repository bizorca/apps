<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
require AS_ROOT . '/includes/western-astrology.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

if (!userHasAccess($user['id'])) {
    setFlash('error', 'Please subscribe to access weekly forecasts.');
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

// Determine week — default to current week's Monday
$requestedDate = $_GET['week'] ?? null;
if ($requestedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
    $weekStart = date('Y-m-d', strtotime('monday', strtotime($requestedDate)));
} else {
    $weekStart = date('Y-m-d', strtotime('monday this week'));
}

$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$weekLabel = date('M j', strtotime($weekStart)) . '–' . date('M j, Y', strtotime($weekEnd));

$prevWeek = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek = date('Y-m-d', strtotime($weekStart . ' +7 days'));

// Load Chinese weekly forecast
$stmt = $db->prepare("SELECT * FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'chinese' AND zodiac_key = ?");
$stmt->execute([$weekStart, $profile['zodiac_animal']]);
$chineseForecast = $stmt->fetch();

// Load Western weekly forecast (only if user has a sign)
$westernForecast = null;
$westernSign = $profile['western_sign'] ?? null;
if (!$westernSign && !empty($profile['birth_month']) && !empty($profile['birth_day'])) {
    $westernSign = getWesternSign((int)$profile['birth_month'], (int)$profile['birth_day']);
}
if ($westernSign) {
    $stmt = $db->prepare("SELECT * FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'western' AND zodiac_key = ?");
    $stmt->execute([$weekStart, $westernSign]);
    $westernForecast = $stmt->fetch();
}

// Load starseed result (for cosmic resonance section)
$stmt = $db->prepare("SELECT * FROM as_starseed_results WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user['id']]);
$starseedResult = $stmt->fetch();
$starseedReading = null;
if ($starseedResult && !empty($starseedResult['reading_json'])) {
    $starseedReading = json_decode($starseedResult['reading_json'], true);
}

if (!$chineseForecast && !$westernForecast) {
    setFlash('error', 'No weekly forecast available for ' . $weekLabel . ' yet. Check back soon!');
    header('Location: ' . url('/weekly-forecasts.php'));
    exit;
}

$chineseData = $chineseForecast ? json_decode($chineseForecast['content_json'], true) : null;
$westernData = $westernForecast ? json_decode($westernForecast['content_json'], true) : null;

$animalInfo = getAnimalInfo($profile['zodiac_animal']);
$css = getElementCSS($profile['zodiac_element']);
$emoji = getAnimalEmoji($profile['zodiac_animal']);
$elementKey = strtolower($profile['zodiac_element']);

$westernSignInfo = $westernSign ? getWesternSignInfo($westernSign) : null;
$westernElement = $westernSign ? ($profile['western_element'] ?? getWesternElement($westernSign)) : null;
$westernCss = $westernElement ? getWesternElementCSS($westernElement) : null;
$westernGlyph = $westernSign ? getWesternSignGlyph($westernSign) : null;

$starseedColors = [
    'pleiadian' => ['bg' => 'bg-rose-50', 'border' => 'border-rose-200', 'text' => 'text-rose-700', 'glyph' => '✦'],
    'sirian' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'text' => 'text-blue-700', 'glyph' => '★'],
    'arcturian' => ['bg' => 'bg-violet-50', 'border' => 'border-violet-200', 'text' => 'text-violet-700', 'glyph' => '◈'],
    'andromedan' => ['bg' => 'bg-sky-50', 'border' => 'border-sky-200', 'text' => 'text-sky-700', 'glyph' => '⬡'],
    'lyran' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-700', 'glyph' => '♦'],
    'orion' => ['bg' => 'bg-orange-50', 'border' => 'border-orange-200', 'text' => 'text-orange-700', 'glyph' => '⚔'],
    'mintakan' => ['bg' => 'bg-teal-50', 'border' => 'border-teal-200', 'text' => 'text-teal-700', 'glyph' => '◎'],
    'hadarian' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-700', 'glyph' => '❋'],
    'alpha_centaurian' => ['bg' => 'bg-cyan-50', 'border' => 'border-cyan-200', 'text' => 'text-cyan-700', 'glyph' => '⬡'],
    'draconian' => ['bg' => 'bg-slate-50', 'border' => 'border-slate-300', 'text' => 'text-slate-700', 'glyph' => '🜃'],
];
$starseedNames = [
    'pleiadian' => 'Pleiadian', 'sirian' => 'Sirian', 'arcturian' => 'Arcturian',
    'andromedan' => 'Andromedan', 'lyran' => 'Lyran', 'orion' => 'Orion',
    'mintakan' => 'Mintakan', 'hadarian' => 'Hadarian',
    'alpha_centaurian' => 'Alpha Centaurian', 'draconian' => 'Draconian',
];

$pageTitle = 'Weekly Forecast: ' . $weekLabel;
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">

    <!-- Week Navigation -->
    <div class="flex items-center justify-between mb-6">
        <a href="<?= url('/weekly-forecast.php') ?>?week=<?= h($prevWeek) ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">&larr; Previous Week</a>
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900">Week of <?= h($weekLabel) ?></h1>
        </div>
        <?php if ($nextWeek <= date('Y-m-d', strtotime('monday next week'))): ?>
        <a href="<?= url('/weekly-forecast.php') ?>?week=<?= h($nextWeek) ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Next Week &rarr;</a>
        <?php else: ?>
        <span class="text-gray-300 text-sm font-medium">Next Week &rarr;</span>
        <?php endif; ?>
    </div>

    <!-- Signs Banner -->
    <div class="flex flex-wrap gap-3 justify-center mb-8">
        <?php if ($chineseData): ?>
        <span class="<?= $css['bg'] ?> <?= $css['border'] ?> <?= $css['text'] ?> border px-4 py-1.5 rounded-full text-sm font-semibold">
            <?= $emoji ?> <?= h($profile['zodiac_element']) ?> <?= h($profile['zodiac_animal']) ?>
        </span>
        <?php endif; ?>
        <?php if ($westernData && $westernSign): ?>
        <span class="<?= $westernCss['badge_bg'] ?> <?= $westernCss['text'] ?> px-4 py-1.5 rounded-full text-sm font-semibold border <?= $westernCss['border'] ?? 'border-gray-200' ?>">
            <?= h($westernGlyph) ?> <?= h($westernSign) ?>
        </span>
        <?php endif; ?>
        <?php if ($starseedResult): ?>
        <?php $ssc = $starseedColors[$starseedResult['primary_lineage']] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-700', 'glyph' => '✦']; ?>
        <span class="<?= $ssc['bg'] ?> <?= $ssc['border'] ?> <?= $ssc['text'] ?> border px-4 py-1.5 rounded-full text-sm font-semibold">
            <?= $ssc['glyph'] ?> <?= h($starseedNames[$starseedResult['primary_lineage']] ?? ucfirst($starseedResult['primary_lineage'])) ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if ($chineseData): ?>
    <!-- Chinese Weekly Section -->
    <div class="mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <?= $emoji ?> Chinese Astrology
            <span class="text-sm font-normal text-gray-500"><?= h($profile['zodiac_element']) ?> <?= h($profile['zodiac_animal']) ?></span>
        </h2>

        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-4">
            <h3 class="font-bold text-gray-900 mb-2">Week Overview</h3>
            <p class="text-gray-700 leading-relaxed"><?= h($chineseData['overview'] ?? '') ?></p>
        </div>

        <?php
        $elementOverlay = $chineseData['element_overlays'][$elementKey] ?? null;
        if ($elementOverlay):
        ?>
        <div class="<?= $css['bg'] ?> <?= $css['border'] ?> border rounded-xl p-6 mb-4">
            <h3 class="font-bold <?= $css['text'] ?> mb-2"><?= h($profile['zodiac_element']) ?> Element This Week</h3>
            <p class="text-gray-700 leading-relaxed"><?= h($elementOverlay) ?></p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <?php if (!empty($chineseData['career'])): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h4 class="font-bold text-gray-900 mb-2 text-sm">Career & Finance</h4>
                <p class="text-sm text-gray-700"><?= h($chineseData['career']) ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($chineseData['relationships'])): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h4 class="font-bold text-gray-900 mb-2 text-sm">Relationships</h4>
                <p class="text-sm text-gray-700"><?= h($chineseData['relationships']) ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($chineseData['health'])): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h4 class="font-bold text-gray-900 mb-2 text-sm">Health & TCM</h4>
                <p class="text-sm text-gray-700"><?= h($chineseData['health']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php if (!empty($chineseData['key_days']) && is_array($chineseData['key_days'])): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h4 class="font-bold text-gray-900 mb-3 text-sm">Key Days</h4>
                <ul class="space-y-2">
                    <?php foreach ($chineseData['key_days'] as $day): ?>
                    <li class="flex items-start gap-2 text-sm">
                        <span class="text-brand-500 mt-0.5">&#x2605;</span>
                        <span class="text-gray-700"><?= h($day) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if (!empty($chineseData['intention'])): ?>
            <div class="bg-brand-50 border border-brand-200 rounded-xl p-5">
                <h4 class="font-bold text-brand-800 mb-2 text-sm">Weekly Intention</h4>
                <p class="text-brand-700 text-sm italic">"<?= h($chineseData['intention']) ?>"</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($westernData && $westernSign && $westernSignInfo): ?>
    <!-- Divider -->
    <?php if ($chineseData): ?>
    <hr class="border-gray-200 mb-8">
    <?php endif; ?>

    <!-- Western Weekly Section -->
    <div class="mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <span><?= h($westernGlyph) ?></span> Western Astrology
            <span class="text-sm font-normal text-gray-500"><?= h($westernSign) ?> &middot; <?= h($westernElement ?? '') ?></span>
        </h2>

        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-4">
            <h3 class="font-bold text-gray-900 mb-2">Week Overview</h3>
            <p class="text-gray-700 leading-relaxed"><?= h($westernData['overview'] ?? '') ?></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <?php if (!empty($westernData['love'])): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h4 class="font-bold text-gray-900 mb-2 text-sm">Love & Relationships</h4>
                <p class="text-sm text-gray-700"><?= h($westernData['love']) ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($westernData['career'])): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h4 class="font-bold text-gray-900 mb-2 text-sm">Career & Ambition</h4>
                <p class="text-sm text-gray-700"><?= h($westernData['career']) ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($westernData['wellness'])): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h4 class="font-bold text-gray-900 mb-2 text-sm">Wellness</h4>
                <p class="text-sm text-gray-700"><?= h($westernData['wellness']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php if (!empty($westernData['key_days']) && is_array($westernData['key_days'])): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h4 class="font-bold text-gray-900 mb-3 text-sm">Key Days</h4>
                <ul class="space-y-2">
                    <?php foreach ($westernData['key_days'] as $day): ?>
                    <li class="flex items-start gap-2 text-sm">
                        <span class="text-brand-500 mt-0.5">&#x2605;</span>
                        <span class="text-gray-700"><?= h($day) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if (!empty($westernData['intention'])): ?>
            <div class="<?= $westernCss['badge_bg'] ?? 'bg-gray-50' ?> border <?= $westernCss['border'] ?? 'border-gray-200' ?> rounded-xl p-5">
                <h4 class="font-bold <?= $westernCss['text'] ?? 'text-gray-700' ?> mb-2 text-sm">Weekly Intention</h4>
                <p class="<?= $westernCss['text'] ?? 'text-gray-700' ?> text-sm italic">"<?= h($westernData['intention']) ?>"</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif ($chineseData && !$westernSign): ?>
    <!-- Prompt to add birth date for Western forecasts -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-8">
        <p class="text-sm text-amber-800">Add your full birth date to unlock your Western sun sign weekly forecast alongside your Chinese forecast.</p>
        <a href="<?= url('/profile.php') ?>?id=<?= $profile['id'] ?>#update-birth" class="text-brand-700 font-medium text-sm hover:underline mt-1 inline-block">Add birth date &rarr;</a>
    </div>
    <?php endif; ?>

    <?php if ($starseedResult && $starseedReading): ?>
    <!-- Starseed Cosmic Resonance -->
    <?php
    $ssc = $starseedColors[$starseedResult['primary_lineage']] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-700', 'glyph' => '✦'];
    $ssName = $starseedNames[$starseedResult['primary_lineage']] ?? ucfirst($starseedResult['primary_lineage']);
    ?>
    <hr class="border-gray-200 mb-8">
    <div class="mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <span><?= $ssc['glyph'] ?></span> Cosmic Resonance
            <span class="text-sm font-normal text-gray-500"><?= h($ssName) ?> Lineage</span>
        </h2>

        <div class="<?= $ssc['bg'] ?> <?= $ssc['border'] ?> border rounded-xl p-6 mb-4">
            <p class="text-sm <?= $ssc['text'] ?> font-semibold mb-2">Activation Practices for This Week</p>
            <p class="text-gray-700 text-sm leading-relaxed"><?= h($starseedReading['activation_practices'] ?? '') ?></p>
        </div>

        <?php if (!empty($starseedReading['message'])): ?>
        <div class="bg-gray-900 rounded-xl p-6 text-center">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-3">Transmission</p>
            <p class="text-white text-sm leading-relaxed italic"><?= h($starseedReading['message']) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif (!$starseedResult): ?>
    <!-- CTA to take starseed quiz -->
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-8 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <p class="font-semibold text-gray-900 mb-1">Unlock Your Cosmic Resonance</p>
            <p class="text-sm text-gray-600">Take the Star Seed quiz to add a third layer to your weekly forecast — activation practices and transmissions from your home lineage.</p>
        </div>
        <a href="<?= url('/starseed-quiz.php') ?>" class="bg-brand-600 text-white px-5 py-2 rounded-lg hover:bg-brand-700 font-medium text-sm text-center shrink-0">Take the Quiz &rarr;</a>
    </div>
    <?php endif; ?>

    <!-- Personal Consultation CTA -->
    <div class="bg-gradient-to-r from-brand-50 to-amber-50 border border-brand-200 rounded-xl p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <h3 class="font-bold text-gray-900 mb-1">Personalized Guidance for This Week</h3>
                <p class="text-sm text-gray-600">Want to move through this week's energy with expert support? Jillian Ribbons offers remote East Asian Medicine sessions tailored to your unique constitution.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-shrink-0">
                <a href="https://joypointacupuncture.fullslate.com/services/413" target="_blank" rel="noopener" class="bg-brand-600 text-white px-5 py-2 rounded-lg hover:bg-brand-700 font-medium text-sm text-center transition">Book a Session</a>
                <a href="https://www.sacredhealingarts.com/services" target="_blank" rel="noopener" class="text-brand-600 hover:text-brand-700 text-xs font-medium text-center">Learn about Jillian &rarr;</a>
            </div>
        </div>
    </div>

    <div class="text-center">
        <a href="<?= url('/weekly-forecasts.php') ?>" class="text-brand-600 hover:text-brand-700 font-medium">&larr; All Weekly Forecasts</a>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
