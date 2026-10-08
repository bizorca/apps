<?php
/** Ported from resources/views (Blade). */
$__title = 'Tags';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Tags</h1>
        <a href="<?= e(route('tags.create')) ?>"
           class="bg-indigo-600 text-white text-sm font-medium px-4 py-1.5 rounded-lg hover:bg-indigo-700 transition">
            + New tag
        </a>
    </div>

    <?php if ($tags->isEmpty()): ?>
        <div class="text-center py-16">
            <p class="text-gray-400 text-sm mb-3">No tags yet.</p>
            <a href="<?= e(route('tags.create')) ?>" class="text-indigo-600 text-sm hover:underline">Create your first tag</a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <?php foreach ($tags as $tag): ?>
                <div class="px-5 py-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 rounded-full flex-shrink-0"
                             style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>"></div>
                        <span class="text-sm font-medium text-gray-900"><?= e($tag->name) ?></span>
                        <span class="text-xs text-gray-400"><?= e($tag->cardsCount()) ?> <?= e(Str::plural('card', $tag->cardsCount())) ?></span>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="<?= e(route('tags.edit', $tag)) ?>" class="text-xs text-gray-400 hover:text-gray-600">Edit</a>
                        <form method="POST" action="<?= e(route('tags.destroy', $tag)) ?>"
                              onsubmit="return confirm('Delete tag \"<?= e($tag->name) ?>\"? It will be removed from all cards.')">
                            <?= csrf_field() ?>
                            <?= method_field('DELETE') ?>
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
