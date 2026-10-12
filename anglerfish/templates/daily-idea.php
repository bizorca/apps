<?php use Anglerfish\Models\DailyIdea; ?>
<a href="<?= url('/daily') ?>" class="text-xs text-muted hover:text-navy">&larr; Daily Idea</a>
<h1 class="mt-2 text-2xl font-bold text-navy"><?= h(DailyIdea::niceTitle($idea['title'])) ?></h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-2 text-sm text-muted">
  Hypnosis Brain &middot; <?= h($idea['topic']) ?> &middot; <?= h(DailyIdea::niceTitle($idea['section'])) ?>
  <?php if ($send): ?>&middot; sent <?= h($send['send_date']) ?><?php endif; ?>
</p>

<div class="mt-6 max-w-3xl space-y-6">
  <?php if ($send): ?>
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
</div>
