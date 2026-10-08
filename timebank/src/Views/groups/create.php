<?php $pageTitle = 'Create Group'; ?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Create a Group</h1>
        <a href="<?= url('/groups') ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Back to groups</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Groups give subsets of the community a space to communicate and share. Neighborhoods, interest circles, or project teams all work great as groups.
        </p>

        <form method="POST" action="<?= url('/groups/create') ?>" class="space-y-5">
            <?= csrf_field() ?>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">Group name <span class="text-red-500">*</span></label>
                <input type="text" id="name" name="name"
                       value="<?= old('name') ?>"
                       required maxlength="100"
                       placeholder="e.g., North Side Neighbors, Gardeners Guild"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Description <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <textarea id="description" name="description" rows="4"
                          placeholder="What is this group about? Who should join?"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('description') ?></textarea>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <a href="<?= url('/groups') ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                    Create Group
                </button>
            </div>
        </form>
    </div>
</div>
