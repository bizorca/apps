<?php $pageTitle = 'Search Forum'; ?>

<div class="max-w-2xl">
    <div class="mb-6">
        <a href="<?= url('forum') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Forum</a>
        <h1 class="text-2xl font-bold mt-1">Search</h1>
    </div>

    <form method="GET" action="<?= url('forum/search') ?>" class="mb-6">
        <?= route_field('forum/search') ?>
        <div class="flex gap-2">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search posts..."
                   autofocus
                   class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                Search
            </button>
        </div>
    </form>

    <?php if ($q !== '' && empty($results)): ?>
        <p class="text-sm text-gray-400">No results found for <strong><?= h($q) ?></strong>.</p>
    <?php elseif (!empty($results)): ?>
        <p class="text-xs text-gray-400 mb-3"><?= count($results) ?> result<?= count($results) !== 1 ? 's' : '' ?> for <strong><?= h($q) ?></strong></p>
        <div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
            <?php foreach ($results as $post): ?>
                <a href="<?= url("forum/post/{$post['id']}") ?>"
                   class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 transition-colors block">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate"><?= h($post['title']) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            <?= h($post['first_name'] . ' ' . $post['last_name']) ?>
                            in <span class="text-indigo-500"><?= h($post['category_name']) ?></span>
                            &middot; <?= timeAgo($post['created_at']) ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
