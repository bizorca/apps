<h1 class="text-2xl font-bold text-navy"><?= h($book['title']) ?></h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-3 text-sm text-muted">
  One infographic per chapter, ordered in batch at
  $<?= number_format($price, 3) ?> each &mdash; half the interactive price, answered
  within 24 hours. Art direction runs first, one call per chapter, so no two
  chapters get the same composition.
</p>

<div class="mt-4 flex flex-wrap items-center gap-3 rounded border border-black/10 bg-white p-4 text-sm">
  <span><strong class="text-navy"><?= (int) $tally['chapters'] ?></strong> chapters</span>
  <span class="text-muted">·</span>
  <span><strong class="text-navy"><?= (int) $tally['ready'] ?></strong> ready</span>
  <span><strong class="text-navy"><?= (int) $tally['pending'] ?></strong> waiting</span>
  <span><strong class="text-navy"><?= (int) $tally['rejected'] ?></strong> rejected</span>
  <span><strong class="text-navy"><?= (int) $tally['none'] ?></strong> never ordered</span>
  <span class="text-muted">·</span>
  <span class="text-muted">$<?= number_format((float) $tally['spent'], 2) ?> spent</span>

  <form method="post" action="<?= url('/library/' . rawurlencode($book['slug']) . '/images') ?>"
        class="ml-auto flex items-center gap-2">
    <?= csrf_field() ?>
    <button class="rounded bg-navy px-3 py-1.5 text-xs text-cream hover:bg-navy2
                   disabled:opacity-40" <?= $unshot ? '' : 'disabled' ?>>
      Order <?= (int) $unshot ?> missing
      <?= $unshot ? '(~$' . number_format($unshot * $price, 2) . ')' : '' ?>
    </button>
  </form>
</div>

<?php if ($batches): ?>
  <div class="mt-4 rounded border border-black/10 bg-white p-4">
    <div class="text-xs font-bold uppercase tracking-wide text-muted">Batches</div>
    <?php foreach (array_slice($batches, 0, 6) as $b): ?>
      <div class="mt-2 flex flex-wrap items-baseline gap-3 border-t border-black/5 pt-2 text-sm">
        <span class="font-mono text-xs text-gold">#<?= (int) $b['id'] ?></span>
        <span class="rounded bg-cream px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-navy">
          <?= h($b['status']) ?>
        </span>
        <span class="text-muted"><?= (int) $b['request_count'] ?> requested</span>
        <?php if ((int) $b['applied_count']): ?>
          <span class="text-muted"><?= (int) $b['applied_count'] ?> landed</span>
        <?php endif; ?>
        <?php if ((int) $b['failed_count']): ?>
          <span class="text-muted"><?= (int) $b['failed_count'] ?> failed</span>
        <?php endif; ?>
        <span class="text-xs text-muted"><?= h((string) $b['remote_state']) ?></span>
        <?php if ($b['error']): ?>
          <span class="text-xs text-red-700"><?= h(mb_substr((string) $b['error'], 0, 160)) ?></span>
        <?php endif; ?>
        <span class="ml-auto text-xs text-muted"><?= h((string) $b['created_at']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($rows as $r):
    $status = $r['asset_status'] ?? null;
    $label = trim(($r['number_label'] ? $r['number_label'] . ' — ' : '') . $r['title']);
?>
  <div class="rounded border border-black/10 bg-white">
    <?php if ($status === 'ready' && $r['web_path']): ?>
      <a href="<?= url('/media/asset/' . (int) $r['asset_id']) ?>" target="_blank">
        <img src="<?= url('/media/asset/' . (int) $r['asset_id'] . '?w=substack') ?>"
             alt="<?= h($label) ?>" loading="lazy"
             class="aspect-video w-full rounded-t object-cover">
      </a>
    <?php else: ?>
      <div class="flex aspect-video w-full items-center justify-center rounded-t bg-paper text-xs text-muted">
        <?= match ($status) {
              'pending', 'rendering' => 'waiting on Gemini',
              'rejected'  => 'rejected',
              'superseded'=> 'superseded',
              default     => 'not ordered',
            } ?>
      </div>
    <?php endif; ?>

    <div class="p-3">
      <div class="text-sm font-bold text-navy"><?= h($label) ?></div>
      <?php if ($r['core_idea']): ?>
        <p class="mt-1 text-xs text-muted"><?= h(mb_substr((string) $r['core_idea'], 0, 140)) ?></p>
      <?php endif; ?>

      <div class="mt-3 flex items-center gap-2 border-t border-black/5 pt-3">
        <?php if ($status === 'ready'): ?>
          <form method="post" action="<?= url('/library/' . rawurlencode($book['slug']) . '/images/act') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="asset_id" value="<?= (int) $r['asset_id'] ?>">
            <input type="hidden" name="action" value="reject">
            <button class="rounded border border-black/20 px-2 py-1 text-xs hover:bg-black/5">Reject</button>
          </form>
        <?php endif; ?>

        <?php if ($status !== 'pending' && $status !== 'rendering'): ?>
          <form method="post" action="<?= url('/library/' . rawurlencode($book['slug']) . '/images/act') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="asset_id" value="<?= (int) ($r['asset_id'] ?? 0) ?>">
            <input type="hidden" name="chapter_id" value="<?= (int) $r['chapter_id'] ?>">
            <input type="hidden" name="action" value="requeue">
            <button class="rounded bg-navy px-2 py-1 text-xs text-cream hover:bg-navy2">
              <?= $status ? 'Reject &amp; redraw' : 'Order' ?>
            </button>
          </form>
        <?php endif; ?>

        <?php if ($r['version']): ?>
          <span class="ml-auto text-[10px] text-muted">v<?= (int) $r['version'] ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <p class="mt-6 rounded border border-black/10 bg-white p-4 text-sm text-muted">
    No extracted chapters with summaries in this book yet. Pass 2 writes the core
    idea and the summary that art direction reads; without it there is nothing to
    illustrate from.
  </p>
<?php endif; ?>
