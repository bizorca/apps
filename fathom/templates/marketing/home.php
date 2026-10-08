<?php
/** Ported from resources/views (Blade). */
$__title = 'Fathom — Kanban boards for independent professionals';
$__meta = 'Fathom is a free Kanban board built for independent service businesses. Manage clients, track work, and run your practice — no subscription required, no credit card, free forever.';
ob_start();
?>
<section class="max-w-6xl mx-auto px-6 pt-20 pb-16 text-center">
    <div class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 text-xs font-semibold px-3 py-1.5 rounded-full mb-8 border border-emerald-200">
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
        Free forever — no credit card required
    </div>

    <h1 class="text-5xl md:text-6xl font-extrabold text-gray-900 leading-tight tracking-tight mb-6">
        Kanban boards for people<br class="hidden md:block">
        <span class="text-indigo-600">who bill by the hour.</span>
    </h1>

    <p class="text-xl text-gray-500 max-w-2xl mx-auto mb-10 leading-relaxed">
        Fathom keeps your client work organized — without the enterprise pricing, the feature bloat, or the credit card field. Manage your pipeline, track active clients, and run your back office from one place.
    </p>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
        <a href="<?= e(route('signup')) ?>" class="w-full sm:w-auto bg-indigo-600 text-white font-semibold px-8 py-3.5 rounded-xl text-base hover:bg-indigo-700 transition shadow-sm">
            Get started — it's free
        </a>
        <a href="<?= e(route('features')) ?>" class="w-full sm:w-auto text-gray-700 font-semibold px-8 py-3.5 rounded-xl text-base border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition">
            See what's included
        </a>
    </div>

    <p class="mt-5 text-sm text-gray-400">No trial period. No paid tier. Free, period.</p>
</section>


<section class="max-w-6xl mx-auto px-6 pb-20">
    <div class="rounded-2xl border border-gray-200 overflow-hidden shadow-xl bg-gray-50">
        
        <div class="bg-gray-100 border-b border-gray-200 px-4 py-3 flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-red-400"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
            <div class="w-3 h-3 rounded-full bg-green-400"></div>
            <div class="ml-4 flex-1 max-w-xs bg-white rounded border border-gray-300 text-xs text-gray-400 px-3 py-1">fathom.bizorca.com/boards</div>
        </div>
        
        <div class="p-6 overflow-x-auto">
            <div class="flex gap-4 min-w-max">
                <?php foreach ([
                    ['col' => 'New Leads', 'color' => 'bg-indigo-100 text-indigo-700', 'cards' => ['Maria Torres — Career Change', 'Robert Chen — S-Corp Filing']],
                    ['col' => 'Discovery Call', 'color' => 'bg-yellow-100 text-yellow-700', 'cards' => ['James Whitfield — Debt Payoff', 'Priya Nair — Budget + Savings']],
                    ['col' => 'Proposal Sent', 'color' => 'bg-orange-100 text-orange-700', 'cards' => ['Derek Okonkwo — Tax Consulting']],
                    ['col' => 'Enrolled', 'color' => 'bg-emerald-100 text-emerald-700', 'cards' => ['Sandra Briggs — Retirement Plan', 'Luis Medina — Credit Repair']],
                ] as $column): ?>
                <div class="w-52 flex-shrink-0">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold <?= e($column['color']) ?> px-2 py-0.5 rounded"><?= e($column['col']) ?></span>
                        <span class="text-xs text-gray-400"><?= e(count($column['cards'])) ?></span>
                    </div>
                    <div class="space-y-2">
                        <?php foreach ($column['cards'] as $card): ?>
                        <div class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm text-xs text-gray-700 font-medium">
                            <?= e($card) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>


<section class="bg-indigo-50 border-y border-indigo-100 py-20">
    <div class="max-w-6xl mx-auto px-6">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Sign up, pick your business type, and you're running.</h2>
            <p class="text-lg text-gray-500 max-w-xl mx-auto">Fathom pre-builds your boards, columns, and tags on day one. No blank-canvas paralysis. No spending your first afternoon setting things up.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <?php foreach ([
                [
                    'type' => 'Financial Coach',
                    'color' => 'indigo',
                    'icon_path' => 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z',
                    'boards' => ['Client Pipeline', 'Active Clients', 'Operations'],
                    'tags' => ['Referral', 'Debt Payoff', 'Investing', 'Retirement'],
                ],
                [
                    'type' => 'Tax Consultant',
                    'color' => 'violet',
                    'icon_path' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z',
                    'boards' => ['Client Pipeline', 'Tax Returns', 'Operations'],
                    'tags' => ['Individual', 'Business', 'Extension Filed', 'Audit'],
                ],
                [
                    'type' => 'Career Coach',
                    'color' => 'emerald',
                    'icon_path' => 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z',
                    'boards' => ['Client Pipeline', 'Active Clients', 'Operations'],
                    'tags' => ['Resume', 'Interview Prep', 'Negotiation', 'Placed'],
                ],
            ] as $bt): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-<?= e($bt['color']) ?>-100 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-<?= e($bt['color']) ?>-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?= e($bt['icon_path']) ?>"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-900 mb-3"><?= e($bt['type']) ?></h3>
                <div class="space-y-1.5 mb-4">
                    <?php foreach ($bt['boards'] as $board): ?>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-3.5 h-3.5 text-<?= e($bt['color']) ?>-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        <?= e($board) ?> board
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($bt['tags'] as $tag): ?>
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <p class="text-center text-sm text-gray-500 mt-8">More business types coming. Blank workspace available for everyone else.</p>
    </div>
</section>


<section class="max-w-6xl mx-auto px-6 py-20">
    <div class="text-center mb-14">
        <h2 class="text-3xl font-bold text-gray-900 mb-4">The features you need. That's it.</h2>
        <p class="text-lg text-gray-500 max-w-xl mx-auto">Fathom isn't trying to be everything. It's trying to be exactly what you need to manage clients and run a service business.</p>
    </div>

    <div class="grid md:grid-cols-3 gap-8">
        <?php foreach ([
            ['icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z', 'title' => 'Kanban boards', 'body' => 'Create as many boards as you need. Customize columns to match your exact workflow — not somebody else\'s idea of one.'],
            ['icon' => 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z', 'title' => 'Cards with everything attached', 'body' => 'Checklists, comments, tags, due dates, file attachments, assignees — each card holds as much or as little as the work requires.'],
            ['icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z', 'title' => 'Team collaboration', 'body' => 'Invite your team, assign cards, and watch the activity feed so nobody is ever wondering what happened or who touched what.'],
            ['icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', 'title' => 'No passwords to lose', 'body' => 'Magic link sign-in — enter your email and we send a link. That\'s it. No password resets, no "forgot my password" at 6am before a client call.'],
            ['icon' => 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244', 'title' => 'Public board sharing', 'body' => 'Share a read-only view of any board with a client or stakeholder — no login required on their end. A clean, live status page they can bookmark.'],
            ['icon' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0', 'title' => 'Notifications', 'body' => 'Get notified when someone comments on your card, completes a step, or closes something you\'re watching. Signal only — no noise.'],
        ] as $feature): ?>
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= e($feature['icon']) ?>"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 mb-1"><?= e($feature['title']) ?></h3>
                <p class="text-sm text-gray-500 leading-relaxed"><?= e($feature['body']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>


<section class="max-w-6xl mx-auto px-6 py-20">
    <div class="bg-gray-50 border border-gray-200 rounded-2xl p-10 md:p-14 flex flex-col md:flex-row items-center gap-8">
        <div class="flex-1">
            <h2 class="text-2xl font-bold text-gray-900 mb-3">Want us to build it for you?</h2>
            <p class="text-gray-500 leading-relaxed">
                The templates are a starting point. A real tax consultant's boards don't look like "Client Pipeline." They look like "Q1 Returns / Extensions / Amended / State-Only." Schedule a 30-minute call and we'll build your workspace to match your actual workflow.
            </p>
        </div>
        <div class="flex-shrink-0">
            <a href="<?= e(route('services')) ?>" class="inline-block bg-indigo-600 text-white font-semibold px-8 py-3.5 rounded-xl text-base hover:bg-indigo-700 transition shadow-sm">
                See setup services
            </a>
            <p class="text-xs text-gray-400 mt-2 text-center">Starting at $99</p>
        </div>
    </div>
</section>


<section class="bg-indigo-600 py-20">
    <div class="max-w-3xl mx-auto px-6 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">Free forever isn't a marketing line.</h2>
        <p class="text-indigo-200 text-lg mb-10 leading-relaxed">
            There's no paid tier. No upgrade prompt. No "your trial ends in 3 days" email. Fathom is free because we believe independent professionals shouldn't pay a subscription tax just to track their own work.
        </p>
        <a href="<?= e(route('signup')) ?>" class="inline-block bg-white text-indigo-600 font-bold px-8 py-3.5 rounded-xl text-base hover:bg-indigo-50 transition shadow-sm">
            Create your free account
        </a>
        <p class="mt-4 text-indigo-300 text-sm">Takes about 60 seconds. No credit card.</p>
    </div>
</section>
<?php fm_layout('marketing', (string) $__title, (string) ob_get_clean(), ['meta' => $__meta]); ?>
