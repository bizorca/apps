<?php
use Dispatch\Core\View;
$title = 'Edit Venue';
// $venue, $types, $methods
$assetReqs = json_decode($venue['asset_requirements'] ?? '[]', true) ?: [];
?>
<div class="max-w-2xl">
    <div class="mb-6">
        <a href="<?= $_base ?>/admin/venues" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Venues
        </a>
    </div>

    <form method="POST" action="<?= $_base ?>/admin/venues/<?= $venue['id'] ?>" class="bg-white rounded-2xl border border-slate-200 p-6 space-y-5">
        <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">

        <div class="grid md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Venue Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required value="<?= View::e($venue['name']) ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Type</label>
                <select name="type" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($types as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $venue['type'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Submission Method</label>
                <select name="submission_method" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($methods as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $venue['submission_method'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Submission URL</label>
                <input type="url" name="submission_url" value="<?= View::e($venue['submission_url'] ?? '') ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Submission Email</label>
                <input type="email" name="submission_email" value="<?= View::e($venue['submission_email'] ?? '') ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Lead Time (days)</label>
                <input type="number" name="lead_time_days" min="0" value="<?= $venue['lead_time_days'] ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Buffer Days</label>
                <input type="number" name="buffer_days" min="0" value="<?= $venue['buffer_days'] ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>
        </div>

        <!-- Asset Requirements -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <label class="block text-sm font-semibold text-slate-700">Asset Requirements</label>
                <button type="button" onclick="addAsset()" class="text-xs text-indigo-600 font-medium">+ Add Asset</button>
            </div>
            <div id="asset-list" class="space-y-2">
                <?php if (!empty($assetReqs)): ?>
                    <?php foreach ($assetReqs as $asset): ?>
                    <div class="grid grid-cols-2 gap-2 asset-row">
                        <input type="text" name="asset_types[]" value="<?= View::e($asset['type'] ?? '') ?>" placeholder="Type" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <input type="text" name="asset_specs[]" value="<?= View::e($asset['specs'] ?? '') ?>" placeholder="Specs" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="grid grid-cols-2 gap-2 asset-row">
                    <input type="text" name="asset_types[]" placeholder="Type" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                    <input type="text" name="asset_specs[]" placeholder="Specs" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Notes</label>
            <textarea name="notes" rows="3"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?= View::e($venue['notes'] ?? '') ?></textarea>
        </div>

        <!-- Contact Info -->
        <div class="border-t border-slate-100 pt-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Contact Info <span class="text-slate-400 font-normal">(optional)</span></h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Contact Name</label>
                    <input type="text" name="contact_name" value="<?= View::e($venue['contact_name'] ?? '') ?>"
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"
                        placeholder="Jane Smith">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Contact Email</label>
                    <input type="email" name="contact_email" value="<?= View::e($venue['contact_email'] ?? '') ?>"
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"
                        placeholder="events@venue.com">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Contact Notes</label>
                    <textarea name="contact_notes" rows="2"
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?= View::e($venue['contact_notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= $venue['is_active'] ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 border-slate-300 rounded">
            <label for="is_active" class="text-sm font-medium text-slate-700">Active</label>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="<?= $_base ?>/admin/venues" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
            <button type="submit" class="px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                Save Changes
            </button>
        </div>
    </form>
</div>
<script>
function addAsset() {
    const list = document.getElementById('asset-list');
    const div = document.createElement('div');
    div.className = 'grid grid-cols-2 gap-2 asset-row';
    div.innerHTML = `<input type="text" name="asset_types[]" placeholder="Type" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400"><input type="text" name="asset_specs[]" placeholder="Specs" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">`;
    list.appendChild(div);
}
</script>
