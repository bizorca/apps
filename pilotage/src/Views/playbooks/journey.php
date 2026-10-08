<?php
/**
 * The journey. Coach-side gets controls and drift; client-side gets FR-4.10 —
 * where we are, what is done, what is next.
 *
 * @var array $engagement @var array $phases @var array $progress @var ?array $drift
 * @var bool $clientSide @var bool $canRun @var array $user @var array $tenant
 */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'journey'], null);

$dot = [
  'complete'    => 'bg-emerald-500',
  'skipped'     => 'bg-slate-300',
  'in_progress' => 'bg-amber-500',
  'available'   => 'bg-slate-900',
  'locked'      => 'bg-slate-200',
];
?>
<div class="max-w-3xl mx-auto px-4 py-8">

  <div class="mb-6">
    <h1 class="text-xl font-semibold"><?= h($engagement['title']) ?></h1>
    <div class="mt-3 flex items-center gap-3">
      <div class="flex-1 h-2 rounded-full bg-slate-200 overflow-hidden">
        <div class="h-full bg-emerald-500" style="width: <?= (int) $progress['percent'] ?>%"></div>
      </div>
      <span class="text-xs text-slate-500 whitespace-nowrap">
        <?= (int) $progress['complete'] ?> of <?= (int) $progress['total'] ?> done<?php
          if ((int) $progress['skipped'] > 0): ?>, <?= (int) $progress['skipped'] ?> skipped<?php endif; ?>
      </span>
    </div>
  </div>

  <?php if (!$clientSide && $drift !== null && $drift['latest_version'] !== null && $drift['latest_version'] > $drift['current_version']): ?>
    <div class="mb-6 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
      <strong>Version <?= (int) $drift['latest_version'] ?> of this playbook is available.</strong>
      This engagement is running v<?= (int) $drift['current_version'] ?>.
      <?= count($drift['added']) ?> added, <?= count($drift['changed']) ?> changed, <?= count($drift['removed']) ?> removed.
      Nothing changes here unless you pull it forward.
    </div>
  <?php endif; ?>

  <?php foreach ($phases as $phase): ?>
    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-2 mt-6"><?= h($phase['title']) ?></h2>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($phase['steps'] as $step):
        $status = (string) $step['status'];
        $locked = $status === 'locked';
      ?>
        <div class="px-4 py-3 <?= $locked ? 'opacity-50' : '' ?>">
          <div class="flex items-start gap-3">
            <span class="mt-1.5 h-2 w-2 rounded-full flex-shrink-0 <?= $dot[$status] ?? 'bg-slate-200' ?>"></span>
            <div class="flex-1">
              <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-medium <?= $status === 'complete' ? 'line-through text-slate-400' : '' ?>">
                  <?= h($step['title']) ?>
                </div>
                <span class="text-xs text-slate-400"><?= h(str_replace('_', ' ', $status)) ?></span>
              </div>

              <?php
              $guidance = $clientSide ? ($step['client_guidance'] ?? null) : ($step['coach_guidance'] ?? $step['client_guidance'] ?? null);
              if (!$locked && !empty($guidance)): ?>
                <p class="text-xs text-slate-600 mt-1 whitespace-pre-line"><?= h((string) $guidance) ?></p>
              <?php endif; ?>

              <?php if (!empty($step['skipped_reason'])): ?>
                <p class="text-xs text-slate-500 mt-1 italic">Skipped: <?= h((string) $step['skipped_reason']) ?></p>
              <?php endif; ?>

              <?php if ($canRun && !$locked): ?>
                <div class="mt-2 flex gap-2 items-center">
                  <?php if ($status === 'available'): ?>
                    <?php foreach ([['start', 'Start'], ['complete', 'Mark done']] as [$act, $label]): ?>
                      <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/steps/' . $step['id'])) ?>">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="<?= h($act) ?>">
                        <button class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50"><?= h($label) ?></button>
                      </form>
                    <?php endforeach; ?>
                  <?php elseif ($status === 'in_progress'): ?>
                    <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/steps/' . $step['id'])) ?>">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action" value="complete">
                      <button class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50">Mark done</button>
                    </form>
                  <?php endif; ?>

                  <?php if (in_array($status, ['available', 'in_progress'], true)): ?>
                    <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/steps/' . $step['id'])) ?>" class="flex gap-1">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action" value="skip">
                      <input name="reason" required placeholder="Why skip?"
                             class="text-xs rounded border border-slate-300 px-2 py-1 w-40 focus:border-slate-900 focus:outline-none">
                      <button class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50">Skip</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/steps/' . $step['id'])) ?>">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action" value="reopen">
                      <button class="text-xs text-slate-500 underline">Reopen</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
