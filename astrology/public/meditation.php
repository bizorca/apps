<?php
require __DIR__ . '/_bootstrap.php';
require AS_ROOT . '/includes/astrology.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: ' . url('/meditations.php'));
    exit;
}

// Load meditation
$stmt = $db->prepare("SELECT * FROM as_meditations WHERE slug = ?");
$stmt->execute([$slug]);
$meditation = $stmt->fetch();

if (!$meditation) {
    setFlash('error', 'Meditation not found.');
    header('Location: ' . url('/meditations.php'));
    exit;
}

// Check access: user's element is free, others require premium
$stmt2 = $db->prepare("SELECT zodiac_element FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt2->execute([$user['id']]);
$profile = $stmt2->fetch();
$userElement = $profile ? $profile['zodiac_element'] : null;
$isUserElement = $userElement && strtolower($meditation['element']) === strtolower($userElement);

if (!$isUserElement && !hasActiveSubscription($user['id']) && !userHasAccess($user['id'])) {
    setFlash('error', 'This meditation requires a Premium subscription.');
    header('Location: ' . url('/meditations.php'));
    exit;
}

$content = json_decode($meditation['content_json'], true);
$css = getElementCSS($meditation['element']);

// Track start
$stmt = $db->prepare("INSERT INTO as_user_meditations (user_id, meditation_id) VALUES (?, ?)");
try { $stmt->execute([$user['id'], $meditation['id']]); } catch (PDOException $e) { /* already tracked */ }

// Handle completion POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $stmt = $db->prepare("UPDATE as_user_meditations SET completed_at = NOW() WHERE user_id = ? AND meditation_id = ? AND completed_at IS NULL");
        $stmt->execute([$user['id'], $meditation['id']]);
        setFlash('success', 'Meditation completed! Well done.');
        header('Location: ' . url('/meditations.php'));
        exit;
    }
}

$pageTitle = $meditation['title'];
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <!-- Meditation Header -->
    <div class="<?= $css['bg'] ?> <?= $css['border'] ?> border rounded-2xl p-8 mb-8 text-center">
        <div class="text-4xl mb-3">
            <?php
            $icons = ['Wood' => '&#x1F331;', 'Fire' => '&#x1F525;', 'Earth' => '&#x26F0;', 'Metal' => '&#x2699;', 'Water' => '&#x1F4A7;'];
            echo $icons[$meditation['element']] ?? '&#x2728;';
            ?>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-1"><?= h($meditation['title']) ?></h1>
        <p class="text-sm <?= $css['accent'] ?>"><?= h($meditation['element']) ?> Element &middot; <?= h($meditation['duration_minutes']) ?> minutes</p>
    </div>

    <?php if ($content): ?>
    <!-- Introduction -->
    <?php if (!empty($content['intro'])): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <p class="text-gray-700 leading-relaxed text-lg italic"><?= h($content['intro']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Steps -->
    <?php if (!empty($content['steps']) && is_array($content['steps'])): ?>
    <div class="space-y-4 mb-6" id="meditation-steps">
        <?php foreach ($content['steps'] as $i => $step): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6 meditation-step <?= $i > 0 ? 'hidden' : '' ?>" data-step="<?= $i ?>">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold text-gray-900">Step <?= $i + 1 ?>: <?= h($step['title'] ?? 'Continue') ?></h3>
                <?php if (!empty($step['duration_seconds'])): ?>
                <span class="text-sm text-gray-500"><?= (int)($step['duration_seconds'] / 60) ?>:<?= str_pad($step['duration_seconds'] % 60, 2, '0', STR_PAD_LEFT) ?></span>
                <?php endif; ?>
            </div>
            <p class="text-gray-700 leading-relaxed"><?= h($step['instruction'] ?? '') ?></p>
            <div class="mt-4 flex justify-between items-center">
                <?php if ($i > 0): ?>
                <button onclick="showStep(<?= $i - 1 ?>)" class="text-gray-500 hover:text-gray-700 text-sm">&larr; Previous</button>
                <?php else: ?>
                <span></span>
                <?php endif; ?>
                <?php if ($i < count($content['steps']) - 1): ?>
                <button onclick="showStep(<?= $i + 1 ?>)" class="bg-brand-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-brand-700 transition">Next Step &rarr;</button>
                <?php else: ?>
                <button onclick="document.getElementById('completion-section').classList.remove('hidden')" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition">Complete</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center text-sm text-gray-500 mb-6">
        Step <span id="current-step">1</span> of <?= count($content['steps']) ?>
    </div>
    <?php endif; ?>

    <!-- Closing -->
    <?php if (!empty($content['closing'])): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6 hidden" id="closing-section">
        <p class="text-gray-700 leading-relaxed italic"><?= h($content['closing']) ?></p>
    </div>
    <?php endif; ?>

    <!-- TCM Connection -->
    <?php if (!empty($content['tcm_connection'])): ?>
    <div class="<?= $css['bg'] ?> <?= $css['border'] ?> border rounded-xl p-6 mb-6">
        <h3 class="font-bold <?= $css['text'] ?> mb-2">TCM Connection</h3>
        <p class="text-gray-700 text-sm"><?= h($content['tcm_connection']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Completion -->
    <div class="text-center mb-8 hidden" id="completion-section">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="complete" value="1">
            <button type="submit" class="bg-green-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-green-700 transition">
                Mark as Complete
            </button>
        </form>
    </div>
    <?php else: ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <p class="text-gray-700"><?= h($meditation['description']) ?></p>
    </div>
    <?php endif; ?>

    <div class="text-center">
        <a href="<?= url('/meditations.php') ?>" class="text-brand-600 hover:text-brand-700 font-medium">&larr; All Meditations</a>
    </div>
</div>

<script>
function showStep(n) {
    document.querySelectorAll('.meditation-step').forEach(el => el.classList.add('hidden'));
    document.querySelector('[data-step="' + n + '"]').classList.remove('hidden');
    document.getElementById('current-step').textContent = n + 1;
    window.scrollTo({top: document.querySelector('[data-step="' + n + '"]').offsetTop - 100, behavior: 'smooth'});
}
</script>

<?php require AS_ROOT . '/templates/footer.php'; ?>
