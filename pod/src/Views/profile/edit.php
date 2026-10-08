<?php $pageTitle = 'Edit Profile'; ?>

<div class="max-w-lg">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Edit Profile</h1>
        <p class="text-sm text-gray-400 mt-1">Update your public profile info.</p>
    </div>

    <?php if ($error ?? null): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-5">
            <?= h($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($success ?? null): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3 mb-5">
            <?= h($success) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <form method="POST" action="<?= url('profile/update') ?>">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">First name</label>
                    <input type="text" name="first_name" required
                           value="<?= h($profile['first_name'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Last name</label>
                    <input type="text" name="last_name"
                           value="<?= h($profile['last_name'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Bio</label>
                <textarea name="bio" rows="4"
                          placeholder="Tell the community a bit about yourself..."
                          class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"><?= h($profile['bio'] ?? '') ?></textarea>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Profile image URL</label>
                <input type="url" name="avatar_url"
                       value="<?= h($profile['avatar_url'] ?? '') ?>"
                       placeholder="https://example.com/your-photo.jpg"
                       class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <p class="text-xs text-gray-400 mt-1">Paste a URL to a profile image. Gravatar or LinkedIn photo links work great.</p>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors">
                    Save changes
                </button>
                <a href="<?= url('profile/' . $user['id']) ?>" class="text-sm text-gray-400 hover:text-gray-600">View profile</a>
            </div>
        </form>
    </div>
</div>
