<?php
/** @var array $notifications @var int $unread @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Notifications;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="flex items-start justify-between mb-6">
    <div>
      <h1 class="text-xl font-semibold">Notifications</h1>
      <p class="text-sm text-slate-500">
        <?= $unread === 0 ? 'All caught up.' : $unread . ' unread' ?>
        · <a href="<?= h(url('/settings/notifications')) ?>" class="underline hover:text-slate-900">what lands here</a>
      </p>
    </div>
    <?php if ($unread > 0): ?>
      <form method="post" action="<?= h(url('/notifications/read')) ?>">
        <?= Csrf::field() ?>
        <button class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50">Mark all read</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($notifications === []): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-8 text-center">
      <p class="text-sm text-slate-500">Nothing yet. This fills up as work moves.</p>
    </div>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($notifications as $n): ?>
        <?php $meta = Notifications::CATALOGUE[$n['event_type']] ?? null; ?>
        <div class="flex items-start gap-3 px-4 py-3 <?= $n['read_at'] === null ? 'bg-slate-50/60' : '' ?>">
          <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= $n['read_at'] === null ? 'bg-slate-900' : 'bg-transparent' ?>"></span>
          <div class="min-w-0 flex-1">
            <div class="text-sm <?= $n['read_at'] === null ? 'font-medium' : '' ?>"><?= h((string) $n['title']) ?></div>
            <?php if (!empty($n['body'])): ?>
              <div class="text-sm text-slate-600 mt-0.5"><?= h((string) $n['body']) ?></div>
            <?php endif; ?>
            <div class="text-xs text-slate-400 mt-1">
              <?= h(date('j M, H:i', strtotime((string) $n['created_at']))) ?>
              <?php if ($meta !== null): ?> · <?= h($meta['label']) ?><?php endif; ?>
            </div>
          </div>
          <?php if (!empty($n['link'])): ?>
            <form method="post" action="<?= h(url('/notifications/' . (int) $n['id'])) ?>">
              <?= Csrf::field() ?>
              <button class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50 whitespace-nowrap">Open</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
