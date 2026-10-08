<?php $title = 'About - Burn Rate'; ?>
<?php ob_start(); ?>

<!-- Hero -->
<div class="bg-gradient-to-br from-red-800 via-red-700 to-amber-600 py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl font-extrabold text-white sm:text-5xl">About Burn Rate</h1>
        <p class="mt-6 text-xl text-red-100 max-w-3xl mx-auto">
            A financial literacy tool disguised as the worst financial advice ever given.
        </p>
    </div>
</div>

<div class="bg-gray-950 py-20">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-16">

        <!-- The Premise -->
        <div>
            <h2 class="text-3xl font-bold text-white mb-6">The Premise</h2>
            <div class="space-y-4 text-lg text-gray-400 leading-relaxed">
                <p>
                    Congratulations. A distant relative you never met has shuffled off this mortal coil, and they left you $1 billion. One. Billion. Dollars. More money than most nations' annual budgets. More than you could spend in ten lifetimes of reasonable living.
                </p>
                <p>
                    Your mission, should you choose to accept it: <span class="text-amber-400 font-semibold">go broke as fast as humanly possible.</span>
                </p>
                <p>
                    Buy mansions on every continent. Collect supercars like trading cards. Fund a crypto startup your astrologer recommended. Hire a party planner to throw events that cost more than a hospital wing. The leaderboard doesn't celebrate the richest player&mdash;it celebrates whoever hits zero first.
                </p>
            </div>
        </div>

        <!-- The Real Lesson -->
        <div>
            <h2 class="text-3xl font-bold text-white mb-6">The Real Lesson</h2>
            <div class="space-y-4 text-lg text-gray-400 leading-relaxed">
                <p>
                    Here's the thing nobody tells you about financial education: <span class="text-red-400 font-semibold">people learn faster from catastrophic mistakes than from sensible advice.</span> Nobody remembers a chapter on compound interest, but everyone remembers watching a fortune evaporate.
                </p>
                <p>
                    Every terrible choice in this game mirrors a real financial mistake people make. That mansion with $200K/month in maintenance? It's the real reason lottery winners go bankrupt. That "sure thing" crypto venture? It's every speculative bubble since the Dutch tulip craze. That entourage of yes-men burning through your cash? Welcome to the economics of lifestyle creep.
                </p>
                <p>
                    Burn Rate teaches financial literacy by inversion. Instead of telling you what to do, it lets you experience&mdash;in vivid, entertaining detail&mdash;exactly what <em>not</em> to do. By the time you've burned through your virtual billions, you understand depreciation, carrying costs, speculative risk, and the merciless mathematics of expenses that compound faster than any investment.
                </p>
            </div>
        </div>

        <!-- What You'll Actually Learn -->
        <div>
            <h2 class="text-3xl font-bold text-white mb-6">What You'll Actually Learn</h2>
            <p class="text-lg text-gray-400 mb-6">(Despite laughing the entire time)</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
                    <h3 class="font-bold text-red-400 mb-2">Depreciation Is Brutal</h3>
                    <p class="text-gray-400">That $50M yacht is worth $35M next year. Every toy, every mansion, every luxury item bleeds value. This is why real millionaires rent.</p>
                </div>
                <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
                    <h3 class="font-bold text-red-400 mb-2">Carrying Costs Kill</h3>
                    <p class="text-gray-400">A private island isn't a one-time purchase. Staff, maintenance, insurance, docking fees&mdash;the cost of <em>owning</em> things is the real trap.</p>
                </div>
                <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
                    <h3 class="font-bold text-red-400 mb-2">Speculation Is Gambling</h3>
                    <p class="text-gray-400">Crypto ventures, hedge funds, speculative startups&mdash;the game shows you exactly how "sure things" crater, and why diversification matters.</p>
                </div>
                <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
                    <h3 class="font-bold text-red-400 mb-2">Lifestyle Creep Compounds</h3>
                    <p class="text-gray-400">Hiring a Personal Shopper sounds harmless. Then the Party Planner. Then the entourage. Suddenly your monthly burn rate exceeds most companies' revenue.</p>
                </div>
                <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
                    <h3 class="font-bold text-red-400 mb-2">Economic Cycles Are Real</h3>
                    <p class="text-gray-400">The Crypto Crater doesn't care about your portfolio. Market crashes affect everyone, but they devastate the leveraged and the reckless.</p>
                </div>
                <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
                    <h3 class="font-bold text-red-400 mb-2">Bad Advisors Are Expensive</h3>
                    <p class="text-gray-400">Your in-game Astrologer gives terrible investment advice. In the real world, people pay for equally bad guidance all the time. Know who you're listening to.</p>
                </div>
            </div>
        </div>

        <!-- The History -->
        <div>
            <h2 class="text-3xl font-bold text-white mb-6">Built on Two Decades of Simulation</h2>
            <div class="space-y-4 text-lg text-gray-400 leading-relaxed">
                <p>
                    Burn Rate's economic engine has been teaching financial concepts for over twenty years. Originally built as a wealth-building simulation in the early 2000s, the game has been reimagined with a darkly comedic twist&mdash;because nothing sticks in your memory quite like setting ten billion dollars on fire.
                </p>
                <p>
                    The underlying models are based on real-world financial principles. Property depreciation, market volatility, inflation, carrying costs&mdash;they're all modeled with enough accuracy to make the lessons transferable to actual financial decisions. We just wrapped the medicine in a very entertaining candy shell.
                </p>
            </div>
        </div>

    </div>
</div>

<!-- CTA -->
<div class="bg-gray-900 py-16 border-t border-gray-800">
    <div class="mx-auto max-w-4xl px-4 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">Learn by Losing Everything</h2>
        <p class="text-gray-400 text-lg mb-8">No textbooks. No lectures. Just ten billion dollars and a talent for terrible decisions.</p>
        <a href="<?= '/account/register.php?next=' . rawurlencode(url('/account')) ?>" class="inline-block rounded-xl bg-amber-500 px-10 py-4 text-lg font-bold text-red-900 shadow-lg hover:bg-amber-400 transition-all hover:scale-105">
            Start Burning Money
        </a>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/marketing.php'; ?>
