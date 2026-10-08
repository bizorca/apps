<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';

        // Name, email and password belong to the shared tools account now
        // (/account/settings.php). Only Astrology's own setting lives here.
        if ($action === 'update_profile') {
            $timezones = timezone_identifiers_list();
            $timezone  = (string) ($_POST['timezone'] ?? 'America/New_York');
            if (!in_array($timezone, $timezones, true)) $errors[] = 'Choose a timezone from the list.';

            if (empty($errors)) {
                $db->prepare("INSERT INTO as_members (user_id, timezone) VALUES (?, ?) AS new
                              ON DUPLICATE KEY UPDATE timezone = new.timezone")
                   ->execute([$user['id'], $timezone]);
                setFlash('success', 'Settings saved.');
                header('Location: ' . url('/settings.php'));
                exit;
            }
        }
    }
}

// Refresh user data
$user = getCurrentUser();

$pageTitle = 'Settings';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-2xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
        <div class="flex gap-4">
            <?php if (isAdmin()): ?>
            <a href="<?= url('/admin-settings.php') ?>" class="text-sm text-amber-600 hover:text-amber-700 font-medium">Admin &rarr;</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <?php foreach ($errors as $error): ?>
            <p class="text-red-700 text-sm"><?= h($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Profile Settings -->
    <div class="bg-white shadow-sm rounded-xl p-6 border border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Profile</h2>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="text-sm text-gray-600">
                Signed in as <strong class="text-gray-900"><?= h($user['name']) ?></strong> (<?= h($user['email']) ?>).
                Your name, email and password are part of your Bizorca Tools account:
                <a href="/account/settings.php" class="text-brand-600 hover:text-brand-700 font-medium">change them there &rarr;</a>
            </div>

            <div>
                <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                <select name="timezone" id="timezone"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 px-4 py-2.5 border">
                    <?php
                    $timezones = ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
                                  'America/Phoenix', 'America/Anchorage', 'Pacific/Honolulu', 'America/Toronto',
                                  'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Asia/Tokyo', 'Asia/Shanghai',
                                  'Australia/Sydney', 'Pacific/Auckland'];
                    foreach ($timezones as $tz):
                    ?>
                        <option value="<?= $tz ?>" <?= $user['timezone'] === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="bg-brand-600 text-white px-5 py-2.5 rounded-lg hover:bg-brand-700 font-medium text-sm">
                Save Changes
            </button>
        </form>
    </div>

</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
