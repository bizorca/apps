<?php
$h = \App\Core\Helpers::class;
$d = $data;

if ($bankrupt) {
    $title = 'BANKRUPT! - Burn Rate';
} else {
    $title = 'SHAME! - Burn Rate';
}
?>
<?php ob_start(); ?>

<?php if ($bankrupt): ?>
<!-- ==================== BANKRUPTCY CELEBRATION ==================== -->
<div class="max-w-3xl mx-auto">

    <!-- Victory Header -->
    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-2xl p-8 text-center text-white shadow-xl mb-8 relative overflow-hidden">
        <!-- Confetti decoration -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-2 left-10 w-3 h-3 bg-yellow-300 rotate-12 rounded-sm"></div>
            <div class="absolute top-8 right-16 w-2 h-4 bg-pink-300 -rotate-45 rounded-sm"></div>
            <div class="absolute top-4 left-1/3 w-4 h-2 bg-blue-300 rotate-6 rounded-sm"></div>
            <div class="absolute bottom-12 right-1/4 w-3 h-3 bg-purple-300 rotate-45 rounded-sm"></div>
            <div class="absolute bottom-6 left-20 w-2 h-5 bg-red-300 -rotate-12 rounded-sm"></div>
            <div class="absolute top-16 right-10 w-4 h-2 bg-amber-300 rotate-30 rounded-sm"></div>
        </div>

        <h1 class="text-5xl font-black tracking-tight relative">CONGRATULATIONS!</h1>
        <h2 class="text-3xl font-bold mt-2 relative">YOU'RE BANKRUPT!</h2>
        <div class="mt-6 relative">
            <p class="text-xl text-white/90">
                <?= htmlspecialchars($player->PlayerName) ?> burned through
                <span class="font-black text-yellow-200">\$1 BILLION</span>
                in <?= $d['bankruptTurn'] ?> turns
                (<?= $h::turnToYearsMonths($d['bankruptTurn']) ?>)
            </p>
        </div>
        <p class="mt-4 text-5xl font-black text-yellow-200 relative"><?= $h::money(0) ?></p>
        <p class="text-lg text-white/80 mt-1 relative">Final Net Worth - Beautiful Zero</p>
    </div>

    <!-- Destruction Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="font-semibold text-green-700 mb-4">The Glorious Damage</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Total Burned</dt>
                    <dd class="font-bold text-green-600"><?= $h::money($d['totalBurned']) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Bank Balance</dt>
                    <dd class="font-bold"><?= $h::money($d['bankBalance']) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Properties Owned</dt>
                    <dd class="font-bold"><?= $d['propertySummary']['count'] ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Toys Bought</dt>
                    <dd class="font-bold"><?= $d['toySummary']['count'] ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Bad Investments Made</dt>
                    <dd class="font-bold"><?= $d['investSummary']['count'] ?? 0 ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Investment Losses</dt>
                    <dd class="font-bold text-green-600"><?= $h::money($d['investSummary']['totalLoss'] ?? 0) ?></dd>
                </div>
                <hr>
                <div class="flex justify-between text-lg">
                    <dt class="font-semibold text-gray-900">Speed to Bankruptcy</dt>
                    <dd class="font-bold text-green-600"><?= $d['bankruptTurn'] ?> turns</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="font-semibold text-green-700 mb-4">Entourage Final Ratings</h3>
            <p class="text-xs text-gray-400 mb-3">Your team of professional enablers who made this possible.</p>
            <div class="space-y-2">
                <?php foreach ($d['entourage'] as $member): ?>
                <div class="flex items-center justify-between bg-green-50 rounded-lg p-3">
                    <span class="text-sm text-gray-600"><?= $member['name'] ?></span>
                    <span class="font-bold <?= $h::ratingColor($member['rating']) ?>"><?= number_format($member['rating'], 0) ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Celebratory Quote -->
    <div class="bg-gradient-to-r from-green-50 to-green-50 rounded-xl border border-green-200 p-6 mb-8 text-center">
        <p class="text-lg italic text-green-800">"<?= htmlspecialchars($d['quote']) ?>"</p>
        <p class="text-sm text-green-600 mt-4 font-semibold">
            Your father would be so proud. Actually, no. But you made the leaderboard!
        </p>
    </div>

    <!-- Tier Upgrade -->
    <?php if (!empty($tierUpgrade)): ?>
        <?php if ($tierUpgrade['upgraded']): ?>
        <div class="bg-gradient-to-r from-amber-900 to-amber-700 rounded-2xl p-8 text-center text-white shadow-xl mb-8 relative overflow-hidden">
            <div class="absolute inset-0 opacity-10 pointer-events-none">
                <div class="absolute top-3 left-12 w-3 h-3 bg-yellow-300 rotate-12 rounded-sm"></div>
                <div class="absolute top-6 right-20 w-2 h-4 bg-amber-300 -rotate-45 rounded-sm"></div>
                <div class="absolute bottom-8 left-1/3 w-4 h-2 bg-yellow-200 rotate-6 rounded-sm"></div>
            </div>
            <p class="text-amber-300 text-sm font-bold uppercase tracking-widest mb-2">Round <?= $tierUpgrade['roundsCompleted'] ?> Complete</p>
            <h2 class="text-3xl font-black text-white mb-1">Tier Unlocked!</h2>
            <p class="text-2xl font-bold text-amber-200 mb-6"><?= htmlspecialchars($tierUpgrade['newName']) ?></p>
            <?php if (!empty($tierUpgrade['unlocks'])): ?>
            <div class="bg-black/30 rounded-xl p-4 text-left max-w-sm mx-auto">
                <p class="text-amber-300 text-xs font-bold uppercase mb-3">What you just unlocked:</p>
                <ul class="space-y-2">
                    <?php foreach ($tierUpgrade['unlocks'] as $unlock): ?>
                    <li class="flex items-start text-sm text-white">
                        <span class="text-amber-400 mr-2 mt-0.5">&#10003;</span>
                        <?= htmlspecialchars($unlock) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 text-center mb-8">
            <p class="text-gray-400 text-sm">You've reached the <span class="text-amber-400 font-bold"><?= htmlspecialchars($tierUpgrade['newName']) ?></span> tier — the absolute pinnacle of ruin. There is nowhere left to fall.</p>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Play Again -->
    <div class="text-center">
        <a href="<?= url('/account') ?>" class="inline-block rounded-xl bg-green-600 px-8 py-4 text-lg font-bold text-white hover:bg-green-700 transition-colors shadow-lg">
            Go Bankrupt Again
        </a>
    </div>
</div>

<?php else: ?>
<!-- ==================== SHAME - STILL RICH ==================== -->
<div class="max-w-3xl mx-auto">

    <!-- Shame Header -->
    <div class="bg-gradient-to-r from-red-600 to-red-800 rounded-2xl p-8 text-center text-white shadow-xl mb-8">
        <h1 class="text-5xl font-black tracking-tight">SHAME!</h1>
        <h2 class="text-2xl font-bold mt-2 text-red-200">YOU STILL HAVE MONEY!</h2>
        <p class="mt-4 text-5xl font-black text-yellow-300"><?= $h::money($d['netWorth']) ?></p>
        <p class="text-lg text-white/80 mt-1">Remaining Net Worth - Embarrassing</p>
        <p class="text-sm text-white/60 mt-2"><?= $d['turns'] ?> turns (~<?= round($d['turns'] / 12) ?> years of failure)</p>
    </div>

    <!-- The Failure Report -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="font-semibold text-red-700 mb-4">The Shameful Remains</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Bank Balance</dt>
                    <dd class="font-bold text-red-600"><?= $h::money($d['bankBalance']) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Property Equity</dt>
                    <dd class="font-bold text-red-600"><?= $h::money($d['propertySummary']['totalEquity']) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Toy Value</dt>
                    <dd class="font-bold text-red-600"><?= $h::money($d['toySummary']['totalValue']) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Investment Value</dt>
                    <dd class="font-bold text-red-600"><?= $h::money($d['investSummary']['totalCurrentValue'] ?? 0) ?></dd>
                </div>
                <hr>
                <div class="flex justify-between text-lg">
                    <dt class="font-semibold text-gray-900">Total Net Worth</dt>
                    <dd class="font-bold text-red-600"><?= $h::money($d['netWorth']) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Total Burned</dt>
                    <dd class="font-bold text-green-600"><?= $h::money($d['totalBurned']) ?></dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="font-semibold text-red-700 mb-4">Entourage (They Failed You)</h3>
            <div class="space-y-2">
                <?php foreach ($d['entourage'] as $member): ?>
                <div class="flex items-center justify-between bg-red-50 rounded-lg p-3">
                    <span class="text-sm text-gray-600"><?= $member['name'] ?></span>
                    <span class="font-bold <?= $h::ratingColor($member['rating']) ?>"><?= number_format($member['rating'], 0) ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sardonic Commentary -->
    <div class="bg-red-50 rounded-xl border border-red-200 p-6 mb-8">
        <p class="text-center text-red-800 font-semibold text-lg mb-4">
            You had 611 months and couldn't even go broke properly.
        </p>
        <p class="text-center text-red-600 mb-4">
            Your father would be disappointed... that you're still rich.
        </p>

        <div class="mt-4 border-t border-red-200 pt-4">
            <p class="text-sm font-semibold text-red-700 mb-2">Suggestions for Next Time:</p>
            <ul class="text-sm text-red-600 space-y-1 list-disc list-inside">
                <li>Have you tried buying more private islands? They're excellent money pits.</li>
                <li>Your entourage isn't enabling hard enough. Upgrade them.</li>
                <li>Consider investing in things that sound made up. If someone says "blockchain" and "AI" in the same sentence, write the check immediately.</li>
                <li>Throw bigger parties. If you can remember the party, it wasn't expensive enough.</li>
                <li>Buy more yachts. A yacht for each ocean. Then a yacht for each sea.</li>
            </ul>
        </div>

        <div class="mt-4 border-t border-red-200 pt-4 text-center">
            <p class="text-sm italic text-red-500">"<?= htmlspecialchars($d['quote']) ?>"</p>
        </div>
    </div>

    <!-- Tier Upgrade -->
    <?php if (!empty($tierUpgrade)): ?>
        <?php if ($tierUpgrade['upgraded']): ?>
        <div class="bg-gradient-to-r from-amber-900 to-amber-700 rounded-2xl p-8 text-center text-white shadow-xl mb-8">
            <p class="text-amber-300 text-sm font-bold uppercase tracking-widest mb-2">Round <?= $tierUpgrade['roundsCompleted'] ?> Complete</p>
            <h2 class="text-3xl font-black text-white mb-1">Tier Unlocked!</h2>
            <p class="text-2xl font-bold text-amber-200 mb-6"><?= htmlspecialchars($tierUpgrade['newName']) ?></p>
            <?php if (!empty($tierUpgrade['unlocks'])): ?>
            <div class="bg-black/30 rounded-xl p-4 text-left max-w-sm mx-auto">
                <p class="text-amber-300 text-xs font-bold uppercase mb-3">What you just unlocked:</p>
                <ul class="space-y-2">
                    <?php foreach ($tierUpgrade['unlocks'] as $unlock): ?>
                    <li class="flex items-start text-sm text-white">
                        <span class="text-amber-400 mr-2 mt-0.5">&#10003;</span>
                        <?= htmlspecialchars($unlock) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 text-center mb-8">
            <p class="text-gray-400 text-sm">You've reached <span class="text-amber-400 font-bold"><?= htmlspecialchars($tierUpgrade['newName']) ?></span> — the absolute pinnacle of ruin.</p>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Try Again -->
    <div class="text-center">
        <a href="<?= url('/account') ?>" class="inline-block rounded-xl bg-red-600 px-8 py-4 text-lg font-bold text-white hover:bg-red-700 transition-colors shadow-lg">
            Try Harder This Time
        </a>
    </div>
</div>
<?php endif; ?>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
