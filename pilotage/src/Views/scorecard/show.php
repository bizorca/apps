<?php
/**
 * The scoreboard: priorities, the numbers, the issues list.
 *
 * @var array $engagement @var array $grid @var array $goals @var array $load
 * @var array $issues @var array $missing @var array $people @var string $quarter
 * @var bool $clientSide @var bool $canDefine @var bool $canSetGoals
 * @var array $user @var array $tenant
 */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'scoreboard'], null);
$act = url('/engagements/' . $engagement['id'] . '/scoreboard');
$field = 'w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-900 focus:outline-none';
// Shared with the period report — see num() in src/Core/helpers.php.
$num = static fn (?float $v): string => num($v);
$dot = ['on_track' => 'bg-emerald-500', 'at_risk' => 'bg-amber-500', 'off_track' => 'bg-red-500',
        'done' => 'bg-emerald-600', 'dropped' => 'bg-slate-300'];
?>
<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="flex items-baseline justify-between mb-6">
    <div>
      <h1 class="text-xl font-semibold"><?= h($engagement['title']) ?></h1>
      <p class="text-sm text-slate-600">Scoreboard · <?= h($quarter) ?></p>
    </div>
  </div>

  <?php if ($missing !== [] && !$clientSide): ?>
    <div class="mb-6 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
      <strong>No number yet this period:</strong>
      <?= h(implode(', ', array_column($missing, 'name'))) ?>.
    </div>
  <?php endif; ?>

  <!-- Priorities -->
  <div class="flex items-baseline justify-between mb-2">
    <h2 class="text-sm font-semibold">Priorities this quarter</h2>
    <span class="text-xs text-slate-500"><?= (int) $load['count'] ?> set</span>
  </div>

  <?php if (!empty($load['advice'])): ?>
    <p class="text-xs <?= $load['over_cap'] ? 'text-amber-700' : 'text-slate-500' ?> mb-2"><?= h((string) $load['advice']) ?></p>
  <?php endif; ?>

  <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-4">
    <?php if ($goals === []): ?>
      <div class="px-4 py-3 text-sm text-slate-500">Nothing set.</div>
    <?php endif; ?>
    <?php foreach ($goals as $g): ?>
      <div class="px-4 py-3 flex items-start justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
          <span class="mt-1.5 h-2 w-2 rounded-full flex-shrink-0 <?= $dot[$g['status']] ?? 'bg-slate-300' ?>"></span>
          <div class="min-w-0">
            <div class="text-sm <?= $g['status'] === 'done' ? 'line-through text-slate-400' : '' ?>"><?= h($g['title']) ?></div>
            <div class="text-xs text-slate-500">
              <?= h((string) ($g['owner_name'] ?? 'unassigned')) ?>
              <?php if (!empty($g['success_criteria'])): ?> · <?= h((string) $g['success_criteria']) ?><?php endif; ?>
              <?php if (!empty($g['carried_from_id'])): ?>
                <span class="ml-1 rounded bg-amber-50 text-amber-700 px-1">carried forward</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php if (!$clientSide): ?>
          <form method="post" action="<?= h($act) ?>" class="flex-shrink-0">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="goal_status">
            <input type="hidden" name="goal_id" value="<?= (int) $g['id'] ?>">
            <select name="status" onchange="this.form.submit()" class="text-xs rounded border border-slate-300 px-1 py-0.5">
              <?php foreach (['on_track' => 'on track', 'at_risk' => 'at risk', 'off_track' => 'off track', 'done' => 'done', 'dropped' => 'dropped'] as $k => $lbl): ?>
                <option value="<?= h($k) ?>" <?= $g['status'] === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($canSetGoals): ?>
    <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-8 space-y-2">
      <?= Csrf::field() ?><input type="hidden" name="action" value="add_goal">
      <div class="grid grid-cols-3 gap-2">
        <input name="title" required placeholder="What has to happen this quarter" class="<?= $field ?> col-span-2">
        <select name="owner_user_id" class="<?= $field ?>">
          <option value="">Owner</option>
          <?php foreach ($people as $p): ?><option value="<?= (int) $p['id'] ?>"><?= h($p['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <input name="success_criteria" placeholder="How we will know it happened" class="<?= $field ?>">
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add priority</button>
    </form>
  <?php endif; ?>

  <!-- The numbers -->
  <h2 class="text-sm font-semibold mb-2">The numbers</h2>
  <?php if ($grid['rows'] === []): ?>
    <p class="text-sm text-slate-500 mb-4">No metrics yet.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white mb-4 overflow-x-auto">
      <table class="text-sm w-full">
        <thead>
          <tr class="border-b border-slate-100">
            <th class="text-left font-medium px-3 py-2 sticky left-0 bg-white">Metric</th>
            <th class="text-right font-medium px-2 py-2 whitespace-nowrap">Target</th>
            <?php foreach ($grid['periods'] as $p): ?>
              <th class="text-right font-normal text-xs text-slate-400 px-2 py-2 whitespace-nowrap"><?= h(date('j M', strtotime($p))) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grid['rows'] as $row): ?>
            <tr class="border-b border-slate-50 last:border-0">
              <td class="px-3 py-2 sticky left-0 bg-white">
                <div class="font-medium whitespace-nowrap"><?= h($row['name']) ?></div>
                <div class="text-xs text-slate-400 whitespace-nowrap">
                  <?= h((string) ($row['owner_name'] ?? 'unowned')) ?> · <?= (int) $row['entry_rate'] ?>% entered
                </div>
              </td>
              <td class="text-right px-2 py-2 text-xs text-slate-500 whitespace-nowrap">
                <?= $row['target_value'] === null ? '—' : ($row['direction'] === 'lower' ? '≤' : '≥') . ' ' . h($num((float) $row['target_value'])) ?>
              </td>
              <?php foreach ($row['cells'] as $cell): ?>
                <td class="text-right px-2 py-2 whitespace-nowrap <?= $cell['hit'] === false ? 'text-red-600 font-medium' : ($cell['hit'] === true ? 'text-emerald-700' : 'text-slate-700') ?>">
                  <?php if ($cell['value'] === null): ?>
                    <span class="text-slate-300">·</span>
                  <?php else: ?>
                    <?= h($num($cell['value'])) ?><?php if ($cell['on_behalf']): ?><span class="text-slate-400" title="entered by the advisor">*</span><?php endif; ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-xs text-slate-400 mb-4">* entered by the advisor, not the owner.</p>

    <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-4">
      <?= Csrf::field() ?><input type="hidden" name="action" value="record">
      <div class="grid grid-cols-4 gap-2 items-end">
        <select name="metric_id" required class="<?= $field ?>">
          <?php foreach ($grid['rows'] as $row): ?>
            <option value="<?= (int) $row['id'] ?>"><?= h($row['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input name="value" type="number" step="any" required placeholder="Number" class="<?= $field ?>">
        <input name="period_start" type="date" value="<?= h(date('Y-m-d')) ?>" class="<?= $field ?>">
        <button class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Record</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($canDefine): ?>
    <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-8 space-y-2">
      <?= Csrf::field() ?><input type="hidden" name="action" value="add_metric">
      <h3 class="text-sm font-semibold">Add a number to watch</h3>
      <div class="grid grid-cols-5 gap-2">
        <input name="name" required placeholder="Name" class="<?= $field ?> col-span-2">
        <input name="unit" placeholder="Unit" class="<?= $field ?>">
        <select name="direction" class="<?= $field ?>">
          <option value="higher">higher is better</option>
          <option value="lower">lower is better</option>
        </select>
        <input name="target_value" type="number" step="any" placeholder="Target" class="<?= $field ?>">
      </div>
      <select name="owner_user_id" class="<?= $field ?>">
        <option value="">Who enters it?</option>
        <?php foreach ($people as $p): ?><option value="<?= (int) $p['id'] ?>"><?= h($p['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add metric</button>
    </form>
  <?php endif; ?>

  <!-- Issues -->
  <h2 class="text-sm font-semibold mb-2">Issues</h2>
  <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-4">
    <?php if ($issues === []): ?>
      <div class="px-4 py-3 text-sm text-slate-500">Nothing on the list.</div>
    <?php endif; ?>
    <?php foreach ($issues as $i): ?>
      <div class="px-4 py-3">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-sm font-medium">
              <?php if ((string) $i['priority'] === 'high'): ?><span class="text-red-600">!</span> <?php endif; ?>
              <?= h($i['title']) ?>
            </div>
            <?php if (!empty($i['detail'])): ?>
              <div class="text-xs text-slate-600 mt-0.5 whitespace-pre-line"><?= h((string) $i['detail']) ?></div>
            <?php endif; ?>
            <?php if ((string) $i['origin'] === 'missed_commitment'): ?>
              <div class="text-xs text-amber-700 mt-1">Came from a commitment that stalled.</div>
            <?php endif; ?>
          </div>
          <span class="text-xs text-slate-400 flex-shrink-0"><?= h((string) $i['priority']) ?></span>
        </div>

        <?php if (!$clientSide): ?>
          <details class="mt-2">
            <summary class="text-xs text-slate-500 cursor-pointer hover:text-slate-900">Resolve</summary>
            <form method="post" action="<?= h($act) ?>" class="mt-2 space-y-2">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="resolve_issue">
              <input type="hidden" name="issue_id" value="<?= (int) $i['id'] ?>">
              <input name="identified" placeholder="What the problem actually is" class="<?= $field ?>">
              <input name="discussed" placeholder="What was said" class="<?= $field ?>">
              <input name="decided" required placeholder="What we are going to do (required)" class="<?= $field ?>">
              <button class="rounded border border-slate-300 px-3 py-1 text-xs hover:bg-slate-50">Record the decision</button>
            </form>
          </details>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4 space-y-2">
    <?= Csrf::field() ?><input type="hidden" name="action" value="raise_issue">
    <div class="grid grid-cols-4 gap-2">
      <input name="title" required placeholder="What is in the way?" class="<?= $field ?> col-span-3">
      <select name="priority" class="<?= $field ?>">
        <option value="normal">normal</option>
        <option value="high">high</option>
        <option value="low">low</option>
      </select>
    </div>
    <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Raise it</button>
  </form>
</div>
