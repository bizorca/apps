<?php $title = 'All Players - Admin'; ?>
<?php ob_start(); ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">All Players</h1>
        <a href="<?= url('/admin') ?>" class="text-red-600 hover:text-red-700 font-medium text-sm">Back to Admin</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">Player ID</th>
                    <th class="px-4 py-2 text-left">Name</th>
                    <th class="px-4 py-2 text-left">Owner</th>
                    <th class="px-4 py-2 text-right">Turn</th>
                    <th class="px-4 py-2 text-right">Bankrupt</th>
                    <th class="px-4 py-2 text-left">Last Played</th>
                    <th class="px-4 py-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($players as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><?= $p->PlayerID ?></td>
                    <td class="px-4 py-2 font-medium"><?= htmlspecialchars($p->PlayerName) ?></td>
                    <td class="px-4 py-2 text-gray-500"><?= htmlspecialchars($p->Email) ?></td>
                    <td class="px-4 py-2 text-right"><?= $p->Turn ?>/611</td>
                    <td class="px-4 py-2 text-right <?= $p->BankruptTurn > 0 ? 'text-green-600 font-bold' : 'text-red-500' ?>"><?= $p->BankruptTurn > 0 ? "Turn {$p->BankruptTurn}" : 'Still Rich' ?></td>
                    <td class="px-4 py-2 text-gray-500"><?= $p->LastPlayedDate ?></td>
                    <td class="px-4 py-2">
                        <a href="<?= url('/admin/gamelogs') ?>?player_id=<?= $p->PlayerID ?>" class="text-blue-600 hover:text-blue-700 text-xs">View Logs</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
