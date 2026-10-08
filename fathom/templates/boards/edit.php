<?php
/** Ported from resources/views (Blade). */
$__title = 'Edit board';
ob_start();
?>
<div class="max-w-xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('boards.index')) ?>" class="hover:text-gray-600">Boards</a>
        <span class="mx-2">/</span>
        <a href="<?= e(route('boards.show', $board)) ?>" class="hover:text-gray-600"><?= e($board->name) ?></a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Edit</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-900 mb-6">Edit board</h1>

        <form method="POST" action="<?= e(route('boards.update', $board)) ?>"
              x-data="{ autoPostpone: <?= e($board->auto_postpone ? 'true' : 'false') ?> }"
              class="space-y-5">
            <?= csrf_field() ?>
            <?= method_field('PUT') ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="<?= e(old('name', $board->name)) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                          placeholder="What's this board for?"><?= e(old('description', $board->description)) ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                <div class="flex gap-2">
                    <label>
                        <input type="radio" name="color" value="" <?= e(!$board->color ? 'checked' : '') ?> class="sr-only peer">
                        <div class="w-6 h-6 rounded-full cursor-pointer border-2 border-gray-300 peer-checked:border-gray-500 bg-white"></div>
                    </label>
                    <?php foreach (['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#6b7280'] as $color): ?>
                        <label>
                            <input type="radio" name="color" value="<?= e($color) ?>"
                                   <?= e(old('color', $board->color) === $color ? 'checked' : '') ?> class="sr-only peer">
                            <div class="w-6 h-6 rounded-full cursor-pointer ring-2 ring-transparent peer-checked:ring-gray-400 peer-checked:ring-offset-1"
                                 style="background-color: <?= e($color) ?>"></div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_public" name="is_public" value="1"
                       <?= e(old('is_public', $board->is_public) ? 'checked' : '') ?>
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_public" class="text-sm text-gray-700">Make this board publicly viewable</label>
            </div>

            <div class="border-t border-gray-100 pt-4 space-y-3">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="auto_postpone" name="auto_postpone" value="1"
                           x-model="autoPostpone"
                           <?= e(old('auto_postpone', $board->auto_postpone) ? 'checked' : '') ?>
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="auto_postpone" class="text-sm text-gray-700">Auto-postpone stalled cards</label>
                </div>

                <div x-show="autoPostpone" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Move stalled cards after</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="postpone_days" min="1" max="365"
                               value="<?= e(old('postpone_days', $board->postpone_days ?? 14)) ?>"
                               class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <span class="text-sm text-gray-500">days of inactivity</span>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg hover:bg-indigo-700 transition">
                    Save changes
                </button>
                <a href="<?= e(route('boards.show', $board)) ?>" class="text-sm text-gray-500 px-5 py-2 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>

    
    <?php if (auth()->user()->isAdmin()): ?>
        <div class="mt-6 bg-white rounded-xl border border-red-200 p-5">
            <h2 class="text-sm font-semibold text-red-700 mb-2">Delete board</h2>
            <p class="text-xs text-gray-500 mb-3">Permanently deletes this board and all its columns and cards.</p>
            <form method="POST" action="<?= e(route('boards.destroy', $board)) ?>"
                  onsubmit="return confirm('Delete board \"<?= e($board->name) ?>\"? This cannot be undone.')">
                <?= csrf_field() ?>
                <?= method_field('DELETE') ?>
                <button type="submit" class="text-sm text-red-600 border border-red-200 px-4 py-1.5 rounded-lg hover:bg-red-50 transition">
                    Delete board
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
