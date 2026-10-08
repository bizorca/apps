<?php
$fmtLabel = fn($f) => ucwords(str_replace('_', ' ', $f));
?>
<div class="flex items-end justify-between gap-6">
  <div>
    <h1 class="text-2xl font-bold text-navy">Posts</h1>
    <div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
  </div>
  <form method="get" action="<?= AF_BASE ?>/" class="flex gap-2">
    <input type="hidden" name="r" value="/posts">
    <input type="hidden" name="format" value="<?= h($format) ?>">
    <input name="q" value="<?= h($q) ?>" placeholder="Search titles and bodies…"
           class="rounded border border-black/15 px-3 py-1.5 text-sm w-64">
    <button class="rounded bg-navy px-3 py-1.5 text-sm text-cream hover:bg-navy2">Search</button>
  </form>
</div>

<nav class="mt-5 flex flex-wrap gap-1 text-sm">
  <a href="<?= url('/posts') ?>"
     class="rounded px-3 py-1.5 <?= $format === '' ? 'bg-navy text-cream' : 'text-muted hover:bg-black/5' ?>">
    All <span class="opacity-60"><?= array_sum(array_column($counts, 'n')) ?></span>
  </a>
  <?php foreach ($counts as $c): ?>
    <a href="<?= url('/posts?format=' . $c['format']) ?>"
       class="rounded px-3 py-1.5 <?= $format === $c['format'] ? 'bg-navy text-cream' : 'text-muted hover:bg-black/5' ?>">
      <?= h($fmtLabel($c['format'])) ?> <span class="opacity-60"><?= (int) $c['n'] ?></span>
    </a>
  <?php endforeach; ?>
  <?php foreach ($statuses as $st): if ($st['status'] === 'draft') continue; ?>
    <a href="<?= url('/posts?status=' . $st['status']) ?>"
       class="rounded px-3 py-1.5 <?= $status === $st['status'] ? 'bg-gold text-navy' : 'text-muted hover:bg-black/5' ?>">
      <?= h($st['status']) ?> <span class="opacity-60"><?= (int) $st['n'] ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php if (!$posts): ?>
  <p class="mt-10 text-sm text-muted">Nothing matches.</p>
<?php else: ?>
<div class="mt-6 divide-y divide-black/5 rounded border border-black/10 bg-white">
<?php foreach ($posts as $p): ?>
  <a href="<?= url('/posts/' . rawurlencode($p['slug'])) ?>"
     class="flex items-baseline gap-3 px-4 py-2.5 hover:bg-black/[.02]">
    <span class="w-10 shrink-0 text-right font-mono text-[11px] text-muted"><?= (int) $p['number'] ?></span>
    <span class="w-24 shrink-0 text-[10px] uppercase tracking-wide text-gold">
      <?= h($fmtLabel($p['format'])) ?>
    </span>
    <span class="flex-1 text-sm font-bold text-navy"><?= h($p['title']) ?></span>
    <?php if ($p['queued']): ?>
      <span class="text-[10px] font-bold text-gold">QUEUED</span>
    <?php endif; ?>
    <?php if ($p['status'] === 'published'): ?>
      <span class="text-[10px] text-muted"><?= h(substr((string) $p['published_at'], 0, 10)) ?></span>
    <?php endif; ?>
  </a>
<?php endforeach; ?>
</div>
<p class="mt-3 text-xs text-muted"><?= count($posts) ?> shown</p>
<?php endif; ?>
