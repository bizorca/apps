<?php
/** @var array $orgs @var array $counts @var string $filter @var string $search @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-semibold">Clients</h1>
    <a href="<?= h(url('/clients/new')) ?>" class="rounded bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Add a client</a>
  </div>

  <div class="flex items-center gap-4 mb-4 text-sm">
    <?php foreach (['active' => 'Active', 'prospect' => 'Prospects', 'archived' => 'Archived'] as $key => $label): ?>
      <a href="<?= h(url('/clients?status=' . $key)) ?>"
         class="<?= $filter === $key ? 'font-semibold text-slate-900 border-b-2 border-slate-900 pb-1' : 'text-slate-500 hover:text-slate-900' ?>">
        <?= h($label) ?> <span class="text-slate-400">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
      </a>
    <?php endforeach; ?>
    <form method="get" action="<?= h(url('/clients')) ?>" class="ml-auto">
        <?= pl_route_field('/clients') ?>
      <input name="q" value="<?= h($search) ?>" placeholder="Search"
             class="rounded border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-900 focus:outline-none">
    </form>
  </div>

  <?php if ($orgs === []): ?>
    <div class="rounded-lg border border-dashed border-slate-300 p-10 text-center">
      <p class="text-sm text-slate-500">Nothing here yet.</p>
    </div>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($orgs as $org): ?>
        <a href="<?= h(url('/clients/' . $org['id'])) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
          <div>
            <div class="font-medium text-sm"><?= h($org['name']) ?></div>
            <div class="text-xs text-slate-500">
              <?= h($org['city'] ?? '') ?><?= !empty($org['city']) && !empty($org['region']) ? ', ' : '' ?><?= h($org['region'] ?? '') ?>
              <?= !empty($org['employee_count']) ? ' · ' . (int) $org['employee_count'] . ' staff' : '' ?>
            </div>
          </div>
          <span class="text-xs rounded-full px-2 py-0.5 <?= $org['status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : ($org['status'] === 'prospect' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500') ?>">
            <?= h($org['status']) ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
