<?php
use Dispatch\Core\View;
$title = 'Flyer Tracker';
// $active: array of active flyer locations
// $removed: array of removed/inactive locations
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <p class="text-slate-500 text-sm mt-1"><?= count($active) ?> active location<?= count($active) !== 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= $_base ?>/flyers/create" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Log Location
    </a>
</div>

<?php if (empty($active) && empty($removed)): ?>
<div class="bg-white rounded-2xl border border-slate-200 p-16 text-center">
    <div class="text-6xl mb-4">📌</div>
    <h3 class="text-xl font-bold text-slate-900 mb-2">No flyer locations yet</h3>
    <p class="text-slate-500 mb-8 max-w-sm mx-auto">Track every bulletin board, coffee shop, and community center where you've posted flyers.</p>
    <a href="<?= $_base ?>/flyers/create" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-colors">
        Log Your First Location
    </a>
</div>
<?php else: ?>

<!-- Active Locations -->
<?php if (!empty($active)): ?>
<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
        <span class="w-3 h-3 bg-emerald-400 rounded-full"></span>
        <h2 class="font-semibold text-slate-900">Active Locations</h2>
        <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($active) ?></span>
    </div>
    <div class="divide-y divide-slate-50">
        <?php foreach ($active as $flyer): ?>
        <div class="px-6 py-4 flex items-start gap-4">
            <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900"><?= View::e($flyer['location_name']) ?></p>
                        <?php if ($flyer['address']): ?>
                        <p class="text-sm text-slate-500 mt-0.5">📍 <?= View::e($flyer['address']) ?></p>
                        <?php endif; ?>
                        <div class="flex items-center gap-4 mt-2 flex-wrap">
                            <?php if ($flyer['posted_at']): ?>
                            <span class="text-xs text-slate-500">
                                Posted: <strong><?= View::date($flyer['posted_at'], 'M j, Y') ?></strong>
                            </span>
                            <?php endif; ?>
                            <span class="text-xs text-slate-500">
                                Quantity: <strong><?= $flyer['quantity'] ?></strong>
                            </span>
                            <?php if ($flyer['campaign_name']): ?>
                            <span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full">
                                <?= View::e($flyer['campaign_name']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($flyer['notes']): ?>
                        <p class="text-xs text-slate-400 mt-1.5 italic"><?= View::e($flyer['notes']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <a href="<?= $_base ?>/flyers/<?= $flyer['id'] ?>/edit" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                        <form method="POST" action="<?= $_base ?>/flyers/<?= $flyer['id'] ?>/delete" onsubmit="return confirm('Remove this flyer location?')">
                            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                            <button type="submit" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Removed Locations -->
<?php if (!empty($removed)): ?>
<details>
    <summary class="text-sm text-slate-500 cursor-pointer hover:text-slate-700 mb-3 select-none">
        Show removed locations (<?= count($removed) ?>)
    </summary>
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mt-3">
        <div class="divide-y divide-slate-50">
            <?php foreach ($removed as $flyer): ?>
            <div class="px-6 py-4 flex items-center gap-4 opacity-60">
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-slate-700 line-through"><?= View::e($flyer['location_name']) ?></p>
                    <div class="flex items-center gap-3 mt-1">
                        <?php if ($flyer['removed_at']): ?>
                        <span class="text-xs text-slate-400">Removed: <?= View::date($flyer['removed_at']) ?></span>
                        <?php endif; ?>
                        <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= $flyer['status'] ?></span>
                    </div>
                </div>
                <a href="<?= $_base ?>/flyers/<?= $flyer['id'] ?>/edit" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Restore</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</details>
<?php endif; ?>

<?php endif; ?>
