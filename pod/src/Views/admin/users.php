<?php $pageTitle = 'Admin — Users'; ?>

<div class="mb-6">
    <a href="<?= url('admin') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Admin</a>
    <h1 class="text-2xl font-bold mt-1">Users</h1>
</div>

<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php if (empty($users)): ?>
        <p class="px-4 py-6 text-sm text-gray-400">No users yet.</p>
    <?php endif; ?>
    <?php foreach ($users as $u): ?>
        <div class="flex items-center gap-4 px-4 py-3">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium <?= $u['is_active'] ? '' : 'text-gray-400 line-through' ?>">
                    <?= h($u['first_name'] . ' ' . $u['last_name']) ?>
                </p>
                <p class="text-xs text-gray-400"><?= h($u['email']) ?></p>
            </div>

            <?php if ($u['is_admin']): ?>
                <span class="text-xs bg-red-50 text-red-700 px-2 py-0.5 rounded-full">Admin</span>
            <?php elseif ($u['is_staff']): ?>
                <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full">Staff</span>
            <?php endif; ?>

            <?php if (!$u['is_active']): ?>
                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Disabled</span>
            <?php endif; ?>

            <span class="text-xs text-gray-400"><?= timeAgo($u['created_at']) ?></span>

            <?php if (!$u['is_admin']): ?>
                <a href="<?= url("admin/users/{$u['id']}/edit") ?>"
                   class="text-xs text-gray-400 hover:text-indigo-600 transition-colors">Edit</a>

                <form method="POST" action="<?= url("admin/users/{$u['id']}/toggle-staff") ?>">
                    <?= csrf_field() ?>
                    <button class="text-xs text-gray-400 hover:text-indigo-600 transition-colors">
                        <?= $u['is_staff'] ? 'Remove staff' : 'Make staff' ?>
                    </button>
                </form>

                <form method="POST" action="<?= url("admin/users/{$u['id']}/toggle-access") ?>">
                    <?= csrf_field() ?>
                    <button class="text-xs transition-colors <?= $u['is_active'] ? 'text-gray-400 hover:text-red-600' : 'text-green-600 hover:text-green-800' ?>">
                        <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
