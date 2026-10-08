<?php
/** @var array $playbook @var array $version @var array $phases @var bool $canEdit @var bool $canRevise @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-4xl mx-auto px-4 py-8">
  <div class="flex items-start justify-between mb-6">
    <div>
      <h1 class="text-xl font-semibold"><?= h($playbook['name']) ?></h1>
      <p class="text-sm text-slate-500 mt-1">
        Version <?= (int) $version['version_number'] ?>
        <span class="<?= $version['state'] === 'draft' ? 'text-amber-600' : 'text-emerald-600' ?>">· <?= h($version['state']) ?></span>
      </p>
    </div>
    <?php if ($canEdit): ?>
      <form method="post" action="<?= h(url('/playbooks/' . $playbook['id'] . '/publish')) ?>">
        <?= Csrf::field() ?>
        <button class="rounded bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Publish</button>
      </form>
    <?php elseif (!empty($canRevise)): ?>
      <form method="post" action="<?= h(url('/playbooks/' . $playbook['id'] . '/revise')) ?>">
        <?= Csrf::field() ?>
        <button class="rounded border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Revise</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($phases === []): ?>
    <p class="text-sm text-slate-500 mb-6">No phases yet. Add one below, or paste in a process you already have.</p>
  <?php endif; ?>

  <?php foreach ($phases as $phase): ?>
    <div class="mb-6">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-2"><?= h($phase['title']) ?></h2>
      <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
        <?php foreach ($phase['steps'] as $step): ?>
          <div class="px-4 py-3">
            <div class="flex items-center justify-between">
              <div class="text-sm font-medium"><?= h($step['title']) ?></div>
              <div class="text-xs text-slate-400">
                <?= h($step['gating']) ?>
                <?php if ((int) $step['is_required'] === 0): ?> · optional<?php endif; ?>
              </div>
            </div>
            <?php if (!empty($step['coach_guidance'])): ?>
              <p class="text-xs text-slate-600 mt-1 whitespace-pre-line"><?= h(mb_strimwidth((string) $step['coach_guidance'], 0, 220, '…')) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($phase['steps'] === []): ?>
          <div class="px-4 py-3 text-xs text-slate-400">No steps in this phase yet.</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($canEdit): ?>
    <div class="grid grid-cols-2 gap-4 mt-8">
      <form method="post" action="<?= h(url('/playbooks/' . $playbook['id'] . '/phases')) ?>" class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
        <?= Csrf::field() ?>
        <h3 class="text-sm font-semibold">Add a phase</h3>
        <input name="title" required placeholder="Phase title" class="<?= $field ?>">
        <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add phase</button>
      </form>

      <?php if ($phases !== []): ?>
        <form method="post" action="<?= h(url('/playbooks/' . $playbook['id'] . '/steps')) ?>" class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
          <?= Csrf::field() ?>
          <h3 class="text-sm font-semibold">Add a step</h3>
          <select name="phase_id" class="<?= $field ?>">
            <?php foreach ($phases as $phase): ?>
              <option value="<?= (int) $phase['id'] ?>"><?= h($phase['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <input name="title" required placeholder="Step title" class="<?= $field ?>">
          <textarea name="coach_guidance" rows="2" placeholder="How to run this step (coach only)" class="<?= $field ?>"></textarea>
          <textarea name="client_guidance" rows="2" placeholder="What the client sees" class="<?= $field ?>"></textarea>
          <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add step</button>
        </form>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= h(url('/playbooks/' . $playbook['id'] . '/import')) ?>" class="rounded-lg border border-slate-200 bg-white p-5 space-y-3 mt-4">
      <?= Csrf::field() ?>
      <h3 class="text-sm font-semibold">Paste in a process you already have</h3>
      <p class="text-xs text-slate-500">
        <code># Phase</code> · <code>## Step</code> · plain lines become coach guidance ·
        <code>&gt; quotes</code> become client guidance · <code>- [ ] items</code> become tasks.
      </p>
      <textarea name="markdown" rows="6" class="<?= $field ?> font-mono text-xs" placeholder="# Onboarding&#10;## Welcome call&#10;Set expectations.&#10;&gt; We will spend our first hour getting oriented.&#10;- [ ] Send the welcome pack"></textarea>
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Import</button>
    </form>
  <?php endif; ?>
</div>
