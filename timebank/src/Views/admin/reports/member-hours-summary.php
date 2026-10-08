<?php $pageTitle = 'Member Hours Summary'; ?>
<?php $currencyName = $tenant['currency_name'] ?? 'Hour'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="<?= url('/admin/reports') ?>" class="hover:text-teal-600 transition-colors">Reports</a>
            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900">Member Hours Summary</h1>
        <p class="text-sm text-gray-500 mt-1">Given vs. received per member</p>
    </div>
    <a href="<?= url('/admin/reports/member-hours-summary?export=csv') ?>"
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
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Member</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= e($currencyName) ?>s Given</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= e($currencyName) ?>s Received</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Net Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($members as $m): ?>
                    <?php
                    $given    = (float)($m['hours_given']    ?? 0);
                    $received = (float)($m['hours_received'] ?? 0);
                    $net      = $given - $received;
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="<?= url('/admin/members/' . ($m['id'] ?? '')) ?>"
                               class="font-medium text-gray-800 hover:text-teal-700 transition-colors">
                                <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                            </a>
                        </td>
                        <td class="px-5 py-3.5 text-right text-teal-600 font-medium"><?= number_format($given, 2) ?></td>
                        <td class="px-5 py-3.5 text-right text-orange-500 font-medium"><?= number_format($received, 2) ?></td>
                        <td class="px-5 py-3.5 text-right font-semibold <?= $net >= 0 ? 'text-gray-800' : 'text-red-500' ?>">
                            <?= ($net >= 0 ? '+' : '') . number_format($net, 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                    <td class="px-5 py-3 text-sm text-gray-700">Totals</td>
                    <td class="px-5 py-3 text-right text-sm text-teal-600"><?= number_format(array_sum(array_column($members, 'hours_given')), 2) ?></td>
                    <td class="px-5 py-3 text-right text-sm text-orange-500"><?= number_format(array_sum(array_column($members, 'hours_received')), 2) ?></td>
                    <td class="px-5 py-3 text-right text-sm text-gray-700">&mdash;</td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No data available yet.</p>
    </div>
<?php endif; ?>
