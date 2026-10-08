<?php $title = 'My Trust Fund Babies - Burn Rate'; ?>
<?php $h = \App\Core\Helpers::class; ?>
<?php
$memberLevel = (int) $app->memberLevel();
$tiers = \App\Models\Owner::TIERS;
$tierNames = \App\Models\Owner::TIER_NAMES;
$tierUnlocks = \App\Models\Owner::TIER_UNLOCKS;
$currentTierIndex = array_search($memberLevel, $tiers);
$nextTierIndex = ($currentTierIndex !== false && isset($tiers[$currentTierIndex + 1])) ? $currentTierIndex + 1 : null;
$nextTierLevel = $nextTierIndex !== null ? $tiers[$nextTierIndex] : null;
?>
<?php ob_start(); ?>

<div class="space-y-8">

    <!-- Welcome Header + Tier Status -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Your Trust Fund Babies</h1>
                <p class="mt-1 text-gray-500">Welcome back, <?= htmlspecialchars($owner->FirstName ?? 'Player') ?>. Time to burn through some fortunes.</p>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center rounded-full bg-amber-100 border border-amber-300 px-4 py-1.5 text-sm font-bold text-amber-800">
                    <?= htmlspecialchars($tierNames[$memberLevel] ?? 'Unknown') ?>
                </span>
                <p class="text-xs text-gray-400 mt-1"><?= (int) ($owner->RoundsCompleted ?? 0) ?> round(s) completed</p>
            </div>
        </div>

        <?php if ($nextTierLevel !== null): ?>
        <div class="mt-4 bg-gray-50 rounded-lg border border-gray-200 p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Next unlock: <span class="text-amber-600"><?= htmlspecialchars($tierNames[$nextTierLevel]) ?></span> — complete one more round</p>
            <?php if (!empty($tierUnlocks[$nextTierLevel])): ?>
            <ul class="flex flex-wrap gap-x-4 gap-y-1">
                <?php foreach ($tierUnlocks[$nextTierLevel] as $unlock): ?>
                <li class="text-xs text-gray-500 flex items-center"><span class="text-amber-400 mr-1">&#8594;</span><?= htmlspecialchars($unlock) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="mt-4 bg-amber-50 rounded-lg border border-amber-200 p-4 text-center">
            <p class="text-sm font-semibold text-amber-700">Maximum tier reached. You are the Inner Circle of Ruin. We're sorry.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Create New Trust Fund Baby -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Create New Trust Fund Baby</h2>
        <form method="POST" action="<?= url('/account/player/create') ?>" class="flex items-end space-x-4">
            <?= $h::csrfField($csrf_token) ?>
            <div class="flex-1">
                <label for="player_name" class="block text-sm font-medium text-gray-700">Baby Name</label>
                <input type="text" id="player_name" name="player_name" required
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 px-4 py-2 border"
                    placeholder="Name your little money furnace">
            </div>
            <button type="submit"
                class="rounded-lg bg-red-600 px-6 py-2 text-sm font-bold text-white hover:bg-red-700 transition-colors">
                Create Trust Fund Baby
            </button>
        </form>
    </div>

    <!-- Players List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Your Trust Fund Babies</h2>
        </div>

        <?php if (empty($players)): ?>
            <div class="p-12 text-center">
                <div class="text-5xl mb-4">&#x1F4B8;</div>
                <h3 class="text-lg font-medium text-gray-900">No trust fund babies yet.</h3>
                <p class="mt-1 text-gray-500">Create one and start burning through \$1 billion!</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($players as $p): ?>
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex-1">
                            <h3 class="text-base font-semibold text-gray-900">
                                <?= htmlspecialchars($p->PlayerName) ?>
                            </h3>
                            <div class="mt-1 flex items-center space-x-4 text-sm">
                                <span class="text-gray-500">Turn <b class="text-gray-700"><?= $p->Turn ?></b> / 611</span>
                                <?php if ($p->BankruptTurn > 0): ?>
                                    <span class="font-bold text-green-600">BANKRUPT at Turn <?= $p->BankruptTurn ?>!</span>
                                <?php else: ?>
                                    <span class="font-semibold text-red-600">Still Rich (Turn <?= $p->Turn ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php $progress = min(100, round(($p->Turn / 611) * 100)); ?>
                            <div class="mt-2 w-full max-w-xs bg-gray-200 rounded-full h-2">
                                <div class="bg-red-600 h-2 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <a href="<?= url("/account/player/{$p->PlayerID}/play") ?>"
                                class="rounded-lg bg-amber-500 px-5 py-2 text-sm font-bold text-white hover:bg-amber-600 transition-colors">
                                Play
                            </a>
                            <form method="POST" action="<?= url("/account/player/{$p->PlayerID}/delete") ?>"
                                onsubmit="return confirm('Delete this trust fund baby? This cannot be undone!')">
                                <?= $h::csrfField($csrf_token) ?>
                                <button type="submit"
                                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200 transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
