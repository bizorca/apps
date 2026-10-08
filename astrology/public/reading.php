<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
require AS_ROOT . '/includes/claude-api.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Load primary profile
$stmt = $db->prepare("SELECT * FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    setFlash('error', 'Please create your zodiac profile first.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$animalInfo = getAnimalInfo($profile['zodiac_animal']);
$tcm = getElementTCM($profile['zodiac_element']);
$css = getElementCSS($profile['zodiac_element']);
$compat = getCompatibility($profile['zodiac_animal']);
$emoji = getAnimalEmoji($profile['zodiac_animal']);
$yearInfo = getCurrentYearInfo();

// Load or generate full reading
$stmt = $db->prepare("SELECT * FROM as_readings WHERE profile_id = ? AND reading_type = 'full' ORDER BY id DESC LIMIT 1");
$stmt->execute([$profile['id']]);
$readingRow = $stmt->fetch();

// Load or calculate Four Pillars
$fourPillars = !empty($profile['four_pillars_json']) ? json_decode($profile['four_pillars_json'], true) : null;
if (!$fourPillars) {
    $fourPillars = calculateFourPillars(
        (int)$profile['birth_year'],
        $profile['birth_month'] !== null ? (int)$profile['birth_month'] : null,
        $profile['birth_day'] !== null ? (int)$profile['birth_day'] : null,
        $profile['birth_hour'] !== null ? (int)$profile['birth_hour'] : null
    );
    $db->prepare("UPDATE as_profiles SET four_pillars_json = ? WHERE id = ?")->execute([json_encode($fourPillars), $profile['id']]);
}

if (!$readingRow) {
    // Generate full reading
    $profileData = [
        'birth_year' => $profile['birth_year'],
        'birth_month' => $profile['birth_month'],
        'birth_day' => $profile['birth_day'],
        'birth_hour' => $profile['birth_hour'],
        'zodiac_animal' => $profile['zodiac_animal'],
        'zodiac_element' => $profile['zodiac_element'],
        'yin_yang' => $profile['yin_yang'],
        'heavenly_stem' => $profile['heavenly_stem'],
        'earthly_branch' => $profile['earthly_branch'],
        'four_pillars' => $fourPillars,
    ];

    $readingData = generateFullReading($profileData);
    if ($readingData) {
        $stmt = $db->prepare("INSERT INTO as_readings (profile_id, user_id, reading_type, content_json) VALUES (?, ?, 'full', ?)");
        $stmt->execute([$profile['id'], $user['id'], json_encode($readingData)]);
        $reading = $readingData;
    } else {
        $reading = null;
    }
} else {
    $reading = json_decode($readingRow['content_json'], true);
}

$pageTitle = 'Your Full Reading';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="<?= $css['bg'] ?> <?= $css['border'] ?> border rounded-2xl p-8 mb-8 text-center">
        <div class="text-6xl mb-4"><?= $emoji ?></div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= h($profile['zodiac_element']) ?> <?= h($profile['zodiac_animal']) ?></h1>
        <div class="flex items-center justify-center gap-3 mb-3">
            <span class="element-badge element-<?= strtolower($profile['zodiac_element']) ?>"><?= h($profile['zodiac_element']) ?></span>
            <span class="element-badge bg-gray-100 text-gray-700"><?= h($profile['yin_yang']) ?></span>
        </div>
        <p class="text-sm text-gray-600">
            <?= h($profile['heavenly_stem']) ?> &middot; <?= h($profile['earthly_branch']) ?> &middot; Born <?= h($profile['birth_year']) ?>
            <?php if ($fourPillars && $fourPillars['day_master']): ?>
            &middot; Day Master: <?= h($fourPillars['day_master']['chinese']) ?> <?= h($fourPillars['day_master']['name']) ?> (<?= h($fourPillars['day_master']['element']) ?>)
            <?php endif; ?>
        </p>
    </div>

    <?php if ($fourPillars && $fourPillars['pillar_count'] > 1): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-3">Four Pillars (Ba Zi)</h2>
        <div class="grid grid-cols-<?= min($fourPillars['pillar_count'], 4) ?> gap-3">
            <?php
            $pillarKeys = ['hour_pillar', 'day_pillar', 'month_pillar', 'year_pillar'];
            foreach ($pillarKeys as $key):
                $p = $fourPillars[$key] ?? null;
                if (!$p) continue;
                $isDay = ($key === 'day_pillar');
                $stemCss = getElementCSS($p['stem']['element']);
                $branchCss = getElementCSS($p['branch']['element']);
            ?>
            <div class="text-center p-3 rounded-lg border <?= $isDay ? 'border-brand-300 bg-brand-50/50' : 'border-gray-200 bg-gray-50' ?>">
                <div class="text-xs font-medium <?= $isDay ? 'text-brand-600' : 'text-gray-500' ?>"><?= h($p['chinese_label']) ?> <span class="text-gray-400"><?= h($p['metaphor']) ?></span></div>
                <div class="text-xl font-bold text-gray-900 mt-1"><?= h($p['stem']['chinese']) ?></div>
                <span class="inline-block px-1.5 py-0.5 rounded text-xs <?= $stemCss['badge_bg'] ?> <?= $stemCss['text'] ?>"><?= h($p['stem']['element']) ?></span>
                <div class="border-t border-gray-200 my-1.5"></div>
                <div class="text-xl font-bold text-gray-900"><?= h($p['branch']['chinese']) ?></div>
                <div class="text-xs text-gray-500"><?= getAnimalEmoji($p['branch']['animal']) ?> <?= h($p['branch']['animal']) ?></div>
                <span class="inline-block px-1.5 py-0.5 rounded text-xs <?= $branchCss['badge_bg'] ?> <?= $branchCss['text'] ?>"><?= h($p['branch']['element']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$reading): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center">
        <p class="text-yellow-800">We couldn't generate your reading right now. Please try refreshing the page in a moment.</p>
    </div>
    <?php else: ?>

    <!-- Reading Sections -->
    <?php
    $sections = [
        'overview' => ['title' => 'Overview', 'icon' => '&#x2728;'],
        'bazi_analysis' => ['title' => 'Four Pillars Analysis', 'icon' => '&#x1F3DB;'],
        'personality' => ['title' => 'Personality', 'icon' => '&#x1F9E0;'],
        'element_analysis' => ['title' => $profile['zodiac_element'] . ' Element Analysis', 'icon' => '&#x1F30A;'],
        'element_balance_advice' => ['title' => 'Element Balance', 'icon' => '&#x2696;'],
        'tcm_health' => ['title' => 'TCM Health Profile', 'icon' => '&#x1F33F;'],
        'relationships' => ['title' => 'Relationships', 'icon' => '&#x2764;'],
        'career' => ['title' => 'Career & Purpose', 'icon' => '&#x1F3AF;'],
        'life_path' => ['title' => 'Life Path', 'icon' => '&#x1F6E4;'],
        'current_year' => ['title' => $yearInfo['year'] . ' - Year of the ' . $yearInfo['animal'], 'icon' => '&#x1F4C5;'],
    ];
    foreach ($sections as $key => $section):
        if (empty($reading[$key])) continue;
    ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-4">
        <h2 class="text-lg font-bold text-gray-900 mb-3"><span class="mr-2"><?= $section['icon'] ?></span><?= h($section['title']) ?></h2>
        <p class="text-gray-700 leading-relaxed"><?= h($reading[$key]) ?></p>
    </div>
    <?php endforeach; ?>

    <!-- Seasonal Guidance -->
    <?php if (!empty($reading['seasonal_guidance']) && is_array($reading['seasonal_guidance'])): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-4">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Seasonal Wellness Guide</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php
            $seasonColors = ['spring' => 'green', 'summer' => 'red', 'autumn' => 'amber', 'winter' => 'blue'];
            foreach ($reading['seasonal_guidance'] as $season => $guidance):
                $sColor = $seasonColors[$season] ?? 'gray';
            ?>
            <div class="bg-<?= $sColor ?>-50 rounded-lg p-4 border border-<?= $sColor ?>-100">
                <h3 class="font-semibold text-gray-900 capitalize mb-1"><?= h($season) ?></h3>
                <p class="text-sm text-gray-700"><?= h($guidance) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Personal Consultation -->
    <div class="bg-gradient-to-r from-brand-50 to-amber-50 border border-brand-200 rounded-xl p-6 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <h3 class="font-bold text-gray-900 mb-1">Go Deeper with a Personal Consultation</h3>
                <p class="text-sm text-gray-600">Want personalized guidance rooted in East Asian Medicine? Jillian Ribbons offers remote sessions combining acupuncture principles, TCM pulse diagnosis, and holistic healing.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-shrink-0">
                <a href="https://joypointacupuncture.fullslate.com/services/413" target="_blank" rel="noopener" class="bg-brand-600 text-white px-5 py-2 rounded-lg hover:bg-brand-700 font-medium text-sm text-center transition">Book a Session</a>
                <a href="https://www.sacredflowhealingarts.com/services" target="_blank" rel="noopener" class="text-brand-600 hover:text-brand-700 text-xs font-medium text-center">Learn about Jillian &rarr;</a>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <!-- Static Data Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <!-- TCM Element -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-3">TCM Element: <?= h($profile['zodiac_element']) ?></h3>
            <div class="space-y-2 text-sm">
                <p><span class="font-medium text-gray-500">Organs:</span> <?= h(implode(' / ', $tcm['organs'])) ?></p>
                <p><span class="font-medium text-gray-500">Positive:</span> <?= h($tcm['emotion_positive']) ?></p>
                <p><span class="font-medium text-gray-500">Challenge:</span> <?= h($tcm['emotion_negative']) ?></p>
                <p><span class="font-medium text-gray-500">Foods:</span> <?= h(implode(', ', $tcm['foods'])) ?></p>
                <p class="text-gray-600 mt-2"><?= h($tcm['health_focus']) ?></p>
            </div>
        </div>

        <!-- Compatibility -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-3">Compatibility</h3>
            <div class="space-y-2 text-sm">
                <p>
                    <span class="font-medium text-green-700">Best Match:</span>
                    <?= getAnimalEmoji($compat['best_match']) ?> <?= h($compat['best_match']) ?>
                </p>
                <p>
                    <span class="font-medium text-blue-700">Harmonies:</span>
                    <?php foreach ($compat['harmonies'] as $h): ?>
                        <?= getAnimalEmoji($h) ?> <?= h($h) ?>&nbsp;
                    <?php endforeach; ?>
                </p>
                <p>
                    <span class="font-medium text-red-700">Clash:</span>
                    <?= getAnimalEmoji($compat['clash']) ?> <?= h($compat['clash']) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Lucky Attributes -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-8">
        <h3 class="font-bold text-gray-900 mb-3">Lucky Attributes</h3>
        <div class="grid grid-cols-3 gap-4 text-center text-sm">
            <div class="<?= $css['bg'] ?> rounded-lg p-4">
                <div class="font-medium text-gray-500 mb-1">Numbers</div>
                <div class="font-bold <?= $css['text'] ?> text-lg"><?= h(implode(', ', $animalInfo['lucky_numbers'] ?? [])) ?></div>
            </div>
            <div class="<?= $css['bg'] ?> rounded-lg p-4">
                <div class="font-medium text-gray-500 mb-1">Colors</div>
                <div class="font-bold <?= $css['text'] ?>"><?= h(implode(', ', $animalInfo['lucky_colors'] ?? [])) ?></div>
            </div>
            <div class="<?= $css['bg'] ?> rounded-lg p-4">
                <div class="font-medium text-gray-500 mb-1">Direction</div>
                <div class="font-bold <?= $css['text'] ?> text-lg"><?= h($animalInfo['lucky_direction'] ?? '') ?></div>
            </div>
        </div>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
