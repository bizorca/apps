<?php
/** Ported from resources/views (Blade). */
$__title = 'All Accounts — Admin';
ob_start();
?>
<div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="<?= e(route('admin.dashboard')) ?>" class="text-sm text-gray-400 hover:text-gray-600">&larr; Admin</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">All Accounts <span class="text-gray-400 font-normal text-lg">(<?= e($accounts->count()) ?>)</span></h1>
        </div>
    </div>

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
                    <th class="px-4 py-3 text-right font-medium">Clients</th>
                    <th class="px-4 py-3 text-left font-medium">Created</th>
                    <th class="px-4 py-3 text-left font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($accounts as $account): ?>
                <tr class="hover:bg-gray-50 <?= e($account->isCancelled() ? 'opacity-60' : '') ?>">
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
                    <td class="px-4 py-3 text-right text-gray-700"><?= e($account->clientsCount()) ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= e($account->created_at->format('M j, Y')) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($account->isCancelled()): ?>
                            <span class="text-xs text-red-600 font-medium">Cancelled <?= e($account->cancelled_at->format('M j, Y')) ?></span>
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
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
