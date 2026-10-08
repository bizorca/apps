<?php
use Dispatch\Core\View;
$title = 'Notifications';
// $notifications
?>
<div class="max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <p class="text-slate-500 text-sm"><?= count($notifications) ?> notification<?= count($notifications) !== 1 ? 's' : '' ?></p>
        <?php if (!empty($notifications)): ?>
        <form method="POST" action="<?= $_base ?>/notifications/read-all">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
            <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">Mark all read</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-16 text-center">
        <div class="text-5xl mb-4">🔔</div>
        <h3 class="text-lg font-bold text-slate-900 mb-2">No notifications</h3>
        <p class="text-slate-500 text-sm">You're all caught up.</p>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
        <?php foreach ($notifications as $n):
            $isRead = !empty($n['read_at']);
            $typeIcon = match($n['type']) {
                'venue_approved' => '✅',
                'venue_rejected' => '❌',
                default          => '🔔',
            };
        ?>
        <div class="px-6 py-4 flex items-start gap-4 <?= $isRead ? 'opacity-60' : 'bg-indigo-50/30' ?>">
            <span class="text-xl flex-shrink-0 mt-0.5"><?= $typeIcon ?></span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-900"><?= View::e($n['title']) ?></p>
                <p class="text-sm text-slate-600 mt-0.5"><?= View::e($n['message']) ?></p>
                <p class="text-xs text-slate-400 mt-1"><?= View::relativeDate($n['created_at']) ?></p>
                <?php if (!empty($n['link'])): ?>
                <a href="<?= $_base ?><?= View::e($n['link']) ?>" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium mt-1 inline-block">View →</a>
                <?php endif; ?>
            </div>
            <?php if (!$isRead): ?>
            <span class="w-2 h-2 bg-indigo-500 rounded-full flex-shrink-0 mt-1.5"></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
