<?php $pageTitle = 'Member Balances'; ?>
<?php $currencyName = $tenant['currency_name'] ?? 'Hour'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="<?= url('/admin/reports') ?>" class="hover:text-teal-600 transition-colors">Reports</a>
            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900">Member Balances</h1>
        <p class="text-sm text-gray-500 mt-1"><?= count($members ?? []) ?> members &mdash; generated <?= date('F j, Y') ?></p>
    </div>
    <a href="<?= url('/admin/reports/member-balances?export=csv') ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-xl hover:bg-gray-900 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Export CSV
    </a>
</div>

<?php if (!empty($members)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Member</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Balance (<?= e($currencyName) ?>s)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($members as $i => $m): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 text-xs text-gray-400"><?= $i + 1 ?></td>
                        <td class="px-5 py-3">
                            <a href="<?= url('/admin/members/' . ($m['id'] ?? '')) ?>"
                               class="font-medium text-gray-800 hover:text-teal-700 transition-colors text-sm">
                                <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                            </a>
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-sm"><?= e($m['email'] ?? '') ?></td>
                        <td class="px-5 py-3 text-right font-semibold <?= (float)($m['balance'] ?? 0) >= 0 ? 'text-teal-600' : 'text-red-500' ?> text-sm">
                            <?= number_format((float)($m['balance'] ?? 0), 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                    <td colspan="3" class="px-5 py-3 text-sm text-gray-700">Total pool balance</td>
                    <td class="px-5 py-3 text-right text-sm text-teal-600">
                        <?= number_format(array_sum(array_column($members, 'balance')), 2) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No member data available.</p>
    </div>
<?php endif; ?>
