<?php
$h = \App\Core\Helpers::class;

$enablerEffects = [
    'Personal Shopper' => 'Luxury item markups (up to +20%)',
    'Party Planner' => 'Party and entertainment costs (up to +100%)',
    'Yes Man' => 'All spending across the board (up to +15%)',
    'Celebrity Chef' => 'Food and dining costs (up to +50%)',
    'Fashion Consultant' => 'Wardrobe and clothing costs (up to +30%)',
    'Art Dealer' => 'Art purchase markups (up to +25%)',
    'Interior Designer' => 'Renovation and redecorating costs (up to +30%)',
    'Travel Agent' => 'Travel and vacation costs (up to +40%)',
    'Social Media Manager' => 'Lifestyle and image costs (up to +20%)',
    'Life Coach' => 'Self-care and wellness costs (up to +30%)',
    'Astrologer' => 'Bad investment markups (up to +15%)',
    'Concierge' => 'Surcharges on everything (up to +10%)',
];
?>

<h3 class="text-lg font-bold text-orange-800 mb-1">Your Entourage</h3>
<p class="text-sm text-orange-600 mb-6">The people who help you spend faster. Every upgrade makes them better at draining your fortune.</p>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($entourage as $enabler): ?>
    <?php
        $upgradeCost = round(50000 * $player->OneDollar * (1 + $enabler['rating'] / 100), 2);
        $effect = $enablerEffects[$enabler['name']] ?? 'Makes everything more expensive';
    ?>
    <div class="rounded-xl p-5 border <?= $enabler['maxed'] ? 'border-amber-400 bg-amber-50' : 'border-orange-200 bg-orange-50' ?>">
        <div class="flex justify-between items-start">
            <h4 class="font-bold text-gray-900"><?= $enabler['name'] ?></h4>
            <?php if ($enabler['maxed']): ?>
            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-400 text-amber-900 border border-amber-500 shadow-sm">
                MAXIMUM ENABLING
            </span>
            <?php else: ?>
            <span class="text-lg font-bold <?= $enabler['rating'] > 50 ? 'text-orange-600' : 'text-red-500' ?>">
                <?= number_format($enabler['rating'], 1) ?>%
            </span>
            <?php endif; ?>
        </div>

        <p class="mt-2 text-xs text-gray-600"><?= $enabler['description'] ?></p>

        <!-- Effect Description -->
        <div class="mt-2 text-xs text-orange-700 font-medium">
            Effect: <?= $effect ?>
        </div>

        <!-- Rating Bar -->
        <div class="mt-3">
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="h-3 rounded-full transition-all <?= $enabler['maxed'] ? 'bg-gradient-to-r from-amber-400 to-amber-500' : ($enabler['rating'] > 50 ? 'bg-orange-500' : 'bg-red-400') ?>"
                     style="width: <?= min(100, $enabler['rating']) ?>%"></div>
            </div>
            <div class="flex justify-between text-xs mt-1">
                <span class="text-gray-400">0%</span>
                <span class="text-gray-400">100%</span>
            </div>
        </div>

        <!-- Sardonic Quote -->
        <div class="mt-3 bg-white rounded-lg p-2 border border-orange-100">
            <p class="text-xs text-gray-500 italic">"<?= $enabler['sardonic'] ?>"</p>
        </div>

        <?php if ($enabler['maxed']): ?>
            <div class="mt-3 text-center">
                <p class="text-sm font-bold text-amber-700">This enabler has reached peak destruction capability.</p>
            </div>
        <?php else: ?>
            <a href="<?= url("/game/do/{$enabler['code']}") ?>?t=<?= urlencode($csrf_token) ?>"
                class="mt-3 block w-full text-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white hover:bg-orange-700 transition-colors">
                Upgrade - <?= $h::money($upgradeCost) ?>
            </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Enabler Tip -->
<div class="mt-6 bg-orange-100 border border-orange-300 rounded-xl p-4">
    <p class="text-sm text-orange-800">
        <b>How it works:</b> Each enabler makes a specific category of spending more expensive.
        Your <b>Life Coach</b> makes all other enablers improve faster.
        Your <b>Yes Man</b> adds a bonus to every upgrade.
        The higher the rating, the more money they help you burn.
    </p>
</div>
