<?php
/** Ported from resources/views (Blade). */
$__title = 'Edit card';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('boards.index')) ?>" class="hover:text-gray-600">Boards</a>
        <span class="mx-2">/</span>
        <a href="<?= e(route('boards.show', $card->board)) ?>" class="hover:text-gray-600"><?= e($card->board->name) ?></a>
        <span class="mx-2">/</span>
        <a href="<?= e(route('cards.show', $card)) ?>" class="hover:text-gray-600"><?= e(Str::limit($card->title, 40)) ?></a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Edit</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-900 mb-6">Edit card</h1>

        <form method="POST" action="<?= e(route('cards.update', $card)) ?>" class="space-y-4">
            <?= csrf_field() ?>
            <?= method_field('PUT') ?>

            
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                <input type="text" id="title" name="title" value="<?= e(old('title', $card->title)) ?>" required autofocus
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="5"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                          placeholder="Markdown supported…"><?= e(old('description', $card->description)) ?></textarea>
            </div>

            
            <div>
                <label for="due_at" class="block text-sm font-medium text-gray-700 mb-1">Due date</label>
                <input type="date" id="due_at" name="due_at"
                       value="<?= e(old('due_at', $card->due_at?->format('Y-m-d'))) ?>"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color strip</label>
                <div class="flex gap-2">
                    <label>
                        <input type="radio" name="color" value="" <?= e(!$card->color ? 'checked' : '') ?> class="sr-only peer">
                        <div class="w-6 h-6 rounded-full cursor-pointer border-2 border-gray-300 peer-checked:border-gray-500 bg-white"></div>
                    </label>
                    <?php foreach (['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#6b7280'] as $color): ?>
                        <label>
                            <input type="radio" name="color" value="<?= e($color) ?>"
                                   <?= e(old('color', $card->color) === $color ? 'checked' : '') ?> class="sr-only peer">
                            <div class="w-6 h-6 rounded-full cursor-pointer ring-2 ring-transparent peer-checked:ring-gray-400 peer-checked:ring-offset-1"
                                 style="background-color: <?= e($color) ?>"></div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            
            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_golden" name="is_golden" value="1"
                       <?= e(old('is_golden', $card->is_golden) ? 'checked' : '') ?>
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_golden" class="text-sm text-gray-700">⭐ Mark as golden</label>
            </div>

            
            <?php if ($tags->isNotEmpty()): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($tags as $tag): ?>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="tags[]" value="<?= e($tag->id) ?>"
                                       <?= e(in_array($tag->id, old('tags', $card->tags->pluck('id')->toArray())) ? 'checked' : '') ?>
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                                      style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>; color: #374151">
                                    <?= e($tag->name) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    Save changes
                </button>
                <a href="<?= e(route('cards.show', $card)) ?>" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
