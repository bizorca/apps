<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/bootstrap.php';

if (currentUser()) redirect(url('/dashboard.php'));

$pageTitle = '';
renderHeader(compact('pageTitle'));
?>
<div class="mx-auto max-w-readable py-8">
  <h1 class="text-3xl font-semibold tracking-tight">Advisor Field Kit</h1>
  <p class="mt-3 text-lg text-muted">
    The assessment instruments from the business advising framework, in a form you can work
    through with a client sitting across the table — and come back to next quarter.
  </p>

  <p class="mt-6">
    Micro-businesses fail from unpriced labor, operational chaos, and unaddressed regulatory
    exposure, not from a shortage of corporate tactics. Diagnose before prescribing. Fix the
    foundation before applying growth.
  </p>

  <h2 class="mt-10 text-lg font-semibold">The four-layer diagnostic</h2>
  <p class="hint">Assess and prioritize in this order. The order is itself the prioritization rule.</p>
  <ol class="mt-4 space-y-3">
    <?php foreach ([
      ['R',  'Regulatory', 'Entity, licensing, tax registration and classification, worker classification, insurance, permits.', 'The only layer where one problem can end the business overnight.'],
      ['F',  'Financial',  'Owner pay, pricing, cash flow, collections, tax reserves, debt, margins.', 'Every other conversation depends on true numbers.'],
      ['O',  'Offer',      "What's sold, to whom, at what price, how buyers find it.", 'More marketing poured into a leaky financial structure just makes the leak bigger.'],
      ['Op', 'Operator',   "The owner's time, capacity, systems, documentation, delegation.", 'Last not because it matters least, but because it holds everything else up.'],
    ] as [$tag, $name, $what, $why]): ?>
      <li class="card p-4">
        <div class="flex items-baseline gap-2">
          <span class="tag <?= h(layerClasses($tag)) ?>"><?= h($tag) ?></span>
          <h3 class="font-semibold"><?= h($name) ?></h3>
        </div>
        <p class="mt-1 text-sm"><?= h($what) ?></p>
        <p class="mt-1 text-sm text-muted"><?= h($why) ?></p>
      </li>
    <?php endforeach; ?>
  </ol>

  <h2 class="mt-10 text-lg font-semibold">What's in the box</h2>
  <ul class="mt-3 space-y-2">
    <?php foreach (instruments() as $m): ?>
      <li class="flex flex-wrap items-baseline gap-x-2 text-sm">
        <?php if ($m['layer'] !== ''): ?><span class="tag <?= h(layerClasses($m['layer'])) ?>"><?= h($m['layer']) ?></span><?php endif; ?>
        <span class="font-medium"><?= h($m['name']) ?></span>
        <span class="text-muted"><?= h($m['summary']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="card mt-10 p-6">
    <h2 class="font-semibold">Three things this tool will not do</h2>
    <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm">
      <li>
        <strong>It does not perform the assessment.</strong> It organizes it, does the
        arithmetic, and shows the math so you can check it. The judgment stays yours.
      </li>
      <li>
        <strong>It does not give tax, legal, accounting, or insurance advice</strong>, and it
        does not replace the referral boundary. It helps you name the issue and refer it.
      </li>
      <li>
        <strong>It does not state Washington or federal rules as fact.</strong> Rates,
        thresholds, and requirements change. Everything regulatory points you at the agency
        to verify with.
      </li>
    </ol>
  </div>

  <div class="mt-10">
    <a href="<?= h(kitLoginUrl()) ?>" class="btn-primary">Sign in to start</a>
    <p class="hint mt-2">Free. No account yet? <a href="<?= h(kitRegisterUrl()) ?>" class="underline decoration-hairline underline-offset-4 hover:text-ink">Create one</a> &mdash; one Bizorca Tools account covers every tool on the site.</p>
  </div>
</div>
<?php renderFooter();
