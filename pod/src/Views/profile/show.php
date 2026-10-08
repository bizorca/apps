<?php $pageTitle = $target['first_name'] . ' ' . $target['last_name']; ?>

<div class="max-w-2xl">

    <!-- Profile header -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6 flex items-start gap-6">
        <?php if (!empty($target['avatar_url'])): ?>
            <img src="<?= h(safe_url($target['avatar_url'])) ?>"
                 alt="<?= h($target['first_name']) ?>"
                 class="w-20 h-20 rounded-full object-cover shrink-0 border border-gray-200">
        <?php else: ?>
            <div class="w-20 h-20 rounded-full shrink-0 bg-indigo-100 flex items-center justify-center text-indigo-600 text-2xl font-bold select-none">
                <?= h(mb_strtoupper(mb_substr($target['first_name'], 0, 1))) ?>
            </div>
        <?php endif; ?>

        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-bold text-gray-900">
                <?= h($target['first_name'] . ' ' . $target['last_name']) ?>
            </h1>
            <p class="text-xs text-gray-400 mt-1">Member since <?= date('F Y', strtotime($target['created_at'])) ?></p>
            <?php if (!empty($target['bio'])): ?>
                <p class="text-sm text-gray-600 mt-3"><?= nl2br(h($target['bio'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent forum posts -->
    <h2 class="text-lg font-semibold mb-3">Recent Posts</h2>
    <div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
        <?php if (empty($posts)): ?>
            <p class="px-4 py-6 text-sm text-gray-400">No posts yet.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <a href="<?= url("forum/post/{$post['id']}") ?>"
                   class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 transition-colors block">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate"><?= h($post['title']) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            in <span class="text-indigo-500"><?= h($post['category_name']) ?></span>
                            &middot; <?= timeAgo($post['created_at']) ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
