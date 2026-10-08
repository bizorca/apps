<?php
/** @var array $engagement @var array $available @var bool $canApply @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1"><?= h($engagement['title']) ?></h1>
  <p class="text-sm text-slate-600 mb-6">No process running yet.</p>

  <?php if ($canApply && $available !== []): ?>
    <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/apply')) ?>"
          class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
      <?= Csrf::field() ?>
      <p class="text-sm text-slate-600">
        Applying a playbook takes a copy. Editing the template later will not change this engagement.
      </p>
      <select name="version_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none">
        <?php foreach ($available as $p): ?>
          <option value="<?= (int) $p['version_id'] ?>"><?= h($p['name']) ?> (v<?= (int) $p['version_number'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Start this process</button>
    </form>
  <?php elseif ($canApply): ?>
    <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center">
      <p class="text-sm text-slate-600 mb-3">You have no published playbooks yet.</p>
      <a href="<?= h(url('/playbooks')) ?>" class="text-sm underline">Build one</a>
    </div>
  <?php else: ?>
    <p class="text-sm text-slate-500">Your advisor has not started the process yet.</p>
  <?php endif; ?>
</div>
