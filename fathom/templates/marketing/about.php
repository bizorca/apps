<?php
/** Ported from resources/views (Blade). */
$__title = 'About — Fathom';
$__meta = 'Why we built Fathom, who it is for, and what "free forever" actually means. No corporate vision statement. Just a straight explanation.';
ob_start();
?>
<section class="max-w-3xl mx-auto px-6 pt-20 pb-16">
    <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight mb-6">
        Why this exists.
    </h1>
    <p class="text-xl text-gray-500 leading-relaxed">
        The short version: we needed a Kanban board for a service business. The options on the market were either a toy or an enterprise platform with an enterprise price tag. So we built our own.
    </p>
</section>


<section class="max-w-3xl mx-auto px-6 pb-24 space-y-12 text-gray-700 text-lg leading-relaxed">

    <div class="prose-like space-y-5">
        <h2 class="text-2xl font-bold text-gray-900">The tool problem every solo operator knows</h2>
        <p>
            If you run a small service business — coaching clients, filing tax returns, placing candidates in jobs — you've tried a dozen tools. Trello used to be the answer, until they gutted the free plan. Asana is fine if you have a project management department to manage it. Notion is a blank canvas that will eat your afternoon before you've created a single useful thing.
        </p>
        <p>
            What you actually need is simple: a board that shows you where every client stands, columns that match your workflow, and enough structure to stay on top of things without building a second job managing the tool itself.
        </p>
    </div>

    <div class="space-y-5">
        <h2 class="text-2xl font-bold text-gray-900">Where Fathom came from</h2>
        <p>
            Fathom is a PHP/Laravel app built from the ground up for independent service professionals. We added business-type onboarding templates, a client portal, and all the workflow-specific features a solo operator actually needs — then deployed it as a free product under our own flag.
        </p>
        <p>
            The name is Fathom — as in, to fully understand something. Which is the job of a good dashboard: give you clear visibility into where everything stands, so you're never guessing.
        </p>
    </div>

    <div class="space-y-5">
        <h2 class="text-2xl font-bold text-gray-900">Who this is built for</h2>
        <p>Fathom is for independent professionals and small teams who manage client relationships as the core of their work. People like:</p>
        <ul class="space-y-2 ml-5">
            <?php foreach ([
                'Financial coaches tracking client pipelines and active engagements',
                'Tax consultants managing returns from intake through filing',
                'Career coaches moving candidates from resume review through offer',
                'Consultants, bookkeepers, virtual assistants — anyone billing by the client',
                'Small teams where one person can\'t afford to drop a ball',
            ] as $item): ?>
            <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-indigo-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                <?= e($item) ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <p>
            If you're managing software sprints or running a fifty-person engineering team, Fathom probably isn't your tool. There are better options for that. This one is built for the people who don't need a thousand features — just the right ones.
        </p>
    </div>

    <div class="space-y-5">
        <h2 class="text-2xl font-bold text-gray-900">What "free forever" actually means</h2>
        <p>
            Freemium is a trap. You get hooked on a tool, build your whole workflow around it, and then one day the pricing changes. Features get locked behind a paywall. Your data becomes a hostage. We've all been through it.
        </p>
        <p>
            Fathom is not freemium. There is no paid tier. There is no upgrade prompt. There is no "you've hit your free limit" notification. The product is free because we believe small service businesses shouldn't pay a monthly subscription tax just to track their own client work.
        </p>
        <p>
            Your data is yours. Export it any time in standard formats. We won't delete it, hold it hostage, or use it to train anything.
        </p>

        <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-6">
            <div class="font-bold text-indigo-900 mb-2">The free forever commitment</div>
            <ul class="space-y-1.5 text-indigo-800 text-base">
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> No credit card — now or ever</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> No paid tier hiding features</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> No seat limits or board caps</li>
                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg> Export your data any time, in full</li>
            </ul>
        </div>
    </div>

    <div class="space-y-5">
        <h2 class="text-2xl font-bold text-gray-900">Support Fathom</h2>
        <p>
            Fathom is free because it should be. But if it's earning its place in your workflow — if you're actually billing clients and running your business out of it — you can pay what you think it's worth. Not a subscription. Not a donation. Just a one-time payment, whatever amount feels right to you.
        </p>
        <p>
            Think of it like Bandcamp: you set the price. Five bucks, fifty bucks, nothing — all of those are fine. The app doesn't change either way. It's just a way to say the tool has value, if it does.
        </p>
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6">
            <p class="text-gray-700 font-medium mb-3">Pay What You Want</p>
            <p class="text-sm text-gray-500 mb-4">Fathom is free because we believe it should be. If it's making your business better, you can support its development — pay what that value is worth.</p>
            <a href="<?= e(FM_SUPPORT_URL) ?>" class="inline-block bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition">
                Support Fathom
            </a>
        </div>
    </div>

    <div class="space-y-5">
        <h2 class="text-2xl font-bold text-gray-900">What's next</h2>
        <p>
            Fathom is actively developed. The business type template library will grow — real estate investors, bookkeepers, virtual assistants, and more are on the list. The core boards-and-cards experience will keep getting sharper. And the price will stay exactly where it is.
        </p>
        <p>
            If Fathom doesn't do something you need, or if it does something wrong, tell us. The feedback loop is short.
        </p>
    </div>

</section>


<section class="bg-gray-50 border-t border-gray-200 py-20">
    <div class="max-w-2xl mx-auto px-6 text-center">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Give it a try. It costs exactly nothing.</h2>
        <p class="text-gray-500 mb-8">Takes about 60 seconds to create an account. Pick your business type and your boards are ready to go.</p>
        <a href="<?= e(route('signup')) ?>" class="inline-block bg-indigo-600 text-white font-bold px-8 py-3.5 rounded-xl text-base hover:bg-indigo-700 transition">
            Get started free
        </a>
    </div>
</section>
<?php fm_layout('marketing', (string) $__title, (string) ob_get_clean(), ['meta' => $__meta]); ?>
