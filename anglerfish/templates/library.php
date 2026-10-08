<?php
$tabList = [
  'press'     => ['Press scope', $tabs['press']     ?? 0],
  'intake'    => ['Intake',      $tabs['intake']    ?? 0],
  'reference' => ['Reference',   $tabs['reference'] ?? 0],
  'all'       => ['All',         $tabs['all_books'] ?? 0],
];
?>
<div class="flex items-end justify-between gap-6">
  <div>
    <h1 class="text-2xl font-bold text-navy">Library</h1>
    <div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>
  </div>
  <form method="get" action="<?= AF_BASE ?>/" class="flex gap-2">
    <input type="hidden" name="r" value="/library">
    <input type="hidden" name="scope" value="<?= h($scope) ?>">
    <input name="q" value="<?= h($q) ?>" placeholder="Search titles…"
           class="rounded border border-black/15 px-3 py-1.5 text-sm w-56">
    <button class="rounded bg-navy px-3 py-1.5 text-sm text-cream hover:bg-navy2">Search</button>
  </form>
</div>

<nav class="mt-5 flex gap-1 text-sm">
<?php foreach ($tabList as $key => [$label, $n]): ?>
  <a href="<?= url('/library?scope=' . $key) ?>"
     class="rounded px-3 py-1.5 <?= $scope === $key ? 'bg-navy text-cream' : 'text-muted hover:bg-black/5' ?>">
    <?= h($label) ?> <span class="opacity-60"><?= (int) $n ?></span>
  </a>
<?php endforeach; ?>
</nav>

<?php if (!$books): ?>
  <p class="mt-10 text-sm text-muted">Nothing here.</p>
<?php else: ?>
<div class="mt-6 grid gap-5 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6">
<?php foreach ($books as $b): ?>
  <div class="group">
    <a href="<?= url('/library/' . rawurlencode($b['slug'])) ?>" class="block">
      <div class="relative overflow-hidden rounded border border-black/10 bg-white shadow-sm
                  transition group-hover:shadow-md">
        <?php if ((int) $b['artifacts']): ?>
          <span class="absolute left-0 top-0 z-10 bg-navy px-1.5 py-0.5 text-[10px]
                       font-bold uppercase tracking-wide text-cream">
            <?= (int) $b['artifacts'] ?> artifact<?= $b['artifacts'] == 1 ? '' : 's' ?>
          </span>
        <?php endif; ?>
        <img src="<?= url('/media/cover/' . (int) $b['id']) ?>" alt=""
             loading="lazy" class="aspect-[2/3] w-full object-cover">
      </div>
    </a>
    <p class="mt-2 text-xs font-bold leading-snug text-navy line-clamp-2">
      <a href="<?= url('/library/' . rawurlencode($b['slug'])) ?>"><?= h($b['title']) ?></a>
    </p>
    <p class="text-[11px] text-muted">
      <?= h($b['authors'] ?? '') ?><?= $b['year'] ? ' · ' . (int) $b['year'] : '' ?>
    </p>
    <p class="mt-0.5 text-[11px] text-muted">
      <?php if ((int) $b['page_rows']): ?>
        <?= (int) $b['page_rows'] ?> pp
      <?php else: ?>
        <span class="text-gold">no text yet</span>
      <?php endif; ?>
      <?= (int) $b['concepts'] ? ' · ' . (int) $b['concepts'] . ' concepts' : '' ?>
    </p>
    <?php if (!(int) $b['scope_confirmed']): ?>
      <form method="post" action="<?= url('/library/scope') ?>" class="mt-1 flex gap-1">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <input type="hidden" name="back" value="<?= h($scope) ?>">
        <button name="scope" value="press"
                class="rounded bg-navy px-1.5 py-0.5 text-[10px] text-cream">Press</button>
        <button name="scope" value="reference"
                class="rounded border border-black/20 px-1.5 py-0.5 text-[10px]">Ref</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
