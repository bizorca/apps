<?php
/** Ported from resources/views (Blade). */
$__title = 'Edit comment';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('boards.show', $card->board)) ?>" class="hover:text-gray-600"><?= e($card->board->name) ?></a>
        <span class="mx-2">/</span>
        <a href="<?= e(route('cards.show', $card)) ?>" class="hover:text-gray-600"><?= e(Str::limit($card->title, 40)) ?></a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Edit comment</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-900 mb-6">Edit comment</h1>

        <form method="POST" action="<?= e(route('cards.comments.update', [$card, $comment])) ?>" class="space-y-4">
            <?= csrf_field() ?>
            <?= method_field('PUT') ?>

            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1">Comment <span class="text-red-500">*</span></label>
                <textarea id="body" name="body" rows="6" required
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                          placeholder="Markdown supported. Use @name to mention a teammate."><?= e(old('body', $comment->body)) ?></textarea>
                <p class="mt-1 text-xs text-gray-400">Markdown supported. Use @name to mention a teammate.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    Save changes
                </button>
                <a href="<?= e(route('cards.show', $card)) ?>#comment-<?= e($comment->id) ?>"
                   class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
