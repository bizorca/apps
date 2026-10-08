<?php
/** Ported from resources/views (Blade). */
$__title = $account->name . ' — Admin';
ob_start();
?>
<div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">

    
    <div>
        <a href="<?= e(route('admin.accounts.index')) ?>" class="text-sm text-gray-400 hover:text-gray-600">&larr; All Accounts</a>
        <div class="flex items-start justify-between mt-1">
            <div>
                <h1 class="text-2xl font-bold text-gray-900"><?= e($account->name) ?></h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    slug: <span class="font-mono"><?= e($account->slug) ?></span>
                    &middot; id: <span class="font-mono text-xs"><?= e($account->id) ?></span>
                    &middot; created <?= e($account->created_at->format('M j, Y')) ?>
                </p>
            </div>
            <?php if ($account->isCancelled()): ?>
                <span class="text-sm text-red-600 font-medium bg-red-50 border border-red-200 rounded px-2 py-1">
                    Cancelled <?= e($account->cancelled_at->format('M j, Y')) ?>
                </span>
            <?php else: ?>
                <span class="text-sm text-green-700 font-medium bg-green-50 border border-green-200 rounded px-2 py-1">Active</span>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="grid sm:grid-cols-2 gap-4">

        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3 text-sm">
            <h2 class="font-semibold text-gray-900">Account Details</h2>
            <dl class="space-y-2">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Business type</dt>
                    <dd class="text-gray-900"><?= e($account->business_type ?? '—') ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Plan</dt>
                    <dd><span class="text-xs bg-gray-100 text-gray-700 rounded px-1.5 py-0.5"><?= e($account->plan) ?></span></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Invite code</dt>
                    <dd class="font-mono text-xs text-gray-700"><?= e($account->invite_code ?? '—') ?></dd>
                </div>
                <?php if ($account->settings): ?>
                <div class="flex justify-between items-start">
                    <dt class="text-gray-500">Settings</dt>
                    <dd class="font-mono text-xs text-gray-700 text-right"><?= e(json_encode($account->settings, JSON_PRETTY_PRINT)) ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <?php foreach ([
                ['label' => 'Boards',        'value' => $account->boardsCount()],
                ['label' => 'Cards',         'value' => $account->cardsCount()],
                ['label' => 'Clients',       'value' => $account->clientsCount()],
                ['label' => 'Exports',       'value' => $account->exportsCount()],
                ['label' => 'Templates',     'value' => $account->cardTemplatesCount()],
                ['label' => 'Tags',          'value' => $account->tagsCount()],
            ] as $stat): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-gray-900"><?= e($stat['value']) ?></p>
                <p class="text-xs text-gray-500 mt-0.5"><?= e($stat['label']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

    
    <div>
        <h2 class="text-base font-semibold text-gray-900 mb-3">
            Users <span class="text-gray-400 font-normal">(<?= e($users->count()) ?>)</span>
        </h2>

        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Name</th>
                        <th class="px-4 py-3 text-left font-medium">Email</th>
                        <th class="px-4 py-3 text-left font-medium">Role</th>
                        <th class="px-4 py-3 text-left font-medium">Joined</th>
                        <th class="px-4 py-3 text-right font-medium">Impersonate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <?= e($user->name) ?>
                            <?php if ($user->is_sysop): ?>
                                <span class="ml-1 text-xs bg-indigo-100 text-indigo-700 rounded px-1.5 py-0.5">sysop</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-500"><?= e($user->email_address) ?></td>
                        <td class="px-4 py-3">
                            <span class="text-xs rounded px-1.5 py-0.5 <?= e($user->isAdmin() ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600') ?>">
                                <?= e($user->role) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs"><?= e($user->created_at->format('M j, Y')) ?></td>
                        <td class="px-4 py-3 text-right">
                            <?php if (!$user->is_sysop): ?>
                            <form method="POST" action="<?= e(route('admin.impersonate.store', $user)) ?>">
                                <?= csrf_field() ?>
                                <button type="submit"
                                    onclick="return confirm('Impersonate <?= e(addslashes($user->name)) ?>?')"
                                    class="text-xs text-indigo-600 hover:underline font-medium">
                                    Impersonate
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="text-xs text-gray-300">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <?php if (!$account->isCancelled()): ?>
    <div class="border border-red-200 rounded-xl p-5">
        <h2 class="text-base font-semibold text-red-700 mb-1">Danger Zone</h2>
        <p class="text-sm text-gray-500 mb-4">Cancelling this account will mark it as cancelled and prevent sign-in. It does not delete data.</p>
        <form method="POST" action="<?= e(route('admin.accounts.cancel', $account)) ?>">
            <?= csrf_field() ?>
            <?= method_field('PATCH') ?>
            <button type="submit"
                onclick="return confirm('Cancel account <?= e(addslashes($account->name)) ?>? This will lock out all users.')"
                class="text-sm bg-red-600 text-white font-medium px-4 py-2 rounded-lg hover:bg-red-700 transition">
                Cancel This Account
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
