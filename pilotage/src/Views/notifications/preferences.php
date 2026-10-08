<?php
/** @var array $preferences @var array $channels @var bool $saved @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Notifications;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);

$labels = [
  Notifications::EMAIL  => 'Email me',
  Notifications::DIGEST => 'In my digest',
  Notifications::IN_APP => 'In the app only',
  Notifications::OFF    => 'Not at all',
];
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Notifications</h1>
  <p class="text-sm text-slate-600 mb-6">
    Everything appears in the app either way. This decides what also reaches your inbox, and whether
    it arrives on its own or waits for your digest.
  </p>

  <?php if ($saved): ?>
    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 mb-5">Saved.</div>
  <?php endif; ?>

  <form method="post" action="<?= h(url('/settings/notifications')) ?>">
    <?= Csrf::field() ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($preferences as $p): ?>
        <div class="flex items-center justify-between gap-4 px-4 py-3">
          <div class="min-w-0">
            <div class="text-sm"><?= h($p['label']) ?></div>
            <?php if ($p['transactional']): ?>
              <div class="text-xs text-slate-500 mt-0.5">Always emailed — this is part of the work, not an update about it.</div>
            <?php endif; ?>
          </div>
          <?php if ($p['transactional']): ?>
            <span class="text-xs text-slate-400 whitespace-nowrap">Email</span>
          <?php else: ?>
            <select name="channel[<?= h($p['event_type']) ?>]"
                    class="rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-900 focus:outline-none">
              <?php foreach ($channels as $c): ?>
                <option value="<?= h($c) ?>" <?= $p['channel'] === $c ? 'selected' : '' ?>>
                  <?= h($labels[$c] ?? $c) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-5">
      <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Save</button>
    </div>
  </form>
</div>
