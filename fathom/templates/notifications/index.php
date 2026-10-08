<?php
/** Ported from resources/views (Blade). */
$__title = 'Notifications';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
        <?php if (auth()->user()->hasUnreadNotifications()): ?>
            <form method="POST" action="<?= e(route('notifications.read_all')) ?>">
                <?= csrf_field() ?> <?= method_field('PATCH') ?>
                <button type="submit" class="text-sm text-indigo-600 hover:underline">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>

    <?php $__empty1 = true; foreach ($notifications as $notification): $__empty1 = false; ?>
        <div class="flex gap-3 p-4 rounded-lg mb-2 <?= e($notification->isUnread() ? 'bg-indigo-50 border border-indigo-100' : 'bg-white border border-gray-200') ?>">
            <div class="flex-1">
                <?php if ($notification->card): ?>
                    <a href="<?= e(route('cards.show', $notification->card)) ?>" class="text-sm text-gray-900 hover:text-indigo-600">
                        <?= e($notification->message) ?>
                    </a>
                    <p class="text-xs text-gray-400 mt-1"><?= e($notification->card->board->name) ?> · <?= e($notification->created_at->diffForHumans()) ?></p>
                <?php else: ?>
                    <p class="text-sm text-gray-900"><?= e($notification->message) ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= e($notification->created_at->diffForHumans()) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($notification->isUnread()): ?>
                <form method="POST" action="<?= e(route('notifications.read', $notification)) ?>">
                    <?= csrf_field() ?> <?= method_field('PATCH') ?>
                    <button type="submit" class="text-xs text-indigo-600 hover:underline flex-shrink-0">Read</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; if ($__empty1): ?>
        <div class="text-center py-16 text-gray-400">
            <p>You're all caught up.</p>
        </div>
    <?php endif; ?>

    <div class="mt-6">
        <?= $paginator->links() ?>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
