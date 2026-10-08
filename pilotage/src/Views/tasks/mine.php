<?php
/** @var array $mine @var array $overdue @var bool $firmSide @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-6">My tasks</h1>

  <?php if ($firmSide && $overdue !== []): ?>
    <h2 class="text-sm font-semibold text-red-700 mb-2">Overdue across your clients (<?= count($overdue) ?>)</h2>
    <div class="rounded-lg border border-red-200 bg-white divide-y divide-slate-100 mb-8">
      <?php foreach ($overdue as $t): echo View::render('tasks._row', ['t' => $t, 'sub' => false], null); endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 class="text-sm font-semibold mb-2">Owed by me (<?= count($mine) ?>)</h2>
  <?php if ($mine === []): ?>
    <p class="text-sm text-slate-500">Nothing outstanding. Enjoy it.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($mine as $t): echo View::render('tasks._row', ['t' => $t, 'sub' => false], null); endforeach; ?>
    </div>
  <?php endif; ?>
</div>
