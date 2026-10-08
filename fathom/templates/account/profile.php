<?php
/** Ported from resources/views (Blade). */
$__title = 'Profile';
ob_start();
?>
<div class="max-w-lg mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('account.show')) ?>" class="hover:text-gray-600">Account</a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Profile</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-900 mb-6">Edit profile</h1>

        <form method="POST" action="<?= e(route('account.profile.update')) ?>" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <?= method_field('PATCH') ?>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" id="name" name="name" value="<?= e(old('name', $user->name)) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label for="email_address" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                <input type="email" id="email_address" value="<?= e($user->email_address) ?>" disabled
                       class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500">
                <p class="mt-1 text-xs text-gray-400">This is the sign-in for your Bizorca Tools account, shared by every tool. Your name here is that account's name too.</p>
            </div>

            <div>
                <label for="time_zone" class="block text-sm font-medium text-gray-700 mb-1">Time zone</label>
                <input type="text" id="time_zone" name="time_zone" value="<?= e(old('time_zone', $user->time_zone)) ?>"
                       placeholder="e.g. America/Denver"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-400">Use a <a href="https://en.wikipedia.org/wiki/List_of_tz_database_time_zones" target="_blank" class="underline">TZ identifier</a>, e.g. America/New_York.</p>
            </div>

            <div>
                <label for="avatar" class="block text-sm font-medium text-gray-700 mb-1">Avatar</label>
                <?php if ($user->avatar_path): ?>
                    <img src="<?= e(route('account.avatar', $user)) ?>" alt="<?= e($user->name) ?>"
                         class="w-12 h-12 rounded-full object-cover mb-2">
                <?php endif; ?>
                <input type="file" id="avatar" name="avatar" accept="image/*"
                       class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-gray-400">JPG, PNG, GIF up to 2MB.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    Save changes
                </button>
                <a href="<?= e(route('account.show')) ?>" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
