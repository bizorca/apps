<?php
use Dispatch\Core\View;
$title = 'Admin Dashboard';
// $stats: ['users'=>int, 'venues'=>int, 'campaigns'=>int, 'pending_submissions'=>int]
// $recentUsers: array
// $pendingSubmissions: array
?>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php
    $statCards = [
        ['label' => 'Total Users',    'value' => $stats['users'],    'icon' => '👥', 'href' => '/admin/users',   'color' => 'indigo'],
        ['label' => 'Active Venues',  'value' => $stats['venues'],   'icon' => '🏛️', 'href' => '/admin/venues',  'color' => 'violet'],
        ['label' => 'Campaigns',      'value' => $stats['campaigns'],'icon' => '📅', 'href' => '/campaigns',     'color' => 'blue'],
        ['label' => 'Pending Review', 'value' => $stats['pending_submissions'], 'icon' => '📥', 'href' => '/admin/submissions', 'color' => $stats['pending_submissions'] > 0 ? 'amber' : 'slate'],
    ];
    foreach ($statCards as $card):
    ?>
    <a href="<?= $_base ?><?= $card['href'] ?>" class="bg-white rounded-2xl border border-slate-200 p-5 hover:border-indigo-200 hover:shadow-sm transition-all block">
        <div class="flex items-center justify-between mb-3">
            <span class="text-2xl"><?= $card['icon'] ?></span>
            <?php if ($card['value'] > 0 && $card['label'] === 'Pending Review'): ?>
            <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-0.5 rounded-full">Needs attention</span>
            <?php endif; ?>
        </div>
        <div class="text-3xl font-bold text-slate-900"><?= $card['value'] ?></div>
        <div class="text-xs text-slate-500 mt-1"><?= $card['label'] ?></div>
    </a>
    <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <!-- Pending Submissions -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-900">Pending Venue Submissions</h2>
            <a href="<?= $_base ?>/admin/submissions" class="text-xs text-indigo-600 font-medium hover:text-indigo-700">View All →</a>
        </div>
        <?php if (empty($pendingSubmissions)): ?>
        <div class="p-8 text-center text-slate-400 text-sm">No pending submissions</div>
        <?php else: ?>
        <div class="divide-y divide-slate-50">
            <?php foreach (array_slice($pendingSubmissions, 0, 5) as $s): ?>
            <div class="px-6 py-3.5 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900 truncate"><?= View::e($s['venue_name']) ?></p>
                    <p class="text-xs text-slate-400">by <?= View::e($s['submitter_name']) ?> · <?= View::date($s['created_at']) ?></p>
                </div>
                <a href="<?= $_base ?>/admin/submissions/<?= $s['id'] ?>" class="flex-shrink-0 text-xs font-semibold text-indigo-600 hover:text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-lg">
                    Review
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Users -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-900">Recent Signups</h2>
            <a href="<?= $_base ?>/admin/users" class="text-xs text-indigo-600 font-medium hover:text-indigo-700">View All →</a>
        </div>
        <?php if (empty($recentUsers)): ?>
        <div class="p-8 text-center text-slate-400 text-sm">No users yet</div>
        <?php else: ?>
        <div class="divide-y divide-slate-50">
            <?php foreach ($recentUsers as $u): ?>
            <div class="px-6 py-3.5 flex items-center gap-3">
                <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-indigo-700 font-semibold text-xs"><?= strtoupper(substr($u['name'], 0, 1)) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-900 truncate"><?= View::e($u['name']) ?></p>
                    <p class="text-xs text-slate-400 truncate"><?= View::e($u['email']) ?></p>
                </div>
                <span class="flex-shrink-0 text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full"><?= $u['role'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick links -->
<div class="mt-8 grid grid-cols-3 gap-4">
    <a href="<?= $_base ?>/admin/venues/create" class="flex items-center gap-3 p-4 bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-sm transition-all">
        <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-xl">🏛️</div>
        <div>
            <p class="font-semibold text-slate-900 text-sm">Add Venue</p>
            <p class="text-xs text-slate-400">Add to library</p>
        </div>
    </a>
    <a href="<?= $_base ?>/admin/submissions" class="flex items-center gap-3 p-4 bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-sm transition-all">
        <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-xl">📥</div>
        <div>
            <p class="font-semibold text-slate-900 text-sm">Triage Queue</p>
            <p class="text-xs text-slate-400"><?= $stats['pending_submissions'] ?> pending</p>
        </div>
    </a>
    <a href="<?= $_base ?>/admin/users" class="flex items-center gap-3 p-4 bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-sm transition-all">
        <div class="w-10 h-10 bg-violet-50 rounded-xl flex items-center justify-center text-xl">👥</div>
        <div>
            <p class="font-semibold text-slate-900 text-sm">Manage Users</p>
            <p class="text-xs text-slate-400"><?= $stats['users'] ?> total</p>
        </div>
    </a>
</div>
