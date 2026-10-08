<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Get user's element
$stmt = $db->prepare("SELECT zodiac_element FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
$userElement = $profile ? $profile['zodiac_element'] : null;

// Load all meditations
$stmt = $db->query("SELECT * FROM as_meditations ORDER BY sort_order ASC");
$meditations = $stmt->fetchAll();

// Load user's completed meditations
$stmt = $db->prepare("SELECT meditation_id, completed_at FROM as_user_meditations WHERE user_id = ?");
$stmt->execute([$user['id']]);
$userMeditations = [];
foreach ($stmt->fetchAll() as $um) {
    $userMeditations[$um['meditation_id']] = $um;
}

$hasPremium = hasActiveSubscription($user['id']);

$pageTitle = 'Guided Meditations';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Guided Meditations</h1>
    <p class="text-gray-600 mb-8">Five element-based meditations grounded in Traditional Chinese Medicine. <?php if ($userElement): ?>Your element is <span class="font-medium"><?= h($userElement) ?></span>.<?php endif; ?></p>

    <?php if (empty($meditations)): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-8 text-center">
        <p class="text-gray-500">Meditations coming soon!</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($meditations as $med):
            $css = getElementCSS($med['element']);
            $isUserElement = $userElement && strtolower($med['element']) === strtolower($userElement);
            $isLocked = !$isUserElement && !$hasPremium && !userHasAccess($user['id']);
            $isCompleted = isset($userMeditations[$med['id']]) && $userMeditations[$med['id']]['completed_at'];
        ?>
        <div class="relative <?= $css['bg'] ?> <?= $css['border'] ?> border rounded-xl p-6 <?= $isUserElement ? 'ring-2 ring-brand-300' : '' ?>">
            <?php if ($isUserElement): ?>
            <span class="absolute -top-2 -right-2 bg-brand-600 text-white text-xs px-2 py-0.5 rounded-full">Your Element</span>
            <?php endif; ?>
            <?php if ($isCompleted): ?>
            <span class="absolute -top-2 left-3 bg-green-600 text-white text-xs px-2 py-0.5 rounded-full">Completed</span>
            <?php endif; ?>

            <div class="text-center mb-4">
                <div class="text-3xl mb-2">
                    <?php
                    $icons = ['Wood' => '&#x1F331;', 'Fire' => '&#x1F525;', 'Earth' => '&#x26F0;', 'Metal' => '&#x2699;', 'Water' => '&#x1F4A7;'];
                    echo $icons[$med['element']] ?? '&#x2728;';
                    ?>
                </div>
                <h2 class="text-lg font-bold text-gray-900"><?= h($med['title']) ?></h2>
                <p class="text-xs <?= $css['accent'] ?>"><?= h($med['element']) ?> Element &middot; <?= h($med['duration_minutes']) ?> min</p>
            </div>

            <p class="text-sm text-gray-600 mb-4"><?= h($med['description']) ?></p>

            <?php if ($isLocked): ?>
            <div class="text-center">
                <span class="text-sm text-gray-400">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Premium
                </span>
            </div>
            <?php else: ?>
            <a href="<?= url('/meditation.php') ?>?slug=<?= h($med['slug']) ?>"
               class="block text-center bg-white text-brand-700 border border-brand-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-50 transition">
                <?= $isCompleted ? 'Repeat' : 'Begin' ?> Meditation
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$hasPremium && !userHasAccess($user['id'])): ?>
    <div class="mt-8 text-center">
        <p class="text-gray-600 mb-3">Unlock all 5 element meditations with Premium.</p>
        <a href="<?= url('/pricing.php') ?>" class="inline-block bg-brand-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-brand-700 transition">View Pricing</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
