<?php
/** Ported from resources/views (Blade). */
$__title = $card->title . ' — Fathom';
ob_start();
?>
<div class="max-w-2xl">
    <div class="mb-4 text-sm text-gray-400">
        <a href="<?= e(route('public.boards.show', $card->board->share_token)) ?>" class="hover:text-gray-600">
            <?= e($card->board->name) ?>
        </a>
        <span class="mx-2">/</span>
        <span><?= e($card->column?->name ?? 'No column') ?></span>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <?php if ($card->color): ?>
            <div class="h-1.5 rounded-full mb-4" style="background-color: <?= e($card->color) ?>"></div>
        <?php endif; ?>

        <h1 class="text-2xl font-bold text-gray-900 mb-3 leading-snug"><?= e($card->title) ?></h1>

        <div class="flex flex-wrap gap-3 text-xs text-gray-400 mb-4">
            <span><?= e($card->column?->name ?? 'No column') ?></span>
            <?php if ($card->creator): ?>
                <span>· Created by <?= e($card->creator->name) ?></span>
            <?php endif; ?>
            <span>· <?= e($card->created_at->format('M j, Y')) ?></span>
            <?php if ($card->due_at): ?>
                <span>· Due <?= e($card->due_at->format('M j, Y')) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($card->tags->isNotEmpty()): ?>
            <div class="flex flex-wrap gap-1.5 mb-4">
                <?php foreach ($card->tags as $tag): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                          style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>; color: #374151">
                        <?= e($tag->name) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($card->assignees->isNotEmpty()): ?>
            <div class="flex items-center gap-2 mb-4">
                <span class="text-xs text-gray-400">Assigned to</span>
                <?php foreach ($card->assignees as $assignee): ?>
                    <div class="flex items-center gap-1.5">
                        <div class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">
                            <?= e(substr($assignee->name, 0, 1)) ?>
                        </div>
                        <span class="text-xs text-gray-600"><?= e($assignee->name) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($card->description): ?>
            <div class="prose prose-sm max-w-none text-gray-700 border-t border-gray-100 pt-4 mt-4">
                <?= markdown($card->description) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($card->steps->isNotEmpty()): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">
                Checklist
                <span class="font-normal text-gray-400 ml-1"><?= e($card->completedStepsCount()) ?>/<?= e($card->totalStepsCount()) ?></span>
            </h2>
            <div class="space-y-2">
                <?php foreach ($card->steps as $step): ?>
                    <div class="flex items-center gap-3">
                        <div class="w-4 h-4 rounded border <?= e($step->completed ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300') ?> flex items-center justify-center flex-shrink-0">
                            <?php if ($step->completed): ?>
                                <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm <?= e($step->completed ? 'line-through text-gray-400' : 'text-gray-700') ?>"><?= e($step->title) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($card->comments->isNotEmpty()): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Comments (<?= e($card->comments->count()) ?>)</h2>
            <div class="space-y-5">
                <?php foreach ($card->comments as $comment): ?>
                    <div class="flex gap-3">
                        <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-medium flex-shrink-0">
                            <?= e($comment->creatorInitials()) ?>
                        </div>
                        <div>
                            <div class="flex items-baseline gap-2 mb-1">
                                <span class="text-sm font-medium text-gray-900"><?= e($comment->creatorName()) ?></span>
                                <span class="text-xs text-gray-400"><?= e($comment->created_at->diffForHumans()) ?></span>
                            </div>
                            <div class="prose prose-sm max-w-none text-gray-700">
                                <?= $comment->html() ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php fm_layout('public', (string) $__title, (string) ob_get_clean()); ?>
