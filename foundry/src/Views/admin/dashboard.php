<?php
$title = 'Admin — Bizorca Consulting';
ob_start();
?>

<nav class="bg-slate-950 border-b border-white/5 px-6 py-4 sticky top-0 z-40">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-6">
            <span class="text-white font-semibold text-sm">Bizorca <span class="text-brand-500">Admin</span></span>
            <nav class="hidden md:flex gap-5 text-sm">
                <a href="<?= u('/admin') ?>" class="text-white font-medium">Dashboard</a>
                <a href="<?= u('/admin/applications') ?>" class="text-slate-400 hover:text-white transition-colors">Applications</a>
                <a href="<?= u('/admin/engagements') ?>" class="text-slate-400 hover:text-white transition-colors">Engagements</a>
            </nav>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <span class="text-slate-400"><?= h($user['first_name']) ?></span>
            <form method="POST" action="<?= u('/logout') ?>" class="inline">
                <?= csrf_field() ?>
                <button class="text-slate-500 hover:text-white transition-colors">Sign out</button>
            </form>
        </div>
    </div>
</nav>

<div class="min-h-screen bg-slate-50">
    <div class="max-w-6xl mx-auto px-6 py-10">
        <h1 class="text-2xl font-bold text-slate-900 mb-8">Dashboard</h1>

        <!-- Summary cards -->
        <div class="grid sm:grid-cols-3 gap-5 mb-10">
            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <div class="text-3xl font-black text-slate-900 mb-1"><?= count($pendingApps) ?></div>
                <div class="text-sm text-slate-500">Pending Applications</div>
                <a href="<?= u('/admin/applications') ?>" class="text-xs text-brand-600 hover:text-brand-700 mt-2 inline-block">Review &rarr;</a>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <div class="text-3xl font-black text-slate-900 mb-1">
                    <?= count(array_filter($engagements, fn($e) => $e['status'] === 'active')) ?>
                </div>
                <div class="text-sm text-slate-500">Active Engagements</div>
                <a href="<?= u('/admin/engagements') ?>" class="text-xs text-brand-600 hover:text-brand-700 mt-2 inline-block">View all &rarr;</a>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <div class="text-3xl font-black text-slate-900 mb-1"><?= count($pendingChanges) ?></div>
                <div class="text-sm text-slate-500">Pending Change Requests</div>
            </div>
        </div>

        <!-- Pending applications -->
        <?php if (!empty($pendingApps)): ?>
        <div class="mb-10">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Pending Applications</h2>
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Company</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Revenue</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Budget</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Received</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($pendingApps as $app): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900"><?= h($app['first_name'] . ' ' . $app['last_name']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= h($app['company_name']) ?></td>
                            <td class="px-4 py-3 text-slate-500"><?= h($app['revenue_range']) ?></td>
                            <td class="px-4 py-3 text-slate-500"><?= h($app['budget_range']) ?></td>
                            <td class="px-4 py-3 text-slate-400 text-xs"><?= time_ago($app['created_at']) ?></td>
                            <td class="px-4 py-3">
                                <a href="<?= u('/admin/applications/' . ($app['id'])) ?>" class="text-brand-600 hover:text-brand-700 text-xs font-medium">Review</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pending change requests -->
        <?php if (!empty($pendingChanges)): ?>
        <div>
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Pending Change Requests</h2>
            <div class="space-y-3">
                <?php foreach ($pendingChanges as $cr): ?>
                <div class="bg-white border border-amber-200 rounded-xl p-4 flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs text-slate-400 mb-1"><?= h($cr['engagement_title']) ?> &middot; <?= h($cr['first_name'] . ' ' . $cr['last_name']) ?></div>
                        <div class="font-medium text-slate-900"><?= h($cr['title']) ?></div>
                        <p class="text-sm text-slate-500 mt-1"><?= h(mb_substr($cr['description'], 0, 120)) ?><?= mb_strlen($cr['description']) > 120 ? '…' : '' ?></p>
                    </div>
                    <a href="<?= u('/admin/engagements/' . ($cr['engagement_id'])) ?>" class="flex-shrink-0 text-xs text-brand-600 hover:text-brand-700 font-medium">Review &rarr;</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
