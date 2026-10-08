<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

// Signed-in users go straight to their reports. tl_user() never starts a
// session, so anonymous visitors get no cookie here.
if (getCurrentUser()) {
    redirect('/dashboard.php');
}

$start = '/account/register.php?next=' . rawurlencode(url('/wizard.php?step=1'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?> — Know What a Business Is Worth Before You Sign</title>
    <meta name="description" content="Free business valuation reports for first-time buyers: SDE and revenue multiples, an illustrative DCF, risk flags and a PDF, from three years of financials.">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white">

<?php include NB_ROOT . '/templates/header.php'; ?>

<!-- Hero -->
<section class="max-w-3xl mx-auto px-4 pt-20 pb-16 text-center">
    <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 leading-tight mb-5">
        Know what a business is worth<br class="hidden sm:block"> before you sign.
    </h1>
    <p class="text-lg text-gray-500 max-w-xl mx-auto mb-8">
        Enter three years of financials for a business you're thinking of buying. Numbrella runs the standard main-street methods (SDE multiples, revenue multiples and an illustrative DCF), flags the risks, and tells you whether the asking price is in range. Free.
    </p>
    <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <a href="<?= h($start) ?>"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-3.5 rounded-xl text-base transition-colors">
            Get Your Valuation Report
        </a>
        <a href="#how-it-works"
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-8 py-3.5 rounded-xl text-base transition-colors">
            See how it works
        </a>
    </div>
    <p class="mt-4 text-xs text-gray-400">Free with a Bizorca Tools account. Already have one? <a class="underline" href="/account/login.php?next=<?= rawurlencode(url('/dashboard.php')) ?>">Sign in</a>.</p>
</section>

<!-- How it works -->
<section id="how-it-works" class="bg-gray-50 border-y border-gray-100 py-20">
    <div class="max-w-3xl mx-auto px-4">
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-12">Three steps. Ten minutes. One clear answer.</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-12 h-12 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-lg font-extrabold mx-auto mb-4">1</div>
                <h3 class="font-semibold text-gray-900 mb-2">Enter the basics</h3>
                <p class="text-sm text-gray-500">Industry, entity type, years in operation. No spreadsheet required.</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-lg font-extrabold mx-auto mb-4">2</div>
                <h3 class="font-semibold text-gray-900 mb-2">Enter up to three years of financials</h3>
                <p class="text-sm text-gray-500">Revenue, net profit, owner pay, and any add-backs. Straight from the P&amp;L or tax return.</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-lg font-extrabold mx-auto mb-4">3</div>
                <h3 class="font-semibold text-gray-900 mb-2">Get your report</h3>
                <p class="text-sm text-gray-500">Valuation range, risk flags, and a plain-language explanation of every number.</p>
            </div>
        </div>
    </div>
</section>

<!-- What's in the report -->
<section class="max-w-3xl mx-auto px-4 py-16">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">What's in the report</h2>
    <p class="text-gray-500 text-sm mb-8">Every formula shown, every flag explained, every assumption documented.</p>

    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 text-sm text-gray-600">
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> SDE multiple valuation (primary)</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> Revenue multiple valuation</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> Discounted cash flow (illustrative)</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> Consensus value range</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> Asking price verdict</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> Risk flags with plain explanations</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> 3-year cash flow projection</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> Scenario analysis at five price points</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> Industry benchmark context</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> Full methodology notes</li>
        <li class="flex gap-2"><span class="text-indigo-500">✓</span> PDF download + shareable link</li>
    </ul>
</section>

<!-- Who it's for -->
<section class="bg-gray-50 border-y border-gray-100 py-16">
    <div class="max-w-3xl mx-auto px-4">
        <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">Built for first-time buyers</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
            <div>
                <p class="text-sm font-semibold text-gray-900 mb-1">Found something on BizBuySell</p>
                <p class="text-sm text-gray-500">You have the listing. You have the seller's asking price. Now you need to know if it's real.</p>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900 mb-1">In due diligence</p>
                <p class="text-sm text-gray-500">You've seen the financials. Run them through a methodology before you counter-offer.</p>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900 mb-1">Working with a broker</p>
                <p class="text-sm text-gray-500">Walk into the conversation knowing the numbers, not just taking the broker's word for it.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="bg-indigo-600 py-16">
    <div class="max-w-xl mx-auto px-4 text-center">
        <h2 class="text-2xl font-bold text-white mb-3">Don't make a six-figure decision without the analysis.</h2>
        <p class="text-indigo-200 text-sm mb-6">Not a certified appraisal, but a solid first look before you pay for one.</p>
        <a href="<?= h($start) ?>"
            class="inline-block bg-white text-indigo-600 hover:bg-indigo-50 font-bold px-8 py-3.5 rounded-xl text-base transition-colors">
            Get Your Report
        </a>
    </div>
</section>

<?php include NB_ROOT . '/templates/footer.php'; ?>

</body>
</html>
