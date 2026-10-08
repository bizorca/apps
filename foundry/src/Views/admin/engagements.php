<?php
$title = 'Engagements — Bizorca Admin';
ob_start();

$statusColors = [
    'onboarding' => 'bg-violet-100 text-violet-700',
    'active'     => 'bg-emerald-100 text-emerald-700',
    'paused'     => 'bg-amber-100 text-amber-700',
    'complete'   => 'bg-slate-100 text-slate-600',
    'cancelled'  => 'bg-red-100 text-red-700',
];
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4 sticky top-0 z-40">
    <div class="max-w-6xl mx-auto flex items-center gap-6">
        <a href="<?= u('/') ?>" class="text-white font-semibold text-sm">Bizorca <span class="text-brand-500">Admin</span></a>
        <a href="<?= u('/admin') ?>" class="text-slate-400 hover:text-white text-sm transition-colors">Dashboard</a>
        <a href="<?= u('/admin/applications') ?>" class="text-slate-400 hover:text-white text-sm transition-colors">Applications</a>
        <a href="<?= u('/admin/engagements') ?>" class="text-white font-medium text-sm">Engagements</a>
    </div>
</nav>

<div class="min-h-screen bg-slate-50">
    <div class="max-w-6xl mx-auto px-6 py-10">
        <h1 class="text-2xl font-bold text-slate-900 mb-8">Engagements</h1>

        <div class="space-y-4">
            <?php foreach ($engagements as $engagement): ?>
            <?php $sc = $statusColors[$engagement['status']] ?? 'bg-slate-100 text-slate-600'; ?>
            <a href="<?= u('/admin/engagements/' . ($engagement['id'])) ?>" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-300 hover:shadow-sm transition-all">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-slate-900"><?= h($engagement['title']) ?></h3>
                        <p class="text-sm text-slate-500 mt-0.5"><?= h($engagement['first_name'] . ' ' . $engagement['last_name']) ?> &middot; <?= h($engagement['email']) ?></p>
                        <p class="text-xs text-slate-400 mt-1">Created <?= time_ago($engagement['created_at']) ?></p>
                    </div>
                    <span class="flex-shrink-0 text-xs font-medium px-2.5 py-1 rounded-full <?= $sc ?>"><?= ucfirst($engagement['status']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>

            <?php if (empty($engagements)): ?>
            <div class="text-center py-12 text-slate-400 text-sm">No engagements yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
