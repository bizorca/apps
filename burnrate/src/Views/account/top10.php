<?php $title = 'Hall of Shame - Burn Rate'; ?>
<?php $h = \App\Core\Helpers::class; ?>
<?php ob_start(); ?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="bg-gradient-to-r from-red-500 to-amber-600 px-6 py-6 text-center">
            <h1 class="text-2xl font-bold text-white">Hall of Shame</h1>
            <p class="text-white/80 text-sm mt-1">The fastest fortune destroyers in Burn Rate history</p>
        </div>

        <?php if (empty($players)): ?>
            <div class="p-8 text-center text-gray-400">No players yet. Someone needs to start wasting money!</div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($players as $i => $p): ?>
                <div class="px-6 py-4 flex items-center space-x-4 hover:bg-gray-50">
                    <span class="text-2xl font-bold <?= $i === 0 ? 'text-yellow-500' : ($i === 1 ? 'text-gray-400' : ($i === 2 ? 'text-amber-600' : 'text-gray-300')) ?>">
                        #<?= $i + 1 ?>
                    </span>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($p->PlayerName) ?></h3>
                        <p class="text-sm text-gray-500">
                            <?= htmlspecialchars(($p->FirstName ?? '') . ' ' . ($p->LastName ?? '')) ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <?php if ($p->BankruptTurn > 0): ?>
                            <p class="font-bold text-green-600">BANKRUPT! Turn <?= $p->BankruptTurn ?></p>
                            <p class="text-xs text-gray-400"><?= $h::turnToYearsMonths($p->BankruptTurn) ?></p>
                        <?php else: ?>
                            <p class="italic text-red-600">Still Rich at Turn <?= $p->Turn ?></p>
                            <p class="text-xs text-gray-400">(Needs to try harder)</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-6 text-center">
        <a href="<?= url('/account') ?>" class="text-red-600 hover:text-red-700 font-medium">Back to My Account</a>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
