<?php
/** @var array $engagement @var array $sessions @var array $templates @var bool $canRun @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'sessions'], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1"><?= h($engagement['title']) ?></h1>
  <p class="text-sm text-slate-600 mb-6">Sessions</p>

  <?php if ($sessions === []): ?>
    <p class="text-sm text-slate-500 mb-6">Nothing scheduled yet.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
      <?php foreach ($sessions as $s): ?>
        <a href="<?= h(url('/sessions/' . $s['id'])) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
          <div>
            <div class="text-sm font-medium"><?= h($s['title']) ?></div>
            <div class="text-xs text-slate-500">
              <?= $s['scheduled_at'] ? h(date('j M Y, H:i', strtotime((string) $s['scheduled_at']))) . ' UTC' : 'unscheduled' ?>
              · <?= (int) $s['duration_minutes'] ?> min
            </div>
          </div>
          <span class="text-xs rounded-full px-2 py-0.5 <?= $s['status'] === 'complete' ? 'bg-emerald-50 text-emerald-700' : ($s['status'] === 'in_progress' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') ?>">
            <?= h(str_replace('_', ' ', (string) $s['status'])) ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($canRun): ?>
    <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/sessions')) ?>"
          class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
      <?= Csrf::field() ?>
      <h2 class="text-sm font-semibold">Schedule a session</h2>
      <input name="title" required placeholder="Title" class="<?= $field ?>">
      <div class="grid grid-cols-2 gap-3">
        <input name="scheduled_at" type="datetime-local" class="<?= $field ?>">
        <select name="template_id" class="<?= $field ?>">
          <option value="0">No agenda template</option>
          <?php foreach ($templates as $t): ?>
            <option value="<?= (int) $t['id'] ?>"><?= h($t['name']) ?><?= $t['time_box_minutes'] ? ' (' . (int) $t['time_box_minutes'] . ' min)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <input name="location" placeholder="Room or video link" class="<?= $field ?>">
      <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Schedule</button>
    </form>
  <?php endif; ?>
</div>
