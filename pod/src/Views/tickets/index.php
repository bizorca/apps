<?php $pageTitle = 'Support Tickets'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Support Tickets</h1>
    <a href="<?= url('tickets/new') ?>" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
        + New ticket
    </a>
</div>

<?php
$statusColors = [
    'open'     => 'bg-yellow-50 text-yellow-700',
    'answered' => 'bg-blue-50 text-blue-700',
    'closed'   => 'bg-gray-100 text-gray-500',
];
?>

<?php if (empty($tickets)): ?>
    <div class="text-center py-16 text-gray-400">No tickets yet. Open one if you need help!</div>
<?php else: ?>
<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php foreach ($tickets as $t): ?>
        <a href="<?= url("tickets/{$t['id']}") ?>"
           class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 transition-colors block">
            <span class="text-xs px-2 py-0.5 rounded-full <?= $statusColors[$t['status']] ?? '' ?> shrink-0 capitalize">
                <?= h($t['status']) ?>
            </span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate"><?= h($t['subject']) ?></p>
                <?php if (!empty($t['first_name'])): ?>
                    <p class="text-xs text-gray-400"><?= h($t['first_name'] . ' ' . $t['last_name']) ?> &middot; <?= h($t['email']) ?></p>
                <?php endif; ?>
            </div>
            <span class="text-xs text-gray-400 shrink-0"><?= timeAgo($t['updated_at']) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
