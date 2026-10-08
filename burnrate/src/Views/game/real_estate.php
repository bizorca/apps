<?php
$h = \App\Core\Helpers::class;
$reModel = new \App\Models\RealEstate($app->db);
$forSale = $reModel->getForSale($player->PlayerID);
?>

<h3 class="text-lg font-bold text-red-800 mb-1">Mansions & Islands</h3>
<p class="text-sm text-red-600 mb-6">Trophy properties that hemorrhage money. Every square foot is a liability.</p>

<!-- Owned Properties -->
<?php if (!empty($propertySummary['properties'])): ?>
<div class="mb-8">
    <h4 class="text-sm font-medium text-red-700 uppercase tracking-wide mb-3">
        Your Properties (<?= $propertySummary['count'] ?>)
        <span class="text-red-500 ml-2">Total Depreciation: <span class="font-bold"><?= $h::money($propertySummary['totalDepreciation']) ?></span></span>
    </h4>

    <div class="space-y-4">
        <?php foreach ($propertySummary['properties'] as $prop): ?>
        <?php
            $loss = $prop->PurchasePrice - $prop->CurrentValue;
            $sellValue = round($prop->CurrentValue * 0.90, 2);
            $avgRating = ($prop->RoofRating + $prop->KitchenRating + $prop->BathroomsRating
                        + $prop->FlooringRating + $prop->PaintRating + $prop->MajorSystemsRating) / 6;
            $monthlyInsurance = round((0.02 * $prop->CurrentValue) / 12, 2);
            $monthlyTaxes = round(($prop->TaxRate / 100 * $prop->CurrentValue) / 12, 2);
            $maintenanceCost = round((100 - $avgRating) / 100 * $prop->CurrentValue * 0.003, 2);
            $totalMonthly = $prop->StaffCost + $maintenanceCost + $monthlyInsurance + $monthlyTaxes;
        ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-5" x-data="{ showSell: false }">
            <div class="flex justify-between items-start">
                <div>
                    <h5 class="text-base font-bold text-gray-900"><?= htmlspecialchars($prop->Description) ?></h5>
                    <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium uppercase">
                        <?= htmlspecialchars($prop->PropertyType ?? 'mansion') ?>
                    </span>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-gray-900"><?= $h::money($prop->CurrentValue) ?></p>
                    <p class="text-xs text-red-600 font-medium">
                        Depreciation: <?= number_format($prop->AppreciationRate, 2) ?>%/yr
                    </p>
                </div>
            </div>

            <!-- Purchase vs Current Value -->
            <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-3">
                <div class="bg-white rounded-lg p-3 border border-red-100">
                    <p class="text-xs text-gray-500">Purchase Price</p>
                    <p class="text-sm font-bold text-gray-700"><?= $h::money($prop->PurchasePrice) ?></p>
                </div>
                <div class="bg-white rounded-lg p-3 border border-red-100">
                    <p class="text-xs text-gray-500">Current Value</p>
                    <p class="text-sm font-bold text-gray-700"><?= $h::money($prop->CurrentValue) ?></p>
                </div>
                <div class="bg-red-100 rounded-lg p-3 border border-red-200">
                    <p class="text-xs text-red-600">Total Loss</p>
                    <p class="text-sm font-bold text-red-700"><?= $loss > 0 ? '-' : '' ?><?= $h::money(abs($loss)) ?></p>
                </div>
            </div>

            <!-- Monthly Costs Breakdown -->
            <div class="mt-3">
                <p class="text-xs font-medium text-red-700 uppercase tracking-wide mb-2">Monthly Costs Breakdown</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <div class="bg-white rounded-lg p-2 border border-red-100 text-center">
                        <p class="text-xs text-gray-500">Staff</p>
                        <p class="text-sm font-bold text-red-600"><?= $h::money($prop->StaffCost) ?></p>
                    </div>
                    <div class="bg-white rounded-lg p-2 border border-red-100 text-center">
                        <p class="text-xs text-gray-500">Maintenance</p>
                        <p class="text-sm font-bold text-red-600"><?= $h::money($maintenanceCost) ?></p>
                    </div>
                    <div class="bg-white rounded-lg p-2 border border-red-100 text-center">
                        <p class="text-xs text-gray-500">Insurance</p>
                        <p class="text-sm font-bold text-red-600"><?= $h::money($monthlyInsurance) ?></p>
                    </div>
                    <div class="bg-white rounded-lg p-2 border border-red-100 text-center">
                        <p class="text-xs text-gray-500">Property Tax</p>
                        <p class="text-sm font-bold text-red-600"><?= $h::money($monthlyTaxes) ?></p>
                    </div>
                </div>
                <div class="mt-2 bg-red-100 rounded-lg p-2 text-center border border-red-200">
                    <p class="text-xs text-red-600">Total Monthly Drain</p>
                    <p class="text-lg font-bold text-red-700">-<?= $h::money($totalMonthly) ?></p>
                </div>
            </div>

            <!-- Condition Ratings -->
            <div class="mt-3">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Property Condition</p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ([
                        'Roof' => $prop->RoofRating,
                        'Kitchen' => $prop->KitchenRating,
                        'Bathrooms' => $prop->BathroomsRating,
                        'Flooring' => $prop->FlooringRating,
                        'Paint' => $prop->PaintRating,
                        'Systems' => $prop->MajorSystemsRating,
                    ] as $label => $rating): ?>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs text-gray-600 w-16"><?= $label ?></span>
                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all <?= $rating > 70 ? 'bg-amber-500' : ($rating > 40 ? 'bg-red-400' : 'bg-red-600') ?>"
                                 style="width: <?= min(100, $rating) ?>%"></div>
                        </div>
                        <span class="text-xs font-medium <?= $rating > 70 ? 'text-amber-600' : ($rating > 40 ? 'text-red-500' : 'text-red-700') ?>"><?= number_format($rating, 0) ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sell Button -->
            <div class="mt-4">
                <button @click="showSell = !showSell"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 transition-colors">
                    Sell Property
                </button>
                <div x-show="showSell" x-cloak class="mt-3 bg-red-100 rounded-lg p-3 border border-red-200">
                    <p class="text-sm text-red-700 mb-2">
                        Sell at current value minus 10% realtor fee: <b><?= $h::money($sellValue) ?></b>
                    </p>
                    <form method="POST" action="<?= url('/game/re/sell') ?>" class="flex items-center space-x-2">
                        <?= $h::csrfField($csrf_token) ?>
                        <input type="hidden" name="re_id" value="<?= $prop->REID ?>">
                        <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800"
                            onclick="return confirm('Sell for <?= $h::money($sellValue) ?>? This cannot be undone.')">
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

<!-- Available Properties For Sale -->
<?php if (!empty($forSale)): ?>
<div>
    <h4 class="text-sm font-medium text-red-700 uppercase tracking-wide mb-3">Available Properties (<?= count($forSale) ?>)</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($forSale as $prop): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-5" x-data="{ expanded: false }">
            <div class="flex justify-between items-start">
                <div>
                    <h5 class="font-bold text-gray-900"><?= htmlspecialchars($prop->Description) ?></h5>
                    <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium uppercase">
                        <?= htmlspecialchars($prop->PropertyType ?? 'mansion') ?>
                    </span>
                </div>
                <span class="text-xl font-bold text-red-700"><?= $h::money($prop->AskingPrice) ?></span>
            </div>

            <!-- Cash Purchase Badge -->
            <div class="mt-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                    Cash Purchase - No Mortgage Needed
                </span>
            </div>

            <!-- Condition Ratings -->
            <div class="mt-3 flex flex-wrap gap-1">
                <?php foreach ([
                    'Roof' => $prop->RoofRating, 'Kitchen' => $prop->KitchenRating, 'Bath' => $prop->BathroomsRating,
                    'Floor' => $prop->FlooringRating, 'Paint' => $prop->PaintRating, 'Systems' => $prop->MajorSystemsRating
                ] as $label => $rating): ?>
                <span class="text-xs px-1.5 py-0.5 rounded <?= $rating > 70 ? 'bg-amber-100 text-amber-700' : ($rating > 40 ? 'bg-red-100 text-red-600' : 'bg-red-200 text-red-800') ?>">
                    <?= $label ?>: <?= number_format($rating, 0) ?>%
                </span>
                <?php endforeach; ?>
            </div>

            <div class="mt-2 text-xs text-red-600">
                <span>Depreciation: <?= number_format($prop->AppreciationRate, 2) ?>%/yr</span>
                <span class="ml-3">Staff: <?= $h::money($prop->StaffCost) ?>/mo</span>
                <span class="ml-3">Tax: <?= number_format($prop->TaxRate, 2) ?>%</span>
            </div>

            <!-- Buy at Asking Price -->
            <div class="mt-3">
                <form method="POST" action="<?= url('/game/re/offer') ?>">
                    <?= $h::csrfField($csrf_token) ?>
                    <input type="hidden" name="re_id" value="<?= $prop->REID ?>">
                    <input type="hidden" name="offer_price" value="<?= $prop->AskingPrice ?>">
                    <button type="submit"
                        class="w-full rounded-lg bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800 transition-colors"
                        onclick="return confirm('Buy for <?= $h::money($prop->AskingPrice) ?> cash?')">
                        Buy Now - <?= $h::money($prop->AskingPrice) ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php elseif (empty($propertySummary['properties'])): ?>
<div class="text-center py-8 bg-red-50 rounded-xl border border-red-100">
    <p class="text-lg text-red-700 font-semibold">No properties available right now.</p>
    <p class="mt-1 text-sm text-red-500">The real estate market is quiet. Check back next turn.</p>
</div>
<?php endif; ?>
