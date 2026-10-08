<?php
/**
 * FAQ.
 *
 * The accordion is <details>/<summary>. This project removed Alpine.js to get
 * 'unsafe-eval' out of the CSP after discovering it was loaded on every page to
 * power exactly one accordion the browser implements natively — so reaching for
 * a script here would be undoing that on the very page it was learned on.
 *
 * The structured-data block is generated from the same array as the visible
 * questions. Two hand-maintained copies of an FAQ drift, and the one search
 * engines read is the one nobody proofreads.
 *
 * @var bool $beta
 * @var array<string,array<int,array{0:string,1:string}>> $groups
 */
use Bizorca\Pilotage\Core\Csp;

$flat = [];
foreach ($groups as $questions) {
    foreach ($questions as [$q, $a]) {
        $flat[] = [
            '@type'          => 'Question',
            'name'           => $q,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($a)],
        ];
    }
}
?>

<section class="mx-auto max-w-3xl px-5 pt-20">
    <p class="text-sm font-medium uppercase tracking-wider text-tide-600">FAQ</p>
    <h1 class="mt-3 font-display text-4xl leading-tight tracking-tight sm:text-5xl">
        The questions worth asking before you move a client roster
    </h1>
    <p class="mt-6 text-lg leading-relaxed text-slate-600">
        Answered specifically. If yours is not here,
        <a href="mailto:hello@<?= h(base_domain()) ?>" class="font-medium text-tide-600 underline underline-offset-4">email us</a>
        and it probably will be tomorrow.
    </p>
</section>

<section class="mx-auto max-w-3xl px-5 py-16">
    <?php foreach ($groups as $groupName => $questions): ?>
        <h2 class="mb-2 mt-12 font-display text-2xl leading-tight tracking-tight first:mt-0">
            <?= h($groupName) ?>
        </h2>

        <div class="divide-y divide-slate-200 border-y border-slate-200">
            <?php foreach ($questions as [$q, $a]): ?>
                <details class="group py-5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
                        <span><?= h($q) ?></span>
                        <span class="shrink-0 text-lg leading-none text-slate-400 transition group-open:rotate-45" aria-hidden="true">+</span>
                    </summary>
                    <div class="mt-3 space-y-3 text-sm leading-relaxed text-slate-600"><?= $a ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="mt-16 rounded-2xl bg-slate-50 p-8 text-center">
        <h2 class="font-display text-2xl leading-tight tracking-tight">Still deciding?</h2>
        <p class="mx-auto mt-3 max-w-lg text-sm leading-relaxed text-slate-600">
            Create a workspace and run one real engagement through it. That answers more than any
            page can, and <?= $beta ? 'it costs nothing while we are in beta' : 'the trial needs no card' ?>.
        </p>
        <a href="<?= h(app_url('/signup')) ?>"
           class="mt-6 inline-block rounded-md bg-ink px-5 py-3 text-sm font-medium text-white hover:bg-ink-soft">
            <?= $beta ? 'Create your workspace — free' : 'Start your free trial' ?>
        </a>
    </div>
</section>

<script type="application/ld+json" <?= Csp::attr() ?>><?= json_encode([
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $flat,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
