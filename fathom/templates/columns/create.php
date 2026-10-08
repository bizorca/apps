<?php
/** Ported from resources/views (Blade). */
$__title = 'Add column';
ob_start();
?>
<div class="max-w-lg mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('boards.index')) ?>" class="hover:text-gray-600">Boards</a>
        <span class="mx-2">/</span>
        <a href="<?= e(route('boards.show', $board)) ?>" class="hover:text-gray-600"><?= e($board->name) ?></a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Add column</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-900 mb-6">Add column</h1>

        <form method="POST" action="<?= e(route('boards.columns.store', $board)) ?>" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" id="name" name="name" value="<?= e(old('name')) ?>" required autofocus
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="e.g. In Review">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                <div class="flex gap-2">
                    <label>
                        <input type="radio" name="color" value="" checked class="sr-only peer">
                        <div class="w-6 h-6 rounded-full cursor-pointer border-2 border-gray-300 peer-checked:border-gray-500 bg-white"></div>
                    </label>
                    <?php foreach (['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#6b7280'] as $color): ?>
                        <label>
                            <input type="radio" name="color" value="<?= e($color) ?>"
                                   <?= e(old('color') === $color ? 'checked' : '') ?> class="sr-only peer">
                            <div class="w-6 h-6 rounded-full cursor-pointer ring-2 ring-transparent peer-checked:ring-gray-400 peer-checked:ring-offset-1"
                                 style="background-color: <?= e($color) ?>"></div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label for="cards_limit" class="block text-sm font-medium text-gray-700 mb-1">WIP limit</label>
                <input type="number" id="cards_limit" name="cards_limit" min="1"
                       value="<?= e(old('cards_limit')) ?>"
                       class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="None">
                <p class="mt-1 text-xs text-gray-400">Maximum number of cards allowed in this column. Leave blank for no limit.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    Add column
                </button>
                <a href="<?= e(route('boards.show', $board)) ?>" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
