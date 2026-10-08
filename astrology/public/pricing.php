<?php
require __DIR__ . '/_bootstrap.php';

if (isset($_GET['canceled'])) {
    setFlash('error', 'Checkout canceled. No charges were made.');
}

$pageTitle = 'Pricing';
$fullWidth = true;
require AS_ROOT . '/templates/header.php';
?>

<!-- Hero -->
<div class="bg-gradient-to-br from-brand-600 to-brand-800 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-extrabold text-white tracking-tight">Everything is Free</h1>
        <p class="mt-4 text-xl text-brand-100 max-w-2xl mx-auto">We're in early access &mdash; all features are open to every registered user, no subscription required.</p>
    </div>
</div>

<!-- Feature Card -->
<div class="bg-gray-50 py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-xl mx-auto">
            <div class="bg-white rounded-2xl p-8 border-2 border-brand-500 shadow-xl relative">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                    <span class="bg-brand-600 text-white text-xs font-bold px-3 py-1 rounded-full">Early Access &mdash; All Free</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">Full Access</h3>
                <div class="mt-4 flex items-baseline">
                    <span class="text-4xl font-extrabold text-gray-900">$0</span>
                    <span class="text-gray-500 ml-2">during early access</span>
                </div>
                <p class="text-gray-500 mt-3 text-sm">Every feature, no limits, no credit card.</p>
                <ul class="mt-8 space-y-3">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-600 text-sm">Zodiac animal &amp; element profile</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-600 text-sm">Full Ba Zi (Four Pillars) reading</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-600 text-sm">Western sun sign profile &amp; full reading</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-600 text-sm">Weekly forecasts &mdash; Chinese + Western + Star Seed resonance</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-600 text-sm">All 5 guided meditations</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-600 text-sm">TCM health profile &amp; dietary guidance</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-600 text-sm">Seasonal wellness guidance</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-600 text-sm">Star Seed Lineage Quiz + personalized reading</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-600 text-sm">Compatibility checker</span>
                    </li>
                </ul>
                <div class="mt-8">
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= url('/dashboard.php') ?>" class="block w-full text-center py-2.5 rounded-xl font-medium bg-brand-600 text-white hover:bg-brand-700 transition">Go to Dashboard</a>
                    <?php else: ?>
                        <a href="<?= authUrl('register') ?>" class="block w-full text-center py-2.5 rounded-xl font-medium bg-brand-600 text-white hover:bg-brand-700 transition">Create Free Account</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Work with Jillian ladder -->
        <div class="max-w-3xl mx-auto mt-16">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Ready to Go Deeper?</h2>
                <p class="text-gray-500 max-w-xl mx-auto">The app is free. The deeper work happens live with Jillian Ribbons of Sacred Flow Healing Arts — starting with a 1:1 reading and continuing through small group journeys.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                <div class="bg-violet-50 border border-violet-200 rounded-xl p-5">
                    <div class="text-xs font-semibold uppercase tracking-widest text-violet-700 mb-1">Step 1</div>
                    <h3 class="font-bold text-gray-900">Star Seed Reading 1:1</h3>
                    <p class="text-sm text-gray-600 mt-1 mb-2">A live 75-minute reading of your lineage and charts.</p>
                    <div class="text-lg font-extrabold text-gray-900">$111 <span class="text-xs font-normal text-gray-500">per session</span></div>
                </div>
                <div class="bg-rose-50 border border-rose-200 rounded-xl p-5">
                    <div class="text-xs font-semibold uppercase tracking-widest text-rose-700 mb-1">Step 2</div>
                    <h3 class="font-bold text-gray-900">Star Seed Activation Group</h3>
                    <p class="text-sm text-gray-600 mt-1 mb-2">6 weeks of lineage-specific activation in a small circle.</p>
                    <div class="text-lg font-extrabold text-gray-900">$333 <span class="text-xs font-normal text-gray-500">per cohort</span></div>
                </div>
                <div class="bg-teal-50 border border-teal-200 rounded-xl p-5">
                    <div class="text-xs font-semibold uppercase tracking-widest text-teal-700 mb-1">Step 3</div>
                    <h3 class="font-bold text-gray-900">Timeline Clearing Group</h3>
                    <p class="text-sm text-gray-600 mt-1 mb-2">4 weeks clearing ancestral and past-timeline patterns.</p>
                    <div class="text-lg font-extrabold text-gray-900">$444 <span class="text-xs font-normal text-gray-500">per cohort</span></div>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                    <div class="text-xs font-semibold uppercase tracking-widest text-amber-700 mb-1">Step 4</div>
                    <h3 class="font-bold text-gray-900">Integration Journey Group</h3>
                    <p class="text-sm text-gray-600 mt-1 mb-2">A 12-week container for living what you've learned.</p>
                    <div class="text-lg font-extrabold text-gray-900">$1,111 <span class="text-xs font-normal text-gray-500">per journey</span></div>
                </div>
            </div>
            <div class="text-center">
                <a href="<?= url('/work-with-jillian.php') ?>" class="inline-block bg-violet-700 text-white px-8 py-3 rounded-xl font-semibold hover:bg-violet-800 transition">Explore Working with Jillian &rarr;</a>
            </div>
        </div>

        <!-- FAQ -->
        <div class="max-w-3xl mx-auto mt-16">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">Questions</h2>
            <div class="space-y-4">
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <h3 class="font-semibold text-gray-900 mb-2">Is this actually free?</h3>
                    <p class="text-gray-500 text-sm">Yes &mdash; we're in early access and every feature is open to all registered users at no charge. No credit card, no hidden trial period.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <h3 class="font-semibold text-gray-900 mb-2">How are readings generated?</h3>
                    <p class="text-gray-500 text-sm">Readings are generated using Traditional Chinese Medicine and Chinese zodiac principles, personalized to your specific birth data, zodiac animal, and element.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <h3 class="font-semibold text-gray-900 mb-2">Will this stay free forever?</h3>
                    <p class="text-gray-500 text-sm">The app — quiz, readings, forecasts, meditations — is free. The paid work happens live with Jillian: 1:1 readings and small group journeys. The app is where you start; working with Jillian is where you go deeper.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require AS_ROOT . '/templates/footer.php'; ?>
