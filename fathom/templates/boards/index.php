<?php
/** Ported from resources/views (Blade). */
$__title = 'Boards';
ob_start();
?>
<div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Boards</h1>
        <a href="<?= e(route('boards.create')) ?>"
           class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
            New board
        </a>
    </div>

    <?php if ($boards->isEmpty() && $archivedBoards->isEmpty()): ?>
        <div class="text-center py-20 text-gray-400">
            <p class="text-lg">No boards yet.</p>
            <p class="text-sm mt-1">Create your first board to get started.</p>
        </div>
    <?php else: ?>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($boards as $board): ?>
                <a href="<?= e(route('boards.show', $board)) ?>"
                   class="group block bg-white rounded-xl border border-gray-200 p-5 hover:border-indigo-300 hover:shadow-sm transition">
                    <div class="flex items-start justify-between">
                        <div>
                            <?php if ($board->color): ?>
                                <div class="w-3 h-3 rounded-full mb-2" style="background-color: <?= e($board->color) ?>"></div>
                            <?php endif; ?>
                            <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600"><?= e($board->name) ?></h3>
                            <?php if ($board->description): ?>
                                <p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= e($board->description) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-4 text-xs text-gray-400">
                        <span><?= e($board->openCardsCount()) ?> open cards</span>
                        <span><?= e($board->columns->count()) ?> columns</span>
                        <?php if ($board->is_public): ?>
                            <span class="text-green-500">Public</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        
        <?php if ($archivedBoards->isNotEmpty()): ?>
            <div class="mt-10">
                <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Archived</h2>
                <div class="space-y-2">
                    <?php foreach ($archivedBoards as $board): ?>
                        <div class="flex items-center justify-between bg-white border border-gray-200 rounded-lg px-4 py-3">
                            <span class="text-sm text-gray-600"><?= e($board->name) ?></span>
                            <form method="POST" action="<?= e(route('boards.unarchive', $board)) ?>">
                                <?= csrf_field() ?>
                                <?= method_field('PATCH') ?>
                                <button type="submit" class="text-xs text-indigo-600 hover:underline">Restore</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
