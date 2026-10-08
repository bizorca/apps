<?php
use Dispatch\Core\View;
$title = 'Log Flyer Location';
// $campaigns: array of active campaigns
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

    <form method="POST" action="<?= $_base ?>/flyers" class="bg-white rounded-2xl border border-slate-200 p-6 space-y-5">
        <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Location Name <span class="text-red-500">*</span></label>
            <input type="text" name="location_name" required autofocus
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"
                placeholder="Port Townsend Food Co-op Bulletin Board">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Address <span class="text-slate-400 font-normal">(optional)</span></label>
            <input type="text" name="address"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"
                placeholder="414 Kearney St, Port Townsend WA">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date Posted</label>
                <input type="date" name="posted_at" value="<?= date('Y-m-d') ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Quantity</label>
                <input type="number" name="quantity" min="1" value="1"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
        </div>

        <?php if (!empty($campaigns)): ?>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Link to Campaign <span class="text-slate-400 font-normal">(optional)</span></label>
            <select name="campaign_id"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">— No campaign —</option>
                <?php foreach ($campaigns as $c): ?>
                <option value="<?= $c['id'] ?>"><?= View::e($c['name']) ?> (<?= View::date($c['event_date']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Notes <span class="text-slate-400 font-normal">(optional)</span></label>
            <textarea name="notes" rows="3"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                placeholder="e.g. 'Needs approval from staff', 'Check monthly'"></textarea>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="<?= $_base ?>/flyers" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
            <button type="submit" class="px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                Log Location
            </button>
        </div>
    </form>
</div>
