<?php
/** Ported from resources/views (Blade). */
$__title = 'Account';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <h1 class="text-xl font-semibold text-gray-900 mb-6">Account</h1>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-0.5">Account name</p>
                <p class="text-sm text-gray-900 font-medium"><?= e($account->name) ?></p>
            </div>
        </div>

        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-0.5">Slug</p>
                <p class="text-sm text-gray-900 font-mono"><?= e($account->slug) ?></p>
            </div>
        </div>

        <?php if ($account->business_type): ?>
        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-0.5">Business type</p>
                <p class="text-sm text-gray-900"><?= e(ucwords(str_replace('_', ' ', $account->business_type))) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-0.5">Members</p>
                <p class="text-sm text-gray-900"><?= e($account->users->count()) ?> <?= e(Str::plural('member', $account->users->count())) ?></p>
            </div>
            <a href="<?= e(route('account.members')) ?>" class="text-sm text-indigo-600 hover:underline">Manage</a>
        </div>

        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-0.5">Plan</p>
                <p class="text-sm text-gray-900"><?= e($account->plan ?? 'Free') ?></p>
            </div>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="<?= e(route('account.profile')) ?>" class="text-sm text-indigo-600 hover:underline">Edit profile</a>
        <span class="text-gray-300">·</span>
        <a href="<?= e(route('account.password')) ?>" class="text-sm text-indigo-600 hover:underline">Change password</a>
        <span class="text-gray-300">·</span>
        <a href="<?= e(route('account.invite')) ?>" class="text-sm text-indigo-600 hover:underline">Invite members</a>
        <span class="text-gray-300">·</span>
        <a href="<?= e(route('clients.index')) ?>" class="text-sm text-indigo-600 hover:underline">Clients</a>
        <span class="text-gray-300">·</span>
        <a href="<?= e(route('account.export')) ?>" class="text-sm text-indigo-600 hover:underline">Export data</a>
        <?php if (auth()->user()->isAdmin()): ?>
            <span class="text-gray-300">·</span>
            <a href="<?= e(route('account.danger')) ?>" class="text-sm text-red-500 hover:underline">Danger zone</a>
        <?php endif; ?>
    </div>

    
    <div class="mt-8 bg-gray-50 border border-gray-200 rounded-xl p-5">
        <p class="text-sm font-medium text-gray-900 mb-1">Fathom is free forever.</p>
        <p class="text-sm text-gray-500 mb-3">If it's earning its place in your workflow, you can support its development — pay what that value is worth to you.</p>
        <a href="<?= e(FM_SUPPORT_URL) ?>" class="text-sm font-medium text-indigo-600 hover:underline">
            Support Fathom — Pay What You Want →
        </a>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
