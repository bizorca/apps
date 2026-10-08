<?php
$title = 'Application — ' . h($application['first_name'] . ' ' . $application['last_name']);
ob_start();
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4">
    <div class="max-w-4xl mx-auto flex items-center justify-between">
        <a href="<?= u('/admin/applications') ?>" class="text-slate-400 hover:text-white flex items-center gap-2 text-sm transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Applications
        </a>
        <span class="text-white font-semibold text-sm">Bizorca <span class="text-brand-500">Admin</span></span>
    </div>
</nav>

<div class="min-h-screen bg-slate-50 py-10 px-6">
    <div class="max-w-4xl mx-auto">

        <div class="flex items-start justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><?= h($application['first_name'] . ' ' . $application['last_name']) ?></h1>
                <p class="text-slate-500"><?= h($application['email']) ?> &middot; <?= h($application['company_name']) ?></p>
            </div>
            <?php
            $statusColors = ['pending' => 'bg-amber-100 text-amber-700', 'accepted' => 'bg-emerald-100 text-emerald-700', 'declined' => 'bg-red-100 text-red-700'];
            $sc = $statusColors[$application['status']] ?? 'bg-slate-100 text-slate-600';
            ?>
            <span class="text-sm font-semibold px-3 py-1.5 rounded-full <?= $sc ?>"><?= ucfirst($application['status']) ?></span>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <!-- Business info -->
            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Business</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Company</dt><dd class="font-medium text-slate-900"><?= h($application['company_name']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Type</dt><dd class="text-slate-700"><?= h($application['company_type']) ?></dd></div>
                    <?php if ($application['website']): ?>
                    <div class="flex justify-between"><dt class="text-slate-500">Website</dt><dd class="text-slate-700"><?= h($application['website']) ?></dd></div>
                    <?php endif; ?>
                    <div class="flex justify-between"><dt class="text-slate-500">Revenue</dt><dd class="text-slate-700"><?= h($application['revenue_range']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Team size</dt><dd class="text-slate-700"><?= h($application['employee_count']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Budget</dt><dd class="font-medium text-slate-900"><?= h($application['budget_range']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Timeline</dt><dd class="text-slate-700"><?= h($application['timeline']) ?></dd></div>
                </dl>
            </div>

            <!-- Application details -->
            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Details</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Submitted</dt><dd class="text-slate-700"><?= date('M j, Y', strtotime($application['created_at'])) ?></dd></div>
                    <?php if ($application['referral_source']): ?>
                    <div class="flex justify-between"><dt class="text-slate-500">Source</dt><dd class="text-slate-700"><?= h($application['referral_source']) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Core problem & outcome -->
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-6">
            <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">The Real Stuff</h2>
            <div class="space-y-5">
                <div>
                    <h3 class="text-xs font-semibold text-slate-400 uppercase mb-2">Core Problem</h3>
                    <p class="text-slate-700 leading-relaxed text-sm"><?= nl2br(h($application['core_problem'])) ?></p>
                </div>
                <div>
                    <h3 class="text-xs font-semibold text-slate-400 uppercase mb-2">Desired Outcome</h3>
                    <p class="text-slate-700 leading-relaxed text-sm"><?= nl2br(h($application['desired_outcome'])) ?></p>
                </div>
                <?php if ($application['notes']): ?>
                <div>
                    <h3 class="text-xs font-semibold text-slate-400 uppercase mb-2">Additional Notes</h3>
                    <p class="text-slate-700 leading-relaxed text-sm"><?= nl2br(h($application['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Admin notes (existing) -->
        <?php if ($application['admin_notes']): ?>
        <div class="bg-slate-100 border border-slate-200 rounded-xl p-4 mb-6 text-sm text-slate-600">
            <span class="font-semibold">Your notes:</span> <?= nl2br(h($application['admin_notes'])) ?>
        </div>
        <?php endif; ?>

        <!-- Action forms — only show if pending -->
        <?php if ($application['status'] === 'pending'): ?>
        <div class="grid md:grid-cols-2 gap-4">
            <form method="POST" action="<?= u('/admin/applications/' . ($application['id']) . '/accept') ?>" class="bg-white border border-emerald-200 rounded-xl p-5">
                <?= csrf_field() ?>
                <h3 class="font-semibold text-emerald-900 mb-3">Accept &amp; Create Engagement</h3>
                <textarea name="admin_notes" rows="3" placeholder="Notes (optional — for your reference)"
                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors">
                    Accept &amp; Start Onboarding
                </button>
            </form>

            <form method="POST" action="<?= u('/admin/applications/' . ($application['id']) . '/decline') ?>" class="bg-white border border-red-100 rounded-xl p-5">
                <?= csrf_field() ?>
                <h3 class="font-semibold text-slate-900 mb-3">Decline</h3>
                <textarea name="admin_notes" rows="3" placeholder="Reason (optional — for your reference)"
                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                <button type="submit" class="w-full bg-red-100 hover:bg-red-200 text-red-700 font-semibold py-2.5 rounded-lg text-sm transition-colors">
                    Decline Application
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
