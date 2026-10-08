<?php
/** Ported from resources/views (Blade). */
$__title = 'Invite members';
ob_start();
?>
<div class="max-w-lg mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('account.show')) ?>" class="hover:text-gray-600">Account</a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Invite members</span>
    </nav>

    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
        <h1 class="text-lg font-semibold text-gray-900">Invite members</h1>

        
        <div>
            <p class="text-sm font-medium text-gray-700 mb-2">Invite link</p>
            <p class="text-xs text-gray-400 mb-2">Share this link with anyone you want to add to <strong><?= e($account->name) ?></strong>.</p>
            <div class="flex items-center gap-2">
                <input type="text" readonly
                       value="<?= e(route('join', $account->invite_code)) ?>"
                       class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 text-gray-700 font-mono"
                       onclick="this.select()">
                <button type="button"
                        onclick="navigator.clipboard.writeText('<?= e(route('join', $account->invite_code)) ?>').then(() => this.textContent = 'Copied!')"
                        class="text-sm text-indigo-600 border border-indigo-200 rounded-lg px-3 py-2 hover:bg-indigo-50 transition whitespace-nowrap">
                    Copy link
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-400">Invite code: <span class="font-mono font-medium text-gray-600"><?= e($account->invite_code) ?></span></p>
        </div>

        <hr class="border-gray-100">

        
        <div>
            <p class="text-sm font-medium text-gray-700 mb-1">Or send via email</p>
            <form method="POST" action="<?= e(route('account.invite.send')) ?>" class="flex gap-2">
                <?= csrf_field() ?>
                <input type="email" name="email" placeholder="colleague@example.com" required
                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button type="submit"
                        class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    Send
                </button>
            </form>
        </div>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
