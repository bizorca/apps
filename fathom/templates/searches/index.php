<?php
/** Ported from resources/views (Blade). */
$__title = $query ? "Search: {$query}" : 'Search';
ob_start();
?>
<div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-xl font-semibold text-gray-900 mb-6">Search</h1>

    <form method="GET" action="<?= e(route('search')) ?>" class="mb-8">
        <?= fm_route_field('search') ?>
        <div class="flex gap-2">
            <input type="search" name="q" value="<?= e($query) ?>" autofocus
                   placeholder="Search cards and boards…"
                   class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg hover:bg-indigo-700 transition">
                Search
            </button>
        </div>
    </form>

    <?php if ($query && strlen(trim($query)) < 2): ?>
        <p class="text-sm text-gray-500">Enter at least 2 characters to search.</p>
    <?php elseif ($query && $results->isEmpty()): ?>
        <p class="text-sm text-gray-500">No results for <strong>"<?= e($query) ?>"</strong>.</p>
    <?php elseif ($results->isNotEmpty()): ?>
        <p class="text-xs text-gray-400 mb-4"><?= e($results->count()) ?> result<?= e($results->count() === 1 ? '' : 's') ?> for <strong>"<?= e($query) ?>"</strong></p>

        <?php $boards = $results->where('type', 'board');
            $cards  = $results->where('type', 'card'); ?>

        <?php if ($boards->isNotEmpty()): ?>
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Boards</h2>
            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 mb-6">
                <?php foreach ($boards as $result): ?>
                    <?php $board = $result['item']; ?>
                    <a href="<?= e(route('boards.show', $board)) ?>"
                       class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50 transition">
                        <?php if ($board->color): ?>
                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: <?= e($board->color) ?>"></div>
                        <?php endif; ?>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?= e($board->name) ?></p>
                            <?php if ($board->description): ?>
                                <p class="text-xs text-gray-400 truncate"><?= e(Str::limit($board->description, 80)) ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($cards->isNotEmpty()): ?>
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Cards</h2>
            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                <?php foreach ($cards as $result): ?>
                    <?php $card = $result['item']; ?>
                    <a href="<?= e(route('cards.show', $card)) ?>"
                       class="flex items-start gap-4 px-5 py-3.5 hover:bg-gray-50 transition">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate"><?= e($card->title) ?></p>
                            <div class="flex items-center gap-2 text-xs text-gray-400 mt-0.5">
                                <span><?= e($card->board->name) ?></span>
                                <span>·</span>
                                <span><?= e($card->column?->name ?? 'No column') ?></span>
                                <?php if ($card->is_draft): ?>
                                    <span class="text-yellow-600">Draft</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex -space-x-1 flex-shrink-0">
                            <?php foreach ($card->assignees->take(3) as $assignee): ?>
                                <div class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs border border-white"
                                     title="<?= e($assignee->name) ?>">
                                    <?= e(substr($assignee->name, 0, 1)) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
