<?php
$pageTitle = 'Transaction #' . ($transaction['id'] ?? '');
$currencyName  = $tenant['currency_name'] ?? 'Hour';
$currentUserId = \TimeBank\Core\Auth::id();
$isAdmin       = \TimeBank\Core\Auth::isAdmin();
$recordedBy    = (int)($transaction['recorded_by'] ?? 0);
$canDelete     = ($recordedBy === (int)$currentUserId || $isAdmin)
                 && (strtotime($transaction['created_at'] ?? '0') > strtotime('-7 days'));
$statusStyles  = match($transaction['status'] ?? 'confirmed') {
    'confirmed' => 'bg-teal-50 text-teal-700 border-teal-200',
    'disputed'  => 'bg-amber-50 text-amber-700 border-amber-200',
    'cancelled' => 'bg-red-50 text-red-600 border-red-200',
    default     => 'bg-gray-100 text-gray-600 border-gray-200',
};
?>

<div class="max-w-2xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <nav class="flex items-center gap-2 text-sm text-gray-500">
            <a href="<?= url('/transactions') ?>" class="hover:text-teal-600 transition-colors">Transactions</a>
            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-800 font-medium">#<?= (int)($transaction['id'] ?? 0) ?></span>
        </nav>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        <!-- Header stripe -->
        <div class="bg-teal-600 px-6 py-5 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-teal-200 text-xs font-medium mb-1">Transaction</p>
                    <p class="text-2xl font-bold"><?= number_format((float)($transaction['hours'] ?? 0), 2) ?> <?= e($currencyName) ?>s</p>
                </div>
                <span class="inline-block text-xs font-semibold px-3 py-1 rounded-full border <?= $statusStyles ?>">
                    <?= e(ucfirst($transaction['status'] ?? 'confirmed')) ?>
                </span>
            </div>
        </div>

        <div class="p-6 space-y-5">

            <!-- Description -->
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Service</p>
                <p class="text-base text-gray-800 font-medium"><?= e($transaction['description'] ?? '') ?></p>
            </div>

            <!-- Date -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Service Date</p>
                    <p class="text-sm text-gray-800"><?= e(date('F j, Y', strtotime($transaction['service_date'] ?? ''))) ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Recorded</p>
                    <p class="text-sm text-gray-800"><?= e(date('F j, Y', strtotime($transaction['created_at'] ?? ''))) ?></p>
                </div>
            </div>

            <!-- Provider + Receiver -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Provider</p>
                    <a href="<?= url('/members/' . ($transaction['provider_id'] ?? '')) ?>"
                       class="flex items-center gap-2 group">
                        <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center text-teal-700 font-semibold text-sm flex-shrink-0">
                            <?= e(mb_strtoupper(mb_substr($transaction['provider_name'] ?? 'P', 0, 1))) ?>
                        </div>
                        <span class="text-sm font-medium text-gray-800 group-hover:text-teal-700 transition-colors">
                            <?= e($transaction['provider_name'] ?? '') ?>
                        </span>
                    </a>
                </div>
                <?php if (!empty($transaction['receiver_id'])): ?>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Receiver</p>
                        <a href="<?= url('/members/' . ($transaction['receiver_id'] ?? '')) ?>"
                           class="flex items-center gap-2 group">
                            <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-semibold text-sm flex-shrink-0">
                                <?= e(mb_strtoupper(mb_substr($transaction['receiver_name'] ?? 'R', 0, 1))) ?>
                            </div>
                            <span class="text-sm font-medium text-gray-800 group-hover:text-teal-700 transition-colors">
                                <?= e($transaction['receiver_name'] ?? '') ?>
                            </span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Type -->
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Type</p>
                <p class="text-sm text-gray-700">
                    <?= e(match($transaction['type'] ?? 'one_to_one') {
                        'one_to_one'  => 'One-to-One Exchange',
                        'one_to_many' => 'Class / Workshop (One-to-Many)',
                        'many_to_one' => 'Group Project (Many-to-One)',
                        default       => ucwords(str_replace('_', ' ', $transaction['type'] ?? ''))
                    }) ?>
                </p>
            </div>

            <?php if (!empty($transaction['prep_hours']) && (float)$transaction['prep_hours'] > 0): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Prep Hours</p>
                    <p class="text-sm text-gray-700"><?= number_format((float)$transaction['prep_hours'], 2) ?></p>
                </div>
            <?php endif; ?>

            <!-- Recorded by -->
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Recorded by</p>
                <p class="text-sm text-gray-700"><?= e($transaction['recorded_by_name'] ?? 'Unknown') ?></p>
            </div>

        </div>

        <!-- Footer actions -->
        <?php if ($canDelete): ?>
            <div class="px-6 pb-6 pt-0">
                <div class="border-t border-gray-100 pt-5">
                    <form method="POST" action="<?= url('/transactions/' . ($transaction['id'] ?? '') . '/delete') ?>"
                          onsubmit="return confirm('Delete this transaction? The hours will be reversed and this cannot be undone.')">
                        <?= csrf_field() ?>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete Transaction
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
