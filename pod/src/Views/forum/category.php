<?php $pageTitle = $category['name']; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <a href="<?= url('forum') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Forum</a>
        <h1 class="text-2xl font-bold mt-1"><?= h($category['name']) ?></h1>
        <?php if ($category['description']): ?>
            <p class="text-gray-500 text-sm mt-1"><?= h($category['description']) ?></p>
        <?php endif; ?>
    </div>
    <a href="<?= url('forum/new?category=' . urlencode($category['slug'])) ?>"
       class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors shrink-0">
        + New post
    </a>
</div>

<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php if (empty($posts)): ?>
        <p class="px-4 py-6 text-sm text-gray-400">No posts yet in this category.</p>
    <?php endif; ?>
    <?php foreach ($posts as $post): ?>
        <a href="<?= url("forum/post/{$post['id']}") ?>"
           class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 transition-colors block">
            <?php if ($post['is_pinned']): ?>
                <span class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full shrink-0">Pinned</span>
            <?php endif; ?>
            <?php if ($post['is_locked']): ?>
                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full shrink-0">Locked</span>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate"><?= h($post['title']) ?></p>
                <p class="text-xs text-gray-400 mt-0.5">
                    <?= h($post['first_name'] . ' ' . $post['last_name']) ?>
                    &middot; <?= timeAgo($post['created_at']) ?>
                </p>
            </div>
            <?php if ($post['reply_count'] > 0): ?>
                <span class="text-xs text-gray-400 shrink-0"><?= $post['reply_count'] ?> replies</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
