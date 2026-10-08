<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
require AS_ROOT . '/includes/western-astrology.php';
require AS_ROOT . '/includes/claude-api.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();
$isPremium = (getEffectivePlan($user['id']) === 'premium');

// Load primary profile
$profile = null;
if ($user['primary_profile_id']) {
    $stmt = $db->prepare("SELECT * FROM as_profiles WHERE id = ? AND user_id = ?");
    $stmt->execute([$user['primary_profile_id'], $user['id']]);
    $profile = $stmt->fetch();
}
if (!$profile) {
    $stmt = $db->prepare("SELECT * FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
    if ($profile) {
        setPrimaryProfile((int)$user['id'], (int)$profile['id']);
    }
}

if ($profile) {
    $animalInfo = getAnimalInfo($profile['zodiac_animal']);
    $tcm        = getElementTCM($profile['zodiac_element']);
    $css        = getElementCSS($profile['zodiac_element']);
    $emoji      = getAnimalEmoji($profile['zodiac_animal']);

    // Four Pillars
    $fourPillars = !empty($profile['four_pillars_json']) ? json_decode($profile['four_pillars_json'], true) : null;
    if (!$fourPillars) {
        $fourPillars = calculateFourPillars(
            (int)$profile['birth_year'],
            $profile['birth_month'] !== null ? (int)$profile['birth_month'] : null,
            $profile['birth_day']   !== null ? (int)$profile['birth_day']   : null,
            $profile['birth_hour']  !== null ? (int)$profile['birth_hour']  : null
        );
        $db->prepare("UPDATE as_profiles SET four_pillars_json = ? WHERE id = ?")->execute([json_encode($fourPillars), $profile['id']]);
    }

    $currentWeekStart = date('Y-m-d', strtotime('monday this week'));

    // ── Auto-generate weekly forecasts for premium users ──────────────────────
    // We only hit the DB if the session week key has changed, so this check
    // costs a single DB read at most once per week per session.
    if ($isPremium) {
        $sessionKey = 'as_wf_generated_' . $currentWeekStart;
        if (empty($_SESSION[$sessionKey])) {
            $missing = [];

            $stmt = $db->prepare("SELECT id FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'chinese' AND zodiac_key = ?");
            $stmt->execute([$currentWeekStart, $profile['zodiac_animal']]);
            if (!$stmt->fetch()) $missing[] = 'chinese';

            $westernKey = $profile['western_sign'] ?? null;
            if ($westernKey) {
                $stmt = $db->prepare("SELECT id FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'western' AND zodiac_key = ?");
                $stmt->execute([$currentWeekStart, $westernKey]);
                if (!$stmt->fetch()) $missing[] = 'western';
            }

            if (!empty($missing)) {
                $weekLabel = date('M j', strtotime($currentWeekStart)) . ' – ' . date('M j, Y', strtotime($currentWeekStart . ' +6 days'));

                if (in_array('chinese', $missing)) {
                    $content = generateWeeklyChineseForecast($profile['zodiac_animal'], $weekLabel, $currentWeekStart);
                    if ($content) {
                        $db->prepare("INSERT INTO as_weekly_forecasts (week_start, forecast_type, zodiac_key, content_json) VALUES (?, 'chinese', ?, ?)")
                           ->execute([$currentWeekStart, $profile['zodiac_animal'], json_encode($content)]);
                    }
                }

                if (in_array('western', $missing) && $westernKey) {
                    $content = generateWeeklyWesternForecast($westernKey, $weekLabel, $currentWeekStart);
                    if ($content) {
                        $db->prepare("INSERT INTO as_weekly_forecasts (week_start, forecast_type, zodiac_key, content_json) VALUES (?, 'western', ?, ?)")
                           ->execute([$currentWeekStart, $westernKey, json_encode($content)]);
                    }
                }
            }

            $_SESSION[$sessionKey] = true;
        }
    }

    // Load this week's forecast
    $stmt = $db->prepare("SELECT * FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'chinese' AND zodiac_key = ?");
    $stmt->execute([$currentWeekStart, $profile['zodiac_animal']]);
    $weeklyForecast = $stmt->fetch();

    // Load full reading (for seasonal guidance)
    $stmt = $db->prepare("SELECT content_json FROM as_readings WHERE profile_id = ? AND reading_type = 'full' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$profile['id']]);
    $readingRow = $stmt->fetch();
    $fullReading = $readingRow ? json_decode($readingRow['content_json'], true) : null;

    // Current season
    $month = (int)date('n');
    $currentSeason = match(true) {
        $month >= 3 && $month <= 5 => 'spring',
        $month >= 6 && $month <= 8 => 'summer',
        $month >= 9 && $month <= 11 => 'autumn',
        default => 'winter',
    };
}

// Compatibility check
$compatResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['partner_year'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $partnerYear = (int)$_POST['partner_year'];
        if ($partnerYear >= 1920 && $partnerYear <= (int)date('Y')) {
            $partnerAnimal  = getZodiacAnimal($partnerYear);
            $partnerElement = getElement($partnerYear);
            $compatResult   = getCompatibilityBetween($profile['zodiac_animal'], $partnerAnimal);
            $compatResult['partner_animal']  = $partnerAnimal;
            $compatResult['partner_element'] = $partnerElement;
            $compatResult['partner_emoji']   = getAnimalEmoji($partnerAnimal);
        }
    }
}

$pageTitle = 'Dashboard';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Welcome, <?= h($user['name']) ?></h1>

    <?php if (!$profile): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-8 text-center">
        <div class="text-5xl mb-4">&#x2728;</div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Complete Your Zodiac Profile</h2>
        <p class="text-gray-600 mb-6">Enter your birth details to unlock your personalized astrology reading.</p>
        <form action="<?= url('/profile.php') ?>" method="POST" class="max-w-xs mx-auto">
            <?= csrfField() ?>
            <input type="number" name="birth_year" required min="1920" max="<?= date('Y') ?>" placeholder="Birth year (e.g. 1990)"
                   class="w-full rounded-lg border-gray-300 px-4 py-2.5 border mb-3">
            <button type="submit" class="w-full bg-brand-600 text-white py-2.5 rounded-lg hover:bg-brand-700 font-medium">Get My Profile</button>
        </form>
    </div>
    <?php else: ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Profile Card -->
        <div class="<?= $css['bg'] ?> <?= $css['border'] ?> border rounded-xl p-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="text-5xl"><?= $emoji ?></div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900"><?= h($profile['zodiac_element']) ?> <?= h($profile['zodiac_animal']) ?></h2>
                    <div class="flex gap-2 mt-1">
                        <span class="element-badge element-<?= strtolower($profile['zodiac_element']) ?>"><?= h($profile['zodiac_element']) ?></span>
                        <span class="element-badge bg-gray-100 text-gray-700"><?= h($profile['yin_yang']) ?></span>
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-3"><?= h($animalInfo['strengths'] ?? '') ?></p>
            <?php if ($fourPillars && $fourPillars['day_master']): ?>
            <p class="text-xs text-gray-500 mb-3">
                Day Master: <span class="font-semibold"><?= h($fourPillars['day_master']['chinese']) ?></span>
                <?= h($fourPillars['day_master']['name']) ?> (<?= h($fourPillars['day_master']['element']) ?>)
                &middot; <?= $fourPillars['pillar_count'] ?>/4 pillars
            </p>
            <?php elseif (!$fourPillars || $fourPillars['pillar_count'] < 3): ?>
            <p class="text-xs text-brand-600 mb-3">
                <a href="<?= url('/profile.php') ?>?id=<?= $profile['id'] ?>#update-birth">Add birth date for your full Ba Zi chart &rarr;</a>
            </p>
            <?php endif; ?>
            <a href="<?= url('/reading.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">View Full Reading &rarr;</a>
        </div>

        <!-- Weekly Forecast Card -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-1">Weekly Forecast</h3>
            <p class="text-sm text-gray-500 mb-3">Week of <?= date('M j', strtotime($currentWeekStart)) . '–' . date('M j', strtotime($currentWeekStart . ' +6 days')) ?></p>
            <?php if ($isPremium && $weeklyForecast):
                $weeklyData = json_decode($weeklyForecast['content_json'], true);
            ?>
            <p class="text-sm text-gray-700 mb-4"><?= h(substr($weeklyData['overview'] ?? '', 0, 150)) ?>...</p>
            <a href="<?= url('/weekly-forecast.php') ?>?week=<?= h($currentWeekStart) ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Read Full Weekly &rarr;</a>
            <?php elseif ($isPremium): ?>
            <p class="text-sm text-gray-500">Your forecast is being generated — check back in a moment.</p>
            <?php else: ?>
            <p class="text-sm text-gray-500 mb-3">Weekly forecasts are a Premium feature. Upgrade to receive personalized Chinese + Western forecasts every week.</p>
            <a href="<?= url('/pricing.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Upgrade to Premium &rarr;</a>
            <?php endif; ?>
        </div>

        <!-- TCM Health Profile -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-3">&#x1F33F; TCM Health Profile</h3>
            <?php if ($isPremium): ?>
            <div class="space-y-2 text-sm mb-4">
                <p><span class="font-medium text-gray-500">Element:</span> <span class="font-semibold"><?= h($profile['zodiac_element']) ?></span> &middot; <?= h($tcm['season'] ?? '') ?></p>
                <p><span class="font-medium text-gray-500">Organs:</span> <?= h(implode(' / ', $tcm['organs'] ?? [])) ?></p>
                <p><span class="font-medium text-gray-500">Emotion:</span> <?= h($tcm['emotion_positive'] ?? '') ?> / <?= h($tcm['emotion_negative'] ?? '') ?></p>
                <p><span class="font-medium text-gray-500">Foods:</span> <?= h(implode(', ', array_slice($tcm['foods'] ?? [], 0, 5))) ?></p>
                <?php if (!empty($tcm['health_focus'])): ?>
                <p class="text-gray-600 pt-1"><?= h($tcm['health_focus']) ?></p>
                <?php endif; ?>
            </div>
            <a href="<?= url('/reading.php') ?>#tcm" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Full TCM Reading &rarr;</a>
            <?php else: ?>
            <p class="text-sm text-gray-500 mb-1">Your <?= h($profile['zodiac_element']) ?> element maps to specific organ systems, emotional patterns, and dietary guidance in Traditional Chinese Medicine.</p>
            <p class="text-sm text-gray-400 mb-3">Unlock your full TCM health profile with Premium.</p>
            <a href="<?= url('/pricing.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Upgrade to Premium &rarr;</a>
            <?php endif; ?>
        </div>

        <!-- Seasonal Wellness -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <?php
            $seasonLabels = ['spring' => 'Spring 🌱', 'summer' => 'Summer ☀️', 'autumn' => 'Autumn 🍂', 'winter' => 'Winter ❄️'];
            $seasonColors = ['spring' => 'green', 'summer' => 'red', 'autumn' => 'amber', 'winter' => 'blue'];
            $sLabel = $seasonLabels[$currentSeason];
            $sColor = $seasonColors[$currentSeason];
            ?>
            <h3 class="font-bold text-gray-900 mb-1">Seasonal Wellness — <?= $sLabel ?></h3>
            <?php if ($isPremium): ?>
            <?php if (!empty($fullReading['seasonal_guidance'][$currentSeason])): ?>
            <p class="text-sm text-gray-700 leading-relaxed mb-3"><?= h($fullReading['seasonal_guidance'][$currentSeason]) ?></p>
            <?php else: ?>
            <p class="text-sm text-gray-500 mb-3">Generate your full reading to unlock seasonal wellness guidance tailored to your <?= h($profile['zodiac_element']) ?> element.</p>
            <?php endif; ?>
            <a href="<?= url('/reading.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">View All Seasons &rarr;</a>
            <?php else: ?>
            <p class="text-sm text-gray-500 mb-1">Seasonal wellness guidance shows how your <?= h($profile['zodiac_element']) ?> element interacts with each season — and what to eat, avoid, and practice.</p>
            <p class="text-sm text-gray-400 mb-3">Premium feature.</p>
            <a href="<?= url('/pricing.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Upgrade to Premium &rarr;</a>
            <?php endif; ?>
        </div>

        <!-- Meditation Card -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-2">Your Element Meditation</h3>
            <?php
            $stmt = $db->prepare("SELECT * FROM as_meditations WHERE element = ? LIMIT 1");
            $stmt->execute([$profile['zodiac_element']]);
            $meditation = $stmt->fetch();
            if ($meditation):
            ?>
            <p class="text-sm text-gray-500 mb-1"><?= h($meditation['title']) ?> &middot; <?= h($meditation['duration_minutes']) ?> min</p>
            <p class="text-sm text-gray-700 mb-4"><?= h(substr($meditation['description'], 0, 120)) ?>...</p>
            <a href="<?= url('/meditation.php') ?>?slug=<?= h($meditation['slug']) ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Start Meditation &rarr;</a>
            <?php else: ?>
            <p class="text-sm text-gray-500">Meditations coming soon.</p>
            <?php endif; ?>
        </div>

        <!-- Western Zodiac Card -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-2">Western Zodiac</h3>
            <?php
            $westernSign = $profile['western_sign'] ?? null;
            if (!$westernSign && !empty($profile['birth_month']) && !empty($profile['birth_day'])) {
                $westernSign    = getWesternSign((int)$profile['birth_month'], (int)$profile['birth_day']);
                $westernElement = getWesternElement($westernSign);
                $db->prepare("UPDATE as_profiles SET western_sign = ?, western_element = ? WHERE id = ?")
                   ->execute([$westernSign, $westernElement, $profile['id']]);
            } else {
                $westernElement = $profile['western_element'] ?? ($westernSign ? getWesternElement($westernSign) : null);
            }
            if ($westernSign):
                $westInfo  = getWesternSignInfo($westernSign);
                $westCss   = getWesternElementCSS($westernElement ?? getWesternElement($westernSign));
                $westGlyph = getWesternSignGlyph($westernSign);
            ?>
            <div class="flex items-center gap-3 mb-3">
                <div class="text-4xl leading-none"><?= h($westGlyph) ?></div>
                <div>
                    <div class="font-bold text-gray-900"><?= h($westernSign) ?></div>
                    <div class="flex gap-1 mt-0.5 flex-wrap">
                        <span class="<?= $westCss['badge_bg'] ?> <?= $westCss['text'] ?> px-2 py-0.5 rounded-full text-xs font-semibold"><?= h($westernElement ?? '') ?></span>
                        <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs"><?= h($westInfo['ruling_planet'] ?? '') ?></span>
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-3"><?= h(substr($westInfo['strengths'] ?? '', 0, 100)) ?>...</p>
            <a href="<?= url('/western-profile.php') ?>?id=<?= $profile['id'] ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">View Western Profile &rarr;</a>
            <?php else: ?>
            <p class="text-sm text-gray-500 mb-2">Add your full birth date to unlock your Western sun sign.</p>
            <a href="<?= url('/profile.php') ?>?id=<?= $profile['id'] ?>#update-birth" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Add birth date &rarr;</a>
            <?php endif; ?>
        </div>

        <!-- Star Seed Card -->
        <?php
        $starseedStmt = $db->prepare("SELECT * FROM as_starseed_results WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $starseedStmt->execute([$user['id']]);
        $starseedResult = $starseedStmt->fetch();
        $starseedColors = [
            'pleiadian'       => ['bg' => 'bg-rose-50',   'border' => 'border-rose-200',   'text' => 'text-rose-700',   'glyph' => '✦'],
            'sirian'          => ['bg' => 'bg-blue-50',   'border' => 'border-blue-200',   'text' => 'text-blue-700',   'glyph' => '★'],
            'arcturian'       => ['bg' => 'bg-violet-50', 'border' => 'border-violet-200', 'text' => 'text-violet-700', 'glyph' => '◈'],
            'andromedan'      => ['bg' => 'bg-sky-50',    'border' => 'border-sky-200',    'text' => 'text-sky-700',    'glyph' => '⬡'],
            'lyran'           => ['bg' => 'bg-amber-50',  'border' => 'border-amber-200',  'text' => 'text-amber-700',  'glyph' => '♦'],
            'orion'           => ['bg' => 'bg-orange-50', 'border' => 'border-orange-200', 'text' => 'text-orange-700', 'glyph' => '⚔'],
            'mintakan'        => ['bg' => 'bg-teal-50',   'border' => 'border-teal-200',   'text' => 'text-teal-700',   'glyph' => '◎'],
            'hadarian'        => ['bg' => 'bg-emerald-50','border' => 'border-emerald-200','text' => 'text-emerald-700','glyph' => '❋'],
            'alpha_centaurian'=> ['bg' => 'bg-cyan-50',   'border' => 'border-cyan-200',   'text' => 'text-cyan-700',   'glyph' => '⬡'],
            'draconian'       => ['bg' => 'bg-slate-50',  'border' => 'border-slate-300',  'text' => 'text-slate-700',  'glyph' => '🜃'],
        ];
        $starseedNames = [
            'pleiadian' => 'Pleiadian', 'sirian' => 'Sirian', 'arcturian' => 'Arcturian',
            'andromedan' => 'Andromedan', 'lyran' => 'Lyran', 'orion' => 'Orion',
            'mintakan' => 'Mintakan', 'hadarian' => 'Hadarian',
            'alpha_centaurian' => 'Alpha Centaurian', 'draconian' => 'Draconian',
        ];
        ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-2">Star Seed Lineage</h3>
            <?php if ($starseedResult):
                $ssc    = $starseedColors[$starseedResult['primary_lineage']] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-700', 'glyph' => '✦'];
                $ssName = $starseedNames[$starseedResult['primary_lineage']] ?? ucfirst($starseedResult['primary_lineage']);
                $ssSecName = $starseedNames[$starseedResult['secondary_lineage']] ?? '';
            ?>
            <div class="flex items-center gap-3 mb-3">
                <div class="text-3xl leading-none"><?= $ssc['glyph'] ?></div>
                <div>
                    <div class="font-bold text-gray-900"><?= h($ssName) ?></div>
                    <?php if ($ssSecName): ?>
                    <div class="text-xs text-gray-500">Secondary: <?= h($ssSecName) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= url('/starseed-quiz.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">View Full Reading &rarr;</a>
            <?php else: ?>
            <p class="text-sm text-gray-500 mb-2">Discover which star system your soul originates from.</p>
            <a href="<?= url('/starseed-quiz.php') ?>" class="text-brand-600 hover:text-brand-700 text-sm font-medium">Take the Quiz &rarr;</a>
            <?php endif; ?>
        </div>

        <!-- Lucky Attributes -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="font-bold text-gray-900 mb-3">Lucky Attributes</h3>
            <div class="grid grid-cols-3 gap-3 text-center text-sm">
                <div class="<?= $css['bg'] ?> rounded-lg p-3">
                    <div class="text-xs text-gray-500">Numbers</div>
                    <div class="font-bold <?= $css['text'] ?>"><?= h(implode(', ', $animalInfo['lucky_numbers'] ?? [])) ?></div>
                </div>
                <div class="<?= $css['bg'] ?> rounded-lg p-3">
                    <div class="text-xs text-gray-500">Colors</div>
                    <div class="font-bold <?= $css['text'] ?> text-xs"><?= h(implode(', ', $animalInfo['lucky_colors'] ?? [])) ?></div>
                </div>
                <div class="<?= $css['bg'] ?> rounded-lg p-3">
                    <div class="text-xs text-gray-500">Direction</div>
                    <div class="font-bold <?= $css['text'] ?>"><?= h($animalInfo['lucky_direction'] ?? '') ?></div>
                </div>
            </div>
        </div>

    </div>

    <!-- Compatibility Checker -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mt-6">
        <h3 class="font-bold text-gray-900 mb-4">Compatibility Checker</h3>
        <form method="POST" class="flex gap-3 items-end mb-4">
            <?= csrfField() ?>
            <div class="flex-1">
                <label for="partner_year" class="block text-sm text-gray-600 mb-1">Partner's birth year</label>
                <input type="number" name="partner_year" id="partner_year" min="1920" max="<?= date('Y') ?>" required
                       value="<?= h($_POST['partner_year'] ?? '') ?>"
                       placeholder="e.g. 1992" class="w-full rounded-lg border-gray-300 px-4 py-2.5 border">
            </div>
            <button type="submit" class="bg-brand-600 text-white px-6 py-2.5 rounded-lg hover:bg-brand-700 font-medium">Check</button>
        </form>
        <?php if ($compatResult): ?>
        <div class="<?= $compatResult['level'] === 'excellent' ? 'bg-green-50 border-green-200' : ($compatResult['level'] === 'great' ? 'bg-blue-50 border-blue-200' : ($compatResult['level'] === 'challenging' ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200')) ?> border rounded-lg p-4">
            <div class="flex items-center gap-3 mb-2">
                <span class="text-2xl"><?= $emoji ?></span>
                <span class="text-gray-400">&hearts;</span>
                <span class="text-2xl"><?= $compatResult['partner_emoji'] ?></span>
                <span class="font-semibold text-gray-900"><?= h($profile['zodiac_animal']) ?> + <?= h($compatResult['partner_animal']) ?></span>
            </div>
            <p class="font-medium text-gray-800 mb-1"><?= h($compatResult['label']) ?></p>
            <p class="text-sm text-gray-600"><?= h($compatResult['description']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
