<?php
$title            = 'Bizorca Foundry — Business Systems Installation';
$meta_description = 'We forge the operational systems your business needs to run without you. No strategy decks. No recommendations. Actual working systems, installed. Application-only engagements.';
ob_start();
?>

<!-- ══════════════════════════════════════════════
     NAV
══════════════════════════════════════════════ -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-slate-950/90 backdrop-blur-sm border-b border-white/5">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
        <a href="<?= u('/') ?>" class="text-white font-semibold tracking-tight text-sm">
            Bizorca <span class="text-brand-500">Foundry</span>
        </a>
        <div class="flex items-center gap-6 text-sm">
            <a href="#process" class="text-slate-400 hover:text-white transition-colors hidden md:block">Process</a>
            <a href="#deliverables" class="text-slate-400 hover:text-white transition-colors hidden md:block">Deliverables</a>
            <a href="#about" class="text-slate-400 hover:text-white transition-colors hidden md:block">About</a>
            <?php if (current_user()): ?>
            <a href="<?= u('/dashboard') ?>" class="bg-brand-500 hover:bg-brand-600 text-white px-4 py-2 rounded text-sm font-medium transition-colors">Dashboard</a>
            <?php else: ?>
            <a href="<?= u('/apply') ?>" class="bg-brand-500 hover:bg-brand-600 text-white px-4 py-2 rounded text-sm font-medium transition-colors">Apply</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- ══════════════════════════════════════════════
     HERO
══════════════════════════════════════════════ -->
<section class="bg-slate-950 pt-32 pb-24 px-6 relative overflow-hidden">
    <!-- Background grid -->
    <div class="absolute inset-0 opacity-[0.03]" style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 60px 60px;"></div>
    <!-- Glow -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[400px] bg-brand-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-4xl mx-auto relative">
        <div class="inline-flex items-center gap-2 bg-white/5 border border-white/10 rounded-full px-4 py-1.5 text-sm text-slate-400 mb-8">
            <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span>
            Application-only &mdash; limited engagements per quarter
        </div>

        <h1 class="text-5xl md:text-7xl font-bold text-white leading-[1.05] tracking-tight mb-6">
            You built a job.<br>
            <span class="text-brand-500">Let's forge it<br>into a business.</span>
        </h1>

        <p class="text-xl text-slate-400 max-w-2xl leading-relaxed mb-10">
            Business systems installation for founders who are done being the bottleneck. We don't hand you a strategy deck and wish you luck. We forge the operational systems, install them in your business, and hand you something that actually runs.
        </p>

        <div class="flex flex-col sm:flex-row gap-4">
            <a href="<?= u('/apply') ?>" class="inline-flex items-center justify-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold px-8 py-4 rounded-lg text-lg transition-colors">
                Apply for an Engagement
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
            <a href="#process" class="inline-flex items-center justify-center gap-2 border border-white/20 hover:border-white/40 text-slate-300 hover:text-white font-medium px-8 py-4 rounded-lg text-lg transition-colors">
                See how it works
            </a>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mt-20 pt-10 border-t border-white/10">
            <div>
                <div class="text-3xl font-bold text-white">100s</div>
                <div class="text-sm text-slate-500 mt-1">Small business turnarounds</div>
            </div>
            <div>
                <div class="text-3xl font-bold text-white">30+</div>
                <div class="text-sm text-slate-500 mt-1">Startup investments &amp; due diligence</div>
            </div>
            <div>
                <div class="text-3xl font-bold text-white">Dozens</div>
                <div class="text-sm text-slate-500 mt-1">Tech founders mentored</div>
            </div>
            <div>
                <div class="text-3xl font-bold text-white">Multiple</div>
                <div class="text-sm text-slate-500 mt-1">Businesses built &amp; sold</div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     THE PROBLEM
══════════════════════════════════════════════ -->
<section class="py-24 px-6 bg-white">
    <div class="max-w-3xl mx-auto">
        <div class="text-brand-600 text-sm font-semibold uppercase tracking-widest mb-4">The Problem</div>
        <h2 class="text-4xl md:text-5xl font-bold text-slate-900 leading-tight mb-8">
            Most small businesses don't fail.<br>They trap their owners.
        </h2>
        <div class="prose prose-lg prose-slate max-w-none">
            <p>Michael Gerber called it the E-Myth &mdash; the Entrepreneur's Myth. The assumption that because you're great at the work, you know how to build a business that does the work. Most small business owners aren't running a business at all. They've built a job for themselves. One they can't quit, can't take a vacation from, and definitely can't sell &mdash; because without them in it every day, there's nothing to sell.</p>
            <p>The plumber who starts a plumbing company. The accountant who opens a firm. The marketing consultant who goes independent. They're all brilliant at their craft. And every single one of them wakes up at 3 a.m. wondering why the business feels like it's slowly eating them alive. The business didn't fail. It succeeded &mdash; at making them indispensable.</p>
            <p>That's the trap. And it has nothing to do with how hard you work, how smart you are, or how good your product is. It's a systems problem. Specifically, the absence of them.</p>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     STORY 1 PLACEHOLDER
══════════════════════════════════════════════ -->
<section class="py-16 px-6 bg-slate-50 border-y border-slate-200">
    <div class="max-w-3xl mx-auto">
        <div class="border-l-4 border-brand-500 pl-6">
            <!-- ╔══════════════════════════════════════════════════════╗
                 ║  STORY 1 — The E-Myth trap in the wild              ║
                 ║                                                      ║
                 ║  Write a paragraph (in your own voice) about a      ║
                 ║  specific client you worked with who was completely  ║
                 ║  trapped IN the business. What did that look like    ║
                 ║  day-to-day? What was the moment you recognized it?  ║
                 ║  What changed after you built the systems?           ║
                 ║  Anonymize the client if needed.                     ║
                 ╚══════════════════════════════════════════════════════╝ -->
            <p class="text-xl text-slate-500 italic">[STORY 1: Client turnaround — replace this placeholder with your paragraph]</p>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     THE SOLUTION
══════════════════════════════════════════════ -->
<section class="py-24 px-6 bg-white">
    <div class="max-w-3xl mx-auto">
        <div class="text-brand-600 text-sm font-semibold uppercase tracking-widest mb-4">The Solution</div>
        <h2 class="text-4xl md:text-5xl font-bold text-slate-900 leading-tight mb-8">
            Forge the systems. Install them. Hand them off.
        </h2>
        <div class="prose prose-lg prose-slate max-w-none">
            <p>A foundry doesn't make recommendations. It takes raw material and forges something durable. That's the distinction here. When this engagement is done, you don't walk away with a report or a framework or a slide deck full of things you should probably do. You walk away with working systems &mdash; installed, tested, running.</p>
            <p>The model comes from Michael Gerber's franchise prototype concept. Not literally franchising your business &mdash; the discipline of building it <em>as if</em> you were. Documented systems, defined roles, repeatable processes, measurable results. A business that works because of the systems, not because you personally show up and hold everything together every day.</p>
            <p>What makes the Foundry different from every other consultant who's read the same book: I've built the businesses, sold the businesses, invested in the businesses, and fixed the businesses. I know what these systems look like when they work and when they don't. And I can build the software tools that implement them &mdash; not outsource that part, not describe what needs to happen and leave you to figure it out. Build it.</p>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     PROCESS
══════════════════════════════════════════════ -->
<section id="process" class="py-24 px-6 bg-slate-950">
    <div class="max-w-5xl mx-auto">
        <div class="text-brand-500 text-sm font-semibold uppercase tracking-widest mb-4">The Process</div>
        <h2 class="text-4xl md:text-5xl font-bold text-white leading-tight mb-4">
            Four phases. No fluff.
        </h2>
        <p class="text-slate-400 text-lg mb-16 max-w-2xl">Every engagement runs through the same structure, adapted to your business. You always know exactly where we are and what gets forged next.</p>

        <div class="grid md:grid-cols-2 gap-8">
            <!-- Phase 1 -->
            <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:border-brand-500/40 transition-colors">
                <div class="text-brand-500 text-4xl font-black mb-4">01</div>
                <h3 class="text-xl font-bold text-white mb-3">Audit</h3>
                <p class="text-slate-400 leading-relaxed">We figure out where you actually are. Which systems exist, which ones live in your head, where the biggest gaps are. This is the honest part. Most business owners have never had someone sit down and map their actual business &mdash; not the version they think they have, the one that's really running day-to-day.</p>
                <div class="mt-6 space-y-2">
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Operational systems assessment
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Revenue &amp; financial architecture review
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Marketing and offer diagnosis
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        People &amp; role gap analysis
                    </div>
                </div>
            </div>

            <!-- Phase 2 -->
            <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:border-brand-500/40 transition-colors">
                <div class="text-brand-500 text-4xl font-black mb-4">02</div>
                <h3 class="text-xl font-bold text-white mb-3">Architecture</h3>
                <p class="text-slate-400 leading-relaxed">We design the franchise prototype for your specific business. Your Primary Aim, your Strategic Objective, your org design, your systems map. We define exactly what your business needs to do &mdash; and exactly who or what needs to do it &mdash; for you to step back from the day-to-day. This is the blueprint before the forge fires up.</p>
                <div class="mt-6 space-y-2">
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Primary Aim &amp; Strategic Objective definition
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Organizational design &amp; role mapping
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Offer architecture &amp; pricing structure
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Full systems map: ops, marketing, finance, people
                    </div>
                </div>
            </div>

            <!-- Phase 3 -->
            <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:border-brand-500/40 transition-colors">
                <div class="text-brand-500 text-4xl font-black mb-4">03</div>
                <h3 class="text-xl font-bold text-white mb-3">Forge</h3>
                <p class="text-slate-400 leading-relaxed">This is where the work gets done. Operations manuals. Marketing systems. Financial reporting architecture. Hiring and training documentation. Where a specific problem requires a software tool, we build that too. Raw material goes in; working systems come out. This is the phase most consultants skip entirely &mdash; they hand you the blueprint and call it done.</p>
                <div class="mt-6 space-y-2">
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Operations manuals &amp; process documentation
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Marketing system: lead gen, conversion, retention
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        KPI dashboard &amp; financial reporting framework
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Custom software tools where the problem requires it
                    </div>
                </div>
            </div>

            <!-- Phase 4 -->
            <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:border-brand-500/40 transition-colors">
                <div class="text-brand-500 text-4xl font-black mb-4">04</div>
                <h3 class="text-xl font-bold text-white mb-3">Handoff</h3>
                <p class="text-slate-400 leading-relaxed">We train your team, transition your role, and verify the machine runs without you standing next to it. A system that only works when the builder is in the room isn't a system &mdash; it's a dependency. The measure of success is simple: can your business run for two weeks while you're on a beach? If not, we're not done.</p>
                <div class="mt-6 space-y-2">
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Team training &amp; system adoption
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Owner role transition
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        30-day post-handoff support window
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="w-4 h-4 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Verified: business runs without you for 2+ weeks
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     DELIVERABLES
══════════════════════════════════════════════ -->
<section id="deliverables" class="py-24 px-6 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="text-brand-600 text-sm font-semibold uppercase tracking-widest mb-4">Deliverables</div>
        <h2 class="text-4xl md:text-5xl font-bold text-slate-900 leading-tight mb-4">
            What comes out of the forge.
        </h2>
        <p class="text-lg text-slate-500 mb-12 max-w-2xl">Every engagement is scoped to your specific situation. Here are some examples of the type of installed systems you could walk away with.</p>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            $deliverables = [
                ['icon' => '📋', 'title' => 'Operations Manual', 'desc' => 'Every core business process documented, step by step. Anyone of ordinary skill can run it without asking you.'],
                ['icon' => '🏗️', 'title' => 'Org Design & Role Definitions', 'desc' => 'Clear org chart, defined roles, accountability structure — even if you\'re a team of two today.'],
                ['icon' => '📣', 'title' => 'Marketing System', 'desc' => 'Documented lead generation, conversion, and retention — not a campaign, an actual repeatable system.'],
                ['icon' => '💰', 'title' => 'Financial Architecture', 'desc' => 'KPI dashboard, financial reporting framework, and offer pricing structure built on your actual numbers.'],
                ['icon' => '🧩', 'title' => 'Offer Architecture', 'desc' => 'Your offers restructured for clarity, profitability, and scalability. What you sell, how you price it, and why.'],
                ['icon' => '🛠️', 'title' => 'Custom Software (where needed)', 'desc' => 'Where a spreadsheet or PDF won\'t cut it, we build the actual tool. Not as a workaround — as the system.'],
                ['icon' => '👥', 'title' => 'Hiring & Training Docs', 'desc' => 'The materials your next hire needs to get up to speed. Documented and tested before we call it done.'],
                ['icon' => '📊', 'title' => 'Innovation–Quantification–Orchestration Cycle', 'desc' => 'The ongoing improvement framework installed in the business so it keeps getting better after we\'re done.'],
                ['icon' => '🏷️', 'title' => 'A Business You Can Sell', 'desc' => 'The real test: would a buyer pay for this business if you walked away tomorrow? After this engagement, the answer changes.'],
            ];
            foreach ($deliverables as $d):
            ?>
            <div class="bg-slate-50 rounded-xl p-6 border border-slate-100 hover:border-brand-200 hover:bg-brand-50/30 transition-colors">
                <div class="text-2xl mb-3"><?= $d['icon'] ?></div>
                <h3 class="font-semibold text-slate-900 mb-2"><?= h($d['title']) ?></h3>
                <p class="text-sm text-slate-500 leading-relaxed"><?= h($d['desc']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     ABOUT / WHY DIFFERENT
══════════════════════════════════════════════ -->
<section id="about" class="py-24 px-6 bg-slate-950">
    <div class="max-w-3xl mx-auto">
        <div class="text-brand-500 text-sm font-semibold uppercase tracking-widest mb-4">Why the Foundry</div>
        <h2 class="text-4xl md:text-5xl font-bold text-white leading-tight mb-8">
            The combination nobody else brings to the forge.
        </h2>
        <div class="prose prose-lg prose-invert prose-slate max-w-none">
            <p class="text-slate-300">I've started, built, and sold multiple businesses &mdash; so I know what "sellable" looks like from the inside. I've done business turnaround work with hundreds of small businesses, so I know how bad it can get and how fast it can turn around when the right systems get installed. I've mentored dozens of tech startup founders, so the specific problems that come with a tech-forward business aren't foreign to me.</p>
            <p class="text-slate-300">On top of that: I've invested in roughly 30 startups and done full due diligence on each of them. You want to know what sophisticated buyers actually look at when they evaluate a business? It's the systems. Every time. The pitch deck is irrelevant if the underlying business can't demonstrate repeatable, documented processes.</p>
            <p class="text-slate-300">Accounting and tax background means the financial architecture is clean from day one &mdash; not retrofit. Marketing experience means the demand side actually works, not just looks good on paper. And I can build the software tools that implement the systems we design. That's not an upsell &mdash; it's part of what gets forged.</p>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     STORY 2 PLACEHOLDER
══════════════════════════════════════════════ -->
<section class="py-16 px-6 bg-slate-900 border-y border-white/5">
    <div class="max-w-3xl mx-auto">
        <div class="border-l-4 border-brand-500 pl-6">
            <!-- ╔══════════════════════════════════════════════════════╗
                 ║  STORY 2 — "I've done this myself"                  ║
                 ║                                                      ║
                 ║  Write a paragraph about building or selling one    ║
                 ║  of your own businesses: what had to be true about  ║
                 ║  the systems for it to be sellable/transferable?    ║
                 ║  Or: a moment where you DIDN'T have the system      ║
                 ║  and it cost you. Your call.                         ║
                 ╚══════════════════════════════════════════════════════╝ -->
            <p class="text-xl text-slate-500 italic">[STORY 2: The "I've done it myself" moment — replace this placeholder with your paragraph]</p>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     STORY 3 PLACEHOLDER
══════════════════════════════════════════════ -->
<section class="py-16 px-6 bg-slate-900 border-b border-white/5">
    <div class="max-w-3xl mx-auto">
        <div class="border-l-4 border-brand-500 pl-6">
            <!-- ╔══════════════════════════════════════════════════════╗
                 ║  STORY 3 — The investor's view                      ║
                 ║                                                      ║
                 ║  Write a paragraph from your 30-startup investment  ║
                 ║  experience: what did you see over and over in       ║
                 ║  businesses that couldn't scale? What does the      ║
                 ║  absence of the franchise prototype actually look   ║
                 ║  like in a cap table conversation or due diligence? ║
                 ╚══════════════════════════════════════════════════════╝ -->
            <p class="text-xl text-slate-500 italic">[STORY 3: The investor's view — replace this placeholder with your paragraph]</p>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     WHO THIS IS FOR
══════════════════════════════════════════════ -->
<section class="py-24 px-6 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="grid md:grid-cols-2 gap-12">
            <div>
                <div class="text-brand-600 text-sm font-semibold uppercase tracking-widest mb-4">Who this is for</div>
                <h2 class="text-3xl font-bold text-slate-900 mb-6">The right fit.</h2>
                <ul class="space-y-4">
                    <?php
                    $forList = [
                        'Small business owners doing $250K–$5M who are still the primary bottleneck in their own operation',
                        'Founders who know they have something real but can\'t figure out how to get out of their own way',
                        'Businesses where if the owner disappeared for a month, the whole thing would grind to a halt',
                        'Operators who have tried to hire their way out of this and found it made things more chaotic, not less',
                        'Anyone who has said "I need to document this" for three years and still hasn\'t',
                    ];
                    foreach ($forList as $item):
                    ?>
                    <li class="flex gap-3">
                        <svg class="w-5 h-5 text-brand-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span class="text-slate-600"><?= h($item) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <div class="text-slate-400 text-sm font-semibold uppercase tracking-widest mb-4">Who this is NOT for</div>
                <h2 class="text-3xl font-bold text-slate-900 mb-6">Save us both the time.</h2>
                <ul class="space-y-4">
                    <?php
                    $notForList = [
                        'Businesses under $250K — the math on this type of engagement doesn\'t work at that scale yet',
                        'Owners looking for someone to validate what they\'re already doing rather than actually fix it',
                        'Anyone who wants strategy documents without implementation — we\'re forging a working system, not filling a slide deck',
                        'Founders who can\'t commit dedicated time during the engagement — the forge requires your input to work',
                        'Anyone who isn\'t willing to hear hard truths about their business',
                    ];
                    foreach ($notForList as $item):
                    ?>
                    <li class="flex gap-3">
                        <svg class="w-5 h-5 text-slate-300 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        <span class="text-slate-500"><?= h($item) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     SCOPE CREEP CALLOUT
══════════════════════════════════════════════ -->
<section class="py-16 px-6 bg-brand-500">
    <div class="max-w-4xl mx-auto flex flex-col md:flex-row items-center gap-8">
        <div class="flex-1">
            <h3 class="text-2xl font-bold text-white mb-3">A note on scope creep.</h3>
            <p class="text-brand-100 leading-relaxed">Every engagement runs on a locked scope document that both parties accept before the forge fires up. If something new comes up &mdash; and it always does &mdash; it goes through a formal change request. This protects you as much as it protects me. You always know exactly what's being forged and exactly what you've already paid for.</p>
        </div>
        <div class="flex-shrink-0">
            <div class="bg-white/20 rounded-2xl p-6 text-center">
                <div class="text-4xl font-black text-white mb-1">0</div>
                <div class="text-brand-100 text-sm">Surprise invoices</div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     PRICING
══════════════════════════════════════════════ -->
<section class="py-24 px-6 bg-white">
    <div class="max-w-3xl mx-auto">
        <div class="text-brand-600 text-sm font-semibold uppercase tracking-widest mb-4 text-center">Pricing</div>
        <h2 class="text-3xl font-bold text-slate-900 mb-4 text-center">No hourly rates. No retainers. No surprises.</h2>
        <p class="text-lg text-slate-500 mb-12 text-center">Engagements are priced by the week, not the hour. Here's how that works.</p>

        <div class="bg-slate-950 rounded-2xl p-10 text-center mb-10">
            <div class="text-slate-400 text-sm font-semibold uppercase tracking-widest mb-3">Weekly rate</div>
            <div class="text-7xl font-black text-white mb-2">$3,500</div>
            <div class="text-slate-400 text-lg">per week</div>
        </div>

        <div class="space-y-5 text-slate-600 leading-relaxed">
            <p>After our discovery call, I'll tell you exactly how many weeks the engagement will take. That number is the scope. You'll know the total cost before we start, and it won't change unless you add something through the formal change request process.</p>
            <p>Billing by the week instead of by the hour changes the dynamic entirely. You're not watching the clock. I'm not padding time. We're both focused on getting the systems installed and handed off — not on how long it takes to get there.</p>
            <p>Some engagements are a single week — a focused audit, a specific system, one thing that needs to get done. Others run four, six, eight weeks. The maximum I'll take on is twelve. After the discovery call, I'll tell you exactly what I think it takes. If your situation can't be addressed in that window, we'd be doing you a disservice by pretending otherwise.</p>
            <p class="text-slate-400 text-sm">Think of it this way: you're buying focused attention on your business, a week at a time — the same attention I'd give a startup I personally invested in.</p>
            <p class="mt-6 pt-6 border-t border-slate-100 text-slate-500">One more thing, in the interest of full transparency: I use AI in this work. When the spreadsheet was invented, accountants didn't get slower — they got dramatically more productive. Same thing happened when machine shops got programmable CNC equipment. AI is that tool for this kind of work. It helps me move faster, document more thoroughly, and deliver more in a week than would otherwise be possible. The judgment, the diagnosis, the decision-making — that's still me. But I'm not going to pretend the tools don't exist, and I'm not going to charge you for hours I didn't spend.</p>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     FINAL CTA
══════════════════════════════════════════════ -->
<section class="py-32 px-6 bg-slate-950">
    <div class="max-w-2xl mx-auto text-center">
        <div class="text-brand-500 text-sm font-semibold uppercase tracking-widest mb-6">Ready to get out of your own way?</div>
        <h2 class="text-4xl md:text-6xl font-bold text-white leading-tight mb-6">
            Step into the foundry.
        </h2>
        <p class="text-lg text-slate-400 mb-10">The application takes about ten minutes. I read every one personally. If we're a fit, we'll schedule a discovery call. If not, I'll tell you directly &mdash; no form letter.</p>
        <a href="<?= u('/apply') ?>" class="inline-flex items-center gap-3 bg-brand-500 hover:bg-brand-600 text-white font-bold px-10 py-5 rounded-xl text-xl transition-colors shadow-lg shadow-brand-500/20">
            Apply for an Engagement
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
        <p class="text-slate-600 text-sm mt-6">Limited engagements accepted per quarter. Application does not obligate you in any way.</p>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════ -->
<footer class="bg-slate-950 border-t border-white/5 px-6 py-10">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-slate-600">
        <div>Bizorca Foundry &mdash; a Bizorca LLC company</div>
        <div class="flex gap-6">
            <a href="<?= u('/apply') ?>" class="hover:text-slate-400 transition-colors">Apply</a>
            <a href="https://bizorca.com" class="hover:text-slate-400 transition-colors">Bizorca</a>
            <a href="<?= u('/login') ?>" class="hover:text-slate-400 transition-colors">Client Login</a>
        </div>
    </div>
</footer>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
