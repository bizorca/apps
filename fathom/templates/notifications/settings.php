<?php
/** Ported from resources/views (Blade). */
$__title = 'Notification settings';
ob_start();
?>
<div class="max-w-lg mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('notifications.index')) ?>" class="hover:text-gray-600">Notifications</a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Settings</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-900 mb-6">Notification settings</h1>

        <form method="POST" action="<?= e(route('notifications.settings.update')) ?>" class="space-y-5">
            <?= csrf_field() ?>
            <?= method_field('PATCH') ?>

            <div>
                <label for="notification_email" class="block text-sm font-medium text-gray-700 mb-1">Notification email</label>
                <input type="email" id="notification_email" name="notification_email"
                       value="<?= e(old('notification_email', $user->notification_email ?? $user->email_address)) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="<?= e($user->email_address) ?>">
                <p class="mt-1 text-xs text-gray-400">Leave blank to use your account email. Notifications go here instead if set.</p>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <div class="flex items-start gap-3">
                    <input type="checkbox" id="notification_digest" name="notification_digest" value="1"
                           <?= e(old('notification_digest', $user->notification_digest) ? 'checked' : '') ?>
                           class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <div>
                        <label for="notification_digest" class="block text-sm font-medium text-gray-700">Daily digest</label>
                        <p class="text-xs text-gray-400 mt-0.5">Batch notifications into one daily email instead of sending them individually.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    Save settings
                </button>
                <a href="<?= e(route('notifications.index')) ?>" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
