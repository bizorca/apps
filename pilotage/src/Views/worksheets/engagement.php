<?php
/** @var array $engagement @var array $assignments @var array $available @var array $people
 *  @var bool $clientSide @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'worksheets'], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1"><?= h($engagement['title']) ?></h1>
  <p class="text-sm text-slate-600 mb-6">Worksheets</p>

  <?php if ($assignments === []): ?>
    <p class="text-sm text-slate-500 mb-6">Nothing sent out yet.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
      <?php foreach ($assignments as $a): ?>
        <div class="flex items-center justify-between px-4 py-3">
          <div>
            <div class="text-sm font-medium">
              <?= h($a['title']) ?>
              <?php if (!empty($a['label'])): ?><span class="text-slate-400"> · <?= h($a['label']) ?></span><?php endif; ?>
            </div>
            <div class="text-xs text-slate-500">
              <?php if ((int) $a['is_anonymous'] === 1): ?>anonymous · <?php endif; ?>
              <?= (int) $a['submitted_count'] ?> back
              <?php if (!empty($a['due_on'])): ?> · due <?= h(date('j M', strtotime((string) $a['due_on']))) ?><?php endif; ?>
            </div>
          </div>
          <div class="flex gap-2">
            <a href="<?= h(url('/worksheets/fill/' . $a['id'])) ?>"
               class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50">Fill it in</a>
            <?php if (!$clientSide): ?>
              <a href="<?= h(url('/worksheets/results/' . $a['id'])) ?>"
                 class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50">Results</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$clientSide && $available !== []): ?>
    <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/worksheets')) ?>"
          class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
      <?= Csrf::field() ?>
      <h2 class="text-sm font-semibold">Send one out</h2>
      <select name="worksheet_id" required class="<?= $field ?>">
        <?php foreach ($available as $w): if ((string) $w['status'] !== 'published') continue; ?>
          <option value="<?= (int) $w['id'] ?>"><?= h($w['title']) ?><?= (int) $w['is_anonymous'] === 1 ? ' (anonymous)' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <div class="grid grid-cols-3 gap-2">
        <select name="assigned_user_id" class="<?= $field ?>">
          <option value="">Everyone at the client</option>
          <?php foreach ($people as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= h($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input name="label" placeholder="Label, e.g. Baseline" class="<?= $field ?>">
        <input name="due_on" type="date" class="<?= $field ?>">
      </div>
      <p class="text-xs text-slate-500">
        An anonymous worksheet always goes to everyone — sending it to one person would not be anonymous.
        Labelling lets you compare the same assessment over time.
      </p>
      <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Send</button>
    </form>
  <?php endif; ?>
</div>
