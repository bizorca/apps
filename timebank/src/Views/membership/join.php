<?php
/** Join this community (replaces the original's register page). */
$pageTitle = 'Join';
$name = $tenant['name'] ?? 'this timebank';
?>
<div class="max-w-lg mx-auto">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Join <?= e($name) ?></h1>
        <?php if (!empty($tenant['tagline'])): ?>
            <p class="text-gray-500 mt-2"><?= e($tenant['tagline']) ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <?php if (!$registrationOpen): ?>
            <p class="text-sm text-gray-600">This timebank is not taking new members right now. Please contact one of its administrators.</p>
        <?php elseif (!$account): ?>
            <p class="text-sm text-gray-600 mb-5">
                Members sign in with a Bizorca Tools account. Sign in, or create a free account, and you'll come straight back here to join.
            </p>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="<?= e($signInUrl) ?>" class="flex-1 text-center px-4 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors">Sign in</a>
                <a href="<?= e($createAccountUrl) ?>" class="flex-1 text-center px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">Create an account</a>
            </div>
        <?php else: ?>
            <form method="POST" action="<?= url('/join') ?>" class="space-y-5">
                <?= csrf_field() ?>
                <p class="text-sm text-gray-600">
                    Joining as <strong><?= e($account['email']) ?></strong>. The name below is how other members of <?= e($name) ?> will see you.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1.5">First name <span class="text-red-500">*</span></label>
                        <input type="text" id="first_name" name="first_name" required value="<?= old('first_name', $firstName) ?>"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1.5">Last name <span class="text-red-500">*</span></label>
                        <input type="text" id="last_name" name="last_name" required value="<?= old('last_name', $lastName) ?>"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
                <label class="flex items-start gap-3 text-sm text-gray-600">
                    <input type="checkbox" name="terms" value="1" required class="mt-0.5 h-4 w-4 text-teal-600 border-gray-300 rounded">
                    <span>I'll exchange time with other members in good faith and follow this timebank's guidelines.</span>
                </label>
                <?php if ($requiresApproval): ?>
                    <p class="text-xs text-gray-500">New members are approved by an administrator before they can take part.</p>
                <?php elseif ((float) ($tenant['welcome_credits'] ?? 0) > 0): ?>
                    <p class="text-xs text-gray-500">You'll start with <?= e(number_format((float) $tenant['welcome_credits'], 2)) ?> <?= e($tenant['currency_name_plural'] ?? 'Hours') ?> of welcome credits.</p>
                <?php endif; ?>
                <button type="submit" class="w-full px-4 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors">Join <?= e($name) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
