<?php
/**
 * Create a workspace (was "Create your account", signup/create.blade.php).
 * The visitor is already signed in to the shared tools account; this makes
 * their Fathom workspace and seeds it by business type. Expects $shared.
 */
$types = [
    'none'            => 'Just set up a blank workspace',
    'financial_coach' => 'Financial Coach',
    'tax_consultant'  => 'Tax Consultant',
    'career_coach'    => 'Career Coach',
    'yoga_instructor' => 'Yoga Instructor',
    'energy_healer'   => 'Energy Healer',
];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create your workspace — Fathom</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full flex items-center justify-center py-12">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-indigo-600">Fathom</h1>
            <p class="mt-2 text-gray-500 text-sm">Kanban, the way it should work.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <?php if (session('info')): ?>
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800"><?= e(session('info')) ?></div>
            <?php endif; ?>
            <?php if ($errors->any()): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors->all() as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <h2 class="text-lg font-semibold text-gray-900 mb-1">Create your workspace</h2>
            <p class="text-sm text-gray-500 mb-6">Signed in as <strong><?= e($shared['name']) ?></strong> (<?= e($shared['email']) ?>).</p>

            <form method="POST" action="<?= e(route('signup.store')) ?>">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label for="account_name" class="block text-sm font-medium text-gray-700 mb-1">Team / company name</label>
                        <input type="text" id="account_name" name="account_name" value="<?= e(old('account_name')) ?>"
                               required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                               placeholder="Acme Inc.">
                    </div>
                    <div>
                        <label for="business_type" class="block text-sm font-medium text-gray-700 mb-1">What kind of business is this?</label>
                        <select id="business_type" name="business_type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <?php foreach ($types as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= old('business_type', 'none') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Choose a type and we'll pre-build your boards, columns, and tags for you.</p>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-indigo-700 transition">
                        Create workspace
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                Joining a team instead? Open the invite link they sent you.
            </p>
        </div>
    </div>
</body>
</html>
