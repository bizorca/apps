<?php $title = 'Admin Dashboard - Burn Rate'; ?>
<?php ob_start(); ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
        <div class="flex space-x-3">
            <a href="<?= url('/admin/owners') ?>" class="rounded-lg bg-blue-50 text-blue-600 px-4 py-2 text-sm font-medium hover:bg-blue-100">All Owners</a>
            <a href="<?= url('/admin/players') ?>" class="rounded-lg bg-purple-50 text-purple-600 px-4 py-2 text-sm font-medium hover:bg-purple-100">All Players</a>
            <a href="<?= url('/admin/gamelogs') ?>" class="rounded-lg bg-amber-50 text-amber-600 px-4 py-2 text-sm font-medium hover:bg-amber-100">Game Logs</a>
            <a href="<?= url('/admin/settings') ?>" class="rounded-lg bg-red-50 text-red-600 px-4 py-2 text-sm font-medium hover:bg-red-100">Settings</a>
            <a href="<?= url('/admin/users/create') ?>" class="rounded-lg bg-red-50 text-red-600 px-4 py-2 text-sm font-medium hover:bg-red-100">Create User</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-sm font-medium text-gray-500">Total Owners</h3>
            <p class="text-3xl font-bold text-gray-900"><?= number_format($totalOwners) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-sm font-medium text-gray-500">Total Players</h3>
            <p class="text-3xl font-bold text-gray-900"><?= number_format($totalPlayers) ?></p>
        </div>
    </div>

    <!-- Recent Owners -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 border-b"><h2 class="font-semibold">Recent Owners</h2></div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">ID</th>
                    <th class="px-4 py-2 text-left">Email</th>
                    <th class="px-4 py-2 text-left">Name</th>
                    <th class="px-4 py-2 text-left">Signed Up</th>
                    <th class="px-4 py-2 text-left">Last Login</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($recentOwners as $o): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><?= $o->OwnerID ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($o->Email) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars(($o->FirstName ?? '') . ' ' . ($o->LastName ?? '')) ?></td>
                    <td class="px-4 py-2 text-gray-500"><?= $o->SignUpDate ?? 'N/A' ?></td>
                    <td class="px-4 py-2 text-gray-500"><?= $o->LastLoginDate ?? 'Never' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
