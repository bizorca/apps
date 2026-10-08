<?php
$title = 'Pricing - Burn Rate';
$h = \App\Core\Helpers::class;

// Check if Stripe is enabled
$stripeEnabled = false;
try {
    $settingsModel = new \App\Models\SiteSettings($app->db);
    $stripeEnabled = $settingsModel->isStripeEnabled();
} catch (\Throwable $e) {}
?>
<?php ob_start(); ?>

<!-- Hero -->
<div class="bg-gradient-to-br from-red-800 via-red-700 to-amber-600 py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl font-extrabold text-white sm:text-5xl">Choose Your Level of Ruin</h1>
        <p class="mt-6 text-xl text-red-100 max-w-3xl mx-auto">
            Start freeloading and upgrade when you're ready to destroy wealth at a professional level.
        </p>
    </div>
</div>

<div class="bg-gray-950 py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php
            $colors = [
                0 => ['bg' => 'bg-gray-900', 'border' => 'border-gray-700', 'badge' => 'bg-gray-800 text-gray-400', 'btn' => 'bg-gray-700 hover:bg-gray-600', 'accent' => 'text-gray-400'],
                1 => ['bg' => 'bg-gray-900', 'border' => 'border-red-800', 'badge' => 'bg-red-900/50 text-red-300', 'btn' => 'bg-red-700 hover:bg-red-600', 'accent' => 'text-red-400'],
                16 => ['bg' => 'bg-gray-900', 'border' => 'border-slate-600', 'badge' => 'bg-slate-800 text-slate-300', 'btn' => 'bg-slate-600 hover:bg-slate-500', 'accent' => 'text-slate-300'],
                256 => ['bg' => 'bg-gray-900', 'border' => 'border-yellow-700', 'badge' => 'bg-yellow-900/50 text-yellow-300', 'btn' => 'bg-yellow-700 hover:bg-yellow-600', 'accent' => 'text-yellow-400'],
                4096 => ['bg' => 'bg-gradient-to-b from-gray-900 to-purple-950', 'border' => 'border-purple-700', 'badge' => 'bg-purple-900/50 text-purple-300', 'btn' => 'bg-purple-700 hover:bg-purple-600', 'accent' => 'text-purple-400'],
                65536 => ['bg' => 'bg-gradient-to-b from-gray-900 to-cyan-950', 'border' => 'border-cyan-700', 'badge' => 'bg-cyan-900/50 text-cyan-300', 'btn' => 'bg-cyan-700 hover:bg-cyan-600', 'accent' => 'text-cyan-400'],
                1048576 => ['bg' => 'bg-gradient-to-b from-gray-900 to-amber-950', 'border' => 'border-amber-500 ring-2 ring-amber-700', 'badge' => 'bg-amber-900/50 text-amber-300', 'btn' => 'bg-amber-600 hover:bg-amber-500', 'accent' => 'text-amber-400'],
            ];

            $descriptions = [
                0 => [
                    'tagline' => 'Can\'t even afford to lose money properly.',
                    'features' => [
                        'Access to core game',
                        '1 player slot',
                        'Basic property &amp; toy purchasing',
                        'Standard depreciation rates',
                    ],
                ],
                1 => [
                    'tagline' => 'Daddy\'s money, but just a taste.',
                    'features' => [
                        'Everything in Freeloader',
                        'Multiple player slots',
                        'Unlock the Entourage system',
                        'Access to vanity projects',
                    ],
                ],
                16 => [
                    'tagline' => 'Born with a silver spoon. Time to pawn it.',
                    'features' => [
                        'Everything in Trust Fund Baby',
                        'Premium property listings',
                        'Advanced toy categories',
                        'Entourage upgrades available',
                    ],
                ],
                256 => [
                    'tagline' => 'Married for money. Spending for sport.',
                    'features' => [
                        'Everything in Silver Spoon',
                        'Exclusive investment opportunities',
                        'Advanced analytics &amp; charts',
                        'Priority support',
                    ],
                ],
                4096 => [
                    'tagline' => 'Spending is a competitive sport now.',
                    'features' => [
                        'Everything in Gold Digger',
                        'Rare crisis event modifiers',
                        'Custom leaderboard categories',
                        'Detailed financial breakdown reports',
                    ],
                ],
                65536 => [
                    'tagline' => 'Destroying wealth at an industrial scale.',
                    'features' => [
                        'Everything in Platinum Spender',
                        'All premium features unlocked',
                        'Early access to new content',
                        'Admin &amp; moderation tools',
                    ],
                ],
                1048576 => [
                    'tagline' => 'The final circle. No fortune survives.',
                    'features' => [
                        'Everything in Diamond Destroyer',
                        'VIP access to all features',
                        'Shape the game\'s future direction',
                        'Exclusive Inner Circle badge',
                    ],
                ],
            ];
            ?>
            <?php foreach ($tiers as $level => $tier): ?>
                <?php $c = $colors[$level] ?? $colors[0]; ?>
                <?php $desc = $descriptions[$level] ?? $descriptions[0]; ?>
                <div class="<?= $c['bg'] ?> rounded-2xl border <?= $c['border'] ?> p-6 flex flex-col <?= $level === 1048576 ? 'relative' : '' ?>">
                    <?php if ($level === 1048576): ?>
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                            <span class="inline-flex items-center rounded-full bg-amber-600 px-4 py-1 text-xs font-bold text-white shadow-sm">EXCLUSIVE</span>
                        </div>
                    <?php endif; ?>
                    <div class="mb-4">
                        <span class="inline-flex items-center rounded-full <?= $c['badge'] ?> px-3 py-1 text-xs font-semibold border border-white/10"><?= htmlspecialchars($tier['name']) ?></span>
                    </div>
                    <div class="mb-2">
                        <?php if ($tier['monthly'] > 0): ?>
                            <div class="flex items-baseline">
                                <span class="text-3xl font-extrabold text-white">$<?= number_format($tier['monthly'], 2) ?></span>
                                <span class="ml-1 text-sm text-gray-500">/month</span>
                            </div>
                            <?php if ($tier['initial'] > 0): ?>
                                <p class="text-sm text-gray-500 mt-1">$<?= number_format($tier['initial'], 2) ?> initial fee</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="flex items-baseline">
                                <span class="text-3xl font-extrabold text-white">Free</span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">No credit card required</p>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm <?= $c['accent'] ?> italic mb-4"><?= $desc['tagline'] ?></p>
                    <div class="flex-1 mb-6">
                        <ul class="space-y-2 text-sm text-gray-400">
                            <?php foreach ($desc['features'] as $feature): ?>
                                <li class="flex items-start"><span class="text-red-500 mr-2">&#128293;</span> <?= $feature ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div>
                        <?php if ($stripeEnabled && $tier['monthly'] > 0): ?>
                            <button class="w-full rounded-lg <?= $c['btn'] ?> px-4 py-3 text-sm font-bold text-white transition-colors">
                                Subscribe Now
                            </button>
                        <?php elseif ($tier['monthly'] > 0): ?>
                            <div class="w-full rounded-lg bg-gray-800 px-4 py-3 text-sm font-medium text-gray-500 text-center border border-gray-700">
                                Coming Soon
                            </div>
                        <?php else: ?>
                            <a href="<?= '/account/register.php?next=' . rawurlencode(url('/account')) ?>" class="block w-full rounded-lg <?= $c['btn'] ?> px-4 py-3 text-sm font-bold text-white text-center transition-colors">
                                Start Freeloading
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$stripeEnabled): ?>
        <div class="mt-12 text-center">
            <div class="inline-flex items-center rounded-full bg-gray-900 border border-amber-700 px-6 py-3">
                <span class="text-amber-400 text-sm font-medium">Paid memberships are coming soon. Create a free account to start your financial demolition!</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- FAQ -->
<div class="bg-gray-900 py-20 border-t border-gray-800">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-white text-center mb-12">Frequently Asked Questions</h2>
        <div class="space-y-8">
            <div>
                <h3 class="text-lg font-semibold text-white mb-2">Can I really play for free?</h3>
                <p class="text-gray-400">Absolutely. The Freeloader tier gives you access to the core game. You can start torching virtual billions without spending a real cent. We appreciate the irony.</p>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-white mb-2">What do paid memberships unlock?</h3>
                <p class="text-gray-400">Higher tiers unlock the Entourage system (professional enablers), vanity projects, premium property listings, advanced investment opportunities, and exclusive crisis event modifiers. Basically, more creative ways to lose money.</p>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-white mb-2">Can I cancel anytime?</h3>
                <p class="text-gray-400">Yes. Unlike your in-game megayacht, your subscription can be cancelled without a 15% dealer commission. You'll keep access through the end of your billing period.</p>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-white mb-2">Is real money at risk in the game?</h3>
                <p class="text-gray-400">No. Burn Rate is a satirical simulation game. The \$1 billion you're burning through is entirely virtual. The only real money involved is your (entirely optional) subscription. All the financial ruin stays safely inside the game.</p>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-white mb-2">Why is the top tier called "Inner Circle of Ruin"?</h3>
                <p class="text-gray-400">Because if you're paying $497/month to play a game about going bankrupt, you're already embodying the spirit of the game in real life. We salute your commitment.</p>
            </div>
        </div>
    </div>
</div>

<!-- CTA -->
<div class="bg-gray-950 py-16 border-t border-gray-800">
    <div class="mx-auto max-w-4xl px-4 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">Start Your Descent Today</h2>
        <p class="text-gray-400 text-lg mb-8">Create a free account and begin your journey from obscene wealth to glorious bankruptcy.</p>
        <a href="<?= '/account/register.php?next=' . rawurlencode(url('/account')) ?>" class="inline-block rounded-xl bg-amber-500 px-10 py-4 text-lg font-bold text-red-900 shadow-lg hover:bg-amber-400 transition-all hover:scale-105">
            Start Freeloading
        </a>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/marketing.php'; ?>
