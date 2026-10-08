<?php
/**
 * The apex landing page.
 *
 * Two audiences arrive here wanting opposite things: an advisor who heard the
 * name and wants to know what it is, and a business owner who mistyped their
 * coach's address and is lost. The advisor gets the page; the lost client gets
 * a signpost that is impossible to miss, near the top, because they are the one
 * in trouble.
 *
 * @var bool $beta
 * @var bool $intakeOpen
 * @var array<int,array{0:string,1:string}> $plansTeaser
 */
use Bizorca\Pilotage\Services\Billing;
?>

<section class="relative overflow-hidden">
    <div class="mx-auto max-w-6xl px-5 pb-20 pt-20 sm:pt-28">
        <?php if ($beta): ?>
            <p class="mb-7 inline-flex items-center gap-2 rounded-full border border-tide-100 bg-tide-50 px-3.5 py-1.5 text-sm text-tide-700">
                <span class="h-1.5 w-1.5 rounded-full bg-tide-500"></span>
                Free during beta — every feature, no card
            </p>
        <?php endif; ?>

        <h1 class="max-w-3xl font-display text-4xl leading-[1.1] tracking-tight sm:text-6xl">
            Your methodology, running itself
            <span class="text-tide-600">across every client you have.</span>
        </h1>

        <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-600">
            Pilotage is a shared workspace for business coaches, advisors and consultants — and for
            the businesses they work with. You encode your process once. Every engagement runs on it,
            the client does the work in the same place you review it, and both sides look at the same
            scoreboard.
        </p>

        <div class="mt-9 flex flex-wrap items-center gap-3">
            <a href="<?= h(app_url('/signup')) ?>"
               class="rounded-md bg-ink px-5 py-3 text-sm font-medium text-white hover:bg-ink-soft">
                <?= $beta ? 'Create your workspace — free' : 'Start your free trial' ?>
            </a>
            <a href="<?= h(app_url('/pricing')) ?>"
               class="rounded-md border border-slate-300 px-5 py-3 text-sm font-medium hover:bg-slate-50">
                See pricing
            </a>
        </div>

        <p class="mt-4 text-sm text-slate-500">
            <?= $beta
                ? 'No card. No seat limits. Nothing is charged while the product is in beta.'
                : 'Fourteen days, no card. Your data exports in full, whenever you want it.' ?>
        </p>

        <div class="hairline mt-16 h-px"></div>
    </div>
</section>

<?php /* ------------------------------------------------ the lost client */ ?>
<section class="mx-auto max-w-6xl px-5">
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 sm:flex sm:items-center sm:gap-8">
        <div class="sm:flex-1">
            <h2 class="font-medium">Looking for your coach?</h2>
            <p class="mt-1 text-sm leading-relaxed text-slate-600">
                Your advisor's workspace has its own address — something like
                <span class="font-mono text-xs"><?= h(base_domain() . PL_BASE) ?>/f/yourfirm</span>. Use the link they sent you,
                or sign in with your Bizorca Tools account and pick it from your workspaces.
            </p>
        </div>
        <a href="<?= h(app_url('/signin')) ?>"
           class="mt-4 inline-block shrink-0 rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium hover:bg-slate-100 sm:mt-0">
            Find my firm
        </a>
    </div>
</section>

<?php /* ------------------------------------------------ what makes it different */ ?>
<section class="mx-auto max-w-6xl px-5 py-24">
    <h2 class="max-w-2xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
        Built for advisory work, not for life coaching with a business word swapped in.
    </h2>
    <p class="mt-4 max-w-2xl text-slate-600">
        Nearly every coaching platform assumes one coach and one human client. Business advisory
        does not work that way, and three consequences fall out of that.
    </p>

    <div class="mt-12 grid gap-10 md:grid-cols-3">
        <?php
        $pillars = [
            [
                'The client is a company.',
                'A founder, a COO and a controller all need access to the same engagement with '
                . 'different visibility. Client organizations are first-class here, with their own '
                . 'contacts and their own side of the wall. A sponsor who pays for the engagement '
                . 'can watch progress without ever reading a session note — enforced in code, '
                . 'not by convention.',
            ],
            [
                'The methodology is an object.',
                'EOS, Scaling Up, Pinnacle, StratOp, or the twelve-week program you wrote yourself. '
                . 'Encode it once as a playbook with phases, steps, gates and attached templates, '
                . 'then instantiate it per client. Editing the template never mutates a live '
                . 'engagement; each one holds a snapshot of the version it started on.',
            ],
            [
                'Accountability closes the loop.',
                'A commitment made in a session becomes a tracked task, becomes a nudge, becomes a '
                . 'check-in, and opens the agenda of the next session automatically. Miss it twice '
                . 'and it stops being a reminder and becomes an issue to solve. That is the loop '
                . 'most tools leave to the coach to remember.',
            ],
        ];
        foreach ($pillars as $i => [$heading, $body]): ?>
            <div>
                <div class="font-display text-3xl text-tide-600"><?= $i + 1 ?></div>
                <h3 class="mt-3 font-medium"><?= h($heading) ?></h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= h($body) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php /* ------------------------------------------------ the loop, drawn */ ?>
<section class="bg-ink py-24 text-white">
    <div class="mx-auto max-w-6xl px-5">
        <h2 class="max-w-2xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
            What happens to a promise made on a Tuesday
        </h2>
        <p class="mt-4 max-w-2xl text-slate-300">
            This is the sequence the product exists to run. Every step is automatic; the coach's job
            is the conversation, not the bookkeeping around it.
        </p>

        <ol class="mt-12 grid gap-px overflow-hidden rounded-xl bg-white/10 sm:grid-cols-2 lg:grid-cols-3">
            <?php
            $loop = [
                ['In the session', 'The client commits to something specific, with a date and a definition of done. It is captured while you are still talking.'],
                ['Immediately after', 'It becomes a task owned by a named person on the client side — not a line in your notes that only you can see.'],
                ['As the date nears', 'A nudge goes out on their preferred channel, batched into a digest if that is what they asked for. Nobody gets pestered.'],
                ['On the due date', 'They mark it done, or say what got in the way. The second one is more useful and the product treats it that way.'],
                ['Next session', 'Open commitments are already the first block of the agenda. You never start a meeting reconstructing the last one.'],
                ['Missed twice', 'It stops being a task and becomes an issue — a thing to identify, discuss and solve, rather than a quiet failure nobody names.'],
            ];
            foreach ($loop as $n => [$when, $what]): ?>
                <li class="bg-ink p-6">
                    <div class="text-xs font-medium uppercase tracking-wider text-tide-500"><?= h($when) ?></div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-300"><?= h($what) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<?php /* ------------------------------------------------ what is in it */ ?>
<section class="mx-auto max-w-6xl px-5 py-24">
    <h2 class="font-display text-3xl leading-tight tracking-tight sm:text-4xl">Everything an engagement needs</h2>
    <p class="mt-4 max-w-2xl text-slate-600">
        One workspace per firm, one engagement per client, and every artifact belongs to exactly one
        engagement with an explicit visibility flag. No inference, no defaults that leak.
    </p>

    <div class="mt-12 grid gap-x-10 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
        <?php
        $features = [
            ['Playbooks', 'Versioned methodologies. Phases, steps, completion criteria, gating, and artifact templates that come with the step. Import one, start from ours, or write your own.'],
            ['Sessions', 'Agendas built from templates and from what is actually outstanding. Shared notes the client sees, private notes they never do, and a recap that writes itself.'],
            ['Tasks and commitments', 'Two-way accountability. Coaches owe things too, and the client can see whether you delivered — which is the part most tools quietly leave out.'],
            ['Goals, metrics, issues', 'Ninety-day priorities with milestones, a weekly scorecard with targets and trend, and an issues list that runs identify, discuss, solve.'],
            ['Documents', 'Versioned files with a delivery and acknowledgment lifecycle. You can prove what was sent, when, and whether anyone opened it.'],
            ['Worksheets', 'Structured forms assigned to a client and submitted back. They can be genuinely anonymous when the answers need to be — and then they are anonymous to us too.'],
            ['Messaging', 'Threads standalone or attached to any object, with mentions. The conversation lives next to the thing it is about instead of in a mailbox.'],
            ['Reporting and health', 'A per-engagement health score that withholds itself rather than guessing when it has too little to go on, plus a firm-wide dashboard and CSV export.'],
            ['Client portal', 'The other side of the wall, under your branding at your workspace address. Your clients see your firm, not ours.'],
        ];
        foreach ($features as [$name, $blurb]): ?>
            <div>
                <h3 class="flex items-center gap-2 font-medium">
                    <span class="h-1 w-1 rounded-full bg-tide-500"></span><?= h($name) ?>
                </h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= h($blurb) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php /* ------------------------------------------------ trust */ ?>
<section class="border-y border-slate-200 bg-slate-50 py-24">
    <div class="mx-auto max-w-6xl px-5">
        <h2 class="max-w-2xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
            The promises that are load-bearing
        </h2>
        <p class="mt-4 max-w-2xl text-slate-600">
            You are about to put a year of client relationships somewhere. These are the four things
            we decided we could not walk back later, and built accordingly.
        </p>

        <div class="mt-12 grid gap-8 sm:grid-cols-2">
            <?php
            $promises = [
                ['Your data leaves whenever you want', 'A full export of everything, at any time, without asking anyone. It keeps working even if you stop paying — revoking it exactly when someone needs it most would make trusting us retroactively irrational.'],
                ['Lapsing makes it read-only, not gone', 'If a subscription ends, the workspace stops accepting writes. Nothing is deleted, nothing is hidden, no client is locked out of documents you already delivered, and one payment makes it writable again.'],
                ['Firms cannot see each other', 'Every query against tenant-owned data goes through one scoping layer that injects the firm id, and a test suite asks the schema which tables carry one and attacks every table that does. A cross-tenant leak is the failure that ends this business, so it is the thing most tested.'],
                ['Erasure means erasure', 'A right-to-erasure request pseudonymises a person across every table that stores their name — it does not orphan the engagement record. Retention defaults to keep-forever, and scheduled destruction is announced thirty days before it happens.'],
            ];
            foreach ($promises as [$heading, $body]): ?>
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <h3 class="font-medium"><?= h($heading) ?></h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= h($body) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php /* ------------------------------------------------ pricing teaser */ ?>
<section class="mx-auto max-w-6xl px-5 py-24">
    <div class="sm:flex sm:items-end sm:justify-between">
        <div>
            <h2 class="font-display text-3xl leading-tight tracking-tight sm:text-4xl">Priced per firm, not per client</h2>
            <p class="mt-4 max-w-xl text-slate-600">
                You pay for advisor seats. Client contacts are never seats and never will be — charging
                for them would give us a reason to want you inviting fewer of your clients' people,
                which is the opposite of what this is for.
            </p>
        </div>
        <a href="<?= h(app_url('/pricing')) ?>" class="mt-6 inline-block text-sm font-medium text-tide-600 hover:text-tide-700 sm:mt-0">
            Full pricing and what is in each plan &rarr;
        </a>
    </div>

    <div class="mt-10 grid gap-4 sm:grid-cols-3">
        <?php foreach (Billing::PURCHASABLE as $key):
            $plan = Billing::PLANS[$key]; ?>
            <div class="rounded-xl border border-slate-200 p-5">
                <div class="font-medium"><?= h($plan['name']) ?></div>
                <div class="mt-1 font-display text-2xl">
                    <?php if ($beta): ?>
                        <span class="text-tide-600">Free</span>
                        <span class="align-middle text-sm text-slate-400 line-through">$<?= number_format($plan['monthly'] / 100) ?></span>
                    <?php else: ?>
                        $<?= number_format($plan['monthly'] / 100) ?><span class="text-base text-slate-500">/mo</span>
                    <?php endif; ?>
                </div>
                <p class="mt-2 text-sm text-slate-600"><?= h($plan['blurb']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php /* ------------------------------------------------ closing */ ?>
<section class="mx-auto max-w-6xl px-5 pb-8">
    <div class="rounded-2xl bg-ink px-6 py-14 text-center text-white sm:px-14">
        <h2 class="mx-auto max-w-2xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
            Pick your address and start with one real client.
        </h2>
        <p class="mx-auto mt-4 max-w-xl text-slate-300">
            Setup installs a neutral ninety-day operating rhythm and two session agendas — a starting
            shape to rewrite rather than a blank page. Most firms have their first client invited
            inside fifteen minutes.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="<?= h(app_url('/signup')) ?>"
               class="rounded-md bg-white px-5 py-3 text-sm font-medium text-ink hover:bg-slate-100">
                <?= $beta ? 'Create your workspace — free' : 'Start your free trial' ?>
            </a>
            <a href="<?= h(app_url('/faq')) ?>"
               class="rounded-md border border-white/25 px-5 py-3 text-sm font-medium text-white hover:bg-white/10">
                Read the FAQ first
            </a>
        </div>

        <?php if ($intakeOpen): ?>
            <p class="mt-8 border-t border-white/10 pt-6 text-sm text-slate-400">
                Not an advisor — looking for one?
                <a href="<?= h(app_url('/apply')) ?>" class="font-medium text-white underline underline-offset-4">
                    Tell us about your business
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>
