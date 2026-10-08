<?php
require __DIR__ . '/_bootstrap.php';
$pageTitle = 'Turn Your Business Into a Cooperative';
$fullWidth = true;
require CW_ROOT . '/templates/header.php';
?>

<!-- Hero Section -->
<section class="bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28">
        <div class="max-w-3xl">
            <div class="inline-flex items-center gap-2 bg-brand-600 bg-opacity-50 border border-brand-500 text-brand-100 text-xs font-semibold px-3 py-1.5 rounded-full mb-6 uppercase tracking-wide">
                <svg class="w-3.5 h-3.5 text-accent-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                </svg>
                Washington State Only
            </div>

            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight mb-6">
                Turn Your Business<br>
                <span class="text-accent-400">Into a Cooperative</span>
            </h1>

            <p class="text-lg md:text-xl text-brand-100 leading-relaxed mb-4 max-w-2xl">
                You built something worth keeping. When it's time to step back, selling to a private buyer isn't the only option — and often isn't the best one. A worker or community cooperative keeps the business local, rewards the people who helped build it, and gives you a real exit.
            </p>

            <p class="text-brand-200 mb-10 max-w-xl">
                CoopConvert walks Washington state business owners through the entire conversion process: business intake, cooperative structure selection, deal modeling, and document generation. A volunteer coordinator guides you every step of the way.
            </p>

            <div class="flex flex-col sm:flex-row gap-4">
                <a href="<?= url('/register.php') ?>" class="inline-flex items-center justify-center gap-2 bg-accent-500 hover:bg-accent-600 text-white font-semibold px-7 py-3.5 rounded-lg shadow-lg transition-colors text-base">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                    Start Your Conversion
                </a>
                <a href="<?= url('/register.php?role=coordinator') ?>" class="inline-flex items-center justify-center gap-2 bg-white bg-opacity-10 hover:bg-opacity-20 border border-white border-opacity-30 text-white font-semibold px-7 py-3.5 rounded-lg transition-colors text-base">
                    Volunteer as Coordinator
                </a>
            </div>
        </div>
    </div>
</section>

<!-- The Problem / Why It Matters -->
<section class="bg-white py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">The succession problem is real</h2>
            <p class="text-gray-600 text-lg leading-relaxed">
                Tens of thousands of small businesses change hands every decade. Most owners have no succession plan. Private equity rolls up what it can. The rest often just close. Workers lose jobs. Communities lose anchors. There's a better path.
            </p>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="bg-brand-50 border border-brand-100 rounded-xl p-6">
                <div class="text-3xl font-extrabold text-brand-700 mb-2">~$10,000+</div>
                <p class="text-gray-700 text-sm leading-relaxed">What a conversion attorney typically charges just to get through the paperwork — before you've modeled the deal or filed a single form.</p>
            </div>
            <div class="bg-brand-50 border border-brand-100 rounded-xl p-6">
                <div class="text-3xl font-extrabold text-brand-700 mb-2">RCW 23.86</div>
                <p class="text-gray-700 text-sm leading-relaxed">Washington's cooperative statute gives you a legal framework. CoopConvert helps you actually use it, without needing a law degree to understand what you're doing.</p>
            </div>
            <div class="bg-brand-50 border border-brand-100 rounded-xl p-6">
                <div class="text-3xl font-extrabold text-brand-700 mb-2">Free tools</div>
                <p class="text-gray-700 text-sm leading-relaxed">CoopConvert is free to use. Volunteer coordinators — people who have done this before — guide you through every stage at no cost to you.</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="bg-gray-50 py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-3">How It Works</h2>
            <p class="text-gray-500 max-w-xl mx-auto">Four stages, one coordinator, zero guesswork.</p>
        </div>

        <div class="grid md:grid-cols-4 gap-6">
            <!-- Step 1 -->
            <div class="relative bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-lg flex-shrink-0">1</div>
                    <h3 class="font-semibold text-gray-900 text-base">Business Intake</h3>
                </div>
                <svg class="w-8 h-8 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="text-gray-600 text-sm leading-relaxed">Tell us about your business — revenue, employees, industry, ownership structure. This sets the foundation for everything that follows.</p>
            </div>

            <!-- Step 2 -->
            <div class="relative bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-lg flex-shrink-0">2</div>
                    <h3 class="font-semibold text-gray-900 text-base">Structure Selection</h3>
                </div>
                <svg class="w-8 h-8 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <p class="text-gray-600 text-sm leading-relaxed">Worker co-op, consumer co-op, multi-stakeholder — we walk through the differences and help you pick the right legal structure for your situation.</p>
            </div>

            <!-- Step 3 -->
            <div class="relative bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-lg flex-shrink-0">3</div>
                    <h3 class="font-semibold text-gray-900 text-base">Deal Modeling</h3>
                </div>
                <svg class="w-8 h-8 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <p class="text-gray-600 text-sm leading-relaxed">Model the purchase price, financing, member equity, and seller carry-back. See whether the numbers actually work before anyone commits.</p>
            </div>

            <!-- Step 4 -->
            <div class="relative bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-lg flex-shrink-0">4</div>
                    <h3 class="font-semibold text-gray-900 text-base">Document Generation</h3>
                </div>
                <svg class="w-8 h-8 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <p class="text-gray-600 text-sm leading-relaxed">Generate draft articles of incorporation, bylaws, membership agreements, and other required filings — ready for your attorney to review and finalize.</p>
            </div>
        </div>
    </div>
</section>

<!-- Who It's For -->
<section class="bg-white py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-3">Who It's For</h2>
            <p class="text-gray-500 max-w-xl mx-auto">CoopConvert is for people who want a real alternative to selling out or shutting down.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <div class="flex flex-col gap-4">
                <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <h3 class="font-bold text-gray-900 text-lg">Retiring Business Owners</h3>
                <p class="text-gray-600 text-sm leading-relaxed">You've put decades into this. You want the business to survive you, the employees to keep their jobs, and a fair exit for yourself. A cooperative conversion can do all three — if you plan it right.</p>
            </div>

            <div class="flex flex-col gap-4">
                <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h3 class="font-bold text-gray-900 text-lg">Employee Groups</h3>
                <p class="text-gray-600 text-sm leading-relaxed">The owner wants out. You and your coworkers want to buy in. This is the tool that helps you understand whether the deal pencils, what structure to use, and what paperwork you need to file.</p>
            </div>

            <div class="flex flex-col gap-4">
                <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 5h-1a1 1 0 01-1-1v-3a1 1 0 011-1h1a1 1 0 011 1v3a1 1 0 01-1 1z" />
                    </svg>
                </div>
                <h3 class="font-bold text-gray-900 text-lg">Community Development Organizations</h3>
                <p class="text-gray-600 text-sm leading-relaxed">CDFIs, nonprofits, and economic development agencies working to preserve businesses in their communities. Use CoopConvert to help clients navigate the process with a consistent, documented workflow.</p>
            </div>
        </div>
    </div>
</section>

<!-- WA-State Only Banner -->
<section class="bg-brand-700 py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div>
                <p class="text-white font-semibold text-base mb-1">Washington State Only &mdash; for now</p>
                <p class="text-brand-200 text-sm leading-relaxed max-w-xl">CoopConvert is built specifically around Washington state cooperative law (RCW 23.86). The document templates, filing requirements, and structural guidance are Washington-specific. If you're outside Washington, the general concepts apply but the paperwork does not.</p>
            </div>
        </div>
        <a href="<?= url('/register.php') ?>" class="flex-shrink-0 bg-white text-brand-700 hover:bg-brand-50 font-semibold px-6 py-3 rounded-lg transition-colors shadow-sm text-sm">
            Get Started Free
        </a>
    </div>
</section>

<?php require CW_ROOT . '/templates/footer.php'; ?>
