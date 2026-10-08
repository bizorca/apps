<?php $pageTitle = 'Admin Dashboard'; ?>

<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold">Admin Dashboard</h1>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <?php foreach ([
        ['Users',          $stats['users'],        url('admin/users')],
        ['Open Tickets',   $stats['open_tickets'],  url('admin')],
        ['Courses',        $stats['courses'],       url('admin/courses')],
        ['Upcoming Events',$stats['events'],        url('admin/events')],
    ] as [$label, $val, $link]): ?>
        <a href="<?= $link ?>" class="bg-white rounded-xl border border-gray-200 p-5 hover:border-indigo-300 transition-colors text-center">
            <p class="text-3xl font-bold text-indigo-600"><?= $val ?></p>
            <p class="text-sm text-gray-500 mt-1"><?= $label ?></p>
        </a>
    <?php endforeach; ?>
</div>

<!-- Admin nav -->
<div class="flex gap-3 mb-8 flex-wrap">
    <a href="<?= url('admin/courses') ?>" class="text-sm bg-white border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">Courses</a>
    <a href="<?= url('admin/forum') ?>" class="text-sm bg-white border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">Forum categories</a>
    <a href="<?= url('admin/events') ?>" class="text-sm bg-white border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">Events</a>
    <a href="<?= url('admin/users') ?>" class="text-sm bg-white border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">Users</a>
    <a href="<?= url('tickets') ?>" class="text-sm bg-white border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">All tickets</a>
</div>

<!-- Open tickets -->
<h2 class="text-lg font-semibold mb-3">Open Tickets</h2>
<?php if (empty($openTickets)): ?>
    <p class="text-sm text-gray-400">No open tickets.</p>
<?php else: ?>
<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php foreach ($openTickets as $t): ?>
        <a href="<?= url("tickets/{$t['id']}") ?>"
           class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 transition-colors block">
            <span class="text-xs <?= $t['status'] === 'open' ? 'bg-yellow-50 text-yellow-700' : 'bg-blue-50 text-blue-700' ?> px-2 py-0.5 rounded-full shrink-0 capitalize">
                <?= h($t['status']) ?>
            </span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate"><?= h($t['subject']) ?></p>
                <p class="text-xs text-gray-400"><?= h($t['first_name'] . ' ' . $t['last_name']) ?></p>
            </div>
            <span class="text-xs text-gray-400 shrink-0"><?= timeAgo($t['updated_at']) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
