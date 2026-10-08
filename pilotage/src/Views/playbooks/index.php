<?php
/** @var array $playbooks @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-4xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Playbooks</h1>
  <p class="text-sm text-slate-600 mb-6">Your process, written down once and run for every client.</p>

  <?php if ($playbooks === []): ?>
    <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center mb-6">
      <p class="text-sm text-slate-600 mb-4">Nothing here yet. Start from a neutral shape and make it yours.</p>
      <form method="post" action="<?= h(url('/playbooks/starter')) ?>">
        <?= Csrf::field() ?>
        <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Install the 90-day starter</button>
      </form>
    </div>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
      <?php foreach ($playbooks as $p): ?>
        <a href="<?= h(url('/playbooks/' . $p['id'])) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
          <div>
            <div class="text-sm font-medium">
              <?= h($p['name']) ?>
              <?php if ((int) $p['is_system'] === 1): ?><span class="ml-2 text-xs text-slate-400">starter</span><?php endif; ?>
            </div>
            <?php if (!empty($p['description'])): ?>
              <div class="text-xs text-slate-500"><?= h($p['description']) ?></div>
            <?php endif; ?>
          </div>
          <div class="text-xs text-slate-500">
            <?= $p['published_version'] ? 'v' . (int) $p['published_version'] . ' published' : 'unpublished' ?>
            <?php if ((int) $p['has_draft'] > 0): ?><span class="ml-2 text-amber-600">draft</span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= h(url('/playbooks')) ?>" class="rounded-lg border border-slate-200 bg-white p-5 flex gap-3">
    <?= Csrf::field() ?>
    <input name="name" required placeholder="New playbook name"
           class="flex-1 rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none">
    <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Create</button>
  </form>
</div>
