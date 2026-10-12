<?php use Anglerfish\Models\DailyIdea; ?>
<a href="<?= url('/daily') ?>" class="text-xs text-muted hover:text-navy">&larr; Daily Idea</a>
<h1 class="mt-2 text-2xl font-bold text-navy"><?= h(DailyIdea::niceTitle($idea['title'])) ?></h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-2 text-sm text-muted">
  Hypnosis Brain &middot; <?= h($idea['topic']) ?> &middot; <?= h(DailyIdea::niceTitle($idea['section'])) ?>
  <?php if ($send): ?>&middot; <?= h(DailyIdea::STATUSES[$send['status']]) ?> for <?= h($send['send_date']) ?><?php endif; ?>
</p>

<div class="mt-6 max-w-4xl space-y-6">
  <?php if ($send && $send['image_path']): ?>
    <a href="<?= url('/daily/plate/' . (int) $send['id']) ?>" target="_blank">
      <img src="<?= url('/daily/plate/' . (int) $send['id'] . '?w=1') ?>" alt="" class="w-full rounded border border-black/10">
    </a>
  <?php elseif ($send && $send['image_error']): ?>
    <p class="rounded bg-red-50 px-4 py-2 text-xs text-red-700">Plate failed (<?= (int) $send['image_tries'] ?> tries): <?= h($send['image_error']) ?></p>
  <?php endif; ?>

  <?php if ($send && trim((string) $send['distilled']) !== ''): ?>
  <section class="rounded border border-black/10 bg-white p-5">
    <h2 class="text-[11px] font-bold uppercase tracking-[2px] text-gold">The distilled idea</h2>
    <div class="mt-2 whitespace-pre-line text-[15px] leading-relaxed text-ink"><?= h($send['distilled']) ?></div>
  </section>
  <?php endif; ?>

  <section class="rounded border border-black/10 bg-white p-5">
    <h2 class="text-[11px] font-bold uppercase tracking-[2px] text-gold">From the brain</h2>
    <p class="mt-2 text-[15px] leading-relaxed text-ink"><strong><?= h($idea['title']) ?>.</strong> <?= h($idea['body']) ?></p>
    <?php if ($idea['citation'] !== ''): ?>
      <p class="mt-3 text-xs text-muted"><?= h($idea['citation']) ?></p>
    <?php endif; ?>
  </section>

  <?php if ($send && $send['image_prompt']): ?>
  <details class="rounded border border-black/10 bg-white p-5 text-xs text-muted">
    <summary class="cursor-pointer">The prompt that drew the plate</summary>
    <pre class="mt-3 whitespace-pre-wrap"><?= h($send['image_prompt']) ?></pre>
  </details>
  <?php endif; ?>
</div>
