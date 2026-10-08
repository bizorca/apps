<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();

$user = getCurrentUser();

// Client only
if (!in_array($user['role'], ['client'], true)) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();

// Load existing business if any
$stmt = $db->prepare('SELECT * FROM cw_businesses WHERE user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$business = $stmt->fetch() ?: null;

$errors = [];
$values = $business ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $values = [
            'name'                 => trim($_POST['name'] ?? ''),
            'business_type'        => $_POST['business_type'] ?? '',
            'industry'             => trim($_POST['industry'] ?? ''),
            'employee_count'       => $_POST['employee_count'] ?? '',
            'annual_revenue_range' => $_POST['annual_revenue_range'] ?? '',
            'owner_age_range'      => $_POST['owner_age_range'] ?? '',
            'owner_timeline'       => $_POST['owner_timeline'] ?? '',
            'motivation'           => trim($_POST['motivation'] ?? ''),
        ];

        if (empty($values['name']))       $errors[] = 'Business name is required.';
        if (empty($values['business_type'])) $errors[] = 'Business type is required.';
        if (empty($values['employee_count'])) $errors[] = 'Number of employees is required.';
        if (empty($values['annual_revenue_range'])) $errors[] = 'Annual revenue range is required.';
        if (empty($values['owner_age_range'])) $errors[] = 'Owner age range is required.';
        if (empty($values['owner_timeline'])) $errors[] = 'Transition timeline is required.';
        if (empty($values['motivation']))  $errors[] = 'Motivation is required.';

        if (empty($errors)) {
            $now = date('Y-m-d H:i:s');

            if ($business) {
                // UPDATE
                $stmt = $db->prepare(
                    'UPDATE cw_businesses SET name=?, business_type=?, industry=?, employee_count=?,
                     annual_revenue_range=?, owner_age_range=?, owner_timeline=?, motivation=?, updated_at=?
                     WHERE id=?'
                );
                $stmt->execute([
                    $values['name'], $values['business_type'], $values['industry'],
                    $values['employee_count'], $values['annual_revenue_range'],
                    $values['owner_age_range'], $values['owner_timeline'], $values['motivation'],
                    $now, $business['id'],
                ]);
                $businessId = $business['id'];
            } else {
                // INSERT
                $stmt = $db->prepare(
                    'INSERT INTO cw_businesses (user_id, name, business_type, industry, employee_count,
                     annual_revenue_range, owner_age_range, owner_timeline, motivation, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $user['id'], $values['name'], $values['business_type'], $values['industry'],
                    $values['employee_count'], $values['annual_revenue_range'],
                    $values['owner_age_range'], $values['owner_timeline'], $values['motivation'],
                    $now, $now,
                ]);
                $businessId = (int)$db->lastInsertId();
            }

            // Upsert case
            $existingCase = getUserCase((int)$user['id']);
            if (!$existingCase) {
                $stmt = $db->prepare(
                    'INSERT INTO cw_cases (business_id, coordinator_id, status, created_at, updated_at)
                     VALUES (?, NULL, \'structure_selection\', ?, ?)'
                );
                $stmt->execute([$businessId, $now, $now]);
            } elseif ($existingCase['status'] === 'intake') {
                $stmt = $db->prepare(
                    'UPDATE cw_cases SET status=\'structure_selection\', updated_at=? WHERE id=?'
                );
                $stmt->execute([$now, $existingCase['id']]);
            }

            setFlash('success', 'Business information saved. Now let\'s find the right cooperative structure for you.');
            header('Location: ' . url('/structure-selector.php'));
            exit;
        }
    }
}

$flash = getFlash();

$businessTypes = [
    'Retail', 'Restaurant/Food Service', 'Professional Services',
    'Manufacturing', 'Construction/Trades', 'Healthcare', 'Other',
];
$employeeCounts = ['1-5', '6-10', '11-20', '21-50', '50+'];
$revenueRanges = [
    'Under $250K', '$250K-$500K', '$500K-$1M', '$1M-$2M', '$2M-$5M', 'Over $5M',
];
$ageRanges = ['Under 50', '50-55', '56-60', '61-65', 'Over 65'];
$timelines = ['Within 1 year', '1-2 years', '2-5 years', 'Not sure yet'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Intake — CoopConvert</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="bg-emerald-700 text-white px-6 py-4 flex items-center justify-between">
    <a href="<?= url('/dashboard.php') ?>" class="font-bold text-lg tracking-tight">CoopConvert</a>
    <div class="flex items-center gap-6 text-sm">
        <span class="text-emerald-300"><?= h($user['name']) ?></span>
        <a href="<?= url('/logout.php') ?>" class="hover:text-teal-300">Sign out</a>
    </div>
</nav>

<div class="max-w-2xl mx-auto px-4 py-10">

    <?php if ($flash): ?>
    <div class="mb-6 px-4 py-3 rounded-md text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="mb-6 bg-red-50 border border-red-200 rounded-md px-4 py-3">
        <p class="text-red-700 font-semibold text-sm mb-1">Please fix the following:</p>
        <ul class="list-disc list-inside text-red-600 text-sm space-y-0.5">
            <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= $business ? 'Update your business information' : 'Tell us about your business' ?></h1>
        <p class="text-gray-500 text-sm">This information helps us guide your cooperative conversion. All fields marked with * are required.</p>
    </div>

    <form method="POST" action="<?= url('/intake.php') ?>" class="space-y-8">
        <?= csrfField() ?>

        <!-- Section: Basic Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
            <h2 class="font-semibold text-gray-800 text-base border-b border-gray-100 pb-3">Basic Information</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Business name *</label>
                <input type="text" name="name" required value="<?= h($values['name'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business type *</label>
                    <select name="business_type" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Select type…</option>
                        <?php foreach ($businessTypes as $t): ?>
                        <option value="<?= h($t) ?>" <?= ($values['business_type'] ?? '') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Industry</label>
                    <input type="text" name="industry" value="<?= h($values['industry'] ?? '') ?>"
                           placeholder="e.g. Specialty food retail"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        <!-- Section: Scale -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
            <h2 class="font-semibold text-gray-800 text-base border-b border-gray-100 pb-3">Business Scale</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Number of employees *</label>
                    <select name="employee_count" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Select…</option>
                        <?php foreach ($employeeCounts as $opt): ?>
                        <option value="<?= h($opt) ?>" <?= ($values['employee_count'] ?? '') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Annual revenue range *</label>
                    <select name="annual_revenue_range" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Select…</option>
                        <?php foreach ($revenueRanges as $opt): ?>
                        <option value="<?= h($opt) ?>" <?= ($values['annual_revenue_range'] ?? '') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section: Owner Profile -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
            <h2 class="font-semibold text-gray-800 text-base border-b border-gray-100 pb-3">Owner & Transition</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Owner age range *</label>
                    <select name="owner_age_range" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Select…</option>
                        <?php foreach ($ageRanges as $opt): ?>
                        <option value="<?= h($opt) ?>" <?= ($values['owner_age_range'] ?? '') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Desired timeline *</label>
                    <select name="owner_timeline" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Select…</option>
                        <?php foreach ($timelines as $opt): ?>
                        <option value="<?= h($opt) ?>" <?= ($values['owner_timeline'] ?? '') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section: Motivation -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-800 text-base border-b border-gray-100 pb-3 mb-4">Your Motivation *</h2>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tell us a bit about why you're interested in converting to a cooperative…</label>
            <textarea name="motivation" required rows="5"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><?= h($values['motivation'] ?? '') ?></textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-600 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
                Save &amp; Continue &rarr;
            </button>
        </div>

    </form>
</div>
</body>
</html>
