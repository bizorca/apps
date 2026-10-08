<?php
/** Ported from resources/views (Blade). */
$__title = 'Admin';
ob_start();
?>
<div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-gray-900">System Overview</h1>
        <a href="<?= e(route('admin.accounts.index')) ?>" class="text-sm text-indigo-600 hover:underline">All accounts &rarr;</a>
    </div>

    
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-10">
        <?php foreach ([
            ['label' => 'Total Accounts',    'value' => $stats['accounts_total'],   'sub' => $stats['accounts_cancelled'] . ' cancelled'],
            ['label' => 'Active Accounts',   'value' => $stats['accounts_active'],  'sub' => null],
            ['label' => 'Users',             'value' => $stats['users_total'],      'sub' => null],
            ['label' => 'Boards',            'value' => $stats['boards_total'],     'sub' => null],
            ['label' => 'Cards',             'value' => $stats['cards_total'],      'sub' => null],
            ['label' => 'Clients',           'value' => $stats['clients_total'],    'sub' => null],
            ['label' => 'Data Exports',      'value' => $stats['exports_total'],    'sub' => null],
            ['label' => 'Magic Links Issued','value' => $stats['magic_links_total'],'sub' => null],
        ] as $stat): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-2xl font-bold text-gray-900"><?= e(number_format($stat['value'])) ?></p>
            <p class="text-sm text-gray-500 mt-0.5"><?= e($stat['label']) ?></p>
            <?php if ($stat['sub']): ?>
                <p class="text-xs text-gray-400 mt-1"><?= e($stat['sub']) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    
    <div>
        <h2 class="text-base font-semibold text-gray-900 mb-3">Recent Accounts</h2>
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Account</th>
                        <th class="px-4 py-3 text-left font-medium">Business Type</th>
                        <th class="px-4 py-3 text-left font-medium">Plan</th>
                        <th class="px-4 py-3 text-right font-medium">Users</th>
                        <th class="px-4 py-3 text-right font-medium">Boards</th>
                        <th class="px-4 py-3 text-right font-medium">Cards</th>
                        <th class="px-4 py-3 text-left font-medium">Created</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($recentAccounts as $account): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="<?= e(route('admin.accounts.show', $account)) ?>" class="font-medium text-indigo-600 hover:underline">
                                <?= e($account->name) ?>
                            </a>
                            <span class="text-gray-400 text-xs ml-1">/ <?= e($account->slug) ?></span>
                        </td>
                        <td class="px-4 py-3 text-gray-500"><?= e($account->business_type ?? '—') ?></td>
                        <td class="px-4 py-3">
                            <span class="text-xs bg-gray-100 text-gray-700 rounded px-1.5 py-0.5"><?= e($account->plan) ?></span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700"><?= e($account->usersCount()) ?></td>
                        <td class="px-4 py-3 text-right text-gray-700"><?= e($account->boardsCount()) ?></td>
                        <td class="px-4 py-3 text-right text-gray-700"><?= e($account->cardsCount()) ?></td>
                        <td class="px-4 py-3 text-gray-500 text-xs"><?= e($account->created_at->format('M j, Y')) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($account->isCancelled()): ?>
                                <span class="text-xs text-red-600 font-medium">Cancelled</span>
                            <?php else: ?>
                                <span class="text-xs text-green-600 font-medium">Active</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
