<?php $title = 'Pricing'; ?>

<section class="py-24 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-indigo-600 font-semibold text-sm uppercase tracking-wider">Simple & Transparent</span>
            <h1 class="mt-3 text-5xl font-bold text-slate-900 tracking-tight">Completely Free</h1>
            <p class="mt-5 text-xl text-slate-500">Dispatch is a free tool for community organizers, solo practitioners, and small businesses. No tiers, no trials, no credit cards.</p>
        </div>

        <!-- Single plan card -->
        <div class="max-w-lg mx-auto mb-16">
            <div class="relative bg-white border-2 border-indigo-500 rounded-3xl p-10 shadow-xl shadow-indigo-100">
                <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                    <span class="bg-indigo-600 text-white text-sm font-bold px-6 py-1.5 rounded-full shadow-sm">The only plan you need</span>
                </div>

                <div class="text-center mb-8 pt-4">
                    <div class="text-6xl font-black text-slate-900 mb-2">$0</div>
                    <p class="text-slate-500">Forever free</p>
                </div>

                <ul class="space-y-4 mb-10">
                    <?php
                    $feats = [
                        'Unlimited campaigns',
                        'Unlimited action items',
                        'Full venue library access',
                        'Flyer location tracker',
                        'Daily or weekly email digest',
                        'Recurring event support',
                        'Venue suggestion portal',
                        'Mobile-friendly dashboard',
                    ];
                    foreach ($feats as $feat):
                    ?>
                    <li class="flex items-center gap-3">
                        <div class="w-5 h-5 bg-emerald-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-slate-700 font-medium"><?= $feat ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <a href="<?= $_base ?>/register" class="block w-full text-center px-8 py-4 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm text-lg">
                    Create Your Free Account
                </a>
            </div>
        </div>

        <!-- Why free section -->
        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-8">
            <h3 class="text-xl font-bold text-slate-900 mb-3">Why is it free?</h3>
            <p class="text-slate-600 leading-relaxed mb-4">
                Dispatch was created to solve a real problem in community organizing. The people who need this tool most — independent business owners, timebank members, community volunteers — are precisely the people who can't afford another monthly SaaS subscription.
            </p>
            <p class="text-slate-600 leading-relaxed">
                This is a free tool. It exists to reduce friction and help communities thrive. If it helps you, tell someone about it.
            </p>
        </div>
    </div>
</section>
