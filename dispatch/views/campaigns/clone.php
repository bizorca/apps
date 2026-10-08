<?php
use Dispatch\Core\View;
$title = 'Clone Campaign';
// $campaign: the source campaign being cloned
?>
<div class="max-w-lg">
    <div class="mb-6">
        <a href="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Campaign
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <h1 class="text-xl font-bold text-slate-900 mb-1">Clone Campaign</h1>
        <p class="text-sm text-slate-500 mb-6">Creates a new campaign with the same name, venues, and assets — but a fresh action plan for the new date.</p>

        <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 mb-6">
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Cloning from</p>
            <p class="text-sm font-semibold text-slate-900"><?= View::e($campaign['name']) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">Original event: <?= View::date($campaign['event_date'], 'F j, Y') ?></p>
        </div>

        <form method="POST" action="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>/clone">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">New Event Date <span class="text-red-500">*</span></label>
                <input type="date" name="new_event_date" required min="<?= date('Y-m-d') ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm">
            </div>

            <div class="flex items-center justify-between">
                <a href="<?= $_base ?>/campaigns/<?= $campaign['id'] ?>" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
                <button type="submit" class="px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm">
                    Clone Campaign
                </button>
            </div>
        </form>
    </div>
</div>
