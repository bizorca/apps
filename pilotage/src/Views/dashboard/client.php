<?php
/** @var ?array $engagement @var array $mine @var ?array $progress @var ?array $next
 *  @var array $goals @var array $missing @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <?php if ($engagement === null): ?>
    <h1 class="text-xl font-semibold mb-2">Welcome</h1>
    <p class="text-sm text-slate-600">Your advisor has not started an engagement yet. Nothing to do right now.</p>
  <?php else: ?>
    <h1 class="text-xl font-semibold mb-1"><?= h($engagement['title']) ?></h1>
    <p class="text-sm text-slate-600 mb-6">with <?= h($tenant['name']) ?></p>

    <?php if ($progress !== null): ?>
      <div class="rounded-lg border border-slate-200 bg-white p-5 mb-6">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-sm font-semibold">Where we are</h2>
          <span class="text-xs text-slate-500"><?= (int) $progress['complete'] ?> of <?= (int) $progress['total'] ?> steps</span>
        </div>
        <div class="h-2 rounded-full bg-slate-200 overflow-hidden mb-3">
          <div class="h-full bg-emerald-500" style="width: <?= (int) $progress['percent'] ?>%"></div>
        </div>
        <?php if (!empty($progress['next'])): ?>
          <p class="text-sm text-slate-700"><strong>Next:</strong> <?= h((string) $progress['next']['title']) ?></p>
        <?php endif; ?>
        <a href="<?= h(url('/engagements/' . $engagement['id'] . '/journey')) ?>" class="text-xs underline text-slate-600">See the whole journey</a>
      </div>
    <?php endif; ?>

    <?php if ($mine !== [] || $missing !== []): ?>
      <h2 class="text-sm font-semibold mb-2">What is on you</h2>
      <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
        <?php foreach ($mine as $t): ?>
          <?= View::render('tasks._row', ['t' => $t, 'sub' => false], null) ?>
        <?php endforeach; ?>
        <?php foreach ($missing as $m): ?>
          <a href="<?= h(url('/engagements/' . $engagement['id'] . '/scoreboard')) ?>" class="block px-4 py-2.5 hover:bg-slate-50">
            <div class="text-sm">Enter this period's number for <?= h($m['name']) ?></div>
            <div class="text-xs text-slate-500">week of <?= h(date('j M', strtotime((string) $m['period_start']))) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="rounded-lg border border-slate-200 bg-white p-5 mb-6">
        <p class="text-sm text-slate-600">Nothing outstanding on your side.</p>
      </div>
    <?php endif; ?>

    <?php if ($next !== null): ?>
      <div class="rounded-lg border border-slate-200 bg-white p-5 mb-6">
        <h2 class="text-sm font-semibold mb-1">Next session</h2>
        <p class="text-sm"><?= h((string) $next['title']) ?></p>
        <p class="text-xs text-slate-500">
          <?= h(date('l j F, H:i', strtotime((string) $next['scheduled_at']))) ?> UTC
          <?php if (!empty($next['location'])): ?> · <?= h((string) $next['location']) ?><?php endif; ?>
        </p>
        <a href="<?= h(url('/sessions/' . $next['id'])) ?>" class="text-xs underline text-slate-600">Details</a>
      </div>
    <?php endif; ?>

    <?php if (!empty($cohorts)): ?>
      <h2 class="text-sm font-semibold mb-2">Your group<?= count($cohorts) === 1 ? '' : 's' ?></h2>
      <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
        <?php foreach ($cohorts as $c): ?>
          <a href="<?= h(url('/cohorts/' . $c['id'])) ?>" class="block px-4 py-3 hover:bg-slate-50">
            <div class="text-sm font-medium"><?= h((string) $c['name']) ?></div>
            <?php if (!empty($c['description'])): ?>
              <div class="text-xs text-slate-500"><?= h((string) $c['description']) ?></div>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($goals !== []): ?>
      <h2 class="text-sm font-semibold mb-2">This quarter</h2>
      <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
        <?php
        $dot = ['on_track' => 'bg-emerald-500', 'at_risk' => 'bg-amber-500', 'off_track' => 'bg-red-500',
                'done' => 'bg-emerald-600', 'dropped' => 'bg-slate-300'];
        foreach ($goals as $g): ?>
          <div class="px-4 py-2.5 flex items-start gap-3">
            <span class="mt-1.5 h-2 w-2 rounded-full flex-shrink-0 <?= $dot[$g['status']] ?? 'bg-slate-300' ?>"></span>
            <div>
              <div class="text-sm <?= $g['status'] === 'done' ? 'line-through text-slate-400' : '' ?>"><?= h($g['title']) ?></div>
              <div class="text-xs text-slate-500"><?= h(str_replace('_', ' ', (string) $g['status'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
