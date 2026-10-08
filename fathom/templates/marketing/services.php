<?php
/** Ported from resources/views (Blade). */
$__title = 'Services — Fathom';
$__meta = 'Professional setup and ongoing support for your Fathom workspace — Done-For-You Setup, Client Portal Setup, Workflow Audit, and Quarterly Tune-Up.';
ob_start();
?>
<section class="max-w-3xl mx-auto px-6 pt-20 pb-16">
    <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight mb-6">
        Services.
    </h1>
    <p class="text-xl text-gray-500 leading-relaxed">
        Fathom is free. The app takes about 60 seconds to set up. But your <em>workflow</em> — the specific boards, columns, tags, and templates that match how you actually work — that's the hard part. We can help with that.
    </p>
</section>


<section class="max-w-3xl mx-auto px-6 pb-24 space-y-20 text-gray-700 text-lg leading-relaxed">

    
    <div class="space-y-8">
        <div>
            <h2 class="text-3xl font-extrabold text-gray-900">Done-For-You Setup</h2>
            <p class="text-gray-400 text-base mt-1">From $99 — one-time</p>
        </div>

        <div class="space-y-5">
            <h3 class="text-xl font-bold text-gray-900">What you get</h3>
            <p>
                You schedule a 30-minute call. You walk us through your workflow — what types of client work you do, how you move a client from first contact to done, what you need to track along the way. We take notes.
            </p>
            <p>
                Then we build it. Custom boards, columns named after your actual stages, tags that match your actual client categories, and card templates so every new engagement starts the same way. When we're done, you open Fathom and it looks like it was built specifically for you. Because it was.
            </p>

            <ul class="space-y-3 ml-1">
                <?php foreach ([
                    '30-minute onboarding call (video or phone)',
                    'Custom boards built to your workflow',
                    'Column names that match your actual stages',
                    'Tags and color codes for your client categories',
                    'Card templates for recurring engagement types',
                    'Sample cards so you can see how it works',
                    'One round of revisions included',
                ] as $item): ?>
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-indigo-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                    <span><?= e($item) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="space-y-5">
            <h3 class="text-xl font-bold text-gray-900">Why bother?</h3>
            <p>
                The business type templates in Fathom are a starting point. "Client Pipeline" with "New Leads / Discovery Call / Proposal Sent / Enrolled" is fine. But a tax consultant's actual boards look more like "Q1 Individual Returns / Extensions / Amended Returns / State-Only" — with tags like "K-1 Received," "Missing W-2," "IRS Notice Pending." That specificity is the difference between a tool you use every day and a tool you abandon after a week.
            </p>
            <p>
                If you want to build it yourself, go ahead — the app is free and it's not complicated. But if you'd rather spend 30 minutes on a call and open Fathom ready to go, this is what this is.
            </p>
        </div>

        
        <div class="space-y-5">
            <h3 class="text-xl font-bold text-gray-900">Pricing</h3>

            <div class="grid md:grid-cols-3 gap-4">
                <?php foreach ([
                    [
                        'tier' => 'Basic',
                        'price' => '$99',
                        'desc' => 'Solo operator, single workflow',
                        'items' => ['30-min call', '1 board setup', 'Column + tag config', 'Card template'],
                    ],
                    [
                        'tier' => 'Standard',
                        'price' => '$199',
                        'desc' => 'Multiple service lines or client types',
                        'items' => ['30-min call', 'Up to 3 boards', 'Full column + tag config', 'Multiple card templates', '1 revision round'],
                        'featured' => true,
                    ],
                    [
                        'tier' => 'Full Practice',
                        'price' => '$299',
                        'desc' => 'Complex workflows, team setup',
                        'items' => ['60-min call', 'Unlimited boards', 'Team member setup', 'Complete template library', '2 revision rounds'],
                    ],
                ] as $plan): ?>
                <div class="rounded-2xl border <?= e(($plan['featured'] ?? false) ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-white') ?> p-6">
                    <?php if ($plan['featured'] ?? false): ?>
                        <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">Most popular</span>
                    <?php endif; ?>
                    <div class="mt-1 mb-1">
                        <span class="text-3xl font-extrabold text-gray-900"><?= e($plan['price']) ?></span>
                        <span class="text-sm text-gray-500 ml-1">one-time</span>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 mb-1"><?= e($plan['tier']) ?></p>
                    <p class="text-xs text-gray-500 mb-4"><?= e($plan['desc']) ?></p>
                    <ul class="space-y-1.5">
                        <?php foreach ($plan['items'] as $item): ?>
                        <li class="flex items-start gap-2 text-sm text-gray-700">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            <?= e($item) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <hr class="border-gray-200">

    
    <div class="space-y-5">
        <div>
            <h2 class="text-3xl font-extrabold text-gray-900">Client Portal Setup</h2>
            <p class="text-gray-400 text-base mt-1">$79 — one-time</p>
        </div>
        <p>
            Fathom's client portal lets your clients see exactly what's happening with their engagement — which stage they're in, what's outstanding, what's been completed. It's powerful. It's also easy to set up wrong, which means clients see things they shouldn't, or the cards are linked to the wrong records, or the welcome email reads like a form letter.
        </p>
        <p>
            One short call — usually under an hour — and we take care of all of it. We configure your client records, link the right cards to the right portals, and draft welcome email copy that actually sounds like you. You walk away with a portal flow that's ready to hand off to the next client you onboard.
        </p>
        <ul class="space-y-3 ml-1">
            <?php foreach ([
                'Client record configuration',
                'Card-to-portal linking for your existing clients',
                'Welcome email copy (drafted in your voice, ready to send)',
                'Full walkthrough of the portal flow',
                'One short call — no hour-long orientation required',
            ] as $item): ?>
            <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-indigo-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                <span><?= e($item) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <hr class="border-gray-200">

    
    <div class="space-y-5">
        <div>
            <h2 class="text-3xl font-extrabold text-gray-900">Workflow Audit</h2>
            <p class="text-gray-400 text-base mt-1">$149 — one-time</p>
        </div>
        <p>
            Async, no call needed. You give us access to your Fathom workspace; we dig into it and come back with a written report. What's working, what's not, and what to restructure — laid out clearly so you can act on it yourself or hand it back to us.
        </p>
        <p>
            This is the right move if you set up Fathom yourself six months ago, things feel off, and you can't quite put your finger on why. Boards accumulate debt the same way code does. A second set of eyes tends to find it fast.
        </p>
        <ul class="space-y-3 ml-1">
            <?php foreach ([
                'Full review of your workspace (boards, columns, tags, templates)',
                "Written report: what's working, what's not, what to change",
                'Specific restructuring recommendations — not vague observations',
                'Delivered within 3 business days',
                'No call required — fully async',
            ] as $item): ?>
            <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-indigo-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                <span><?= e($item) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <hr class="border-gray-200">

    
    <div class="space-y-5">
        <div>
            <h2 class="text-3xl font-extrabold text-gray-900">Quarterly Tune-Up</h2>
            <p class="text-gray-400 text-base mt-1">$49 — per session</p>
        </div>
        <p>
            A 20-minute call, four times a year, to keep your workspace from turning into a junk drawer. Businesses change. Workflows drift. Tags that made sense in January stop making sense in October. This is the "keep it sharp" service — a quick reorganization of your boards, a cleanup of stale cards, and a check-in on whether your current setup still matches how you're actually working.
        </p>
        <p>
            Most practices that use this book their sessions at the start of each quarter. Some do it after a big push — tax season, a product launch, any stretch where the boards get messy and stay that way. Either way, 20 minutes is usually enough to put it right.
        </p>
        <ul class="space-y-3 ml-1">
            <?php foreach ([
                '20-minute video or phone call',
                'Board reorganization and column cleanup',
                'Stale card triage',
                'Tag and template adjustments as your business evolves',
                'No long-term commitment — book individual sessions as needed',
            ] as $item): ?>
            <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-indigo-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                <span><?= e($item) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    
    <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-8 text-center">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Not sure which one fits?</h2>
        <p class="text-gray-500 mb-6 text-base">Book a call and we'll figure it out. Payment is collected at booking.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="#" class="w-full sm:w-auto bg-indigo-600 text-white font-semibold px-8 py-3 rounded-xl text-base hover:bg-indigo-700 transition">
                Book a call
            </a>
        </div>
        <p class="text-xs text-gray-400 mt-4">Scheduling via Calendly. Payment via Stripe. No surprise fees.</p>
    </div>

</section>
<?php fm_layout('marketing', (string) $__title, (string) ob_get_clean(), ['meta' => $__meta]); ?>
