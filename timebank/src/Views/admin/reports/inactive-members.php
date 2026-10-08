<?php $pageTitle = 'Inactive Members'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="<?= url('/admin/reports') ?>" class="hover:text-teal-600 transition-colors">Reports</a>
            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900">Inactive Members</h1>
        <p class="text-sm text-gray-500 mt-1">
            Members with zero transactions in the last 90 days
            <?php if (!empty($members)): ?>
                &mdash; <?= count($members) ?> found
            <?php endif; ?>
        </p>
    </div>
    <?php if (!empty($members)): ?>
        <a href="<?= url('/admin/reports/inactive-members?export=csv') ?>"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-xl hover:bg-gray-900 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export CSV
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($members)): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-sm text-amber-800">
        These members haven't recorded any transactions in the past 90 days. Consider reaching out with a friendly reminder or checking in on how they're doing.
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Member</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Joined</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Last Login</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Balance</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($members as $m): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="<?= url('/admin/members/' . ($m['id'] ?? '')) ?>"
                               class="font-medium text-gray-800 hover:text-teal-700 transition-colors">
                                <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                            </a>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500"><?= e($m['email'] ?? '') ?></td>
                        <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap text-xs"><?= e(date('M j, Y', strtotime($m['created_at'] ?? ''))) ?></td>
                        <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap text-xs">
                            <?= !empty($m['last_login_at']) ? e(date('M j, Y', strtotime($m['last_login_at']))) : 'Never' ?>
                        </td>
                        <td class="px-5 py-3.5 text-right font-medium <?= (float)($m['balance'] ?? 0) >= 0 ? 'text-gray-700' : 'text-red-500' ?>">
                            <?= number_format((float)($m['balance'] ?? 0), 2) ?>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="<?= url('/messages/compose?to=' . ($m['id'] ?? '')) ?>"
                               class="text-xs text-teal-600 hover:text-teal-800 font-medium transition-colors">Message</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <div class="w-16 h-16 rounded-full bg-teal-50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-base font-semibold text-gray-700 mb-1">Everyone's active</h3>
        <p class="text-sm text-gray-400">No members have been idle for 90+ days. Keep it up.</p>
    </div>
<?php endif; ?>
