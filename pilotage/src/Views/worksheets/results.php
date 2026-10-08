<?php
/** @var array $assignment @var array $results @var array $fields @var array $trend @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Worksheets;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="flex items-start justify-between mb-6">
    <div>
      <h1 class="text-xl font-semibold"><?= h($assignment['title']) ?></h1>
      <p class="text-sm text-slate-500">
        <?php if (!empty($assignment['label'])): ?><?= h($assignment['label']) ?> · <?php endif; ?>
        <?= (int) $results['count'] ?> response<?= (int) $results['count'] === 1 ? '' : 's' ?>
        <?php if ($results['anonymous']): ?> · anonymous<?php endif; ?>
      </p>
    </div>
    <a href="<?= h(url('/worksheets/export/' . $assignment['id'])) ?>"
       class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50">Export CSV</a>
  </div>

  <?php if ($results['withheld']): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 mb-6">
      <h2 class="text-sm font-semibold text-amber-900 mb-1">Held back for now</h2>
      <p class="text-sm text-amber-900">
        <?= (int) $results['count'] ?> of <?= (int) $assignment['min_responses'] ?> replies are in. With this few,
        arithmetic would identify people, so nothing is shown yet — including to you.
      </p>
    </div>

  <?php elseif ($results['anonymous']): ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
      <?php foreach ($results['aggregates'] as $agg): ?>
        <div class="px-4 py-3">
          <div class="text-sm font-medium mb-1"><?= h((string) $agg['label']) ?></div>
          <?php if (isset($agg['mean']) && $agg['mean'] !== null): ?>
            <div class="text-sm text-slate-700">
              average <strong><?= h((string) $agg['mean']) ?></strong>
              <span class="text-xs text-slate-500">
                (<?= (int) $agg['n'] ?> answers, low <?= h((string) $agg['lowest']) ?>, high <?= h((string) $agg['highest']) ?>)
              </span>
            </div>
          <?php elseif (isset($agg['counts'])): ?>
            <ul class="text-sm text-slate-700 space-y-0.5">
              <?php foreach ($agg['counts'] as $choice => $n): ?>
                <li><?= h((string) $choice) ?> — <?= (int) $n ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="text-xs text-slate-500">
              <?= (int) ($agg['n'] ?? 0) ?> answered.
              <?php if (!empty($agg['note'])): ?><?= h((string) $agg['note']) ?><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
      <?php if ($results['responses'] === []): ?>
        <div class="px-4 py-3 text-sm text-slate-500">Nothing back yet.</div>
      <?php endif; ?>
      <?php foreach ($results['responses'] as $r): ?>
        <div class="flex items-center justify-between px-4 py-3">
          <div>
            <div class="text-sm font-medium"><?= h((string) ($r['respondent_name'] ?? 'Unknown')) ?></div>
            <div class="text-xs text-slate-500"><?= h(date('j M Y', strtotime((string) $r['submitted_at']))) ?></div>
          </div>
          <?php if ($r['score_percent'] !== null): ?>
            <div class="text-right">
              <div class="text-sm font-semibold"><?= h((string) round((float) $r['score_percent'])) ?>%</div>
              <?php if (!empty($r['band_label'])): ?>
                <div class="text-xs text-slate-500"><?= h((string) $r['band_label']) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (count($trend) > 1): ?>
    <h2 class="text-sm font-semibold mb-2">Over time</h2>
    <p class="text-xs text-slate-500 mb-2">The same assessment, run more than once. This is the ROI conversation.</p>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($trend as $t): ?>
        <div class="flex items-center justify-between px-4 py-2.5">
          <div class="text-sm">
            <?= h((string) ($t['label'] ?? 'Unlabelled')) ?>
            <span class="text-xs text-slate-400"><?= h(date('j M Y', strtotime((string) $t['created_at']))) ?></span>
          </div>
          <div class="text-sm">
            <?= $t['score'] === null ? '—' : h((string) round((float) $t['score'])) . '%' ?>
            <?php if ($t['delta'] !== null): ?>
              <span class="ml-2 text-xs <?= (float) $t['delta'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                <?= (float) $t['delta'] >= 0 ? '+' : '' ?><?= h((string) round((float) $t['delta'], 1)) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
