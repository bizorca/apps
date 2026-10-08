<?php
/** @var array $engagement @var array $health @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Health;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'health'], null);
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Health — <?= h($engagement['title']) ?></h1>
  <p class="text-sm text-slate-600 mb-6">
    Five signals over the last <?= (int) Health::WINDOW_DAYS ?> days, weighted. The number is only
    useful next to what produced it, so it is never shown on its own.
  </p>

  <?= View::render('reports._health', ['health' => $health, 'compact' => false], null) ?>

  <div class="mt-6 text-xs text-slate-500 space-y-1">
    <p>Measured on <?= (int) $health['measured'] ?> of <?= count($health['factors']) ?> signals.</p>
    <p>Computed fresh each time this page loads — a stored score goes stale and starts disagreeing
       with the detail printed beside it.</p>
  </div>

  <div class="mt-6">
    <a href="<?= h(url('/engagements/' . $engagement['id'] . '/report')) ?>"
       class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Full period report</a>
  </div>
</div>
