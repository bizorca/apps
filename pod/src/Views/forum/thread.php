<?php $pageTitle = $post['title']; ?>

<div class="max-w-3xl">

    <div class="mb-4">
        <a href="<?= url("forum/category/{$post['category_slug']}") ?>" class="text-sm text-indigo-600 hover:underline">
            &larr; <?= h($post['category_name']) ?>
        </a>
    </div>

    <!-- Original post -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
        <div class="flex items-start justify-between gap-4">
            <h1 class="text-xl font-bold text-gray-900"><?= h($post['title']) ?></h1>
            <div class="flex gap-2 shrink-0">
                <?php if ($post['is_pinned']): ?>
                    <span class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full">Pinned</span>
                <?php endif; ?>
                <?php if ($post['is_locked']): ?>
                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Locked</span>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-1 mb-4">
            <a href="<?= url("profile/{$post['user_id']}") ?>" class="hover:text-indigo-600">
                <?= h($post['first_name'] . ' ' . $post['last_name']) ?>
            </a>
            &middot; <?= timeAgo($post['created_at']) ?>
        </p>
        <div class="text-gray-700 text-sm whitespace-pre-wrap"><?= h($post['body']) ?></div>

        <!-- Reactions bar -->
        <div class="mt-4 flex items-center gap-2 flex-wrap">
            <?php
            $reactionMap = [];
            foreach ($reactions as $r) {
                $reactionMap[$r['emoji']] = $r;
            }
            $allowedEmojis = ['👍', '❤️', '😂', '😮'];
            foreach ($allowedEmojis as $emoji):
                $r = $reactionMap[$emoji] ?? null;
                $count   = $r ? (int)$r['count'] : 0;
                $reacted = $r && $r['reacted'];
            ?>
                <form method="POST" action="<?= url("forum/post/{$post['id']}/react") ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="emoji" value="<?= h($emoji) ?>">
                    <button type="submit"
                            class="flex items-center gap-1 px-2.5 py-1 rounded-full text-sm border transition-colors
                                   <?= $reacted ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-indigo-200 hover:bg-indigo-50' ?>">
                        <span><?= $emoji ?></span>
                        <?php if ($count > 0): ?>
                            <span class="text-xs font-medium"><?= $count ?></span>
                        <?php endif; ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($user['is_admin'])): ?>
            <div class="mt-4 flex gap-2">
                <form method="POST" action="<?= url("admin/forum/posts/{$post['id']}/pin") ?>">
                    <?= csrf_field() ?>
                    <button class="text-xs text-gray-400 hover:text-amber-600 transition-colors">
                        <?= $post['is_pinned'] ? 'Unpin' : 'Pin' ?>
                    </button>
                </form>
                <span class="text-gray-200">|</span>
                <form method="POST" action="<?= url("admin/forum/posts/{$post['id']}/lock") ?>">
                    <?= csrf_field() ?>
                    <button class="text-xs text-gray-400 hover:text-amber-600 transition-colors">
                        <?= $post['is_locked'] ? 'Unlock' : 'Lock' ?>
                    </button>
                </form>
                <span class="text-gray-200">|</span>
                <form method="POST" action="<?= url("admin/forum/posts/{$post['id']}/delete") ?>"
                      onsubmit="return confirm('Delete this post and all replies?')">
                    <?= csrf_field() ?>
                    <button class="text-xs text-red-400 hover:text-red-600 transition-colors">Delete</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Replies -->
    <?php foreach ($replies as $reply): ?>
        <div class="bg-white rounded-xl border border-gray-100 px-6 py-4 mb-3">
            <p class="text-xs text-gray-400 mb-2">
                <a href="<?= url("profile/{$reply['user_id']}") ?>" class="hover:text-indigo-600">
                    <?= h($reply['first_name'] . ' ' . $reply['last_name']) ?>
                </a>
                <?php if ($reply['is_staff']): ?>
                    <span class="ml-1 bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded text-xs">Staff</span>
                <?php endif; ?>
                &middot; <?= timeAgo($reply['created_at']) ?>
            </p>
            <div class="text-sm text-gray-700 whitespace-pre-wrap"><?= h($reply['body']) ?></div>
        </div>
    <?php endforeach; ?>

    <!-- Reply form -->
    <?php if (!$post['is_locked']): ?>
        <div id="bottom" class="mt-6 bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="font-medium text-gray-900 mb-3">Leave a reply</h3>
            <form method="POST" action="<?= url("forum/post/{$post['id']}/reply") ?>">
                <?= csrf_field() ?>
                <textarea name="body" rows="4" required
                          placeholder="Write your reply..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none"></textarea>
                <div class="mt-3 text-right">
                    <button type="submit"
                            class="bg-indigo-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                        Post reply
                    </button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <p class="text-sm text-gray-400 mt-6 text-center">This thread is locked.</p>
    <?php endif; ?>

</div>
