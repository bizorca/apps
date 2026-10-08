<?php $title = 'Burn Rate - Master the Art of Going Broke'; ?>
<?php ob_start(); ?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-red-800 via-red-700 to-amber-600">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-10 left-10 text-[200px] font-black text-white">$</div>
        <div class="absolute top-32 right-20 text-[160px] font-black text-white rotate-12">$</div>
        <div class="absolute bottom-10 right-10 text-[150px] font-black text-white">&#128293;</div>
        <div class="absolute bottom-20 left-1/3 text-[120px] font-black text-white -rotate-6">&#128293;</div>
    </div>
    <div class="relative mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8 lg:py-32">
        <div class="text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl">
                Master the Art of<br><span class="text-amber-400">Going Broke</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-xl text-red-100">
                You just inherited $1 billion. Your mission: spend every last cent. The leaderboard celebrates whoever goes bankrupt fastest.
            </p>
            <div class="mt-10 flex items-center justify-center gap-4">
                <a href="<?= '/account/register.php?next=' . rawurlencode(url('/account')) ?>" class="rounded-xl bg-amber-500 px-8 py-4 text-lg font-bold text-red-900 shadow-lg hover:bg-amber-400 transition-all hover:scale-105">
                    Start Burning Money
                </a>
                <a href="#how-it-works" class="rounded-xl bg-white/10 backdrop-blur px-8 py-4 text-lg font-semibold text-white border border-white/20 hover:bg-white/20 transition-all">
                    See How It Works
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Feature Grid -->
<div class="bg-gray-950 py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold text-white">Six Ways to Torch a Fortune</h2>
            <p class="mt-4 text-lg text-gray-400">Every dollar you waste brings you closer to victory</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

            <div class="bg-gray-900 rounded-2xl p-8 border border-gray-800 hover:border-red-700 transition-colors">
                <div class="text-4xl mb-4">&#127968;</div>
                <h3 class="text-xl font-bold text-white mb-2">Mansions &amp; Islands</h3>
                <p class="text-gray-400">Buy mansions you'll never visit. Private islands that cost more to maintain than some countries' GDP. Every property depreciates while you sleep.</p>
            </div>

            <div class="bg-gray-900 rounded-2xl p-8 border border-gray-800 hover:border-red-700 transition-colors">
                <div class="text-4xl mb-4">&#128668;</div>
                <h3 class="text-xl font-bold text-white mb-2">Toys &amp; Luxuries</h3>
                <p class="text-gray-400">Supercars, megayachts, private jets, and art that "a child could paint." Everything you buy is worth less tomorrow.</p>
            </div>

            <div class="bg-gray-900 rounded-2xl p-8 border border-gray-800 hover:border-red-700 transition-colors">
                <div class="text-4xl mb-4">&#128200;</div>
                <h3 class="text-xl font-bold text-white mb-2">Bad Investments</h3>
                <p class="text-gray-400">Hedge funds, crypto ventures, and startups that promise the moon. They all go bust eventually. The question is: how fast?</p>
            </div>

            <div class="bg-gray-900 rounded-2xl p-8 border border-gray-800 hover:border-red-700 transition-colors">
                <div class="text-4xl mb-4">&#129333;</div>
                <h3 class="text-xl font-bold text-white mb-2">The Entourage</h3>
                <p class="text-gray-400">Hire enablers&mdash;a Personal Shopper, Party Planner, Yes Man, and Astrologer&mdash;who help you spend even faster.</p>
            </div>

            <div class="bg-gray-900 rounded-2xl p-8 border border-gray-800 hover:border-red-700 transition-colors">
                <div class="text-4xl mb-4">&#127908;</div>
                <h3 class="text-xl font-bold text-white mb-2">Vanity Projects</h3>
                <p class="text-gray-400">Launch a podcast nobody listens to. Costs $15K/month, earns $2,500. Your documentary will never be finished.</p>
            </div>

            <div class="bg-gray-900 rounded-2xl p-8 border border-gray-800 hover:border-red-700 transition-colors">
                <div class="text-4xl mb-4">&#128165;</div>
                <h3 class="text-xl font-bold text-white mb-2">Economic Chaos</h3>
                <p class="text-gray-400">Navigate crises like "The Crypto Crater" and "The NFT Apocalypse." Watch your investments evaporate overnight.</p>
            </div>

        </div>
    </div>
</div>

<!-- How It Works -->
<div id="how-it-works" class="bg-gray-900 py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold text-white">How It Works</h2>
            <p class="mt-4 text-lg text-gray-400">Three simple steps to financial ruin</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-900 text-2xl font-bold text-amber-400 mb-6 ring-2 ring-red-700">1</div>
                <h3 class="text-lg font-bold text-white mb-3">Inherit $1 Billion</h3>
                <p class="text-gray-400">Dear old Dad left you everything. RIP your finances.</p>
            </div>
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-900 text-2xl font-bold text-amber-400 mb-6 ring-2 ring-red-700">2</div>
                <h3 class="text-lg font-bold text-white mb-3">Spend Recklessly</h3>
                <p class="text-gray-400">Buy mansions, yachts, and crypto. Throw parties. Fly friends on private jets. Hire an astrologer for investment advice.</p>
            </div>
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-900 text-2xl font-bold text-amber-400 mb-6 ring-2 ring-red-700">3</div>
                <h3 class="text-lg font-bold text-white mb-3">Go Broke Gloriously</h3>
                <p class="text-gray-400">The fastest to bankruptcy wins. Still rich after 611 months? That's the real shame.</p>
            </div>
        </div>
    </div>
</div>

<!-- CTA -->
<div class="bg-gray-950 py-16 border-t border-gray-800">
    <div class="mx-auto max-w-4xl px-4 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">Ready to Burn Through a Fortune?</h2>
        <p class="text-gray-400 text-lg mb-8">Join the race to the bottom. Your $1 billion inheritance isn't going to waste itself.</p>
        <a href="<?= '/account/register.php?next=' . rawurlencode(url('/account')) ?>" class="inline-block rounded-xl bg-amber-500 px-10 py-4 text-lg font-bold text-red-900 shadow-lg hover:bg-amber-400 transition-all hover:scale-105">
            Start Burning Money
        </a>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/marketing.php'; ?>
