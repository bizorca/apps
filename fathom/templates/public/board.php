<?php
/** Ported from resources/views (Blade). */
$__title = $board->name . ' — Fathom';
ob_start();
?>
<div class="mb-6">
    <div class="flex items-center gap-3 mb-1">
        <?php if ($board->color): ?>
            <div class="w-3 h-3 rounded-full" style="background-color: <?= e($board->color) ?>"></div>
        <?php endif; ?>
        <h1 class="text-2xl font-bold text-gray-900"><?= e($board->name) ?></h1>
    </div>
    <?php if ($board->description): ?>
        <p class="text-gray-500 text-sm mt-1"><?= e($board->description) ?></p>
    <?php endif; ?>
    <p class="text-xs text-gray-400 mt-2">Read-only view &middot; Last updated <?= e($board->updated_at->diffForHumans()) ?></p>
</div>

<div class="flex gap-4 overflow-x-auto pb-4">
    <?php $__empty1 = true; foreach ($board->columns as $column): $__empty1 = false; ?>
        <div class="w-64 flex-shrink-0">
            <div class="flex items-center gap-2 mb-3">
                <?php if ($column->color): ?>
                    <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?= e($column->color) ?>"></div>
                <?php endif; ?>
                <h3 class="text-sm font-semibold text-gray-700"><?= e($column->name) ?></h3>
                <span class="text-xs text-gray-400 bg-gray-100 rounded-full px-1.5 py-0.5"><?= e($column->cards->count()) ?></span>
            </div>

            <div class="space-y-2">
                <?php $__empty2 = true; foreach ($column->cards as $card): $__empty2 = false; ?>
                    <div class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm">
                        <?php if ($card->color): ?>
                            <div class="h-1 rounded-full mb-2" style="background-color: <?= e($card->color) ?>"></div>
                        <?php endif; ?>
                        <p class="text-sm text-gray-900 font-medium leading-snug"><?= e($card->title) ?></p>

                        <?php if ($card->tags->isNotEmpty()): ?>
                            <div class="flex flex-wrap gap-1 mt-2">
                                <?php foreach ($card->tags as $tag): ?>
                                    <span class="text-xs px-1.5 py-0.5 rounded-full"
                                          style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>; color: #374151">
                                        <?= e($tag->name) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center justify-between mt-2">
                            <div class="flex -space-x-1">
                                <?php foreach ($card->assignees->take(3) as $assignee): ?>
                                    <div class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs border border-white"
                                         title="<?= e($assignee->name) ?>">
                                        <?= e(substr($assignee->name, 0, 1)) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($card->share_token): ?>
                                <a href="<?= e(route('public.cards.show', $card->share_token)) ?>"
                                   class="text-xs text-indigo-500 hover:underline">View</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; if ($__empty2): ?>
                    <div class="text-xs text-gray-400 py-3 text-center border border-dashed border-gray-200 rounded-lg">Empty</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; if ($__empty1): ?>
        <p class="text-gray-400 text-sm">This board has no columns yet.</p>
    <?php endif; ?>
</div>
<?php fm_layout('public', (string) $__title, (string) ob_get_clean()); ?>
