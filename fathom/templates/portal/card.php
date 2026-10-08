<?php
/** Ported from resources/views (Blade). */
$__title = $card->title;
ob_start();
?>
<div class="max-w-3xl mx-auto px-4 py-8">
    
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('portal.dashboard')) ?>" class="hover:text-gray-600">My Work</a>
        <span class="mx-2">/</span>
        <span class="text-gray-600"><?= e(Str::limit($card->title, 50)) ?></span>
    </nav>

    
    <div class="mb-6">
        <?php if ($card->isClosed()): ?>
            <span class="inline-block text-xs font-medium px-2 py-0.5 bg-gray-100 text-gray-600 rounded mb-2">Closed</span>
        <?php endif; ?>
        <h1 class="text-2xl font-bold text-gray-900 leading-snug"><?= e($card->title) ?></h1>
        <div class="flex items-center gap-4 mt-2 text-sm text-gray-400">
            <?php if ($card->column): ?>
                <span><?= e($card->column->name) ?></span>
            <?php endif; ?>
            <?php if ($card->board): ?>
                <span><?= e($card->board->name) ?></span>
            <?php endif; ?>
        </div>
    </div>

    
    <?php if ($card->description): ?>
        <div class="prose prose-sm max-w-none text-gray-700 bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <?= markdown($card->description) ?>
        </div>
    <?php endif; ?>

    
    <?php if ($card->tags->isNotEmpty()): ?>
        <div class="flex flex-wrap gap-1.5 mb-6">
            <?php foreach ($card->tags as $tag): ?>
                <span class="text-xs px-2 py-0.5 rounded-full"
                      style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>; color: #374151">
                    <?= e($tag->name) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    
    <?php if ($card->steps->isNotEmpty()): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                Checklist
                <span class="font-normal text-gray-400 ml-1"><?= e($card->completedStepsCount()) ?>/<?= e($card->totalStepsCount()) ?></span>
            </h3>
            <?php if ($card->totalStepsCount() > 0): ?>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden mb-4">
                    <div class="h-full bg-indigo-500 rounded-full transition-all"
                         style="width: <?= e(round(($card->completedStepsCount() / $card->totalStepsCount()) * 100)) ?>%">
                    </div>
                </div>
            <?php endif; ?>
            <div class="space-y-2">
                <?php foreach ($card->steps as $step): ?>
                    <div class="flex items-center gap-3">
                        <form method="POST" action="<?= e($step->completed ? route('portal.steps.incomplete', [$card, $step]) : route('portal.steps.complete', [$card, $step])) ?>">
                            <?= csrf_field() ?> <?= method_field('PATCH') ?>
                            <button type="submit" class="w-4 h-4 rounded border <?= e($step->completed ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300') ?> flex items-center justify-center flex-shrink-0">
                                <?php if ($step->completed): ?>
                                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                <?php endif; ?>
                            </button>
                        </form>
                        <span class="text-sm <?= e($step->completed ? 'line-through text-gray-400' : 'text-gray-700') ?>"><?= e($step->title) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">
            Comments <span class="font-normal text-gray-400">(<?= e($card->comments->count()) ?>)</span>
        </h3>

        <?php $__empty1 = true; foreach ($card->comments as $comment): $__empty1 = false; ?>
            <div class="flex gap-3 mb-5">
                <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-medium flex-shrink-0">
                    <?= e($comment->creatorInitials()) ?>
                </div>
                <div class="flex-1">
                    <div class="flex items-baseline gap-2 mb-1">
                        <span class="text-sm font-medium text-gray-900"><?= e($comment->creatorName()) ?></span>
                        <span class="text-xs text-gray-400"><?= e($comment->created_at->diffForHumans()) ?></span>
                    </div>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <?= $comment->html() ?>
                    </div>
                </div>
            </div>
        <?php endforeach; if ($__empty1): ?>
            <p class="text-sm text-gray-400 mb-4">No comments yet.</p>
        <?php endif; ?>

        
        <form method="POST" action="<?= e(route('portal.comments.store', $card)) ?>" class="mt-4">
            <?= csrf_field() ?>
            <textarea name="body" rows="3" placeholder="Add a comment…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            <div class="mt-2">
                <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-1.5 rounded-lg hover:bg-indigo-700 transition">
                    Comment
                </button>
            </div>
        </form>
    </div>
</div>
<?php fm_layout('portal', (string) $__title, (string) ob_get_clean()); ?>
