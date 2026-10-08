<?php
/**
 * About.
 *
 * Written as an argument rather than a brochure: why this exists, what it
 * refuses to do, and who is behind it. An advisor deciding where to put a
 * year of client relationships is making a judgment about the people, and a
 * page of adjectives does not help them make it.
 *
 * @var bool $beta
 */
?>

<section class="mx-auto max-w-3xl px-5 pt-20">
    <p class="text-sm font-medium uppercase tracking-wider text-tide-600">About</p>
    <h1 class="mt-3 font-display text-4xl leading-tight tracking-tight sm:text-5xl">
        A pilot knows the water. They do not own the ship.
    </h1>

    <div class="mt-8 space-y-6 text-lg leading-relaxed text-slate-700">
        <p>
            Pilotage is the act of guiding a vessel through water that is difficult to navigate —
            a harbour entrance, a strait, a river mouth. A pilot comes aboard at the hardest part of
            the passage, brings knowledge the crew does not have, and hands the ship back. They do
            not own the vessel, they do not sail it, and their name never goes on the manifest.
        </p>
        <p>
            That is the job a business coach or advisor actually does, and it is a surprisingly hard
            shape to buy software for. The tools built for coaches assume one coach and one human
            client working on that human's life. The tools built around a single methodology are
            excellent at it, and they are sold to the company rather than to the advisor running a
            book of twenty of them. Between the two sits the person this is for: someone with a
            process, a roster of client businesses, and no console that treats either as a real thing.
        </p>
    </div>
</section>

<section class="mx-auto max-w-3xl px-5 py-16">
    <h2 class="font-display text-3xl leading-tight tracking-tight">Where it came from</h2>
    <div class="mt-6 space-y-6 leading-relaxed text-slate-700">
        <p>
            Pilotage is built by Bizorca, a small software company that also runs its own consulting
            practice. That practice is tenant number one — the same workspace, the same kind of address, the
            same read-only gate as everyone else, with no special cases in the code for the people
            who wrote it. The product exists because the practice needed it and the thing to buy did
            not exist.
        </p>
        <p>
            It replaced a single-tenant tool called Foundry that handled scope items, change requests,
            and an onboarding checklist for one firm. Four of Foundry's ideas were worth keeping and
            got ported: scope lock, the change-request workflow, ordered onboarding steps, and
            comment threads on everything. The rest was rebuilt around a premise Foundry got wrong —
            that a client is a person. A client is a company, and once you accept that, every query,
            every permission check and every screen changes.
        </p>
    </div>
</section>

<section class="border-y border-slate-200 bg-slate-50 py-16">
    <div class="mx-auto max-w-3xl px-5">
        <h2 class="font-display text-3xl leading-tight tracking-tight">What it is built on</h2>
        <p class="mt-4 leading-relaxed text-slate-700">
            The functional requirements were drawn from established practice rather than invented in
            a vacuum. If your methodology is one of these, the shapes will feel familiar. If it is
            your own, the playbook engine does not care.
        </p>

        <dl class="mt-8 space-y-6">
            <?php
            $sources = [
                ['EOS and Traction', 'Rocks as quarterly priorities, the weekly Scorecard, the fixed seven-part meeting agenda, and the identify-discuss-solve track for issues.'],
                ['Scaling Up and the Rockefeller Habits', 'The one-page strategic plan, a thirteen-week cadence, and a visible KPI dashboard with trend lines.'],
                ['GROW and T-GROW', 'Session structure, and the rule that every session ends with specific timed commitments and the next one opens by reviewing them.'],
                ['The ICF Code of Ethics and Core Competencies', 'Coaching agreements as an explicit artifact, the sponsor-versus-client confidentiality distinction, and record retention and disposal that protects confidentiality.'],
            ];
            foreach ($sources as [$name, $what]): ?>
                <div>
                    <dt class="font-medium"><?= h($name) ?></dt>
                    <dd class="mt-1 text-sm leading-relaxed text-slate-600"><?= h($what) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

        <p class="mt-8 text-sm leading-relaxed text-slate-600">
            None of it is mandatory. A playbook is a container for whatever process you already run,
            and the starter one that ships with a new workspace is a neutral ninety-day rhythm you
            are expected to rewrite.
        </p>
    </div>
</section>

<section class="mx-auto max-w-3xl px-5 py-16">
    <h2 class="font-display text-3xl leading-tight tracking-tight">What we decided not to build</h2>
    <p class="mt-4 leading-relaxed text-slate-700">
        A short list is more informative than a long one. These are deliberate refusals, not a
        roadmap in disguise, and each of them is something a competitor will happily sell you.
    </p>

    <div class="mt-8 divide-y divide-slate-200 border-y border-slate-200">
        <?php
        $noes = [
            ['Coach-to-client invoicing', 'What you charge your clients is your business, and a billing system that half-works is worse than none. You almost certainly already have one.'],
            ['Video conferencing', 'You have a preference and it is strongly held. Sessions link out to whatever you already use.'],
            ['Real-time chat', 'Messaging here is threaded and attached to the thing it is about. A live chat window competes with the tool your team already leaves open all day, and loses.'],
            ['E-signature', 'Documents have a delivery and acknowledgment lifecycle, which is what most advisory work actually needs. A legally-weighted signature is a specialist product.'],
            ['A public API, for now', 'Wanted, and deliberately not started until a real customer asks for a specific integration. An API is the hardest thing here to change once somebody depends on it, and guessing at one produces something nobody uses and everybody is stuck with.'],
            ['Custom domains', 'Every firm lives at its own address on Bizorca Tools, permanently. Custom domains mean certificate provisioning, DNS support tickets and a second class of tenant URL. Your workspace carries your logo, your colors and your name.'],
        ];
        foreach ($noes as [$what, $why]): ?>
            <div class="py-5">
                <h3 class="font-medium"><?= h($what) ?></h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600"><?= h($why) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="mx-auto max-w-3xl px-5 pb-24">
    <div class="rounded-2xl border border-slate-200 p-8">
        <h2 class="font-display text-2xl leading-tight tracking-tight">Where it is right now</h2>
        <p class="mt-4 leading-relaxed text-slate-700">
            Every module in the plan is built and live: tenancy and branding, authentication,
            client organizations, playbooks, scope and change control, sessions, tasks, documents,
            messaging, goals and metrics, worksheets, notifications and digests, reporting and
            health scoring, billing, and the audit, retention and erasure machinery.
            <?php if ($beta): ?>
                It is free while we run real engagements through it and find out what advisors
                actually need. Charging for something half-finished is a good way never to find out.
            <?php endif; ?>
        </p>
        <div class="mt-7 flex flex-wrap gap-3">
            <a href="<?= h(app_url('/signup')) ?>"
               class="rounded-md bg-ink px-5 py-3 text-sm font-medium text-white hover:bg-ink-soft">
                <?= $beta ? 'Create your workspace — free' : 'Start your free trial' ?>
            </a>
            <a href="mailto:hello@<?= h(base_domain()) ?>"
               class="rounded-md border border-slate-300 px-5 py-3 text-sm font-medium hover:bg-slate-50">
                Ask a question first
            </a>
        </div>
    </div>
</section>
