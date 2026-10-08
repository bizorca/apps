<?php
/** Ported from resources/views (Blade). */
$__title = 'All cards';
ob_start();
?>
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Cards</h1>
        <a href="<?= e(route('cards.create')) ?>"
           class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
            + New card
        </a>
    </div>

    
    <form method="GET" action="<?= e(route('cards.index')) ?>" class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
        <?= fm_route_field('cards.index') ?>
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Board</label>
                <select name="board" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All boards</option>
                    <?php foreach ($boards as $board): ?>
                        <option value="<?= e($board->id) ?>" <?= e(request('board') === $board->id ? 'selected' : '') ?>><?= e($board->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Assignee</label>
                <select name="assignee" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Anyone</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= e($member->id) ?>" <?= e(request('assignee') === $member->id ? 'selected' : '') ?>><?= e($member->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Tag</label>
                <select name="tag" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Any tag</option>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?= e($tag->id) ?>" <?= e(request('tag') === $tag->id ? 'selected' : '') ?>><?= e($tag->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Status</label>
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Any status</option>
                    <option value="open" <?= e(request('status') === 'open' ? 'selected' : '') ?>>Open</option>
                    <option value="closed" <?= e(request('status') === 'closed' ? 'selected' : '') ?>>Closed</option>
                    <option value="draft" <?= e(request('status') === 'draft' ? 'selected' : '') ?>>Draft</option>
                    <option value="golden" <?= e(request('status') === 'golden' ? 'selected' : '') ?>>Golden</option>
                    <option value="stalled" <?= e(request('status') === 'stalled' ? 'selected' : '') ?>>Stalled</option>
                </select>
            </div>

            <button type="submit" class="bg-gray-800 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-gray-700 transition">
                Filter
            </button>

            <?php if (request()->hasAny(['board', 'assignee', 'tag', 'status'])): ?>
                <a href="<?= e(route('cards.index')) ?>" class="text-sm text-gray-400 hover:text-gray-600">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    
    <?php if ($cards->isEmpty()): ?>
        <div class="text-center py-16 text-gray-400 text-sm">No cards match these filters.</div>
    <?php else: ?>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <?php foreach ($cards as $card): ?>
                <a href="<?= e(route('cards.show', $card)) ?>"
                   class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition">
                    
                    <?php if ($card->color): ?>
                        <div class="w-1 rounded-full self-stretch flex-shrink-0" style="background-color: <?= e($card->color) ?>"></div>
                    <?php endif; ?>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <p class="text-sm font-medium text-gray-900 truncate"><?= e($card->title) ?></p>
                            <?php if ($card->is_draft): ?>
                                <span class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded flex-shrink-0">Draft</span>
                            <?php endif; ?>
                            <?php if ($card->is_golden): ?>
                                <span class="text-xs flex-shrink-0">⭐</span>
                            <?php endif; ?>
                            <?php if ($card->isClosed()): ?>
                                <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded flex-shrink-0">Closed</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-gray-400">
                            <span><?= e($card->board->name) ?></span>
                            <span>·</span>
                            <span><?= e($card->column?->name ?? 'No column') ?></span>
                            <?php if ($card->tags->isNotEmpty()): ?>
                                <span>·</span>
                                <?php foreach ($card->tags->take(3) as $tag): ?>
                                    <span class="px-1.5 py-0.5 rounded-full"
                                          style="background-color: <?= e($tag->color ?? '#e5e7eb') ?>; color: #374151">
                                        <?= e($tag->name) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <?php if ($card->due_at): ?>
                            <?php $dueClass = $card->due_at->isPast() ? 'text-red-500' : ($card->due_at->daysFromNow() <= 3 ? 'text-amber-500' : 'text-gray-400'); ?>
                            <span class="text-xs <?= e($dueClass) ?>"><?= e($card->due_at->format('M j')) ?></span>
                        <?php endif; ?>
                        <div class="flex -space-x-1">
                            <?php foreach ($card->assignees->take(3) as $assignee): ?>
                                <div class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs border border-white"
                                     title="<?= e($assignee->name) ?>">
                                    <?= e(substr($assignee->name, 0, 1)) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <?= $paginator->links() ?>
        </div>
    <?php endif; ?>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
