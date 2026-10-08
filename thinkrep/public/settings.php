<?php
require_once __DIR__ . '/_bootstrap.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/settings.php'));
        exit;
    }

    $roleTitle = trim($_POST['role_title'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $timezone = trim($_POST['timezone'] ?? 'America/New_York');

    // Validate timezone
    if (!in_array($timezone, timezone_identifiers_list())) {
        $timezone = 'America/New_York';
    }

    saveProfile((int) $user['id'], [
        'role_title' => $roleTitle ?: null,
        'industry'   => $industry ?: null,
        'timezone'   => $timezone,
    ]);

    setFlash('success', 'Settings updated.');
    header('Location: ' . url('/settings.php'));
    exit;
}

$pageTitle = 'Settings';
require TR_ROOT . '/templates/header.php';

// Common US timezones first, then all
$commonTimezones = [
    'America/New_York' => 'Eastern (New York)',
    'America/Chicago' => 'Central (Chicago)',
    'America/Denver' => 'Mountain (Denver)',
    'America/Los_Angeles' => 'Pacific (Los Angeles)',
    'America/Phoenix' => 'Arizona (Phoenix)',
    'America/Anchorage' => 'Alaska (Anchorage)',
    'Pacific/Honolulu' => 'Hawaii (Honolulu)',
    'America/Puerto_Rico' => 'Atlantic (Puerto Rico)',
];
?>

<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Settings</h1>

    <form method="POST" class="bg-white rounded-lg shadow p-6 space-y-6">
        <?= csrfField() ?>

        <!-- Profile info (from the shared Bizorca Tools account) -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <p class="text-gray-900"><?= h($user['email']) ?></p>
            <p class="text-xs text-gray-400 mt-1">Your Bizorca Tools sign-in address</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <p class="text-gray-900"><?= h($user['name'] ?: '(not set)') ?></p>
            <p class="text-xs text-gray-400 mt-1">Change it in <a href="/account/settings.php" class="text-tr-600 hover:underline">your account settings</a></p>
        </div>

        <hr class="border-gray-200">

        <div>
            <label for="role_title" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select name="role_title" id="role_title" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                <option value="">Not specified</option>
                <?php
                $roles = [
                    'founder' => 'Founder / CEO',
                    'product_manager' => 'Product Manager',
                    'engineering_manager' => 'Engineering Manager',
                    'sales_director' => 'Sales Director',
                    'marketing_director' => 'Marketing Director',
                    'hr_director' => 'HR Director',
                    'consultant' => 'Consultant',
                    'investor' => 'Investor',
                    'data_analyst' => 'Data Analyst',
                    'operations' => 'Operations',
                    'nonprofit_director' => 'Nonprofit Director',
                    'other' => 'Other',
                ];
                foreach ($roles as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($user['role_title'] ?? '') === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="industry" class="block text-sm font-medium text-gray-700 mb-1">Industry</label>
            <select name="industry" id="industry" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                <option value="">Not specified</option>
                <?php
                $industries = [
                    'saas' => 'SaaS / Software',
                    'technology' => 'Technology',
                    'finance' => 'Finance',
                    'fintech' => 'Fintech',
                    'healthcare' => 'Healthcare',
                    'ecommerce' => 'E-commerce / Retail',
                    'consulting' => 'Consulting',
                    'professional_services' => 'Professional Services',
                    'manufacturing' => 'Manufacturing',
                    'education' => 'Education',
                    'nonprofit' => 'Nonprofit',
                    'other' => 'Other',
                ];
                foreach ($industries as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($user['industry'] ?? '') === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
            <select name="timezone" id="timezone" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                <optgroup label="Common">
                    <?php foreach ($commonTimezones as $tz => $label): ?>
                    <option value="<?= $tz ?>" <?= ($user['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="All Timezones">
                    <?php foreach (timezone_identifiers_list() as $tz): ?>
                    <option value="<?= $tz ?>" <?= ($user['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $tz)) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>

        <button type="submit" class="w-full bg-tr-600 text-white py-3 rounded-lg font-semibold hover:bg-tr-700 transition">
            Save Settings
        </button>
    </form>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
