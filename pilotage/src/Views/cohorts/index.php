<?php
/** @var array $cohorts @var ?string $error @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Cohorts</h1>
  <p class="text-sm text-slate-600 mb-6">
    Several clients through one playbook, on one timeline, meeting together. Each of them keeps
    their own engagement — their commitments, documents and numbers stay theirs alone.
  </p>

  <?php if ($error !== null): ?>
    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 mb-5"><?= h((string) $error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= h(url('/cohorts')) ?>"
        class="rounded-lg border border-slate-200 bg-white p-5 mb-8 space-y-3">
    <?= Csrf::field() ?>
    <h2 class="text-sm font-semibold">Start one</h2>
    <input type="text" name="name" required placeholder="What is this group called?" class="<?= $field ?>">
    <textarea name="description" rows="2" placeholder="What is it for? (optional)" class="<?= $field ?>"></textarea>
    <div class="flex gap-2">
      <select name="cadence" class="<?= $field ?>">
        <?php foreach (['weekly' => 'Weekly', 'biweekly' => 'Fortnightly', 'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly', 'adhoc' => 'As needed'] as $v => $label): ?>
          <option value="<?= h($v) ?>" <?= $v === 'monthly' ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="starts_on" class="<?= $field ?>">
    </div>
    <label class="flex items-start gap-2 text-sm">
      <input type="checkbox" name="roster_visible" value="1" class="mt-0.5 rounded border-slate-300">
      <span>
        Let members see who else is in the group
        <span class="block text-xs text-slate-500">
          Leave this off unless everyone has agreed to it. In a peer mastermind the roster is the
          point; in a group you assembled from clients who do not know each other, publishing it is
          a breach of confidence.
        </span>
      </span>
    </label>
    <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Create</button>
  </form>

  <?php if ($cohorts === []): ?>
    <p class="text-sm text-slate-500">None yet.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($cohorts as $c): ?>
        <a href="<?= h(url('/cohorts/' . $c['id'])) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
          <div>
            <div class="text-sm font-medium"><?= h((string) $c['name']) ?></div>
            <div class="text-xs text-slate-500">
              <?= (int) $c['member_count'] ?> member<?= (int) $c['member_count'] === 1 ? '' : 's' ?>
              · <?= (int) $c['sessions_held'] ?> session<?= (int) $c['sessions_held'] === 1 ? '' : 's' ?> held
              · <?= h((string) $c['cadence']) ?>
            </div>
          </div>
          <span class="text-xs rounded px-2 py-0.5 <?= $c['status'] === 'active' ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-600' ?>">
            <?= h((string) $c['status']) ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
