<?php
/** Ported from resources/views (Blade). */
$__title = 'Members';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="<?= e(route('account.show')) ?>" class="hover:text-gray-600">Account</a>
        <span class="mx-2">/</span>
        <span class="text-gray-600">Members</span>
    </nav>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-semibold text-gray-900">Members</h1>
        <?php if (auth()->user()->isAdmin()): ?>
            <a href="<?= e(route('account.invite')) ?>"
               class="bg-indigo-600 text-white text-sm font-medium px-4 py-1.5 rounded-lg hover:bg-indigo-700 transition">
                Invite member
            </a>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <?php $__empty1 = true; foreach ($members as $member): $__empty1 = false; ?>
            <div class="px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm font-medium flex-shrink-0">
                        <?= e($member->initials()) ?>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            <?= e($member->name) ?>
                            <?php if ($member->id === auth()->id()): ?>
                                <span class="text-xs text-gray-400 ml-1">(you)</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs text-gray-400"><?= e($member->email_address) ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs px-2 py-0.5 rounded-full <?= e($member->role === 'admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600') ?>">
                        <?= e(ucfirst($member->role)) ?>
                    </span>

                    <?php if (auth()->user()->isAdmin() && $member->id !== auth()->id()): ?>
                        <form method="POST" action="<?= e(route('account.members.destroy', $member)) ?>"
                              onsubmit="return confirm('Remove <?= e($member->name) ?> from this account?')">
                            <?= csrf_field() ?>
                            <?= method_field('DELETE') ?>
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; if ($__empty1): ?>
            <div class="px-5 py-8 text-center text-sm text-gray-400">No members yet.</div>
        <?php endif; ?>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
