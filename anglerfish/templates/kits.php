<h1 class="text-2xl font-bold text-navy">The Vault</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-3 text-sm text-muted">
  Twenty problem-indexed kits. Companion tools render deterministically &mdash;
  no model, no cost, and the text is exactly the text in the database.
</p>

<div class="mt-6 space-y-3">
<?php foreach ($kits as $k): $tools = $byKit[(int) $k['id']] ?? []; ?>
  <div class="rounded border border-black/10 bg-white p-4">
    <div class="flex items-baseline gap-3">
      <span class="font-mono text-xs text-gold"><?= sprintf('%02d', (int) $k['number']) ?></span>
      <span class="font-bold text-navy"><?= h($k['title']) ?></span>
      <span class="ml-auto text-xs text-muted">
        <?= (int) $k['posts'] ?> posts · <?= count($tools) ?> tool<?= count($tools) === 1 ? '' : 's' ?>
      </span>
    </div>
    <?php if ($k['description']): ?>
      <p class="mt-1 text-sm text-muted"><?= h(mb_substr($k['description'], 0, 200)) ?></p>
    <?php endif; ?>

    <?php foreach ($tools as $t): ?>
      <div class="mt-3 flex items-center gap-3 border-t border-black/5 pt-3 text-sm">
        <span class="rounded bg-cream px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-navy">
          <?= h(str_replace('_', ' ', $t['type'])) ?>
        </span>
        <span class="font-bold text-navy"><?= h($t['title']) ?></span>
        <span class="text-xs text-muted"><?= (int) $t['items'] ?> items</span>
        <span class="ml-auto flex items-center gap-2">
          <?php if ($t['asset_id']): ?>
            <a href="<?= url('/media/asset/' . (int) $t['asset_id']) ?>" target="_blank"
               class="rounded bg-navy px-2 py-1 text-xs text-cream hover:bg-navy2">Open PDF</a>
          <?php endif; ?>
          <form method="post" action="<?= url('/render') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="subject_type" value="artifact">
            <input type="hidden" name="subject_id" value="<?= (int) $t['id'] ?>">
            <input type="hidden" name="target" value="tool_pdf">
            <input type="hidden" name="back" value="/kits">
            <button class="rounded border border-black/20 px-2 py-1 text-xs hover:bg-black/5">
              <?= $t['asset_id'] ? 'Re-render' : 'Render' ?>
            </button>
          </form>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>
