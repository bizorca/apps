<?php
use Dispatch\Core\View;
$title = 'Suggest a Venue';
// $types: type label map
?>
<div class="max-w-2xl">
    <div class="mb-6">
        <a href="<?= $_base ?>/venues" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Venue Library
        </a>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-8">
        <div class="flex gap-3">
            <span class="text-xl">💡</span>
            <div>
                <p class="font-semibold text-amber-900 text-sm">Community-driven library</p>
                <p class="text-amber-800 text-sm mt-1 leading-relaxed">
                    Venue submissions are reviewed by our SysOp before being added to the library. Please provide as much detail as possible so we can verify the submission requirements accurately.
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="<?= $_base ?>/venues/suggest" class="bg-white rounded-2xl border border-slate-200 p-6 space-y-5">
        <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Venue Name <span class="text-red-500">*</span></label>
            <input type="text" name="venue_name" required autofocus
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                placeholder="Port Townsend Farmers Market Newsletter">
        </div>

        <div class="grid md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Type</label>
                <select name="venue_type"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                    <option value="">— Select type —</option>
                    <?php foreach ($types as $key => $label): ?>
                    <option value="<?= $key ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Submission URL</label>
                <input type="url" name="submission_url"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                    placeholder="https://...">
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Submission Email <span class="text-slate-400 font-normal">(if no web form)</span></label>
            <input type="email" name="submission_email"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                placeholder="calendar@example.com">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Estimated Lead Time</label>
            <input type="text" name="perceived_lead_time"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                placeholder="e.g. 'They need it about 2 weeks in advance' or '7–10 days'">
            <p class="text-xs text-slate-400 mt-1">Your best estimate — the SysOp will verify the actual requirement.</p>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Asset / Format Requirements</label>
            <textarea name="asset_requirements" rows="3"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm resize-none"
                placeholder="e.g. '50-word description, square image (1080x1080px), contact info required'"></textarea>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Why Should This Be Added? <span class="text-red-500">*</span></label>
            <textarea name="justification" rows="3" required
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm resize-none"
                placeholder="e.g. 'This newsletter reaches ~500 subscribers in the PT area. It's the main calendar for farmers market vendors.'"></textarea>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="<?= $_base ?>/venues" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
            <button type="submit" class="px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                Submit for Review
            </button>
        </div>
    </form>
</div>
