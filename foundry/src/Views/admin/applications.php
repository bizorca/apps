<?php
$title = 'Applications — Bizorca Admin';
ob_start();

$statusColors = [
    'pending'    => 'bg-amber-100 text-amber-700',
    'accepted'   => 'bg-emerald-100 text-emerald-700',
    'declined'   => 'bg-red-100 text-red-700',
    'waitlisted' => 'bg-blue-100 text-blue-700',
];
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4 sticky top-0 z-40">
    <div class="max-w-6xl mx-auto flex items-center gap-6">
        <a href="<?= u('/') ?>" class="text-white font-semibold text-sm">Bizorca <span class="text-brand-500">Admin</span></a>
        <a href="<?= u('/admin') ?>" class="text-slate-400 hover:text-white text-sm transition-colors">Dashboard</a>
        <a href="<?= u('/admin/applications') ?>" class="text-white font-medium text-sm">Applications</a>
        <a href="<?= u('/admin/engagements') ?>" class="text-slate-400 hover:text-white text-sm transition-colors">Engagements</a>
    </div>
</nav>

<div class="min-h-screen bg-slate-50">
    <div class="max-w-6xl mx-auto px-6 py-10">
        <h1 class="text-2xl font-bold text-slate-900 mb-8">Applications</h1>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Applicant</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Company</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Revenue</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Budget</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($applications as $app): ?>
                    <?php $sc = $statusColors[$app['status']] ?? 'bg-slate-100 text-slate-600'; ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900"><?= h($app['first_name'] . ' ' . $app['last_name']) ?></div>
                            <div class="text-xs text-slate-400"><?= h($app['email']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-slate-700"><?= h($app['company_name']) ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= h($app['revenue_range']) ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= h($app['budget_range']) ?></td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-medium px-2.5 py-1 rounded-full <?= $sc ?>"><?= ucfirst($app['status']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400"><?= time_ago($app['created_at']) ?></td>
                        <td class="px-4 py-3">
                            <a href="<?= u('/admin/applications/' . ($app['id'])) ?>" class="text-brand-600 hover:text-brand-700 text-xs font-medium">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($applications)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-sm">No applications yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
