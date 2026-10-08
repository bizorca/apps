<?php $title = 'Game Logs - Admin'; $h = \App\Core\Helpers::class; ?>
<?php ob_start(); ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Game Logs <?= $playerId ? "(Player #{$playerId})" : '' ?></h1>
        <a href="<?= url('/admin') ?>" class="text-red-600 hover:text-red-700 font-medium text-sm">Back to Admin</a>
    </div>

    <!-- Filter -->
    <?php /* A GET form replaces the whole query string, so in query-routing mode the route rides along as a hidden r. */ ?>
    <form method="GET" action="<?= BR_CLEAN_URLS ? url('/admin/gamelogs') : BR_BASE . '/' ?>" class="flex items-center space-x-3">
        <?php if (!BR_CLEAN_URLS): ?><input type="hidden" name="r" value="/admin/gamelogs"><?php endif; ?>
        <input type="number" name="player_id" value="<?= $playerId ?>" placeholder="Player ID"
            class="border rounded-lg px-3 py-2 text-sm w-40">
        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">View Logs</button>
    </form>

    <?php if (!empty($logs)): ?>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Turn</th>
                    <th class="px-3 py-2 text-right">Net Worth</th>
                    <th class="px-3 py-2 text-right">Bank</th>
                    <th class="px-3 py-2 text-right">Properties</th>
                    <th class="px-3 py-2 text-right">Toys</th>
                    <th class="px-3 py-2 text-right"># Props</th>
                    <th class="px-3 py-2 text-right"># Invest</th>
                    <th class="px-3 py-2 text-right">Interest</th>
                    <th class="px-3 py-2 text-right">Inflation</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($logs as $log): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2"><?= $log->Turn ?></td>
                    <td class="px-3 py-2 text-right font-medium"><?= $h::money($log->NetWorth) ?></td>
                    <td class="px-3 py-2 text-right"><?= $h::money($log->BankBalance) ?></td>
                    <td class="px-3 py-2 text-right"><?= $h::money($log->PropertyNetWorth) ?></td>
                    <td class="px-3 py-2 text-right"><?= $h::money($log->ToyNetWorth) ?></td>
                    <td class="px-3 py-2 text-right"><?= $log->NumProperties ?></td>
                    <td class="px-3 py-2 text-right"><?= $log->NumInvestments ?></td>
                    <td class="px-3 py-2 text-right"><?= number_format($log->InterestRate, 2) ?>%</td>
                    <td class="px-3 py-2 text-right"><?= number_format($log->InflationRate, 2) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ($playerId): ?>
        <p class="text-gray-400 text-center py-8">No logs found for Player #<?= $playerId ?>.</p>
    <?php else: ?>
        <p class="text-gray-400 text-center py-8">Enter a Player ID to view logs.</p>
    <?php endif; ?>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
