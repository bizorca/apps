<?php
use Dispatch\Core\View;
use Dispatch\Core\Auth;
$title = 'Manage Venues';
// $venues, $types
$isSysOp = Auth::isSysOp();
?>
<div class="flex items-center justify-between mb-6">
    <p class="text-slate-500 text-sm"><?= count($venues) ?> venues total</p>
    <a href="<?= $_base ?>/admin/venues/create" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors">
        + Add Venue
    </a>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">Venue</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">Type</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">Lead Time</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">Status</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($venues as $v): ?>
                <tr class="hover:bg-slate-50 transition-colors <?= !$v['is_active'] ? 'opacity-50' : '' ?>">
                    <td class="px-6 py-4">
                        <p class="font-medium text-slate-900 text-sm"><?= View::e($v['name']) ?></p>
                        <?php if ($v['submission_url']): ?>
                        <a href="<?= View::e($v['submission_url']) ?>" target="_blank" class="text-xs text-indigo-500 hover:text-indigo-600 truncate block max-w-xs">
                            <?= View::e(parse_url($v['submission_url'], PHP_URL_HOST) ?? $v['submission_url']) ?>
                        </a>
                        <?php elseif ($v['submission_email']): ?>
                        <p class="text-xs text-slate-400"><?= View::e($v['submission_email']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-medium">
                            <?= View::e($types[$v['type']] ?? $v['type']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-700">
                        <?= $v['lead_time_days'] ?> days
                        <span class="text-xs text-slate-400">(+<?= $v['buffer_days'] ?> buffer)</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= $v['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                            <?= $v['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="<?= $_base ?>/admin/venues/<?= $v['id'] ?>/edit" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-lg">Edit</a>
                            <?php if ($isSysOp): ?>
                            <form method="POST" action="<?= $_base ?>/admin/venues/<?= $v['id'] ?>/delete" onsubmit="return confirm('Delete this venue? This cannot be undone.')">
                                <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                                <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700 bg-red-50 px-3 py-1.5 rounded-lg">Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
