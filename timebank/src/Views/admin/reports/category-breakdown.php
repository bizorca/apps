<?php $pageTitle = 'Category Breakdown'; ?>
<?php
$currencyPlural = $tenant['currency_name_plural'] ?? 'Hours';
$maxHours = !empty($categories) ? max(array_column($categories, 'total_hours') ?: [1]) : 1;
$maxHours = max($maxHours, 0.01);
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="<?= url('/admin/reports') ?>" class="hover:text-teal-600 transition-colors">Reports</a>
            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900">Category Breakdown</h1>
        <p class="text-sm text-gray-500 mt-1">Service activity by category</p>
    </div>
</div>

<?php if (!empty($categories)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Offers</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Requests</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= e($currencyPlural) ?></th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Activity</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($categories as $cat): ?>
                    <?php
                    $pct = $maxHours > 0 ? min(100, round((float)($cat['total_hours'] ?? 0) / $maxHours * 100)) : 0;
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5 font-medium text-gray-800"><?= e($cat['name'] ?? '') ?></td>
                        <td class="px-5 py-3.5 text-right text-gray-600"><?= number_format((int)($cat['offer_count'] ?? 0)) ?></td>
                        <td class="px-5 py-3.5 text-right text-gray-600"><?= number_format((int)($cat['request_count'] ?? 0)) ?></td>
                        <td class="px-5 py-3.5 text-right font-semibold text-teal-600"><?= number_format((float)($cat['total_hours'] ?? 0), 2) ?></td>
                        <td class="px-5 py-3.5 hidden lg:table-cell">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                                    <div class="h-2 rounded-full bg-teal-500 transition-all"
                                         style="width: <?= $pct ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-400 w-8 text-right"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                    <td class="px-5 py-3 text-sm text-gray-700">Totals</td>
                    <td class="px-5 py-3 text-right text-sm text-gray-700"><?= number_format(array_sum(array_column($categories, 'offer_count'))) ?></td>
                    <td class="px-5 py-3 text-right text-sm text-gray-700"><?= number_format(array_sum(array_column($categories, 'request_count'))) ?></td>
                    <td class="px-5 py-3 text-right text-sm text-teal-600"><?= number_format(array_sum(array_column($categories, 'total_hours')), 2) ?></td>
                    <td class="hidden lg:table-cell"></td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No category data available yet.</p>
    </div>
<?php endif; ?>
