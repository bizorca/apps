<?php
/** @var array $sessions @var array $overdue @var array $mine @var array $slipped
 *  @var array $engagements @var int $unread @var int $mentions @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-5xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-6">Good to see you, <?= h(explode(' ', (string) $user['name'])[0]) ?>.</h1>

  <?php if (!empty($needsOnboarding)): ?>
    <div class="rounded-lg border border-slate-900 bg-white p-5 mb-8">
      <h2 class="text-sm font-semibold mb-1">Set your practice up</h2>
      <p class="text-sm text-slate-600 mb-3">
        This installs a neutral 90-day operating rhythm and two session agendas — a starting
        shape you can rewrite, rather than a blank page. Then you add your first client.
      </p>
      <form method="post" action="<?= h(url('/firm/onboard')) ?>">
        <?= Csrf::field() ?>
        <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
          Set up and add my first client
        </button>
      </form>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-4 gap-3 mb-8">
    <?php
    $tiles = [
      ['Overdue',      count($overdue), count($overdue) > 0 ? 'text-red-600' : 'text-slate-900', url('/tasks')],
      ['Owed by you',  count($mine),    'text-slate-900', url('/tasks')],
      ['Rhythm slipped', count($slipped), count($slipped) > 0 ? 'text-amber-600' : 'text-slate-900', null],
      ['Unread',       $unread + $mentions, 'text-slate-900', url('/mentions')],
    ];
    foreach ($tiles as [$label, $n, $cls, $href]): ?>
      <?php if ($href): ?><a href="<?= h($href) ?>" class="block"><?php else: ?><div><?php endif; ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4 <?= $href ? 'hover:bg-slate-50' : '' ?>">
          <div class="text-2xl font-semibold <?= $cls ?>"><?= (int) $n ?></div>
          <div class="text-xs text-slate-500 mt-1"><?= h($label) ?></div>
        </div>
      <?php if ($href): ?></a><?php else: ?></div><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-2 gap-6">
    <div>
      <h2 class="text-sm font-semibold mb-2">Next seven days</h2>
      <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
        <?php if ($sessions === []): ?>
          <div class="px-4 py-3 text-sm text-slate-500">Nothing booked.</div>
        <?php endif; ?>
        <?php foreach ($sessions as $s): ?>
          <a href="<?= h(url('/sessions/' . $s['id'])) ?>" class="block px-4 py-3 hover:bg-slate-50">
            <div class="text-sm font-medium"><?= h($s['title']) ?></div>
            <div class="text-xs text-slate-500">
              <?= h($s['org_name']) ?> · <?= h(date('D j M, H:i', strtotime((string) $s['scheduled_at']))) ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($slipped !== []): ?>
        <h2 class="text-sm font-semibold mb-2 text-amber-700">Rhythm has slipped</h2>
        <div class="rounded-lg border border-amber-200 bg-amber-50/50 divide-y divide-amber-100 mb-6">
          <?php foreach ($slipped as $sl): ?>
            <div class="px-4 py-3">
              <div class="text-sm font-medium"><?= h($sl['org_name']) ?></div>
              <div class="text-xs text-slate-600">
                <?= h($sl['title']) ?> ·
                <?= $sl['days_since'] === null ? 'never met' : (int) $sl['days_since'] . ' days since the last session' ?>,
                nothing booked
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <h2 class="text-sm font-semibold mb-2">Overdue across your clients</h2>
      <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
        <?php if ($overdue === []): ?>
          <div class="px-4 py-3 text-sm text-slate-500">Nothing overdue. Rare and good.</div>
        <?php endif; ?>
        <?php foreach (array_slice($overdue, 0, 8) as $t): ?>
          <?= View::render('tasks._row', ['t' => $t, 'sub' => false], null) ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <h2 class="text-sm font-semibold mb-2">Active engagements</h2>
  <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
    <?php if ($engagements === []): ?>
      <div class="px-4 py-6 text-center">
        <p class="text-sm text-slate-500 mb-2">No engagements yet.</p>
        <a href="<?= h(url('/clients')) ?>" class="text-sm underline">Add a client to get started</a>
      </div>
    <?php endif; ?>
    <?php foreach ($engagements as $e): ?>
      <a href="<?= h(url('/engagements/' . $e['id'] . '/journey')) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
        <div>
          <div class="text-sm font-medium"><?= h($e['org_name']) ?></div>
          <div class="text-xs text-slate-500"><?= h($e['title']) ?></div>
        </div>
        <div class="flex items-center gap-5 text-xs text-slate-500">
          <?php if ($e['completion']['rate'] !== null): ?>
            <span><?= (int) $e['completion']['rate'] ?>% kept</span>
          <?php endif; ?>
          <?php if ($e['progress'] !== null): ?>
            <span class="flex items-center gap-2">
              <span class="inline-block w-16 h-1.5 rounded-full bg-slate-200 overflow-hidden align-middle">
                <span class="block h-full bg-emerald-500" style="width: <?= (int) $e['progress']['percent'] ?>%"></span>
              </span>
              <?= (int) $e['progress']['percent'] ?>%
            </span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
