<?php $pageTitle = 'My Transactions'; ?>
<?php
$currencyName   = $tenant['currency_name'] ?? 'Hour';
$currencyPlural = $tenant['currency_name_plural'] ?? 'Hours';
$currentUserId  = \TimeBank\Core\Auth::id();
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">My Transactions</h1>
        <p class="text-sm text-gray-500 mt-1">Your full exchange history</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= url('/transactions/statement') ?>"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Statement
        </a>
        <a href="<?= url('/transactions/record') ?>"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Record Hours
        </a>
    </div>
</div>

<?php if (!empty($transactions['data'])): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[700px]">
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
                        $isProvider = (int)($tx['provider_id'] ?? 0) === (int)$currentUserId;
                        $hoursColor = $isProvider ? 'text-teal-600' : 'text-orange-500';
                        $hoursSign  = $isProvider ? '+' : '-';
                        $statusStyles = match($tx['status'] ?? 'confirmed') {
                            'confirmed' => 'bg-teal-50 text-teal-700',
                            'disputed'  => 'bg-amber-50 text-amber-700',
                            'cancelled' => 'bg-red-50 text-red-500',
                            default     => 'bg-gray-100 text-gray-500',
                        };
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap">
                                <?= e(date('M j, Y', strtotime($tx['service_date'] ?? $tx['created_at'] ?? ''))) ?>
                            </td>
                            <td class="px-5 py-3.5 text-gray-800 max-w-xs">
                                <span class="block truncate"><?= e(truncate($tx['description'] ?? 'Service exchange', 70)) ?></span>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <a href="<?= url('/members/' . ($tx['provider_id'] ?? '')) ?>"
                                   class="text-gray-700 hover:text-teal-700 transition-colors <?= $isProvider ? 'font-semibold' : '' ?>">
                                    <?= e($tx['provider_name'] ?? '') ?>
                                </a>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <?php if (!empty($tx['receiver_id'])): ?>
                                    <a href="<?= url('/members/' . ($tx['receiver_id'] ?? '')) ?>"
                                       class="text-gray-700 hover:text-teal-700 transition-colors <?= !$isProvider ? 'font-semibold' : '' ?>">
                                        <?= e($tx['receiver_name'] ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-right font-semibold <?= $hoursColor ?> whitespace-nowrap">
                                <?= $hoursSign ?><?= number_format((float)($tx['hours'] ?? 0), 2) ?>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full <?= $statusStyles ?>">
                                    <?= e(ucfirst($tx['status'] ?? 'confirmed')) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="<?= url('/transactions/' . ($tx['id'] ?? '')) ?>"
                                   class="text-xs text-teal-600 hover:text-teal-800 font-medium transition-colors">
                                    View
                                </a>
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
            ?>
            <?php if ($currentPage > 1): ?>
                <a href="<?= url('/transactions?page=' . ($currentPage - 1)) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">&lsaquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                <a href="<?= url('/transactions?page=' . $p) ?>"
                   class="px-3 py-2 text-sm rounded-lg transition-colors <?= $p === $currentPage ? 'bg-teal-600 text-white font-semibold' : 'text-gray-600 bg-white border border-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= url('/transactions?page=' . ($currentPage + 1)) ?>"
                   class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Next &rsaquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-16 text-center">
        <div class="w-16 h-16 rounded-full bg-teal-50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-teal-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-base font-semibold text-gray-600 mb-2">No transactions yet</h3>
        <p class="text-sm text-gray-400 mb-5">Every exchange starts with recording it. Go make something happen.</p>
        <a href="<?= url('/transactions/record') ?>"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors">
            Record Your First Exchange
        </a>
    </div>
<?php endif; ?>
