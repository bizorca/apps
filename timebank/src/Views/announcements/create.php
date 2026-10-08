<?php $pageTitle = 'Post Announcement'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Post Announcement</h1>
        <a href="<?= url('/announcements') ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Back</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <form method="POST" action="<?= url('/announcements/create') ?>" class="space-y-5">
            <?= csrf_field() ?>

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1.5">Title <span class="text-red-500">*</span></label>
                <input type="text" id="title" name="title"
                       value="<?= old('title') ?>"
                       required maxlength="255"
                       placeholder="e.g., Community Potluck — Saturday August 12"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
            </div>

            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1.5">Body <span class="text-red-500">*</span></label>
                <textarea id="body" name="body" rows="8"
                          required
                          placeholder="Write your announcement here..."
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('body') ?></textarea>
            </div>

            <!-- Group target -->
            <?php if (!empty($groups)): ?>
                <div>
                    <label for="group_id" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Post to group <span class="text-gray-400 font-normal">(leave blank for all members)</span>
                    </label>
                    <select id="group_id" name="group_id"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white">
                        <option value="">All members</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= (int)($g['id'] ?? 0) ?>" <?= old('group_id') == ($g['id'] ?? '') ? 'selected' : '' ?>>
                                <?= e($g['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Pin (admin only) -->
            <?php if (\TimeBank\Core\Auth::isAdmin()): ?>
                <div class="flex items-start gap-3 p-4 bg-orange-50 rounded-xl border border-orange-200">
                    <input type="checkbox" id="is_pinned" name="is_pinned" value="1"
                           <?= old('is_pinned') ? 'checked' : '' ?>
                           class="h-4 w-4 mt-0.5 text-orange-500 border-gray-300 rounded focus:ring-orange-400">
                    <div>
                        <label for="is_pinned" class="block text-sm font-semibold text-orange-800 cursor-pointer">Pin this announcement</label>
                        <p class="text-xs text-orange-600 mt-0.5">Pinned announcements appear at the top of the list and on the dashboard.</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex items-center gap-3 pt-2">
                <a href="<?= url('/announcements') ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                    Post Announcement
                </button>
            </div>
        </form>
    </div>
</div>
