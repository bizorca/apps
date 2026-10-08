<?php $pageTitle = 'Forum'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Forum</h1>
    <div class="flex items-center gap-3">
        <form method="GET" action="<?= url('forum/search') ?>" class="flex items-center gap-2">
            <?= route_field('forum/search') ?>
            <input type="text" name="q" placeholder="Search forum..."
                   class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm w-44 focus:outline-none focus:ring-2 focus:ring-indigo-300">
            <button type="submit" class="text-sm text-gray-400 hover:text-indigo-600 transition-colors">&#128269;</button>
        </form>
        <a href="<?= url('forum/new') ?>" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
            + New post
        </a>
    </div>
</div>

<!-- Categories -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-10">
    <?php foreach ($categories as $cat): ?>
        <a href="<?= url("forum/category/{$cat['slug']}") ?>"
           class="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-300 transition-colors relative">
            <div class="flex items-start justify-between gap-2">
                <h2 class="font-semibold text-gray-900"><?= h($cat['name']) ?></h2>
                <?php if ($cat['unread_count'] > 0): ?>
                    <span class="shrink-0 inline-flex items-center justify-center min-w-[1.3rem] h-5 rounded-full bg-indigo-600 text-white text-xs font-semibold px-1.5">
                        <?= $cat['unread_count'] ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($cat['description']): ?>
                <p class="text-sm text-gray-400 mt-1"><?= h($cat['description']) ?></p>
            <?php endif; ?>
            <p class="text-xs text-gray-400 mt-2"><?= $cat['post_count'] ?> posts</p>
        </a>
    <?php endforeach; ?>
</div>

<!-- Recent posts -->
<h2 class="text-lg font-semibold mb-3">Recent Posts</h2>
<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php if (empty($recentPosts)): ?>
        <p class="px-4 py-6 text-sm text-gray-400">No posts yet.</p>
    <?php endif; ?>
    <?php foreach ($recentPosts as $post): ?>
        <a href="<?= url("forum/post/{$post['id']}") ?>"
           class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 transition-colors block">
            <?php if ($post['is_pinned']): ?>
                <span class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full shrink-0">Pinned</span>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate"><?= h($post['title']) ?></p>
                <p class="text-xs text-gray-400 mt-0.5">
                    <?= h($post['first_name'] . ' ' . $post['last_name']) ?>
                    in <span class="text-indigo-500"><?= h($post['category_name']) ?></span>
                    &middot; <?= timeAgo($post['created_at']) ?>
                </p>
            </div>
            <?php if ($post['reply_count'] > 0): ?>
                <span class="text-xs text-gray-400 shrink-0"><?= $post['reply_count'] ?> replies</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
