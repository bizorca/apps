<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
require AS_ROOT . '/includes/western-astrology.php';
require AS_ROOT . '/includes/claude-api.php';

// Load profile
$profileId = (int)($_GET['id'] ?? 0);
if ($profileId <= 0) {
    header('Location: ' . url('/'));
    exit;
}

$db = getDB();
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

// Require birth month + day for western zodiac
if (empty($profileRow['birth_month']) || empty($profileRow['birth_day'])) {
    setFlash('error', 'Western zodiac requires your full birth date. Please update your birth details first.');
    header('Location: ' . url('/profile.php') . '?id=' . $profileId . '#update-birth');
    exit;
}

// Compute or load western sign
$westernSign = $profileRow['western_sign'] ?? null;
$westernElement = $profileRow['western_element'] ?? null;

if (!$westernSign) {
    $westernSign = getWesternSign((int)$profileRow['birth_month'], (int)$profileRow['birth_day']);
    $westernElement = getWesternElement($westernSign);
    $db->prepare("UPDATE as_profiles SET western_sign = ?, western_element = ? WHERE id = ?")
       ->execute([$westernSign, $westernElement, $profileId]);
}

$signInfo = getWesternSignInfo($westernSign);
$css = getWesternElementCSS($westernElement);
$compat = getWesternCompatibility($westernSign);
$glyph = getWesternSignGlyph($westernSign);

// Handle compatibility checker POST
$compatResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['partner_sign'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $partnerSign = $_POST['partner_sign'];
        if (in_array($partnerSign, getAllWesternSigns())) {
            $partnerInfo = getWesternSignInfo($partnerSign);
            $compatResult = getWesternCompatibilityBetween($westernSign, $partnerSign);
            $compatResult['partner_sign'] = $partnerSign;
            $compatResult['partner_glyph'] = getWesternSignGlyph($partnerSign);
            $compatResult['partner_element'] = getWesternElement($partnerSign);
        }
    }
}

// Load or generate teaser reading
$profile = [
    'birth_year' => $profileRow['birth_year'],
    'birth_month' => $profileRow['birth_month'],
    'birth_day' => $profileRow['birth_day'],
    'birth_hour' => $profileRow['birth_hour'],
    'zodiac_animal' => $profileRow['zodiac_animal'],
    'zodiac_element' => $profileRow['zodiac_element'],
    'yin_yang' => $profileRow['yin_yang'],
    'western_sign' => $westernSign,
    'western_element' => $westernElement,
];

$stmt = $db->prepare("SELECT content_json FROM as_readings WHERE profile_id = ? AND reading_type = 'western_teaser' ORDER BY id DESC LIMIT 1");
$stmt->execute([$profileId]);
$readingRow = $stmt->fetch();
$teaserReading = $readingRow ? json_decode($readingRow['content_json'], true) : null;

if (!$teaserReading) {
    $teaserReading = generateWesternTeaserReading($profile, $signInfo);
    if ($teaserReading) {
        $stmt = $db->prepare("INSERT INTO as_readings (profile_id, user_id, reading_type, content_json) VALUES (?, ?, 'western_teaser', ?)");
        $stmt->execute([$profileId, $userId ?? null, json_encode($teaserReading)]);
    }
}

// Load full reading for premium users
$fullReading = null;
$hasAccess = isLoggedIn() && userHasAccess();
if ($hasAccess) {
    $stmt = $db->prepare("SELECT content_json FROM as_readings WHERE profile_id = ? AND reading_type = 'western_full' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$profileId]);
    $fullRow = $stmt->fetch();
    if ($fullRow) {
        $fullReading = json_decode($fullRow['content_json'], true);
    }

    // Generate if missing
    if (!$fullReading) {
        $fullReading = generateWesternFullReading($profile, $signInfo);
        if ($fullReading) {
            $stmt = $db->prepare("INSERT INTO as_readings (profile_id, user_id, reading_type, content_json) VALUES (?, ?, 'western_full', ?)");
            $stmt->execute([$profileId, $userId, json_encode($fullReading)]);
        }
    }
}

$isOwner = isLoggedIn() && $profileRow['user_id'] == $userId;

$pageTitle = $westernSign . ' – Western Zodiac';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">

    <!-- Back link -->
    <div class="mb-4">
        <a href="<?= url('/profile.php') ?>?id=<?= $profileId ?>" class="text-sm text-gray-500 hover:text-brand-600 transition">&larr; Back to Chinese profile</a>
    </div>

    <!-- Header -->
    <div class="<?= $css['bg'] ?> <?= $css['border'] ?> border rounded-2xl p-8 mb-8 text-center">
        <div class="text-7xl mb-3 leading-none"><?= h($glyph) ?></div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= h($westernSign) ?></h1>
        <p class="text-sm <?= $css['accent'] ?> font-medium mb-4"><?= h($signInfo['dates'] ?? '') ?></p>
        <div class="flex items-center justify-center gap-3 mb-4 flex-wrap">
            <span class="<?= $css['badge_bg'] ?> <?= $css['text'] ?> px-3 py-1 rounded-full text-sm font-semibold"><?= h($westernElement) ?> Sign</span>
            <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-semibold"><?= h($signInfo['modality'] ?? '') ?></span>
            <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-semibold">Ruled by <?= h($signInfo['ruling_planet'] ?? '') ?></span>
        </div>
        <p class="text-sm text-gray-500">The <?= h($signInfo['symbol'] ?? '') ?> &middot; <?= h(implode(' · ', $signInfo['keywords'] ?? [])) ?></p>
        <?php if (!empty($profileRow['zodiac_animal'])): ?>
        <p class="text-xs text-gray-400 mt-3">
            Also: <?= h($profileRow['zodiac_element']) ?> <?= h($profileRow['zodiac_animal']) ?> in Chinese astrology
        </p>
        <?php endif; ?>
    </div>

    <!-- Traits -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-3"><?= h($westernSign) ?> Traits</h2>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php foreach ($signInfo['traits'] ?? [] as $trait): ?>
            <span class="px-3 py-1 rounded-full text-sm <?= $css['badge_bg'] ?> <?= $css['text'] ?>"><?= h($trait) ?></span>
            <?php endforeach; ?>
        </div>
        <p class="text-gray-700 mb-2"><span class="font-medium">Strengths:</span> <?= h($signInfo['strengths'] ?? '') ?></p>
        <p class="text-gray-700"><span class="font-medium">Challenges:</span> <?= h($signInfo['challenges'] ?? '') ?></p>
    </div>

    <!-- Teaser Reading -->
    <?php if ($teaserReading && !$fullReading): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Your <?= h($westernSign) ?> Insight</h2>
        <?php if (!empty($teaserReading['overview'])): ?>
        <p class="text-gray-700 mb-4 text-lg leading-relaxed"><?= h($teaserReading['overview']) ?></p>
        <?php endif; ?>
        <?php if (!empty($teaserReading['personality_glimpse'])): ?>
        <div class="mb-4">
            <h3 class="font-semibold text-gray-800 mb-1">Personality</h3>
            <p class="text-gray-600"><?= h($teaserReading['personality_glimpse']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($teaserReading['element_insight'])): ?>
        <div class="mb-4">
            <h3 class="font-semibold text-gray-800 mb-1"><?= h($westernElement) ?> Element</h3>
            <p class="text-gray-600"><?= h($teaserReading['element_insight']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($teaserReading['planet_insight'])): ?>
        <div class="mb-4">
            <h3 class="font-semibold text-gray-800 mb-1">Ruled by <?= h($signInfo['ruling_planet'] ?? '') ?></h3>
            <p class="text-gray-600"><?= h($teaserReading['planet_insight']) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Full Reading (Premium) -->
    <?php if ($fullReading): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-5">Full <?= h($westernSign) ?> Reading</h2>

        <?php if (!empty($fullReading['overview'])): ?>
        <p class="text-gray-700 mb-6 text-lg leading-relaxed"><?= h($fullReading['overview']) ?></p>
        <?php endif; ?>

        <?php
        $sections = [
            'personality'         => 'Personality',
            'element_analysis'    => $westernElement . ' Element Analysis',
            'modality_insight'    => ($signInfo['modality'] ?? '') . ' Modality',
            'planetary_influence' => 'Planetary Influence: ' . ($signInfo['ruling_planet'] ?? ''),
            'relationships'       => 'Relationships',
            'career'              => 'Career',
            'health_wellness'     => 'Health & Wellness',
            'life_path'           => 'Life Path',
            'east_west_synthesis' => 'East Meets West',
        ];
        foreach ($sections as $key => $label):
            if (empty($fullReading[$key])) continue;
        ?>
        <div class="mb-5">
            <h3 class="font-semibold text-gray-800 mb-2"><?= h($label) ?></h3>
            <p class="text-gray-600 leading-relaxed"><?= h($fullReading[$key]) ?></p>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($fullReading['seasonal_guidance'])): ?>
        <div class="mt-6">
            <h3 class="font-semibold text-gray-800 mb-3">Seasonal Guidance</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <?php
                $seasons = ['spring' => '🌱', 'summer' => '☀️', 'autumn' => '🍂', 'winter' => '❄️'];
                foreach ($seasons as $season => $icon):
                    if (empty($fullReading['seasonal_guidance'][$season])) continue;
                ?>
                <div class="<?= $css['bg'] ?> rounded-lg p-3">
                    <div class="text-lg mb-1"><?= $icon ?></div>
                    <div class="text-xs font-semibold <?= $css['text'] ?> uppercase tracking-wide mb-1"><?= ucfirst($season) ?></div>
                    <p class="text-xs text-gray-600"><?= h($fullReading['seasonal_guidance'][$season]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Sign Details Grid -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Sign Details</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div><span class="font-medium text-gray-500">Element:</span><br><?= h($westernElement) ?></div>
            <div><span class="font-medium text-gray-500">Modality:</span><br><?= h($signInfo['modality'] ?? '') ?></div>
            <div><span class="font-medium text-gray-500">Ruling Planet:</span><br><?= h($signInfo['ruling_planet'] ?? '') ?></div>
            <div><span class="font-medium text-gray-500">Symbol:</span><br>The <?= h($signInfo['symbol'] ?? '') ?></div>
            <div><span class="font-medium text-gray-500">Body:</span><br><?= h($signInfo['body_part'] ?? '') ?></div>
            <div><span class="font-medium text-gray-500">Lucky Colors:</span><br><?= h(implode(', ', $signInfo['lucky_colors'] ?? [])) ?></div>
        </div>
    </div>

    <!-- Compatibility -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Compatibility</h2>
        <div class="space-y-3 mb-6">
            <div>
                <span class="font-medium text-green-700">Best matches (Trine):</span>
                <?php foreach ($compat['best_matches'] as $s): ?>
                    <span class="ml-2"><?= h(getWesternSignGlyph($s)) ?> <?= h($s) ?></span>
                <?php endforeach; ?>
            </div>
            <div>
                <span class="font-medium text-blue-700">Good matches (Sextile):</span>
                <?php foreach ($compat['good_matches'] as $s): ?>
                    <span class="ml-2"><?= h(getWesternSignGlyph($s)) ?> <?= h($s) ?></span>
                <?php endforeach; ?>
            </div>
            <div>
                <span class="font-medium text-purple-700">Opposite sign:</span>
                <span class="ml-2"><?= h(getWesternSignGlyph($compat['opposition'])) ?> <?= h($compat['opposition']) ?></span>
            </div>
            <div>
                <span class="font-medium text-red-700">Challenging (Square):</span>
                <?php foreach ($compat['challenging'] as $s): ?>
                    <span class="ml-2"><?= h(getWesternSignGlyph($s)) ?> <?= h($s) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Compatibility Checker -->
        <h3 class="font-semibold text-gray-900 mb-3">Check Compatibility</h3>
        <form method="POST" class="flex gap-3 items-end mb-4">
            <?= csrfField() ?>
            <div class="flex-1">
                <label for="partner_sign" class="block text-sm text-gray-600 mb-1">Partner's sun sign</label>
                <select name="partner_sign" id="partner_sign" required
                        class="w-full rounded-lg border-gray-300 px-3 py-2.5 border focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">Select a sign...</option>
                    <?php foreach (getAllWesternSigns() as $s): ?>
                    <option value="<?= h($s) ?>" <?= (isset($_POST['partner_sign']) && $_POST['partner_sign'] === $s) ? 'selected' : '' ?>>
                        <?= h(getWesternSignGlyph($s)) ?> <?= h($s) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-brand-600 text-white px-6 py-2.5 rounded-lg hover:bg-brand-700 font-medium">Check</button>
        </form>

        <?php if ($compatResult): ?>
        <?php
        $levelColors = [
            'excellent' => 'bg-green-50 border-green-200',
            'great' => 'bg-blue-50 border-blue-200',
            'good' => 'bg-sky-50 border-sky-200',
            'intense' => 'bg-purple-50 border-purple-200',
            'challenging' => 'bg-red-50 border-red-200',
            'neutral' => 'bg-gray-50 border-gray-200',
        ];
        $levelColor = $levelColors[$compatResult['level']] ?? 'bg-gray-50 border-gray-200';
        ?>
        <div class="<?= $levelColor ?> border rounded-lg p-4">
            <div class="flex items-center gap-3 mb-2">
                <span class="text-2xl"><?= h($glyph) ?></span>
                <span class="text-gray-400">&hearts;</span>
                <span class="text-2xl"><?= h($compatResult['partner_glyph']) ?></span>
                <span class="font-semibold text-gray-900"><?= h($westernSign) ?> + <?= h($compatResult['partner_sign']) ?></span>
            </div>
            <p class="font-medium text-gray-800 mb-1"><?= h($compatResult['label']) ?></p>
            <p class="text-sm text-gray-600"><?= h($compatResult['description']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- CTA -->
    <?php if (!$hasAccess): ?>
    <div class="relative mb-8">
        <div class="blur-content bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3">Full <?= h($westernSign) ?> Reading</h2>
            <p class="text-gray-600 mb-3">Your <?= h($westernElement) ?> <?= h($westernSign) ?> sun reveals a deeply layered personality shaped by the interplay of <?= h($signInfo['modality'] ?? '') ?> drive and <?= h($signInfo['ruling_planet'] ?? '') ?> influence. Your relationship style carries hallmarks of the <?= h($westernSign) ?> archetype — striking the precise balance between...</p>
            <p class="text-gray-600 mb-3">Career patterns reflect your <?= h($signInfo['modality'] ?? '') ?> nature. Health guidance connects to your <?= h($signInfo['body_part'] ?? '') ?> rulership. Your seasonal guidance maps out the entire year...</p>
            <p class="text-gray-600">And when your Chinese <?= h($profileRow['zodiac_animal'] ?? 'zodiac') ?> sign meets your Western <?= h($westernSign) ?> sun — the synthesis tells a far more complete story than either system alone.</p>
        </div>
        <div class="absolute inset-0 flex items-center justify-center bg-white/60 rounded-xl">
            <div class="text-center p-8">
                <div class="text-4xl mb-3"><?= h($glyph) ?></div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Unlock Your Full Western Reading</h3>
                <p class="text-gray-600 text-sm mb-4">Get deep-dive analysis of personality, relationships, career, health, and the East–West synthesis — available with Premium.</p>
                <?php if (!isLoggedIn()): ?>
                <a href="<?= authUrl('register') ?>" class="inline-block bg-brand-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-brand-700 transition">
                    Create Free Account
                </a>
                <p class="text-xs text-gray-500 mt-2">
                    Already have an account? <a href="<?= authUrl('login') ?>" class="text-brand-600 hover:text-brand-700">Sign in</a>
                </p>
                <?php else: ?>
                <a href="<?= url('/pricing.php') ?>" class="inline-block bg-brand-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-brand-700 transition">
                    Upgrade to Premium
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
