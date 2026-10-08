<?php $pageTitle = 'All Transactions'; ?>
<?php
$currencyName = $tenant['currency_name'] ?? 'Hour';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">All Transactions</h1>
    <a href="<?= url('/transactions/record') ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Record Hours
    </a>
</div>

<!-- Filters -->
<form method="GET" action="<?= url('/admin/transactions') ?>"
      class="bg-white rounded-2xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
            <?= route_field('/admin/transactions') ?>
    <div class="flex-1 min-w-[200px]">
        <input type="text" name="member" value="<?= e($_GET['member_name'] ?? '') ?>"
               placeholder="Filter by member name..."
               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
    </div>
    <div>
        <input type="date" name="from" value="<?= e($_GET['from'] ?? '') ?>"
               class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
    </div>
    <div>
        <input type="date" name="to" value="<?= e($_GET['to'] ?? '') ?>"
               class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
    </div>
    <select name="status" class="px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white">
        <option value="">All statuses</option>
        <option value="confirmed" <?= ($_GET['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
        <option value="disputed"  <?= ($_GET['status'] ?? '') === 'disputed'  ? 'selected' : '' ?>>Disputed</option>
        <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>
    <button type="submit"
            class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors">
        Filter
    </button>
    <a href="<?= url('/admin/transactions') ?>"
       class="px-5 py-2.5 bg-gray-100 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
        Clear
    </a>
</form>

<?php if (!empty($transactions['data'])): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[800px]">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Provider</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Receiver</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= e($currencyName) ?>s</th>
                        <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($transactions['data'] as $tx): ?>
                        <?php
                        $statusStyles = match($tx['status'] ?? 'confirmed') {
                            'confirmed' => 'bg-teal-50 text-teal-700',
                            'disputed'  => 'bg-amber-50 text-amber-700',
                            'cancelled' => 'bg-red-50 text-red-500',
                            default     => 'bg-gray-100 text-gray-500',
                        };
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs"><?= e(date('M j, Y', strtotime($tx['service_date'] ?? $tx['created_at'] ?? ''))) ?></td>
                            <td class="px-5 py-3 text-gray-700 max-w-xs"><span class="truncate block text-xs"><?= e(truncate($tx['description'] ?? '', 55)) ?></span></td>
                            <td class="px-5 py-3 text-xs whitespace-nowrap">
                                <a href="<?= url('/admin/members/' . ($tx['provider_id'] ?? '')) ?>" class="text-gray-700 hover:text-teal-700 transition-colors">
                                    <?= e($tx['provider_name'] ?? '') ?>
                                </a>
                            </td>
                            <td class="px-5 py-3 text-xs whitespace-nowrap">
                                <?php if (!empty($tx['receiver_id'])): ?>
                                    <a href="<?= url('/admin/members/' . ($tx['receiver_id'] ?? '')) ?>" class="text-gray-700 hover:text-teal-700 transition-colors">
                                        <?= e($tx['receiver_name'] ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-right text-sm font-semibold text-teal-600 whitespace-nowrap"><?= number_format((float)($tx['hours'] ?? 0), 2) ?></td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full <?= $statusStyles ?>">
                                    <?= e(ucfirst($tx['status'] ?? 'confirmed')) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="<?= url('/transactions/' . ($tx['id'] ?? '')) ?>"
                                   class="text-xs text-teal-600 hover:text-teal-800 font-medium transition-colors">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if (($transactions['pages'] ?? 1) > 1): ?>
        <div class="flex items-center justify-center gap-2">
            <?php
            $currentPage = (int)($transactions['current'] ?? 1);
            $totalPages  = (int)($transactions['pages'] ?? 1);
            $baseParams  = array_filter(['member' => $_GET['member'] ?? '', 'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? '', 'status' => $_GET['status'] ?? '']);
            ?>
            <?php if ($currentPage > 1): ?>
                <a href="<?= url('/admin/transactions?' . http_build_query(array_merge($baseParams, ['page' => $currentPage - 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">&lsaquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                <a href="<?= url('/admin/transactions?' . http_build_query(array_merge($baseParams, ['page' => $p]))) ?>"
                   class="px-3 py-2 text-sm rounded-lg transition-colors <?= $p === $currentPage ? 'bg-teal-600 text-white font-semibold' : 'text-gray-600 bg-white border border-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= url('/admin/transactions?' . http_build_query(array_merge($baseParams, ['page' => $currentPage + 1]))) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Next &rsaquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-400">No transactions match the current filters.</p>
    </div>
<?php endif; ?>
