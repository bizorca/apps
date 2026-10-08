<?php
/** Ported from resources/views (Blade). */
$__title = 'Card templates';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Card templates</h1>
        <a href="<?= e(route('card-templates.create')) ?>"
           class="bg-indigo-600 text-white text-sm font-medium px-4 py-1.5 rounded-lg hover:bg-indigo-700 transition">
            + New template
        </a>
    </div>

    <?php if ($templates->isEmpty()): ?>
        <div class="text-center py-16">
            <p class="text-gray-400 text-sm mb-3">No templates yet.</p>
            <p class="text-gray-400 text-sm mb-3">Save any card configuration as a reusable starting point.</p>
            <a href="<?= e(route('card-templates.create')) ?>" class="text-indigo-600 text-sm hover:underline">Create your first template</a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <?php foreach ($templates as $template): ?>
                <div class="px-5 py-4 flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900"><?= e($template->name) ?></p>
                        <?php if ($template->title_pattern): ?>
                            <p class="text-xs text-gray-400 mt-0.5">Title: <?= e($template->title_pattern) ?></p>
                        <?php endif; ?>
                        <div class="flex flex-wrap items-center gap-3 mt-1.5">
                            <?php if ($template->defaultColumn): ?>
                                <span class="text-xs text-gray-400">
                                    Default column: <?= e($template->defaultColumn->board->name ?? '') ?> / <?= e($template->defaultColumn->name) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($template->tags->isNotEmpty()): ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($template->tags as $tag): ?>
                                        <span class="text-xs px-1.5 py-0.5 rounded-full"
                                              style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>; color: #374151">
                                            <?= e($tag->name) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($template->steps->isNotEmpty()): ?>
                                <span class="text-xs text-gray-400"><?= e($template->steps->count()) ?> checklist <?= e(Str::plural('item', $template->steps->count())) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 flex-shrink-0">
                        <a href="<?= e(route('card-templates.edit', $template)) ?>" class="text-xs text-gray-400 hover:text-gray-600">Edit</a>
                        <form method="POST" action="<?= e(route('card-templates.destroy', $template)) ?>"
                              onsubmit="return confirm('Delete template \"<?= e($template->name) ?>\"?')">
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
