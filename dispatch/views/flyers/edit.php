<?php
use Dispatch\Core\View;
$title = 'Edit Flyer Location';
// $flyer, $campaigns
?>
<div class="max-w-xl">
    <div class="mb-6">
        <a href="<?= $_base ?>/flyers" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Flyer Tracker
        </a>
    </div>

    <form id="flyer-edit-form" method="POST" action="<?= $_base ?>/flyers/<?= $flyer['id'] ?>" class="bg-white rounded-2xl border border-slate-200 p-6 space-y-5">
        <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Location Name <span class="text-red-500">*</span></label>
            <input type="text" name="location_name" required value="<?= View::e($flyer['location_name']) ?>"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Address</label>
            <input type="text" name="address" value="<?= View::e($flyer['address'] ?? '') ?>"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date Posted</label>
                <input type="date" name="posted_at" value="<?= View::e($flyer['posted_at'] ?? '') ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date Removed</label>
                <input type="date" name="removed_at" value="<?= View::e($flyer['removed_at'] ?? '') ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Quantity</label>
                <input type="number" name="quantity" min="1" value="<?= $flyer['quantity'] ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Status</label>
                <select name="status"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="active"  <?= $flyer['status'] === 'active'  ? 'selected' : '' ?>>Active</option>
                    <option value="removed" <?= $flyer['status'] === 'removed' ? 'selected' : '' ?>>Removed</option>
                    <option value="unknown" <?= $flyer['status'] === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                </select>
            </div>
        </div>

        <?php if (!empty($campaigns)): ?>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Campaign</label>
            <select name="campaign_id"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">— No campaign —</option>
                <?php foreach ($campaigns as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (string)$flyer['campaign_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                    <?= View::e($c['name']) ?> (<?= View::date($c['event_date']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Notes</label>
            <textarea name="notes" rows="3"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?= View::e($flyer['notes'] ?? '') ?></textarea>
        </div>
    </form>

    <div class="flex items-center justify-between pt-4">
        <form method="POST" action="<?= $_base ?>/flyers/<?= $flyer['id'] ?>/delete" onsubmit="return confirm('Delete this flyer location?')">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete</button>
        </form>
        <div class="flex gap-3">
            <a href="<?= $_base ?>/flyers" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
            <button type="submit" form="flyer-edit-form" class="px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                Save Changes
            </button>
        </div>
    </div>
</div>
