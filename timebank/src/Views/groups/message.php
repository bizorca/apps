<?php
/** Message every member of a group. The original rendered this view but never had it. */
$pageTitle = 'Message ' . ($group['name'] ?? 'group');
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Message <?= e($group['name'] ?? 'group') ?></h1>
        <a href="<?= url('/groups/' . (int) ($group['id'] ?? 0)) ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Back to group</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <form method="POST" action="<?= url('/groups/' . (int) ($group['id'] ?? 0) . '/message') ?>" class="space-y-5">
            <?= csrf_field() ?>
            <p class="text-sm text-gray-600">Goes to every other member of the group.</p>
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1.5">Subject <span class="text-red-500">*</span></label>
                <input type="text" id="subject" name="subject" required maxlength="255" value="<?= old('subject') ?>"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1.5">Message <span class="text-red-500">*</span></label>
                <textarea id="body" name="body" rows="6" required
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none"><?= old('body') ?></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors">Send to group</button>
            </div>
        </form>
    </div>
</div>
