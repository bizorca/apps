<?php
$title = $player->PlayerName . ' - Turn ' . $player->Turn . ' - Burn Rate';
$h = \App\Core\Helpers::class;
$netWorth = $bankBalance + ($propertySummary['totalEquity'] ?? 0) + ($toySummary['totalValue'] ?? 0) + ($investSummary['totalCurrentValue'] ?? 0);
$monthsLeft = 611 - $player->Turn;
$memberLevel = (int) $app->memberLevel();

// Check for game messages
$gameMessage = $app->session->get('game_message');
$app->session->remove('game_message');
?>
<?php ob_start(); ?>

<!-- Game Message Modal -->
<?php if ($gameMessage): ?>
<div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden" @click.away="open = false">
        <div class="bg-gradient-to-r from-red-600 to-red-800 px-6 py-4">
            <h3 class="text-lg font-bold text-white"><?= $gameMessage['title'] ?? 'Update' ?></h3>
        </div>
        <div class="px-6 py-4">
            <?= $gameMessage['body'] ?? '' ?>
        </div>
        <?php if (!empty($gameMessage['counteroffer'])): ?>
        <?php $co = $gameMessage['counteroffer']; ?>
        <div class="px-6 py-4 bg-gray-50 space-y-3">
            <!-- Accept counter offer -->
            <form method="POST" action="<?= url('/game/re/offer') ?>" class="inline">
                <?= $h::csrfField($csrf_token) ?>
                <input type="hidden" name="re_id" value="<?= $co['re_id'] ?>">
                <input type="hidden" name="offer_price" value="<?= $co['counter_price'] ?>">
                <button type="submit" class="w-full rounded-lg bg-red-600 px-6 py-2 text-sm font-bold text-white hover:bg-red-700">
                    Accept Counter Offer ($<?= number_format($co['counter_price'], 2) ?>)
                </button>
            </form>
            <!-- Counter with new price -->
            <form method="POST" action="<?= url('/game/re/offer') ?>" class="flex items-center space-x-2" x-data="{ showCounter: false }">
                <?= $h::csrfField($csrf_token) ?>
                <input type="hidden" name="re_id" value="<?= $co['re_id'] ?>">
                <template x-if="!showCounter">
                    <button type="button" @click="showCounter = true" class="w-full rounded-lg bg-amber-500 px-6 py-2 text-sm font-bold text-white hover:bg-amber-600">
                        Make Counter Offer
                    </button>
                </template>
                <template x-if="showCounter">
                    <div class="flex items-center space-x-2 w-full">
                        <span class="text-sm font-medium text-gray-700">$</span>
                        <input type="number" name="offer_price" step="0.01"
                            value="<?= round(($co['counter_price'] + $co['original_offer']) / 2, 2) ?>"
                            class="flex-1 border rounded-lg px-3 py-2 text-sm">
                        <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-white hover:bg-amber-600">
                            Submit
                        </button>
                    </div>
                </template>
            </form>
            <!-- Walk away -->
            <button @click="open = false" class="w-full rounded-lg bg-gray-200 px-6 py-2 text-sm font-bold text-gray-700 hover:bg-gray-300">
                Walk Away
            </button>
        </div>
        <?php else: ?>
        <div class="px-6 py-3 bg-gray-50 text-right">
            <button @click="open = false" class="rounded-lg bg-red-600 px-6 py-2 text-sm font-bold text-white hover:bg-red-700">
                Continue
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Forced Sale Modal (negative bank balance) -->
<?php if (!empty($forcedSale)): ?>
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-red-700 to-red-900 px-6 py-5 text-center">
            <h3 class="text-2xl font-black text-white">CASH CRISIS!</h3>
            <p class="text-red-200 mt-1">Your bank balance is <span class="text-yellow-300 font-bold"><?= $h::money($bankBalance) ?></span></p>
            <p class="text-red-300 text-sm mt-1">You must sell something to cover your expenses.</p>
        </div>

        <?php if (!empty($forcedSaleItems)): ?>
        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-gray-600 font-medium">Choose an asset to liquidate:</p>

            <?php foreach ($forcedSaleItems as $item): ?>
            <?php
                $typeBadge = match($item['type']) {
                    'property' => ['Mansion/Island', 'bg-red-100 text-red-700'],
                    'toy' => ['Toy/Luxury', 'bg-amber-100 text-amber-700'],
                    'investment' => ['Investment', 'bg-purple-100 text-purple-700'],
                    default => ['Asset', 'bg-gray-100 text-gray-700'],
                };
            ?>
            <div class="border border-gray-200 rounded-xl p-4 flex items-center justify-between hover:border-red-300 transition-colors">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs px-2 py-0.5 rounded-full <?= $typeBadge[1] ?> font-medium"><?= $typeBadge[0] ?></span>
                    </div>
                    <p class="font-bold text-gray-900 truncate"><?= htmlspecialchars($item['name']) ?></p>
                    <p class="text-sm text-gray-500">
                        Value: <?= $h::money($item['value']) ?>
                        &rarr; You receive: <span class="font-bold text-green-600"><?= $h::money($item['saleProceeds']) ?></span>
                    </p>
                </div>
                <form method="POST" action="<?= $item['formAction'] ?>" class="ml-4 flex-shrink-0">
                    <?= $h::csrfField($csrf_token) ?>
                    <input type="hidden" name="<?= $item['fieldName'] ?>" value="<?= $item['id'] ?>">
                    <button type="submit" class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-700 transition-colors"
                        onclick="return confirm('Force sell <?= htmlspecialchars($item['name']) ?> for <?= $h::money($item['saleProceeds']) ?>?')">
                        Force Sell
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="px-6 pb-5">
            <p class="text-xs text-gray-400 italic text-center">"When the bill comes due, even billionaires have to pawn the yacht."</p>
        </div>

        <?php else: ?>
        <div class="px-6 py-8 text-center">
            <p class="text-lg font-semibold text-gray-700 mb-2">No assets left to sell.</p>
            <p class="text-sm text-gray-500 mb-4">You've got nothing left. The bankruptcy check will handle the rest.</p>
            <a href="<?= url('/game') ?>" class="inline-block rounded-lg bg-red-600 px-6 py-3 text-sm font-bold text-white hover:bg-red-700">
                Face the Music
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Top Stats Bar -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <!-- Turn Counter -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Turn</p>
        <p class="text-2xl font-bold text-gray-900"><?= $player->Turn ?><span class="text-sm text-gray-400">/611</span></p>
        <p class="text-xs text-gray-400"><?= $h::turnToYearsMonths($player->Turn) ?></p>
        <p class="text-xs font-semibold text-red-500 mt-1"><?= $monthsLeft ?> Months Until Shame</p>
    </div>

    <!-- Bank Balance (inverted: RED if high/still rich = bad, GREEN if low = good) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Bank Balance</p>
        <p class="text-2xl font-bold <?= $bankBalance > 1000000000 ? 'text-red-600' : ($bankBalance > 100000000 ? 'text-orange-500' : 'text-green-600') ?>">
            <?= $h::money($bankBalance) ?>
        </p>
        <?php if ($bankBalance > 1000000000): ?>
            <p class="text-xs text-red-400">Still disgustingly rich</p>
        <?php elseif ($bankBalance > 100000000): ?>
            <p class="text-xs text-orange-400">Getting there...</p>
        <?php else: ?>
            <p class="text-xs text-green-500">Running low! Nice!</p>
        <?php endif; ?>
    </div>

    <!-- Net Worth (inverted: RED if high, GREEN if low) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Net Worth</p>
        <p class="text-2xl font-bold <?= $netWorth > 1000000000 ? 'text-red-600' : ($netWorth > 100000000 ? 'text-orange-500' : 'text-green-600') ?>">
            <?= $h::money($netWorth) ?>
        </p>
        <p class="text-xs text-gray-400"><?= $h::money(abs($netWorth)) ?> from $0</p>
    </div>

    <!-- Monthly Burn Rate -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Monthly Burn</p>
        <p class="text-2xl font-bold text-red-500"><?= $h::money($monthlyBurn) ?></p>
        <p class="text-xs text-gray-400">Going up in flames</p>
    </div>

    <!-- Random Quote -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center">
        <p class="text-xs italic text-gray-500 leading-relaxed">"<?= htmlspecialchars($quote) ?>"</p>
    </div>
</div>

<!-- Tabbed Navigation -->
<div x-data="{ tab: '<?= htmlspecialchars($activeTab) ?>' }" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

    <!-- Tabs -->
    <div class="border-b border-gray-200 overflow-x-auto">
        <nav class="flex -mb-px">
            <?php
            $tabs = [
                'summary'    => ['label' => 'Damage Report',       'icon' => '<svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"/></svg>'],
                'actions'    => ['label' => 'Blow Money',           'icon' => '<span class="inline-flex items-center justify-center w-4 h-4 bg-red-100 text-red-600 rounded-full text-xs font-black -mt-0.5 mr-1">!</span>'],
                'realestate' => ['label' => 'Mansions & Islands',   'icon' => '<svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"/></svg>'],
                'stocks'     => ['label' => 'Toys & Luxuries',     'icon' => '<svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'],
                'businesses' => ['label' => 'Bad Investments',      'icon' => '<svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>'],
                'dreamteam'  => ['label' => 'The Entourage',        'icon' => '<svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'],
                'economy'    => ['label' => 'Economic Chaos',       'icon' => '<svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'],
            ];
            foreach ($tabs as $key => $t):
            ?>
                <button @click="tab = '<?= $key ?>'; window.history.replaceState({}, '', '<?= url('/game') ?>?tab=<?= $key ?>')"
                    :class="tab === '<?= $key ?>' ? 'border-red-500 text-red-600 bg-red-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap border-b-2 py-3 px-4 text-sm font-medium transition-colors">
                    <?= $t['icon'] ?><?= $t['label'] ?>
                </button>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="p-6">

        <!-- DAMAGE REPORT TAB (Summary) -->
        <div x-show="tab === 'summary'" x-cloak>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Bank Balance Chart -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-red-700">Bank Balance History</h3>
                    <canvas id="bankChart" height="200"></canvas>
                </div>

                <!-- Recent Transactions -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-red-700">Recent Hemorrhaging</h3>
                    <div class="overflow-y-auto max-h-80">
                        <table class="min-w-full text-sm">
                            <thead class="bg-red-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-red-700">Turn</th>
                                    <th class="px-3 py-2 text-left font-medium text-red-700">Description</th>
                                    <th class="px-3 py-2 text-right font-medium text-red-700">Amount</th>
                                    <th class="px-3 py-2 text-right font-medium text-red-700">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($recentTransactions as $tx): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-gray-500"><?= $tx->Turn ?></td>
                                    <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars($tx->Description) ?></td>
                                    <td class="px-3 py-2 text-right <?= $tx->Credit > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= $tx->Credit > 0 ? '+' . $h::money($tx->Credit) : '-' . $h::money($tx->Debit) ?>
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium"><?= $h::money($tx->Balance) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Portfolio Overview -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Properties -->
                <div class="bg-red-50 rounded-lg p-4 border border-red-100">
                    <h4 class="text-sm font-medium text-red-700">Mansions & Islands</h4>
                    <p class="text-xl font-bold text-red-900"><?= $propertySummary['count'] ?> Properties</p>
                    <p class="text-sm text-red-600">Value: <?= $h::money($propertySummary['totalValue']) ?></p>
                    <p class="text-sm text-red-600">Depreciation: <?= $h::money($propertySummary['totalDepreciation'] ?? 0) ?></p>
                    <p class="text-sm text-red-600">Monthly Costs: <?= $h::money(abs($propertySummary['totalCashFlow'])) ?>/mo</p>
                </div>

                <!-- Toys -->
                <div class="bg-amber-50 rounded-lg p-4 border border-amber-100">
                    <h4 class="text-sm font-medium text-amber-700">Toys & Luxuries</h4>
                    <p class="text-xl font-bold text-amber-900"><?= $toySummary['count'] ?> Toys</p>
                    <p class="text-sm text-amber-600">Value: <?= $h::money($toySummary['totalValue']) ?></p>
                    <p class="text-sm text-green-600">Total Loss: <?= $h::money($toySummary['totalLoss']) ?></p>
                </div>

                <!-- Investments -->
                <div class="bg-purple-50 rounded-lg p-4 border border-purple-100">
                    <h4 class="text-sm font-medium text-purple-700">Bad Investments</h4>
                    <p class="text-xl font-bold text-purple-900"><?= $investSummary['count'] ?? 0 ?> Investments</p>
                    <p class="text-sm text-purple-600">Current Value: <?= $h::money($investSummary['totalCurrentValue'] ?? 0) ?></p>
                    <p class="text-sm text-green-600">Total Losses: <?= $h::money($investSummary['totalLoss'] ?? 0) ?></p>
                </div>
            </div>
        </div>

        <!-- BLOW MONEY TAB (Actions) -->
        <div x-show="tab === 'actions'" x-cloak>
            <h3 class="text-lg font-semibold mb-2 text-red-700">How Would You Like to Waste Money Today?</h3>
            <p class="text-sm text-gray-500 mb-6">Each action burns one turn. Spend recklessly!</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Splurge Section -->
                <div class="border-2 border-red-200 rounded-lg p-4">
                    <h4 class="font-semibold text-red-700 mb-3">Splurge</h4>
                    <div class="space-y-2">
                        <a href="<?= url('/game/do/1') ?>?t=<?= urlencode($csrf_token) ?>" class="block rounded-lg bg-red-50 px-4 py-3 text-sm hover:bg-red-100 transition-colors">
                            <span class="font-medium text-red-800">Browse Mansions & Islands</span>
                            <p class="text-red-600 text-xs mt-1">Nothing says "burning money" like a third vacation home</p>
                        </a>
                        <a href="<?= url('/game/do/2') ?>?t=<?= urlencode($csrf_token) ?>" class="block rounded-lg bg-red-50 px-4 py-3 text-sm hover:bg-red-100 transition-colors">
                            <span class="font-medium text-red-800">Shop for Toys</span>
                            <p class="text-red-600 text-xs mt-1">Supercars, yachts, jets, and things that depreciate beautifully</p>
                        </a>
                    </div>
                </div>

                <!-- Invest Badly Section -->
                <div class="border-2 border-purple-200 rounded-lg p-4">
                    <h4 class="font-semibold text-purple-700 mb-3">Invest Badly</h4>
                    <div class="space-y-2">
                        <a href="<?= url('/game/do/101') ?>?t=<?= urlencode($csrf_token) ?>" class="block rounded-lg bg-purple-50 px-4 py-3 text-sm hover:bg-purple-100 transition-colors">
                            <span class="font-medium text-purple-800">Make a "Promising" Investment</span>
                            <p class="text-purple-600 text-xs mt-1">This one is definitely going to the moon (it's not)</p>
                        </a>
                    </div>
                </div>

                <!-- Social Section -->
                <div class="border-2 border-amber-200 rounded-lg p-4">
                    <h4 class="font-semibold text-amber-700 mb-3">Social</h4>
                    <div class="space-y-2">
                        <a href="<?= url('/game/do/201') ?>?t=<?= urlencode($csrf_token) ?>" class="block rounded-lg bg-amber-50 px-4 py-3 text-sm hover:bg-amber-100 transition-colors">
                            <span class="font-medium text-amber-800">Throw a Legendary Party</span>
                            <p class="text-amber-600 text-xs mt-1">Ice sculptures, live tigers, and regret</p>
                        </a>
                        <a href="<?= url('/game/do/301') ?>?t=<?= urlencode($csrf_token) ?>" class="block rounded-lg bg-amber-50 px-4 py-3 text-sm hover:bg-amber-100 transition-colors">
                            <span class="font-medium text-amber-800">Fly Friends In on Private Jet</span>
                            <p class="text-amber-600 text-xs mt-1">Because commercial flights are for people with budgets</p>
                        </a>
                    </div>
                </div>

                <!-- Vanity Section -->
                <div class="border-2 border-pink-200 rounded-lg p-4">
                    <h4 class="font-semibold text-pink-700 mb-3">Vanity</h4>
                    <div class="space-y-2">
                        <?php if ($memberLevel >= 1): ?>
                        <a href="<?= url('/game/do/501') ?>?t=<?= urlencode($csrf_token) ?>" class="block rounded-lg bg-pink-50 px-4 py-3 text-sm hover:bg-pink-100 transition-colors">
                            <span class="font-medium text-pink-800">Focus on Vanity Project</span>
                            <p class="text-pink-600 text-xs mt-1">A documentary about yourself? A fragrance line? Why not both?</p>
                        </a>
                        <?php else: ?>
                        <div class="block rounded-lg bg-gray-50 border border-dashed border-gray-300 px-4 py-3 text-sm opacity-60">
                            <span class="font-medium text-gray-500">&#128274; Focus on Vanity Project</span>
                            <p class="text-gray-400 text-xs mt-1">Unlocks at <b>Trust Fund Baby</b> tier — complete this round to unlock.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Entourage Actions -->
            <div class="mt-6 border-2 border-orange-200 rounded-lg p-4">
                <h4 class="font-semibold text-orange-700 mb-3">Upgrade Your Entourage</h4>
                <p class="text-xs text-gray-500 mb-3">Your team of professional enablers. Better ratings = more extravagant spending.</p>
                <?php if ($memberLevel >= 1): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <?php foreach ($entourage as $member): ?>
                        <?php if (!$member['maxed']): ?>
                        <a href="<?= url("/game/do/{$member['code']}") ?>?t=<?= urlencode($csrf_token) ?>"
                            class="block rounded-lg bg-orange-50 px-4 py-3 text-sm hover:bg-orange-100 transition-colors">
                            <span class="font-medium text-orange-800">Upgrade <?= $member['name'] ?></span>
                            <span class="<?= $h::ratingColor($member['rating']) ?> text-xs font-bold ml-1">(<?= number_format($member['rating'], 1) ?>%)</span>
                        </a>
                        <?php else: ?>
                        <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm">
                            <span class="font-medium text-gray-500"><?= $member['name'] ?></span>
                            <span class="text-amber-500 text-xs font-bold ml-1">MAX</span>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="rounded-xl bg-gray-50 border border-dashed border-gray-300 p-6 text-center opacity-70">
                    <p class="text-gray-500 text-sm font-medium">&#128274; Entourage upgrades locked</p>
                    <p class="text-gray-400 text-xs mt-1">Complete this round to unlock at <b>Trust Fund Baby</b> tier.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MANSIONS & ISLANDS TAB -->
        <div x-show="tab === 'realestate'" x-cloak>
            <?php include __DIR__ . '/real_estate.php'; ?>
        </div>

        <!-- TOYS & LUXURIES TAB -->
        <div x-show="tab === 'stocks'" x-cloak>
            <?php include __DIR__ . '/stocks.php'; ?>
        </div>

        <!-- BAD INVESTMENTS TAB -->
        <div x-show="tab === 'businesses'" x-cloak>
            <?php include __DIR__ . '/businesses.php'; ?>
        </div>

        <!-- THE ENTOURAGE TAB -->
        <div x-show="tab === 'dreamteam'" x-cloak>
            <?php include __DIR__ . '/dream_team.php'; ?>
        </div>

        <!-- ECONOMIC CHAOS TAB -->
        <div x-show="tab === 'economy'" x-cloak>
            <?php if ($memberLevel >= 256): ?>
                <?php include __DIR__ . '/economy.php'; ?>
            <?php else: ?>
            <div class="text-center py-16">
                <div class="text-5xl mb-4">&#128274;</div>
                <h3 class="text-xl font-bold text-gray-700 mb-2">Advanced Analytics Locked</h3>
                <p class="text-gray-500 mb-2">Economic breakdown charts unlock at <b>Gold Digger</b> tier.</p>
                <?php
                $roundsNeeded = match(true) {
                    $memberLevel >= 16 => 1,
                    $memberLevel >= 1  => 2,
                    default            => 3,
                };
                ?>
                <p class="text-sm text-gray-400">Complete <?= $roundsNeeded ?> more round(s) to unlock.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bank Chart Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= url('/api/chart/bank') ?>')
        .then(r => r.json())
        .then(data => {
            new Chart(document.getElementById('bankChart'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Bank Balance',
                        data: data.balance,
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.1)',
                        fill: true,
                        tension: 0.3,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { ticks: { callback: v => '$' + v.toLocaleString() } },
                        x: { title: { display: true, text: 'Turn' } }
                    }
                }
            });
        });
});
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
