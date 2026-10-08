<?php
/** Ported from resources/views (Blade). */
$__title = 'New Board';
ob_start();
?>
<div class="max-w-xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">New Board</h1>

    <form method="POST" action="<?= e(route('boards.store')) ?>" class="space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= e(old('name')) ?>" required autofocus
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   placeholder="e.g. Product Roadmap">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="What's this board for?"><?= e(old('description')) ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
            <div class="flex gap-2">
                <?php foreach (['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#6b7280'] as $color): ?>
                    <label>
                        <input type="radio" name="color" value="<?= e($color) ?>" class="sr-only peer">
                        <div class="w-6 h-6 rounded-full cursor-pointer ring-2 ring-transparent peer-checked:ring-gray-400 peer-checked:ring-offset-1"
                             style="background-color: <?= e($color) ?>"></div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_public" name="is_public" value="1" <?= e(old('is_public') ? 'checked' : '') ?>
                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <label for="is_public" class="text-sm text-gray-700">Make this board publicly viewable</label>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg hover:bg-indigo-700 transition">
                Create board
            </button>
            <a href="<?= e(route('boards.index')) ?>" class="text-sm text-gray-500 px-5 py-2 hover:text-gray-700">Cancel</a>
        </div>
    </form>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
