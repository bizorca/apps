<?php
/** Transaction History report. Listed on the reports page; the original had no view for it. */
$pageTitle = 'Transaction History';
$currencyName = $tenant['currency_name'] ?? 'Hour';
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="<?= url('/admin/reports') ?>" class="hover:text-teal-600 transition-colors">Reports</a>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900">Transaction History</h1>
        <p class="text-sm text-gray-500 mt-1"><?= count($members ?? []) ?> confirmed transactions &mdash; generated <?= date('F j, Y') ?></p>
    </div>
    <a href="<?= url('/admin/reports/transaction-history?export=csv') ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-xl hover:bg-gray-900 transition-colors">Export CSV</a>
</div>

<?php if (!empty($members)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Provider</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Receiver</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= e($currencyName) ?>s</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($members as $t): ?>
                    <tr>
                        <td class="px-5 py-3 text-gray-600"><?= e($t['service_date']) ?></td>
                        <td class="px-5 py-3 text-gray-800"><?= e($t['provider_name']) ?></td>
                        <td class="px-5 py-3 text-gray-800"><?= e($t['receiver_name'] ?? 'Community fund') ?></td>
                        <td class="px-5 py-3 text-gray-600"><?= e(truncate($t['description'], 80)) ?></td>
                        <td class="px-5 py-3 text-right font-medium text-gray-900"><?= e(number_format((float) $t['hours'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-sm text-gray-500">No confirmed transactions yet.</div>
<?php endif; ?>
