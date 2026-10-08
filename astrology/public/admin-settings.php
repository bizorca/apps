<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();

if (!isAdmin()) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/admin-settings.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_tagline') {
        $tagline = trim($_POST['tagline'] ?? '');
        if (!empty($tagline)) {
            setAppSetting('site_tagline', $tagline);
            setFlash('success', 'Tagline updated.');
        }
    }

    header('Location: ' . url('/admin-settings.php'));
    exit;
}

$tagline = getAppSetting('site_tagline', 'Discover Your Chinese Zodiac Path');

// Stats
$stmt = $db->query("SELECT COUNT(*) as total FROM as_members"); // people who use Astrology, not every account on the site
$totalUsers = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM as_profiles");
$totalProfiles = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM as_readings WHERE reading_type = 'full'");
$fullReadings = $stmt->fetch()['total'];

$pageTitle = 'Admin Settings';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Admin Settings</h1>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-gray-900"><?= h($totalUsers) ?></div>
            <div class="text-sm text-gray-500">Total Users</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-gray-900"><?= h($totalProfiles) ?></div>
            <div class="text-sm text-gray-500">Profiles Created</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-gray-900"><?= h($fullReadings) ?></div>
            <div class="text-sm text-gray-500">Full Readings</div>
        </div>
    </div>

    <!-- Site Settings -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="font-bold text-gray-900 mb-4">Site Settings</h2>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_tagline">
            <div class="mb-4">
                <label for="tagline" class="block text-sm font-medium text-gray-700 mb-1">Site Tagline</label>
                <input type="text" name="tagline" id="tagline" value="<?= h($tagline) ?>"
                       class="w-full rounded-lg border-gray-300 px-4 py-2.5 border">
            </div>
            <button type="submit" class="bg-brand-600 text-white px-6 py-2.5 rounded-lg hover:bg-brand-700 font-medium">Save</button>
        </form>
    </div>

    <!-- Quick Links -->
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <h2 class="font-bold text-gray-900 mb-4">Admin Actions</h2>
        <div class="space-y-3">
            <a href="<?= url('/admin-members.php') ?>" class="block p-3 bg-gray-50 rounded-lg hover:bg-brand-50 transition">
                <span class="font-medium text-gray-900">Manage Members</span>
                <span class="text-sm text-gray-500 block">View, search, and manage all registered users</span>
            </a>
            <a href="<?= url('/admin-interest.php') ?>" class="block p-3 bg-gray-50 rounded-lg hover:bg-brand-50 transition">
                <span class="font-medium text-gray-900">Offer Interest</span>
                <span class="text-sm text-gray-500 block">Leads who requested a spot in Jillian's offerings</span>
            </a>
            <a href="<?= url('/admin-calculator.php') ?>" class="block p-3 bg-gray-50 rounded-lg hover:bg-brand-50 transition">
                <span class="font-medium text-gray-900">Business Model Calculator</span>
                <span class="text-sm text-gray-500 block">Interactive funnel &amp; revenue model with live sliders</span>
            </a>
            <a href="<?= url('/admin-forecasts.php') ?>" class="block p-3 bg-gray-50 rounded-lg hover:bg-brand-50 transition">
                <span class="font-medium text-gray-900">Generate Monthly Forecasts</span>
                <span class="text-sm text-gray-500 block">Generate forecasts for all 12 zodiac animals</span>
            </a>
            <a href="<?= url('/admin-weekly-forecasts.php') ?>" class="block p-3 bg-gray-50 rounded-lg hover:bg-brand-50 transition">
                <span class="font-medium text-gray-900">Generate Weekly Forecasts</span>
                <span class="text-sm text-gray-500 block">Chinese + Western, 24 forecasts per week</span>
            </a>
        </div>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
