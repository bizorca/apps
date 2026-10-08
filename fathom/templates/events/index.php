<?php
/** Ported from resources/views (Blade). */
$__title = 'Home';
ob_start();
?>
<div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <div class="space-y-6">
            
            <?php if ($recentBoards->isNotEmpty()): ?>
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Recent Boards</h3>
                    <div class="space-y-1">
                        <?php foreach ($recentBoards as $board): ?>
                            <a href="<?= e(route('boards.show', $board)) ?>" class="block text-sm text-gray-700 hover:text-indigo-600 py-1">
                                <?= e($board->name) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            
            <?php if ($pinnedCards->isNotEmpty()): ?>
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Pinned Cards</h3>
                    <div class="space-y-1">
                        <?php foreach ($pinnedCards as $card): ?>
                            <a href="<?= e(route('cards.show', $card)) ?>" class="block text-sm text-gray-700 hover:text-indigo-600 py-1 leading-snug">
                                <?= e(Str::limit($card->title, 45)) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        
        <div class="lg:col-span-3">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">Activity</h1>

            <?php $__empty1 = true; foreach ($events as $event): $__empty1 = false; ?>
                <div class="flex gap-3 mb-5">
                    <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0 text-xs text-gray-500">
                        <?= e($event->user ? $event->user->initials() : '?') ?>
                    </div>
                    <div>
                        <p class="text-sm text-gray-700">
                            <span class="font-medium"><?= e($event->user?->name ?? 'Someone') ?></span>
                            <?= e($event->action) ?>
                            <?php if ($event->card): ?>
                                on <a href="<?= e(route('cards.show', $event->card)) ?>" class="text-indigo-600 hover:underline"><?= e(Str::limit($event->card->title, 50)) ?></a>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5"><?= e($event->created_at->diffForHumans()) ?></p>
                    </div>
                </div>
            <?php endforeach; if ($__empty1): ?>
                <div class="text-center py-16">
                    <p class="text-gray-400">No activity yet.</p>
                    <p class="text-sm text-gray-400 mt-1">
                        <a href="<?= e(route('boards.create')) ?>" class="text-indigo-600 hover:underline">Create your first board</a> to get started.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
