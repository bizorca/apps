<?php
use Dispatch\Core\View;
$title = 'Review Submission';
// $submission, $types, $methods
$assetHints = $submission['asset_requirements'] ?? '';
?>
<div class="max-w-3xl">
    <div class="mb-6">
        <a href="<?= $_base ?>/admin/submissions" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Triage Queue
        </a>
    </div>

    <!-- Submission info card -->
    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Submission Details</h2>
        <dl class="grid md:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-xs font-semibold text-slate-400 uppercase">Submitted By</dt>
                <dd class="mt-1 text-slate-900"><?= View::e($submission['submitter_name']) ?> (<?= View::e($submission['submitter_email']) ?>)</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-slate-400 uppercase">Submitted</dt>
                <dd class="mt-1 text-slate-900"><?= View::date($submission['created_at'], 'F j, Y g:i A') ?></dd>
            </div>
            <?php if ($submission['submission_url']): ?>
            <div>
                <dt class="text-xs font-semibold text-slate-400 uppercase">URL Provided</dt>
                <dd class="mt-1"><a href="<?= View::e($submission['submission_url']) ?>" target="_blank" class="text-indigo-600 hover:text-indigo-700 break-all"><?= View::e($submission['submission_url']) ?></a></dd>
            </div>
            <?php endif; ?>
            <?php if ($submission['submission_email']): ?>
            <div>
                <dt class="text-xs font-semibold text-slate-400 uppercase">Email Provided</dt>
                <dd class="mt-1 text-slate-900"><?= View::e($submission['submission_email']) ?></dd>
            </div>
            <?php endif; ?>
            <?php if ($submission['perceived_lead_time']): ?>
            <div class="md:col-span-2">
                <dt class="text-xs font-semibold text-slate-400 uppercase">Stated Lead Time</dt>
                <dd class="mt-1 text-slate-700 italic">"<?= View::e($submission['perceived_lead_time']) ?>"</dd>
            </div>
            <?php endif; ?>
            <?php if ($submission['asset_requirements']): ?>
            <div class="md:col-span-2">
                <dt class="text-xs font-semibold text-slate-400 uppercase">Stated Requirements</dt>
                <dd class="mt-1 text-slate-700 italic">"<?= View::e($submission['asset_requirements']) ?>"</dd>
            </div>
            <?php endif; ?>
            <?php if ($submission['justification']): ?>
            <div class="md:col-span-2">
                <dt class="text-xs font-semibold text-slate-400 uppercase">Justification</dt>
                <dd class="mt-1 text-slate-700 leading-relaxed"><?= View::e($submission['justification']) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </div>

    <!-- Approve form -->
    <?php if (in_array($submission['status'], ['pending', 'under_review'])): ?>
    <div class="bg-white rounded-2xl border border-emerald-200 p-6 mb-4">
        <h3 class="font-semibold text-emerald-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            Approve &amp; Build Venue
        </h3>
        <p class="text-xs text-slate-500 mb-5">Fill in the verified details below. This creates the venue in the live library.</p>

        <form method="POST" action="<?= $_base ?>/admin/submissions/<?= $submission['id'] ?>/approve" class="space-y-4">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">

            <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Venue Name (verified)</label>
                    <input type="text" name="venue_name" value="<?= View::e($submission['venue_name']) ?>"
                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Type</label>
                    <select name="type" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <?php foreach ($types as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($submission['venue_type'] === $k) ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Submission Method</label>
                    <select name="submission_method" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <?php foreach ($methods as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Verified Lead Time (days)</label>
                    <input type="number" name="lead_time_days" value="7" min="0"
                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Buffer Days</label>
                    <input type="number" name="buffer_days" value="1" min="0"
                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Verified URL</label>
                    <input type="url" name="submission_url" value="<?= View::e($submission['submission_url'] ?? '') ?>"
                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Verified Email</label>
                    <input type="email" name="submission_email" value="<?= View::e($submission['submission_email'] ?? '') ?>"
                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <!-- Asset requirements builder -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-semibold text-slate-700">Asset Requirements</label>
                    <button type="button" onclick="addAsset()" class="text-xs text-emerald-600 font-medium">+ Add</button>
                </div>
                <div id="asset-list" class="space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="asset_types[]" placeholder="Type" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-400">
                        <input type="text" name="asset_specs[]" placeholder="Specs" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-400">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
                    placeholder="Submission notes visible to all users"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Admin Notes (private)</label>
                <input type="text" name="admin_notes"
                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    placeholder="Internal notes about this submission">
            </div>

            <button type="submit" class="w-full py-3 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 transition-colors">
                Approve &amp; Publish to Library
            </button>
        </form>
    </div>

    <!-- Mark under review -->
    <?php if ($submission['status'] === 'pending'): ?>
    <form method="POST" action="<?= $_base ?>/admin/submissions/<?= $submission['id'] ?>/review" class="bg-white rounded-2xl border border-blue-200 p-4 mb-4">
        <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm text-blue-700 font-medium">Mark as Under Review</p>
            <button type="submit" class="px-4 py-2 bg-blue-50 text-blue-600 text-xs font-semibold rounded-xl hover:bg-blue-100 transition-colors">
                Mark Under Review
            </button>
        </div>
    </form>
    <?php endif; ?>

    <!-- Reject form -->
    <div class="bg-white rounded-2xl border border-red-200 p-6">
        <h3 class="font-semibold text-red-700 mb-4">Reject Submission</h3>
        <form method="POST" action="<?= $_base ?>/admin/submissions/<?= $submission['id'] ?>/reject">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
            <div class="mb-3">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Reason (sent to submitter)</label>
                <input type="text" name="admin_notes" placeholder="e.g. Duplicate of existing venue, insufficient reach..."
                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>
            <button type="submit" class="w-full py-2.5 bg-red-50 text-red-600 font-semibold rounded-xl hover:bg-red-100 transition-colors text-sm">
                Reject Submission
            </button>
        </form>
    </div>
    <?php else: ?>
    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-5 text-center text-slate-500 text-sm">
        This submission has been <strong><?= $submission['status'] ?></strong>.
        <?php if ($submission['admin_notes']): ?>
        <p class="mt-2 italic text-xs">"<?= View::e($submission['admin_notes']) ?>"</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function addAsset() {
    const list = document.getElementById('asset-list');
    const div = document.createElement('div');
    div.className = 'grid grid-cols-2 gap-2';
    div.innerHTML = `<input type="text" name="asset_types[]" placeholder="Type" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-400"><input type="text" name="asset_specs[]" placeholder="Specs" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-400">`;
    list.appendChild(div);
}
</script>
