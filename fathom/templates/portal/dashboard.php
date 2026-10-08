<?php
/** Ported from resources/views (Blade). */
$__title = 'My Work';
ob_start();
?>
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-xl font-semibold text-gray-900 mb-6">Your Work</h1>

    <?php if (empty($cards)): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <p class="text-gray-500 text-sm">No cards have been shared with you yet.</p>
        </div>
    <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($cards as $boardName => $boardCards): ?>
                <div>
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3"><?= e($boardName) ?></h2>
                    <div class="space-y-2">
                        <?php foreach ($boardCards as $card): ?>
                            <a href="<?= e(route('portal.cards.show', $card)) ?>"
                               class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-300 transition group">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 group-hover:text-indigo-600 transition">
                                            <?= e($card->title) ?>
                                        </p>
                                        <?php if ($card->column): ?>
                                            <p class="text-xs text-gray-400 mt-0.5"><?= e($card->column->name) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($card->totalStepsCount() > 0): ?>
                                        <div class="flex-shrink-0 text-xs text-gray-400">
                                            <?= e($card->completedStepsCount()) ?>/<?= e($card->totalStepsCount()) ?> steps
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($card->totalStepsCount() > 0): ?>
                                    <div class="mt-3 h-1 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-indigo-500 rounded-full transition-all"
                                             style="width: <?= e($card->totalStepsCount() > 0 ? round(($card->completedStepsCount() / $card->totalStepsCount()) * 100) : 0) ?>%">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php fm_layout('portal', (string) $__title, (string) ob_get_clean()); ?>
