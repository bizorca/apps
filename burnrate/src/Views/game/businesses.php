<?php
$h = \App\Core\Helpers::class;
$oneDollar = $player->OneDollar;
?>

<h3 class="text-lg font-bold text-purple-800 mb-1">Bad Investments</h3>
<p class="text-sm text-purple-600 mb-6">A graveyard of financial decisions. Each one seemed like a great idea at the time.</p>

<!-- Active Investments -->
<?php if (!empty($investSummary['investments'])): ?>
<div class="mb-6">
    <h4 class="text-sm font-medium text-purple-700 uppercase tracking-wide mb-1">
        Active Investments (<?= $investSummary['count'] ?>)
    </h4>
    <div class="flex flex-wrap gap-4 text-sm text-purple-600 mb-4">
        <span>Total Invested: <b><?= $h::money($investSummary['totalInvested']) ?></b></span>
        <span>Current Value: <b><?= $h::money($investSummary['totalCurrentValue']) ?></b></span>
        <?php if ($investSummary['totalLoss'] > 0): ?>
        <span class="text-red-600">Total Loss: <b>-<?= $h::money($investSummary['totalLoss']) ?></b></span>
        <?php endif; ?>
        <span>Monthly Fees: <b class="text-red-600">-<?= $h::money($investSummary['totalMonthlyFees']) ?></b></span>
    </div>

    <div class="space-y-4">
        <?php foreach ($investSummary['investments'] as $inv): ?>
        <?php
            $currentValue = (float) $inv->CurrentValue;
            $purchasePrice = (float) $inv->PurchasePrice;
            $loss = $purchasePrice - $currentValue;
            $monthlyFees = round($inv->ExpensesPerTurn * $oneDollar, 2);
            $profiting = $currentValue > $purchasePrice;
            $penalty = round($currentValue * 0.05, 2);
            $cashOutValue = round($currentValue - $penalty, 2);
        ?>
        <div class="bg-purple-50 border border-purple-200 rounded-xl p-5" x-data="{ showLiquidate: false }">
            <div class="flex justify-between items-start">
                <div>
                    <h5 class="text-base font-bold text-gray-900"><?= htmlspecialchars($inv->ShortDescription) ?></h5>
                    <?php if (!empty($inv->Category)): ?>
                    <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-medium uppercase">
                        <?= htmlspecialchars($inv->Category) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="text-right">
                    <!-- Performance Indicator -->
                    <?php if ($profiting): ?>
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-300">
                        Somehow Profiting
                    </span>
                    <?php else: ?>
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700 border border-red-300">
                        Losing Money
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Investment Values -->
            <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white rounded-lg p-3 border border-purple-100">
                    <p class="text-xs text-gray-500">Original Investment</p>
                    <p class="text-sm font-bold text-gray-700"><?= $h::money($purchasePrice) ?></p>
                </div>
                <div class="bg-white rounded-lg p-3 border border-purple-100">
                    <p class="text-xs text-gray-500">Current Value</p>
                    <p class="text-sm font-bold text-gray-700"><?= $h::money($currentValue) ?></p>
                </div>
                <div class="<?= $loss > 0 ? 'bg-red-100 border-red-200' : 'bg-green-100 border-green-200' ?> rounded-lg p-3 border">
                    <p class="text-xs <?= $loss > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= $loss > 0 ? 'Total Loss' : 'Total Gain' ?></p>
                    <p class="text-sm font-bold <?= $loss > 0 ? 'text-red-700' : 'text-green-700' ?>">
                        <?= $loss > 0 ? '-' : '+' ?><?= $h::money(abs($loss)) ?>
                    </p>
                </div>
                <div class="bg-purple-100 rounded-lg p-3 border border-purple-200">
                    <p class="text-xs text-purple-600">Monthly Fees</p>
                    <p class="text-sm font-bold text-purple-700">-<?= $h::money($monthlyFees) ?></p>
                </div>
            </div>

            <!-- Liquidate Button -->
            <div class="mt-4">
                <button @click="showLiquidate = !showLiquidate"
                    class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-bold text-white hover:bg-purple-700 transition-colors">
                    Cash Out (5% penalty)
                </button>
                <div x-show="showLiquidate" x-cloak class="mt-3 bg-purple-100 rounded-lg p-3 border border-purple-200">
                    <p class="text-sm text-purple-700 mb-1">
                        Current Value: <b><?= $h::money($currentValue) ?></b>
                    </p>
                    <p class="text-sm text-purple-700 mb-1">
                        Early Withdrawal Penalty (5%): <b class="text-red-600">-<?= $h::money($penalty) ?></b>
                    </p>
                    <p class="text-sm text-purple-700 mb-2">
                        You'll Receive: <b><?= $h::money($cashOutValue) ?></b>
                    </p>
                    <form method="POST" action="<?= url('/game/biz/liquidate') ?>" class="flex items-center space-x-2">
                        <?= $h::csrfField($csrf_token) ?>
                        <input type="hidden" name="biz_id" value="<?= $inv->BusinessID ?>">
                        <button type="submit" class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-bold text-white hover:bg-purple-800"
                            onclick="return confirm('Liquidate for <?= $h::money($cashOutValue) ?>? This cannot be undone.')">
                            Confirm Liquidation
                        </button>
                        <button type="button" @click="showLiquidate = false" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-300">
                            Cancel
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="text-center py-8 bg-purple-50 rounded-xl border border-purple-100 mb-6">
    <p class="text-lg text-purple-700 font-semibold">No active investments.</p>
    <p class="mt-1 text-sm text-purple-500">Your portfolio is blissfully empty. It won't stay that way.</p>
</div>
<?php endif; ?>

<!-- Redirect to Actions -->
<div class="bg-purple-100 border border-purple-200 rounded-xl p-5 text-center">
    <p class="text-sm text-purple-800 font-medium">
        Looking to throw money at something new?
    </p>
    <p class="text-sm text-purple-600 mt-1">
        Visit the <b>"Blow Money"</b> tab to find new investment opportunities.
    </p>
    <a href="<?= url('/game?tab=actions') ?>"
        class="mt-3 inline-block rounded-lg bg-purple-600 px-6 py-2 text-sm font-bold text-white hover:bg-purple-700 transition-colors">
        Browse Bad Ideas
    </a>
</div>
