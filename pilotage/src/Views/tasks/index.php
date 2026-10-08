<?php
/** @var array $engagement @var array $tree @var array $people @var array $rate @var bool $canAssign @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\TaskRepository;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'tasks'], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="flex items-baseline justify-between mb-6">
    <div>
      <h1 class="text-xl font-semibold"><?= h($engagement['title']) ?></h1>
      <p class="text-sm text-slate-600">Commitments</p>
    </div>
    <?php if ($rate['rate'] !== null): ?>
      <div class="text-sm text-slate-600">
        <span class="font-semibold text-slate-900"><?= (int) $rate['rate'] ?>%</span> kept
        <span class="text-xs text-slate-400">(<?= (int) $rate['done'] ?> of <?= (int) $rate['due'] ?> due)</span>
      </div>
    <?php endif; ?>
  </div>

  <div class="flex items-center gap-4 mb-4 text-sm border-b border-slate-200">
    <?php foreach (['list' => 'List', 'board' => 'Board', 'calendar' => 'Calendar'] as $key => $label): ?>
      <a href="<?= h(url('/engagements/' . $engagement['id'] . '/tasks?view=' . $key)) ?>"
         class="pb-2 <?= $view === $key ? 'font-semibold text-slate-900 border-b-2 border-slate-900' : 'text-slate-500 hover:text-slate-900' ?>">
        <?= h($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($view === 'list'): ?>

    <?php if ($tree === []): ?>
      <p class="text-sm text-slate-500 mb-6">Nothing committed yet.</p>
    <?php else: ?>
      <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
        <?php foreach ($tree as $t): ?>
          <?= View::render('tasks._row', ['t' => $t, 'sub' => false], null) ?>
          <?php foreach ($t['children'] as $c): ?>
            <?= View::render('tasks._row', ['t' => $c, 'sub' => true], null) ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($view === 'board'): ?>

    <div class="grid grid-cols-4 gap-3 mb-6">
      <?php foreach ($board as $key => $col): ?>
        <div>
          <div class="flex items-baseline justify-between mb-2">
            <h2 class="text-xs font-semibold uppercase tracking-wide <?= $key === 'overdue' ? 'text-red-600' : 'text-slate-500' ?>">
              <?= h($col['label']) ?>
            </h2>
            <span class="text-xs text-slate-400"><?= count($col['tasks']) ?></span>
          </div>
          <div class="space-y-2 min-h-[3rem]">
            <?php foreach ($col['tasks'] as $t): ?>
              <a href="<?= h(url('/tasks/' . $t['id'])) ?>"
                 class="block rounded-lg border <?= $key === 'overdue' ? 'border-red-200' : 'border-slate-200' ?> bg-white p-3 hover:bg-slate-50">
                <div class="text-sm <?= $key === 'done' ? 'line-through text-slate-400' : '' ?>"><?= h($t['title']) ?></div>
                <div class="text-xs text-slate-500 mt-1">
                  <?php if ($t['due_on'] !== null): ?>
                    <?= h(date('j M', strtotime((string) $t['due_on']))) ?>
                  <?php else: ?>
                    no date
                  <?php endif; ?>
                  <?php if ((int) $t['miss_count'] > 0 && $key !== 'done'): ?>
                    <span class="ml-1 rounded bg-red-50 text-red-700 px-1">&times;<?= (int) $t['miss_count'] ?></span>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
            <?php if ($col['tasks'] === []): ?>
              <div class="rounded-lg border border-dashed border-slate-200 p-3 text-xs text-slate-400">nothing here</div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>

    <?php
    $prev = date('Y-m', strtotime($month . '-01 -1 month'));
    $next = date('Y-m', strtotime($month . '-01 +1 month'));
    $base = url('/engagements/' . $engagement['id'] . '/tasks?view=calendar&month=');
    ?>
    <div class="flex items-center justify-between mb-3">
      <a href="<?= h($base . $prev) ?>" class="text-sm text-slate-500 hover:text-slate-900">&larr; <?= h(date('M Y', strtotime($prev . '-01'))) ?></a>
      <h2 class="text-sm font-semibold"><?= h(date('F Y', strtotime($month . '-01'))) ?></h2>
      <a href="<?= h($base . $next) ?>" class="text-sm text-slate-500 hover:text-slate-900"><?= h(date('M Y', strtotime($next . '-01'))) ?> &rarr;</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white overflow-hidden mb-4">
      <div class="grid grid-cols-7 border-b border-slate-100">
        <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
          <div class="px-2 py-1.5 text-xs font-medium text-slate-500"><?= h($d) ?></div>
        <?php endforeach; ?>
      </div>
      <?php foreach ($calendar['weeks'] as $week): ?>
        <div class="grid grid-cols-7 border-b border-slate-100 last:border-0">
          <?php foreach ($week as $cell): ?>
            <div class="min-h-[5rem] border-r border-slate-50 last:border-0 p-1.5 <?= empty($cell['date']) ? 'bg-slate-50/50' : '' ?>">
              <?php if (!empty($cell['date'])): ?>
                <div class="text-xs mb-1 <?= !empty($cell['today']) ? 'font-semibold text-slate-900' : 'text-slate-400' ?>">
                  <?= (int) $cell['day'] ?>
                </div>
                <?php foreach ($cell['tasks'] as $t): ?>
                  <?php $late = (string) $t['status'] !== 'done' && (string) $cell['date'] < date('Y-m-d'); ?>
                  <a href="<?= h(url('/tasks/' . $t['id'])) ?>"
                     title="<?= h($t['title']) ?>"
                     class="block truncate rounded px-1 py-0.5 mb-0.5 text-xs
                            <?= (string) $t['status'] === 'done' ? 'bg-emerald-50 text-emerald-700 line-through'
                                : ($late ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700') ?>">
                    <?= h($t['title']) ?>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($calendar['undated'] !== []): ?>
      <h2 class="text-sm font-semibold mb-2">No date set</h2>
      <p class="text-xs text-slate-500 mb-2">
        A commitment without a date is the one most likely to be forgotten.
      </p>
      <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
        <?php foreach ($calendar['undated'] as $t): ?>
          <?= View::render('tasks._row', ['t' => $t, 'sub' => false], null) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

  <?php if ($canAssign): ?>
    <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/tasks')) ?>"
          class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
      <?= Csrf::field() ?>
      <h2 class="text-sm font-semibold">Add a commitment</h2>
      <input name="title" required placeholder="What needs to happen" class="<?= $field ?>">
      <input name="definition_of_done" placeholder="How we will know it is done" class="<?= $field ?>">
      <div class="grid grid-cols-3 gap-3">
        <select name="owner_user_id" class="<?= $field ?>">
          <option value="">Unassigned</option>
          <?php foreach ($people as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= h($p['name']) ?><?= $p['client_org_id'] === null ? ' (firm)' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <input name="due_on" type="date" class="<?= $field ?>">
        <select name="evidence_required" class="<?= $field ?>">
          <?php foreach (TaskRepository::EVIDENCE as $k => $lbl): ?>
            <option value="<?= h($k) ?>"><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Add</button>
    </form>
  <?php endif; ?>
</div>
