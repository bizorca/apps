<?php
$h = \App\Core\Helpers::class;

$toyCategories = [
    0 => 'Supercar', 1 => 'Megayacht', 2 => 'Private Jet', 3 => 'Fine Art',
    4 => 'Racehorse', 5 => 'Space Tourism', 6 => 'NFT Collection',
    7 => 'Designer Wardrobe', 8 => 'Exotic Car Fleet', 9 => 'Wine Collection',
];

$categoryIcons = [
    0 => 'bg-red-100 text-red-700', 1 => 'bg-blue-100 text-blue-700',
    2 => 'bg-sky-100 text-sky-700', 3 => 'bg-purple-100 text-purple-700',
    4 => 'bg-green-100 text-green-700', 5 => 'bg-indigo-100 text-indigo-700',
    6 => 'bg-pink-100 text-pink-700', 7 => 'bg-fuchsia-100 text-fuchsia-700',
    8 => 'bg-orange-100 text-orange-700', 9 => 'bg-amber-100 text-amber-700',
];

// Separate owned from available
$ownedToys = [];
$availableToys = [];
if (!empty($toySummary['toys'])) {
    $ownedToys = $toySummary['toys'];
}

// Get all toys to find available ones
$stockModel = new \App\Models\Stock($app->db);
$allToys = $stockModel->getAll($player->PlayerID);
foreach ($allToys as $toy) {
    if ($toy->NumberShares == 0) {
        $availableToys[] = $toy;
    }
}
?>

<h3 class="text-lg font-bold text-amber-800 mb-1">Toys & Luxuries</h3>
<p class="text-sm text-amber-600 mb-6">Depreciating assets disguised as status symbols. Every one of them bleeds money.</p>

<!-- Owned Toys -->
<?php if (!empty($ownedToys)): ?>
<div class="mb-8">
    <h4 class="text-sm font-medium text-amber-700 uppercase tracking-wide mb-3">
        Your Collection (<?= count($ownedToys) ?>)
        <span class="text-amber-600 ml-2">Total Value: <span class="font-bold"><?= $h::money($toySummary['totalValue']) ?></span></span>
        <?php if ($toySummary['totalLoss'] > 0): ?>
        <span class="text-red-600 ml-2">Total Loss: <span class="font-bold">-<?= $h::money($toySummary['totalLoss']) ?></span></span>
        <?php endif; ?>
    </h4>

    <div class="space-y-4">
        <?php foreach ($ownedToys as $toy): ?>
        <?php
            $loss = $toy->PurchasePrice - $toy->StockPrice;
            $monthlyCost = (float) ($toy->MonthlyCost ?? 0);
            $catId = (int) $toy->IndustryID;
            $catName = $toyCategories[$catId] ?? 'Luxury Item';
            $catColors = $categoryIcons[$catId] ?? 'bg-gray-100 text-gray-700';
        ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5" x-data="{ showSell: false }">
            <div class="flex justify-between items-start">
                <div>
                    <h5 class="text-base font-bold text-gray-900"><?= htmlspecialchars($toy->StockSymbol) ?></h5>
                    <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full <?= $catColors ?> font-medium">
                        <?= $catName ?>
                    </span>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-gray-900"><?= $h::money($toy->StockPrice) ?></p>
                    <p class="text-xs <?= $toy->StockPriceChange >= 0 ? 'text-amber-600' : 'text-red-600' ?> font-medium">
                        <?= $toy->StockPriceChange >= 0 ? '+' : '' ?><?= $h::money($toy->StockPriceChange) ?> this turn
                    </p>
                </div>
            </div>

            <!-- Value Comparison -->
            <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white rounded-lg p-3 border border-amber-100">
                    <p class="text-xs text-gray-500">Purchase Price</p>
                    <p class="text-sm font-bold text-gray-700"><?= $h::money($toy->PurchasePrice) ?></p>
                </div>
                <div class="bg-white rounded-lg p-3 border border-amber-100">
                    <p class="text-xs text-gray-500">Current Value</p>
                    <p class="text-sm font-bold text-gray-700"><?= $h::money($toy->StockPrice) ?></p>
                </div>
                <div class="bg-amber-100 rounded-lg p-3 border border-amber-200">
                    <p class="text-xs text-amber-600">Monthly Upkeep</p>
                    <p class="text-sm font-bold text-amber-700">-<?= $h::money($monthlyCost) ?></p>
                </div>
                <div class="<?= $loss > 0 ? 'bg-red-100 border-red-200' : 'bg-green-100 border-green-200' ?> rounded-lg p-3 border">
                    <p class="text-xs <?= $loss > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= $loss > 0 ? 'Loss' : 'Gain' ?></p>
                    <p class="text-sm font-bold <?= $loss > 0 ? 'text-red-700' : 'text-green-700' ?>">
                        <?= $loss > 0 ? '-' : '+' ?><?= $h::money(abs($loss)) ?>
                    </p>
                </div>
            </div>

            <!-- Depreciation This Turn -->
            <div class="mt-2 text-xs text-gray-500">
                Value change this turn:
                <span class="font-bold <?= $toy->StockPriceChange >= 0 ? 'text-amber-600' : 'text-red-600' ?>">
                    <?= $toy->StockPriceChange >= 0 ? '+' : '' ?><?= $h::money($toy->StockPriceChange) ?>
                </span>
            </div>

            <!-- Sell Button -->
            <div class="mt-4">
                <button @click="showSell = !showSell"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700 transition-colors">
                    Sell Toy
                </button>
                <div x-show="showSell" x-cloak class="mt-3 bg-amber-100 rounded-lg p-3 border border-amber-200">
                    <p class="text-sm text-amber-700 mb-2">
                        Sell at current value minus 15% commission: <b><?= $h::money(round($toy->StockPrice * 0.85, 2)) ?></b>
                    </p>
                    <form method="POST" action="<?= url('/game/stock/sell') ?>" class="flex items-center space-x-2">
                        <?= $h::csrfField($csrf_token) ?>
                        <input type="hidden" name="stock_id" value="<?= $toy->StockID ?>">
                        <button type="submit" class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-bold text-white hover:bg-amber-800"
                            onclick="return confirm('Sell for <?= $h::money(round($toy->StockPrice * 0.85, 2)) ?>?')">
                            Confirm Sale
                        </button>
                        <button type="button" @click="showSell = false" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-300">
                            Cancel
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Available Toys to Buy -->
<?php if (!empty($availableToys)): ?>
<div>
    <h4 class="text-sm font-medium text-amber-700 uppercase tracking-wide mb-3">Available Toys (<?= count($availableToys) ?>)</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($availableToys as $toy): ?>
        <?php
            $catId = (int) $toy->IndustryID;
            $catName = $toyCategories[$catId] ?? 'Luxury Item';
            $catColors = $categoryIcons[$catId] ?? 'bg-gray-100 text-gray-700';
            $monthlyCost = (float) ($toy->MonthlyCost ?? 0);
        ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
            <div class="flex justify-between items-start">
                <div>
                    <h5 class="font-bold text-gray-900"><?= htmlspecialchars($toy->StockSymbol) ?></h5>
                    <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full <?= $catColors ?> font-medium">
                        <?= $catName ?>
                    </span>
                </div>
                <span class="text-xl font-bold text-amber-700"><?= $h::money($toy->StockPrice) ?></span>
            </div>

            <!-- Monthly Upkeep Warning -->
            <?php if ($monthlyCost > 0): ?>
            <div class="mt-3 bg-red-50 border border-red-200 rounded-lg p-2">
                <p class="text-xs text-red-700 font-medium">
                    Monthly Upkeep: <span class="font-bold"><?= $h::money($monthlyCost) ?>/mo</span>
                    - this thing bleeds money every single turn
                </p>
            </div>
            <?php else: ?>
            <div class="mt-3 bg-amber-100 border border-amber-200 rounded-lg p-2">
                <p class="text-xs text-amber-700 font-medium">No monthly upkeep. But don't worry, it'll depreciate plenty.</p>
            </div>
            <?php endif; ?>

            <div class="mt-2 text-xs text-gray-500">
                <span>Value change: <span class="<?= $toy->StockPriceChange >= 0 ? 'text-amber-600' : 'text-red-600' ?>"><?= $toy->StockPriceChange >= 0 ? '+' : '' ?><?= $h::money($toy->StockPriceChange) ?></span></span>
            </div>

            <!-- Buy Button -->
            <form method="POST" action="<?= url('/game/stock/buy') ?>" class="mt-3">
                <?= $h::csrfField($csrf_token) ?>
                <input type="hidden" name="stock_id" value="<?= $toy->StockID ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit"
                    class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-700 transition-colors"
                    onclick="return confirm('Buy <?= htmlspecialchars($toy->StockSymbol) ?> for <?= $h::money($toy->StockPrice) ?>?')">
                    Buy - <?= $h::money($toy->StockPrice) ?>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php elseif (empty($ownedToys)): ?>
<div class="text-center py-8 bg-amber-50 rounded-xl border border-amber-100">
    <p class="text-lg text-amber-700 font-semibold">No toys available right now.</p>
    <p class="mt-1 text-sm text-amber-500">The showroom is empty. New luxury items will appear next turn.</p>
</div>
<?php endif; ?>
