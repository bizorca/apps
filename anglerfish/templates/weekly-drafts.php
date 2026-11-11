<h1 class="text-2xl font-bold text-navy">Weekly drafts</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
<p class="mt-2 max-w-3xl text-sm text-muted">
  Every weekly post written but not yet promoted, grouped by curriculum module. Open one to
  read it, check its sources, preview or download its PDF, change the download type, and
  promote it when it's ready (promotion assigns the issue number). <?= count($rows) ?> drafts.
</p>

<?php if (!$rows): ?>
  <p class="mt-8 text-sm text-muted">No unpromoted weekly drafts.</p>
<?php else: $lastMod = null; ?>
  <div class="mt-6 space-y-1">
  <?php foreach ($rows as $r): ?>
    <?php if ($r['module_id'] !== $lastMod): $lastMod = $r['module_id']; ?>
      <p class="pt-4 text-[11px] font-bold uppercase tracking-[2px] text-gold">
        Module <?= (int) $r['module_id'] ?> &middot; <?= h((string) $r['module_title']) ?>
      </p>
    <?php endif; ?>
    <div class="flex items-baseline gap-3 rounded border border-black/10 bg-white px-3 py-2 text-sm">
      <a href="<?= url('/compose/' . (int) $r['id']) ?>" class="flex-1 font-bold text-navy hover:text-gold">
        <?= h($r['title'] ?: '(writing…)') ?>
      </a>
      <span class="text-[11px] text-muted"><?= h(str_replace('_', ' ', (string) $r['deliverable'])) ?></span>
      <?php if ($r['status'] !== 'done'): ?>
        <span class="text-[11px] font-bold <?= $r['status'] === 'failed' ? 'text-red-600' : 'text-gold' ?>"><?= h($r['status']) ?></span>
      <?php endif; ?>
      <?php if ($r['has_download']): ?>
        <a href="<?= url('/compose/' . (int) $r['id'] . '/download') ?>" target="_blank" class="text-[11px] text-navy underline">preview</a>
      <?php endif; ?>
      <?php if ($r['pdf_id']): ?>
        <a href="<?= url('/media/asset/' . (int) $r['pdf_id']) ?>" target="_blank" class="text-[11px] text-navy underline">PDF</a>
      <?php endif; ?>
      <?php if (!empty($r['error'])): ?>
        <span class="text-[11px] text-red-600" title="<?= h($r['error']) ?>">check note</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
