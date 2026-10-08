<?php
/**
 * Pricing.
 *
 * Plan data comes from Billing::PLANS and nowhere else. A marketing page that
 * hardcodes a price is a page that eventually contradicts the checkout, and a
 * firm reading one number and being charged another is the single worst thing
 * a pricing page can do.
 *
 * The month/year toggle is a query parameter, not a script. It costs one page
 * load, the URL is shareable, and it works with JavaScript off — which for a
 * three-option toggle is a better trade than any amount of interactivity.
 *
 * @var bool $beta
 * @var string $interval  'month' or 'year'
 * @var int $trialDays
 */
use Bizorca\Pilotage\Services\Billing;

$yearly = $interval === 'year';

/** Cents to a whole-dollar string. Every price here is a round number. */
$money = static fn (int $cents): string => '$' . number_format($cents / 100);

// The middle plan is the one most small firms land on. Saying so is more
// useful than making all three look equally likely.
$highlight = 'practice';
?>

<section class="mx-auto max-w-6xl px-5 pb-4 pt-20">
    <h1 class="max-w-3xl font-display text-4xl leading-tight tracking-tight sm:text-5xl">
        You pay for advisor seats. Your clients' people are free, always.
    </h1>
    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-slate-600">
        A firm with four coaches and three hundred client contacts pays for four. If that were ever
        ambiguous, we would have a reason to want you inviting fewer of your clients' people into
        their own engagement — so it is not ambiguous.
    </p>

    <?php if ($beta): ?>
        <div class="mt-8 rounded-xl border border-tide-100 bg-tide-50 p-6">
            <h2 class="flex items-center gap-2 font-medium text-tide-700">
                <span class="h-1.5 w-1.5 rounded-full bg-tide-500"></span>
                Everything below is free right now
            </h2>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-tide-700/90">
                Pilotage is in beta. Nothing is charged, no card is collected, trials do not expire,
                and seat and storage limits are not applied — on any plan. The prices are published
                anyway so you know what you are growing into, and so nobody discovers the number on
                the day it starts mattering. When that day comes you will hear it from us first.
            </p>
        </div>
    <?php endif; ?>
</section>

<?php /* ------------------------------------------------ interval toggle */ ?>
<section class="mx-auto max-w-6xl px-5 pt-10">
    <div class="flex items-center justify-center">
        <div class="inline-flex rounded-lg border border-slate-300 bg-white p-1 text-sm">
            <a href="<?= h(app_url('/pricing')) ?>"
               class="rounded-md px-4 py-1.5 <?= $yearly ? 'text-slate-600 hover:text-ink' : 'bg-ink font-medium text-white' ?>">
                Monthly
            </a>
            <a href="<?= h(app_url('/pricing?billing=year')) ?>"
               class="rounded-md px-4 py-1.5 <?= $yearly ? 'bg-ink font-medium text-white' : 'text-slate-600 hover:text-ink' ?>">
                Yearly <span class="<?= $yearly ? 'text-tide-100' : 'text-tide-600' ?>">— 2 months free</span>
            </a>
        </div>
    </div>

    <?php /* ------------------------------------------------ the grid */ ?>
    <div class="mt-10 grid gap-6 lg:grid-cols-3">
        <?php foreach (Billing::PURCHASABLE as $key):
            $plan = Billing::PLANS[$key];
            $isHighlight = $key === $highlight;
            $price = $yearly ? (int) $plan['yearly'] : (int) $plan['monthly'];
            ?>
            <div class="relative flex flex-col rounded-2xl border p-7 <?= $isHighlight ? 'border-ink shadow-lg shadow-slate-200' : 'border-slate-200' ?>">
                <?php if ($isHighlight): ?>
                    <div class="absolute -top-3 left-7 rounded-full bg-ink px-3 py-1 text-xs font-medium text-white">
                        Most firms start here
                    </div>
                <?php endif; ?>

                <h2 class="font-display text-2xl"><?= h($plan['name']) ?></h2>
                <p class="mt-1 text-sm text-slate-600"><?= h($plan['blurb']) ?></p>

                <div class="mt-6">
                    <?php if ($beta): ?>
                        <div class="flex items-baseline gap-2">
                            <span class="font-display text-4xl text-tide-600">Free</span>
                            <span class="text-slate-400 line-through"><?= h($money($price)) ?></span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">during beta</p>
                    <?php else: ?>
                        <div class="flex items-baseline gap-1.5">
                            <span class="font-display text-4xl"><?= h($money($price)) ?></span>
                            <span class="text-sm text-slate-500">/ <?= $yearly ? 'year' : 'month' ?></span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">
                            <?= $yearly
                                ? h($money((int) $plan['monthly'])) . ' a month billed monthly'
                                : h($money((int) $plan['yearly'])) . ' a year saves two months' ?>
                        </p>
                    <?php endif; ?>
                </div>

                <ul class="mt-7 flex-1 space-y-3 text-sm">
                    <?php
                    $seats = (int) $plan['seats'];
                    $lines = [
                        $seats === 1 ? 'One advisor seat' : $seats . ' advisor seats',
                        'Unlimited client organizations',
                        'Unlimited client-side contacts, free',
                        (int) $plan['storage_gb'] . ' GB of document storage',
                        'Playbooks, sessions, tasks, goals, metrics, issues',
                        'Worksheets, documents, messaging, reporting',
                        'Your own workspace address and branding',
                        $plan['remove_credit']
                            ? 'Remove the “powered by Pilotage” credit'
                            : 'Small “powered by Pilotage” footer credit',
                        'Full data export, any time, forever',
                    ];
                    foreach ($lines as $line): ?>
                        <li class="flex gap-2.5">
                            <svg viewBox="0 0 20 20" class="mt-0.5 h-4 w-4 shrink-0 text-tide-500" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="m4 10.5 4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="text-slate-600"><?= h($line) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a href="<?= h(app_url('/signup?plan=' . $key)) ?>"
                   class="mt-8 block rounded-md px-4 py-3 text-center text-sm font-medium <?= $isHighlight
                       ? 'bg-ink text-white hover:bg-ink-soft'
                       : 'border border-slate-300 hover:bg-slate-50' ?>">
                    <?= $beta ? 'Start free' : 'Start ' . h($plan['name']) ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="mt-8 text-center text-sm text-slate-500">
        <?= $beta
            ? 'No card is collected during beta, whichever plan you pick. You can change it later.'
            : 'Every plan starts with a ' . (int) $trialDays . '-day free trial. No card until it ends.' ?>
    </p>
</section>

<?php /* ------------------------------------------------ what happens if you stop */ ?>
<section class="mx-auto mt-24 max-w-6xl px-5">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-8 sm:p-10">
        <h2 class="font-display text-3xl leading-tight tracking-tight">What happens if you stop paying</h2>
        <p class="mt-4 max-w-3xl leading-relaxed text-slate-600">
            The workspace goes read-only. That is the entire penalty, and it is worth being specific
            about, because most SaaS answers to this question are deliberately vague.
        </p>

        <div class="mt-8 grid gap-8 sm:grid-cols-2">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Still works, unpaid</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                    <li>Reading everything, on both sides</li>
                    <li>Your clients' access to documents you already delivered</li>
                    <li>The complete data export</li>
                    <li>Signing in, and the billing screen so you can fix it</li>
                </ul>
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Never happens</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                    <li>Deleting your data because an invoice failed</li>
                    <li>Hiding it behind a paywall until you pay</li>
                    <li>Locking your clients out of their own engagement</li>
                    <li>Making you file a support request to get your data back</li>
                </ul>
            </div>
        </div>

        <p class="mt-8 max-w-3xl text-sm leading-relaxed text-slate-600">
            A card that expires or a bank that declines a legitimate charge does not lock you out
            either — a past-due account stays writable while Stripe retries. Getting between a coach
            and live client work over a payment that is still in flight produces a cancellation, not
            a collection.
        </p>
    </div>
</section>

<?php /* ------------------------------------------------ pricing questions */ ?>
<section class="mx-auto max-w-6xl px-5 py-24">
    <h2 class="font-display text-3xl leading-tight tracking-tight">Questions about the money</h2>

    <div class="mt-8 max-w-3xl divide-y divide-slate-200 border-y border-slate-200">
        <?php
        $qs = [
            ['What counts as a seat?',
             'An active firm-side person: you, your coaches, your associates. Client-side contacts — '
             . 'the owners, COOs and controllers at the businesses you advise — are never seats, in '
             . 'any number.'],
            ['Can I change plans later?',
             'Yes, in either direction, from the billing screen. Moving down to a plan with fewer '
             . 'seats than you are using is refused until you deactivate someone, which is a clearer '
             . 'failure than silently disabling whoever signed up last.'],
            ['Do you bill my clients?',
             'No. The platform never charges a client organization anything. What you invoice your '
             . 'clients for is between you and them — coach-to-client invoicing is deliberately out '
             . 'of scope here.'],
            ['What happens to my data if I leave?',
             'You export it in full, whenever you want, without asking anyone. That export keeps '
             . 'working after a subscription lapses, permanently. It is the reason trusting us with '
             . 'a book of client relationships is a rational thing to do.'],
            ['Is there a discount for annual billing?',
             'Two months. A year costs ten months of the monthly price on every plan.'],
        ];
        foreach ($qs as [$q, $a]): ?>
            <details class="group py-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
                    <?= h($q) ?>
                    <span class="shrink-0 text-slate-400 transition group-open:rotate-45" aria-hidden="true">+</span>
                </summary>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600"><?= h($a) ?></p>
            </details>
        <?php endforeach; ?>
    </div>

    <div class="mt-12">
        <a href="<?= h(app_url('/signup')) ?>"
           class="inline-block rounded-md bg-ink px-5 py-3 text-sm font-medium text-white hover:bg-ink-soft">
            <?= $beta ? 'Create your workspace — free' : 'Start your free trial' ?>
        </a>
        <a href="<?= h(app_url('/faq')) ?>" class="ml-4 text-sm text-slate-600 hover:text-ink">Everything else &rarr;</a>
    </div>
</section>
