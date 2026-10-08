<?php $pageTitle = 'Notifications'; ?>

<div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Notifications</h1>

    <?php if (empty($notifications)): ?>
        <div class="bg-white border border-gray-200 rounded-xl px-6 py-10 text-center">
            <p class="text-gray-400 text-sm">You're all caught up.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
            <?php foreach ($notifications as $n): ?>
                <div class="flex items-start gap-3 px-4 py-3 <?= !$n['is_read'] ? 'bg-indigo-50' : '' ?>">
                    <?php if (!$n['is_read']): ?>
                        <span class="mt-1.5 w-2 h-2 rounded-full bg-indigo-500 shrink-0"></span>
                    <?php else: ?>
                        <span class="mt-1.5 w-2 h-2 rounded-full bg-transparent shrink-0"></span>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <?php if ($n['url']): ?>
                            <a href="<?= h(url($n['url'])) ?>" class="text-sm text-gray-900 hover:text-indigo-600">
                                <?= h($n['message']) ?>
                            </a>
                        <?php else: ?>
                            <p class="text-sm text-gray-900"><?= h($n['message']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-0.5"><?= timeAgo($n['created_at']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
