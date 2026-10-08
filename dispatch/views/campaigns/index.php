<?php
use Dispatch\Core\View;
$title = 'My Campaigns';
// $campaigns: array of campaign rows + pending_count
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <p class="text-slate-500 text-sm mt-1"><?= count($campaigns) ?> campaign<?= count($campaigns) !== 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= $_base ?>/campaigns/create" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        New Campaign
    </a>
</div>

<?php if (empty($campaigns)): ?>
<div class="bg-white rounded-2xl border border-slate-200 p-16 text-center">
    <div class="text-6xl mb-4">📅</div>
    <h3 class="text-xl font-bold text-slate-900 mb-2">No campaigns yet</h3>
    <p class="text-slate-500 mb-8 max-w-sm mx-auto">Create your first campaign to start generating submission deadlines across your venue library.</p>
    <a href="<?= $_base ?>/campaigns/create" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-colors">
        Create Your First Campaign
    </a>
</div>
<?php else: ?>

<!-- Group by status -->
<?php
$active    = array_filter($campaigns, fn($c) => $c['status'] === 'active');
$completed = array_filter($campaigns, fn($c) => $c['status'] === 'completed');
$cancelled = array_filter($campaigns, fn($c) => $c['status'] === 'cancelled');
?>

<div class="space-y-3">
    <?php foreach ($active as $c):
        $eventPast = strtotime($c['event_date']) < strtotime('today');
    ?>
    <div class="bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-sm transition-all p-5 flex items-center gap-5">
        <!-- Date indicator -->
        <div class="flex-shrink-0 w-14 text-center">
            <div class="bg-indigo-50 rounded-xl p-2">
                <div class="text-xs font-bold text-indigo-400 uppercase"><?= date('M', strtotime($c['event_date'])) ?></div>
                <div class="text-2xl font-black text-indigo-700 leading-none"><?= date('j', strtotime($c['event_date'])) ?></div>
                <div class="text-xs text-indigo-400"><?= date('Y', strtotime($c['event_date'])) ?></div>
            </div>
        </div>

        <!-- Campaign info -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <a href="<?= $_base ?>/campaigns/<?= $c['id'] ?>" class="font-semibold text-slate-900 hover:text-indigo-600 transition-colors">
                    <?= View::e($c['name']) ?>
                </a>
                <?php if ($c['recurrence_type']): ?>
                <span class="bg-violet-100 text-violet-700 text-xs font-medium px-2 py-0.5 rounded-full">Recurring</span>
                <?php endif; ?>
                <?php if ($eventPast): ?>
                <span class="bg-slate-100 text-slate-500 text-xs font-medium px-2 py-0.5 rounded-full">Past</span>
                <?php endif; ?>
            </div>
            <?php if ($c['location']): ?>
            <p class="text-sm text-slate-500 mt-0.5">📍 <?= View::e($c['location']) ?></p>
            <?php endif; ?>
            <div class="flex items-center gap-4 mt-2">
                <?php if ($c['pending_count'] > 0): ?>
                <span class="text-xs text-amber-600 font-medium bg-amber-50 px-2 py-0.5 rounded-full">
                    <?= $c['pending_count'] ?> task<?= $c['pending_count'] !== 1 ? 's' : '' ?> pending
                </span>
                <?php else: ?>
                <span class="text-xs text-emerald-600 font-medium">All tasks done</span>
                <?php endif; ?>
                <span class="text-xs text-slate-400"><?= View::relativeDate($c['event_date']) ?></span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 flex-shrink-0">
            <a href="<?= $_base ?>/campaigns/<?= $c['id'] ?>" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="View">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            </a>
            <a href="<?= $_base ?>/campaigns/<?= $c['id'] ?>/edit" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-lg transition-colors" title="Edit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($completed) || !empty($cancelled)): ?>
<details class="mt-6">
    <summary class="text-sm text-slate-500 cursor-pointer hover:text-slate-700 select-none">
        Show completed/cancelled (<?= count($completed) + count($cancelled) ?>)
    </summary>
    <div class="space-y-2 mt-3">
        <?php foreach (array_merge(array_values($completed), array_values($cancelled)) as $c): ?>
        <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 flex items-center gap-4 opacity-70">
            <div class="flex-1 min-w-0">
                <span class="font-medium text-slate-700"><?= View::e($c['name']) ?></span>
                <span class="ml-2 text-xs bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full"><?= $c['status'] ?></span>
                <p class="text-xs text-slate-400 mt-0.5"><?= View::date($c['event_date']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php endif; ?>
