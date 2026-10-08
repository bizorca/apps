<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
require AS_ROOT . '/includes/western-astrology.php';
require AS_ROOT . '/includes/claude-api.php';

$profile = null;
$reading = null;
$animalInfo = null;
$tcm = null;
$compat = null;
$css = null;

// Handle form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/'));
        exit;
    }

    $birthYear = (int)($_POST['birth_year'] ?? 0);
    $birthMonth = !empty($_POST['birth_month']) ? (int)$_POST['birth_month'] : null;
    $birthDay = !empty($_POST['birth_day']) ? (int)$_POST['birth_day'] : null;
    $birthHour = $_POST['birth_hour'] !== '' ? (int)$_POST['birth_hour'] : null;

    if ($birthYear < 1920 || $birthYear > (int)date('Y')) {
        setFlash('error', 'Please enter a valid birth year.');
        header('Location: ' . url('/'));
        exit;
    }

    $updateProfileId = (int)($_POST['profile_id'] ?? 0);

    if ($updateProfileId > 0) {
        // Update existing profile
        $db = getDB();
        $userId = getCurrentUserId();
        $sessionId = session_id();

        $stmt = $db->prepare("SELECT * FROM as_profiles WHERE id = ? AND (user_id = ? OR session_id = ?)");
        $stmt->execute([$updateProfileId, $userId, $sessionId]);
        $existing = $stmt->fetch();

        if (!$existing) {
            setFlash('error', 'Profile not found.');
            header('Location: ' . url('/'));
            exit;
        }

        $profile = calculateProfile($birthYear, $birthMonth, $birthDay, $birthHour);
        $fourPillarsJson = json_encode($profile['four_pillars']);

        $westernSign = null;
        $westernElement = null;
        if ($birthMonth && $birthDay) {
            $westernSign = getWesternSign($birthMonth, $birthDay);
            $westernElement = getWesternElement($westernSign);
        }

        $stmt = $db->prepare("UPDATE as_profiles SET birth_year = ?, birth_month = ?, birth_day = ?, birth_hour = ?, zodiac_animal = ?, zodiac_element = ?, yin_yang = ?, heavenly_stem = ?, earthly_branch = ?, four_pillars_json = ?, western_sign = ?, western_element = ? WHERE id = ?");
        $stmt->execute([
            $profile['birth_year'], $profile['birth_month'], $profile['birth_day'], $profile['birth_hour'],
            $profile['zodiac_animal'], $profile['zodiac_element'], $profile['yin_yang'],
            $profile['heavenly_stem'], $profile['earthly_branch'],
            $fourPillarsJson,
            $westernSign, $westernElement,
            $updateProfileId
        ]);

        // Delete old readings so they regenerate with new pillar data
        $db->prepare("DELETE FROM as_readings WHERE profile_id = ?")->execute([$updateProfileId]);

        // Generate new teaser reading
        $readingData = generateTeaserReading($profile);
        if ($readingData) {
            $stmt = $db->prepare("INSERT INTO as_readings (profile_id, user_id, reading_type, content_json) VALUES (?, ?, 'teaser', ?)");
            $stmt->execute([$updateProfileId, $userId ?? null, json_encode($readingData)]);
        }

        setFlash('success', 'Birth details updated! Your chart has been recalculated.');
        header('Location: ' . url('/profile.php') . '?id=' . $updateProfileId);
        exit;
    }

    // Create new profile
    $profile = calculateProfile($birthYear, $birthMonth, $birthDay, $birthHour);

    $db = getDB();
    $userId = getCurrentUserId();
    $sessionId = session_id();

    $fourPillarsJson = json_encode($profile['four_pillars']);

    $newWesternSign = null;
    $newWesternElement = null;
    if ($birthMonth && $birthDay) {
        $newWesternSign = getWesternSign($birthMonth, $birthDay);
        $newWesternElement = getWesternElement($newWesternSign);
    }

    $stmt = $db->prepare("INSERT INTO as_profiles (user_id, session_id, birth_year, birth_month, birth_day, birth_hour, zodiac_animal, zodiac_element, yin_yang, heavenly_stem, earthly_branch, four_pillars_json, western_sign, western_element) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId, $sessionId,
        $profile['birth_year'], $profile['birth_month'], $profile['birth_day'], $profile['birth_hour'],
        $profile['zodiac_animal'], $profile['zodiac_element'], $profile['yin_yang'],
        $profile['heavenly_stem'], $profile['earthly_branch'],
        $fourPillarsJson,
        $newWesternSign, $newWesternElement
    ]);
    $profileId = (int)$db->lastInsertId();

    // Store in session for claiming on registration
    $_SESSION['as_pending_profile_id'] = $profileId;

    // Generate teaser reading
    $readingData = generateTeaserReading($profile);
    if ($readingData) {
        $stmt = $db->prepare("INSERT INTO as_readings (profile_id, user_id, reading_type, content_json) VALUES (?, ?, 'teaser', ?)");
        $stmt->execute([$profileId, $userId, json_encode($readingData)]);
    }

    // Redirect to GET with profile ID to prevent resubmission
    header('Location: ' . url('/profile.php') . '?id=' . $profileId);
    exit;
}

// Handle GET - display existing profile
$profileId = (int)($_GET['id'] ?? 0);
if ($profileId <= 0) {
    header('Location: ' . url('/'));
    exit;
}

$db = getDB();

// Load profile (allow viewing by session or user)
$userId = getCurrentUserId();
$sessionId = session_id();

$stmt = $db->prepare("SELECT * FROM as_profiles WHERE id = ? AND (user_id = ? OR session_id = ?)");
$stmt->execute([$profileId, $userId, $sessionId]);
$profileRow = $stmt->fetch();

if (!$profileRow) {
    setFlash('error', 'Profile not found.');
    header('Location: ' . url('/'));
    exit;
}

$profile = [
    'birth_year' => $profileRow['birth_year'],
    'birth_month' => $profileRow['birth_month'],
    'birth_day' => $profileRow['birth_day'],
    'birth_hour' => $profileRow['birth_hour'],
    'zodiac_animal' => $profileRow['zodiac_animal'],
    'zodiac_element' => $profileRow['zodiac_element'],
    'yin_yang' => $profileRow['yin_yang'],
    'heavenly_stem' => $profileRow['heavenly_stem'],
    'earthly_branch' => $profileRow['earthly_branch'],
];

$animalInfo = getAnimalInfo($profile['zodiac_animal']);
$tcm = getElementTCM($profile['zodiac_element']);
$compat = getCompatibility($profile['zodiac_animal']);
$css = getElementCSS($profile['zodiac_element']);
$emoji = getAnimalEmoji($profile['zodiac_animal']);

// Load or calculate Four Pillars
$fourPillars = !empty($profileRow['four_pillars_json']) ? json_decode($profileRow['four_pillars_json'], true) : null;
if (!$fourPillars) {
    $fourPillars = calculateFourPillars(
        (int)$profile['birth_year'],
        $profile['birth_month'] !== null ? (int)$profile['birth_month'] : null,
        $profile['birth_day'] !== null ? (int)$profile['birth_day'] : null,
        $profile['birth_hour'] !== null ? (int)$profile['birth_hour'] : null
    );
    $db->prepare("UPDATE as_profiles SET four_pillars_json = ? WHERE id = ?")->execute([json_encode($fourPillars), $profileId]);
}

// Load teaser reading
$stmt = $db->prepare("SELECT content_json FROM as_readings WHERE profile_id = ? AND reading_type = 'teaser' ORDER BY id DESC LIMIT 1");
$stmt->execute([$profileId]);
$readingRow = $stmt->fetch();
$reading = $readingRow ? json_decode($readingRow['content_json'], true) : null;

$isOwner = isLoggedIn() && $profileRow['user_id'] == $userId;

$pageTitle = $profile['zodiac_animal'] . ' - ' . $profile['zodiac_element'] . ' ' . $profile['yin_yang'];
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Profile Header -->
    <div class="<?= $css['bg'] ?> <?= $css['border'] ?> border rounded-2xl p-8 mb-8 text-center">
        <div class="text-6xl mb-4"><?= $emoji ?></div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">
            <?= h($profile['zodiac_element']) ?> <?= h($profile['zodiac_animal']) ?>
        </h1>
        <div class="flex items-center justify-center gap-3 mb-4">
            <span class="element-badge element-<?= strtolower($profile['zodiac_element']) ?>"><?= h($profile['zodiac_element']) ?></span>
            <span class="element-badge bg-gray-100 text-gray-700"><?= h($profile['yin_yang']) ?></span>
            <span class="text-sm text-gray-500">Born <?= h($profile['birth_year']) ?></span>
        </div>
        <p class="text-sm text-gray-600">
            <?= h($profile['heavenly_stem']) ?> &middot; <?= h($profile['earthly_branch']) ?>
            &middot; <?= h($animalInfo['chinese'] ?? '') ?> <?= h($profile['zodiac_animal']) ?>
        </p>
    </div>

    <!-- Four Pillars (Ba Zi) Chart -->
    <?php if ($fourPillars && $fourPillars['pillar_count'] > 0): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-xl font-bold text-gray-900">Four Pillars <span class="text-base font-normal text-gray-500">(Ba Zi)</span></h2>
            <span class="text-sm text-gray-400"><?= $fourPillars['pillar_count'] ?> of 4 pillars</span>
        </div>
        <?php if ($fourPillars['pillar_count'] < 4): ?>
        <p class="text-sm text-brand-600 mb-4"><a href="#update-birth" class="hover:underline">Add more birth details for a complete chart &darr;</a></p>
        <?php else: ?>
        <p class="text-sm text-gray-500 mb-4">Complete Ba Zi chart based on your birth date and time.</p>
        <?php endif; ?>

        <?php if (!empty($fourPillars['li_chun_note'])): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm text-amber-800">
            <?= h($fourPillars['li_chun_note']) ?>
        </div>
        <?php endif; ?>

        <?php if ($fourPillars['day_master']): ?>
        <div class="text-center mb-4 p-3 bg-brand-50 rounded-lg border border-brand-200">
            <div class="text-xs text-brand-600 font-medium uppercase tracking-wider">Day Master (日主)</div>
            <div class="text-3xl font-bold text-brand-800"><?= h($fourPillars['day_master']['chinese']) ?></div>
            <div class="text-sm text-brand-700"><?= h($fourPillars['day_master']['name']) ?> &middot; <?= h($fourPillars['day_master']['element']) ?> <?= h($fourPillars['day_master']['polarity']) ?></div>
        </div>
        <?php endif; ?>

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
            <div class="text-center p-4 rounded-xl border <?= $isDay ? 'border-brand-300 bg-brand-50/50' : 'border-gray-200 bg-gray-50' ?>">
                <div class="text-xs font-medium <?= $isDay ? 'text-brand-600' : 'text-gray-500' ?> mb-0.5"><?= h($p['chinese_label']) ?></div>
                <div class="text-xs text-gray-400 mb-2"><?= h($p['metaphor']) ?> (<?= h($p['metaphor_chinese']) ?>)</div>
                <div class="text-2xl font-bold text-gray-900"><?= h($p['stem']['chinese']) ?></div>
                <div class="text-xs text-gray-600 mb-1"><?= h($p['stem']['name']) ?></div>
                <span class="inline-block px-2 py-0.5 rounded-full text-xs <?= $stemCss['badge_bg'] ?> <?= $stemCss['text'] ?>"><?= h($p['stem']['element']) ?></span>
                <div class="border-t border-gray-200 my-2"></div>
                <div class="text-2xl font-bold text-gray-900"><?= h($p['branch']['chinese']) ?></div>
                <div class="text-xs text-gray-600 mb-1"><?= h($p['branch']['name']) ?> <?= getAnimalEmoji($p['branch']['animal']) ?></div>
                <span class="inline-block px-2 py-0.5 rounded-full text-xs <?= $branchCss['badge_bg'] ?> <?= $branchCss['text'] ?>"><?= h($p['branch']['element']) ?></span>
                <?php if (!empty($p['approximate'])): ?>
                <div class="text-xs text-amber-600 mt-1">~approximate</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($fourPillars['element_balance']):
            $balance = $fourPillars['element_balance'];
            $maxCount = max($balance['counts']);
        ?>
        <div class="mt-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-3">Element Balance</h3>
            <div class="space-y-2">
                <?php foreach ($balance['counts'] as $element => $count):
                    $pct = $balance['total'] > 0 ? ($count / $balance['total']) * 100 : 0;
                    $elCss = getElementCSS($element);
                ?>
                <div class="flex items-center gap-3">
                    <div class="w-14 text-xs font-medium text-gray-600"><?= h($element) ?></div>
                    <div class="flex-1 bg-gray-100 rounded-full h-3">
                        <div class="<?= $elCss['badge_bg'] ?> h-3 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                    </div>
                    <div class="w-4 text-xs text-gray-500 text-right"><?= $count ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($balance['missing'])): ?>
            <p class="text-xs text-red-600 mt-2">Missing: <?= h(implode(', ', $balance['missing'])) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Update Birth Details Form -->
    <?php if ($isOwner || !isLoggedIn()): ?>
    <div id="update-birth" class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-1"><?= $fourPillars && $fourPillars['pillar_count'] >= 4 ? 'Update' : 'Complete' ?> Your Birth Details</h2>
        <p class="text-sm text-gray-500 mb-4"><?= $fourPillars && $fourPillars['pillar_count'] >= 4 ? 'Correct your birth details to recalculate your chart.' : 'Add your full birth date and time for a complete Four Pillars chart.' ?></p>
        <form method="POST" action="<?= url('/profile.php') ?>">
            <?= csrfField() ?>
            <input type="hidden" name="profile_id" value="<?= $profileId ?>">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                <div>
                    <label for="birth_year" class="block text-sm font-medium text-gray-700 mb-1">Birth Year *</label>
                    <input type="number" name="birth_year" id="birth_year" required min="1920" max="<?= date('Y') ?>"
                           value="<?= h($profile['birth_year']) ?>"
                           class="w-full rounded-lg border-gray-300 px-3 py-2.5 border focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
                <div>
                    <label for="birth_month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <select name="birth_month" id="birth_month" class="w-full rounded-lg border-gray-300 px-3 py-2.5 border focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        <option value="">Not set</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ($profile['birth_month'] !== null && (int)$profile['birth_month'] === $m) ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="birth_day" class="block text-sm font-medium text-gray-700 mb-1">Day</label>
                    <select name="birth_day" id="birth_day" class="w-full rounded-lg border-gray-300 px-3 py-2.5 border focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        <option value="">Not set</option>
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                        <option value="<?= $d ?>" <?= ($profile['birth_day'] !== null && (int)$profile['birth_day'] === $d) ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="birth_hour" class="block text-sm font-medium text-gray-700 mb-1">Birth Hour</label>
                    <select name="birth_hour" id="birth_hour" class="w-full rounded-lg border-gray-300 px-3 py-2.5 border focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        <option value="">Not set</option>
                        <?php
                        $hours = [
                            0 => '11pm-1am (Rat)',
                            2 => '1am-3am (Ox)',
                            4 => '3am-5am (Tiger)',
                            6 => '5am-7am (Rabbit)',
                            8 => '7am-9am (Dragon)',
                            10 => '9am-11am (Snake)',
                            12 => '11am-1pm (Horse)',
                            14 => '1pm-3pm (Goat)',
                            16 => '3pm-5pm (Monkey)',
                            18 => '5pm-7pm (Rooster)',
                            20 => '7pm-9pm (Dog)',
                            22 => '9pm-11pm (Pig)',
                        ];
                        foreach ($hours as $hVal => $hLabel): ?>
                        <option value="<?= $hVal ?>" <?= ($profile['birth_hour'] !== null && (int)$profile['birth_hour'] === $hVal) ? 'selected' : '' ?>><?= $hLabel ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="bg-brand-600 text-white px-6 py-2.5 rounded-lg hover:bg-brand-700 font-medium transition">
                Recalculate Chart
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Animal Traits -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-3"><?= h($profile['zodiac_animal']) ?> Traits</h2>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php foreach ($animalInfo['traits'] ?? [] as $trait): ?>
            <span class="px-3 py-1 rounded-full text-sm <?= $css['badge_bg'] ?> <?= $css['text'] ?>"><?= h($trait) ?></span>
            <?php endforeach; ?>
        </div>
        <p class="text-gray-700 mb-2"><span class="font-medium">Strengths:</span> <?= h($animalInfo['strengths'] ?? '') ?></p>
        <p class="text-gray-700"><span class="font-medium">Challenges:</span> <?= h($animalInfo['challenges'] ?? '') ?></p>
    </div>

    <!-- Teaser Reading -->
    <?php if ($reading): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Your Zodiac Insight</h2>
        <?php if (!empty($reading['overview'])): ?>
        <p class="text-gray-700 mb-4 text-lg leading-relaxed"><?= h($reading['overview']) ?></p>
        <?php endif; ?>
        <?php if (!empty($reading['personality_glimpse'])): ?>
        <div class="mb-4">
            <h3 class="font-semibold text-gray-800 mb-1">Personality</h3>
            <p class="text-gray-600"><?= h($reading['personality_glimpse']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($reading['element_insight'])): ?>
        <div class="mb-4">
            <h3 class="font-semibold text-gray-800 mb-1"><?= h($profile['zodiac_element']) ?> Element</h3>
            <p class="text-gray-600"><?= h($reading['element_insight']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($reading['health_hint'])): ?>
        <div class="mb-4">
            <h3 class="font-semibold text-gray-800 mb-1">Health Connection</h3>
            <p class="text-gray-600"><?= h($reading['health_hint']) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- TCM Element Card -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-3">Your Element: <?= h($profile['zodiac_element']) ?></h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div><span class="font-medium text-gray-500">Season:</span><br><?= h($tcm['season']) ?></div>
            <div><span class="font-medium text-gray-500">Organs:</span><br><?= h(implode(' / ', $tcm['organs'])) ?></div>
            <div><span class="font-medium text-gray-500">Emotion:</span><br><?= h($tcm['emotion_positive']) ?></div>
            <div><span class="font-medium text-gray-500">Color:</span><br><?= h($tcm['color']) ?></div>
            <div><span class="font-medium text-gray-500">Taste:</span><br><?= h($tcm['taste']) ?></div>
            <div><span class="font-medium text-gray-500">Direction:</span><br><?= h($tcm['direction']) ?></div>
        </div>
    </div>

    <!-- Compatibility -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-3">Compatibility</h2>
        <div class="space-y-3">
            <div>
                <span class="font-medium text-green-700">Best Match:</span>
                <?= getAnimalEmoji($compat['best_match']) ?> <?= h($compat['best_match']) ?>
            </div>
            <div>
                <span class="font-medium text-blue-700">Harmonies:</span>
                <?php foreach ($compat['harmonies'] as $h): ?>
                    <?= getAnimalEmoji($h) ?> <?= h($h) ?>&nbsp;&nbsp;
                <?php endforeach; ?>
            </div>
            <div>
                <span class="font-medium text-red-700">Clash:</span>
                <?= getAnimalEmoji($compat['clash']) ?> <?= h($compat['clash']) ?>
            </div>
        </div>
    </div>

    <!-- Lucky Attributes -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-3">Lucky Attributes</h2>
        <div class="grid grid-cols-3 gap-4 text-sm text-center">
            <div class="<?= $css['bg'] ?> rounded-lg p-4">
                <div class="font-medium text-gray-500 mb-1">Numbers</div>
                <div class="font-bold <?= $css['text'] ?>"><?= h(implode(', ', $animalInfo['lucky_numbers'] ?? [])) ?></div>
            </div>
            <div class="<?= $css['bg'] ?> rounded-lg p-4">
                <div class="font-medium text-gray-500 mb-1">Colors</div>
                <div class="font-bold <?= $css['text'] ?>"><?= h(implode(', ', $animalInfo['lucky_colors'] ?? [])) ?></div>
            </div>
            <div class="<?= $css['bg'] ?> rounded-lg p-4">
                <div class="font-medium text-gray-500 mb-1">Direction</div>
                <div class="font-bold <?= $css['text'] ?>"><?= h($animalInfo['lucky_direction'] ?? '') ?></div>
            </div>
        </div>
    </div>

    <!-- Western Zodiac Section -->
    <?php
    $westernSign = $profileRow['western_sign'] ?? null;
    if (!$westernSign && !empty($profile['birth_month']) && !empty($profile['birth_day'])) {
        $westernSign = getWesternSign((int)$profile['birth_month'], (int)$profile['birth_day']);
        $westernElement = getWesternElement($westernSign);
        $db->prepare("UPDATE as_profiles SET western_sign = ?, western_element = ? WHERE id = ?")
           ->execute([$westernSign, $westernElement, $profileId]);
    } else {
        $westernElement = $profileRow['western_element'] ?? ($westernSign ? getWesternElement($westernSign) : null);
    }
    ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-xl font-bold text-gray-900">Western Zodiac</h2>
            <?php if ($westernSign): ?>
            <a href="<?= url('/western-profile.php') ?>?id=<?= $profileId ?>" class="text-sm text-brand-600 hover:text-brand-700 font-medium">Full profile &rarr;</a>
            <?php endif; ?>
        </div>
        <?php if ($westernSign): ?>
        <?php
        $westSignInfo = getWesternSignInfo($westernSign);
        $westCss = getWesternElementCSS($westernElement ?? getWesternElement($westernSign));
        $westGlyph = getWesternSignGlyph($westernSign);
        ?>
        <div class="flex items-center gap-4 mb-4">
            <div class="text-5xl leading-none"><?= h($westGlyph) ?></div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?= h($westernSign) ?></div>
                <div class="text-sm text-gray-500"><?= h($westSignInfo['dates'] ?? '') ?></div>
                <div class="flex gap-2 mt-1 flex-wrap">
                    <span class="<?= $westCss['badge_bg'] ?> <?= $westCss['text'] ?> px-2 py-0.5 rounded-full text-xs font-semibold"><?= h($westernElement ?? '') ?> Sign</span>
                    <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full text-xs font-semibold"><?= h($westSignInfo['modality'] ?? '') ?></span>
                    <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full text-xs font-semibold"><?= h($westSignInfo['ruling_planet'] ?? '') ?></span>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 mb-3">
            <?php foreach ($westSignInfo['traits'] ?? [] as $trait): ?>
            <span class="px-2 py-1 rounded-full text-xs <?= $westCss['badge_bg'] ?> <?= $westCss['text'] ?>"><?= h($trait) ?></span>
            <?php endforeach; ?>
        </div>
        <p class="text-sm text-gray-600 mb-4"><?= h($westSignInfo['strengths'] ?? '') ?></p>
        <a href="<?= url('/western-profile.php') ?>?id=<?= $profileId ?>" class="inline-block bg-brand-600 text-white px-5 py-2 rounded-lg hover:bg-brand-700 font-medium text-sm transition">
            View Western Profile &amp; Reading
        </a>
        <?php else: ?>
        <p class="text-sm text-gray-500 mb-3">Western astrology (sun sign) requires your full birth date — month and day.</p>
        <a href="#update-birth" class="text-sm text-brand-600 hover:text-brand-700 font-medium">Add your birthday to unlock your sun sign &darr;</a>
        <?php endif; ?>
    </div>

    <!-- Blurred Full Reading Tease -->
    <?php if (!isLoggedIn()): ?>
    <div class="relative mb-8">
        <div class="blur-content bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3">Full Reading</h2>
            <p class="text-gray-600 mb-3">Your detailed personality analysis reveals a complex interplay between your Water element and Tiger energy. The liver meridian connection suggests a natural tendency toward creative expression, while your emotional landscape shows deep capacity for both fierce protection and gentle nurturing...</p>
            <p class="text-gray-600 mb-3">Career path analysis indicates strong leadership potential with a preference for independent ventures. Your TCM health profile highlights the importance of seasonal liver cleansing and spring renewal practices...</p>
            <p class="text-gray-600">Relationship dynamics show a pattern of intense loyalty balanced with a need for personal freedom. Your ideal partner combines the stability of Earth with the warmth of Fire...</p>
        </div>
        <div class="absolute inset-0 flex items-center justify-center bg-white/60 rounded-xl">
            <div class="text-center p-8">
                <svg class="w-12 h-12 text-brand-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Unlock Your Full Reading</h3>
                <p class="text-gray-600 text-sm mb-4">Create a free account to access your complete enhanced astrology profile, monthly forecasts, and guided meditations.</p>
                <a href="<?= authUrl('register') ?>" class="inline-block bg-brand-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-brand-700 transition">
                    Create Free Account
                </a>
                <p class="text-xs text-gray-500 mt-2">
                    Already have an account? <a href="<?= authUrl('login') ?>" class="text-brand-600 hover:text-brand-700">Sign in</a>
                </p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="text-center mb-8">
        <a href="<?= url('/reading.php') ?>" class="inline-block bg-brand-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-brand-700 transition">
            View Your Full Reading
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
