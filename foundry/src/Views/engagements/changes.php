<?php
$title = 'Change Requests — ' . h($engagement['title']);
ob_start();
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4">
    <div class="max-w-3xl mx-auto flex items-center justify-between">
        <a href="<?= u('/engagements/' . ($engagement['id'])) ?>" class="text-slate-500 hover:text-white flex items-center gap-2 text-sm transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to Board
        </a>
        <span class="text-white font-semibold text-sm">Bizorca <span class="text-brand-500">Consulting</span></span>
    </div>
</nav>

<div class="min-h-screen bg-slate-50 py-12 px-6">
    <div class="max-w-3xl mx-auto">

        <div class="mb-8">
            <div class="text-brand-600 text-xs font-semibold uppercase tracking-widest mb-2">Change Requests</div>
            <h1 class="text-3xl font-bold text-slate-900 mb-2"><?= h($engagement['title']) ?></h1>
            <p class="text-slate-500 text-sm">Anything outside the original scope goes through here first. No change is free — each one gets reviewed before any work starts.</p>
        </div>

        <!-- Submit new change request -->
        <?php if ($engagement['status'] === 'active' && $engagement['scope_locked_at']): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-8" x-data="{ open: false }">
            <button @click="open = !open" class="flex items-center gap-2 text-sm font-semibold text-slate-900 hover:text-brand-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Request a scope change
            </button>

            <form method="POST" action="<?= u('/engagements/' . ($engagement['id']) . '/changes') ?>" x-show="open" x-cloak class="mt-6 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">What do you want to add or change? <span class="text-red-500">*</span></label>
                    <input type="text" name="title" placeholder="Brief title" required
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Describe the change in detail <span class="text-red-500">*</span></label>
                    <textarea name="description" rows="4" required
                              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Why is this needed?</label>
                    <textarea name="justification" rows="2" placeholder="Optional — helps with the review"
                              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                </div>
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                    Submit Change Request
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Change request list -->
        <?php if (empty($changes)): ?>
        <div class="text-center py-12 text-slate-400">
            <p>No change requests yet.</p>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php
            $statusColors = [
                'pending'  => 'bg-amber-100 text-amber-700',
                'approved' => 'bg-emerald-100 text-emerald-700',
                'declined' => 'bg-red-100 text-red-700',
            ];
            foreach ($changes as $cr):
            $sc = $statusColors[$cr['status']] ?? 'bg-slate-100 text-slate-600';
            ?>
            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <div class="flex items-start justify-between gap-4 mb-2">
                    <h3 class="font-semibold text-slate-900"><?= h($cr['title']) ?></h3>
                    <span class="flex-shrink-0 text-xs font-medium px-2.5 py-1 rounded-full <?= $sc ?>">
                        <?= ucfirst($cr['status']) ?>
                    </span>
                </div>
                <p class="text-slate-600 text-sm mb-2"><?= nl2br(h($cr['description'])) ?></p>
                <?php if ($cr['justification']): ?>
                <p class="text-slate-400 text-xs italic mb-2"><?= h($cr['justification']) ?></p>
                <?php endif; ?>
                <div class="text-xs text-slate-400">
                    Submitted by <?= h($cr['first_name'] . ' ' . $cr['last_name']) ?> &middot; <?= time_ago($cr['created_at']) ?>
                </div>
                <?php if ($cr['review_note']): ?>
                <div class="mt-3 p-3 bg-slate-50 rounded-lg text-xs text-slate-600">
                    <span class="font-medium">Review note:</span> <?= h($cr['review_note']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
