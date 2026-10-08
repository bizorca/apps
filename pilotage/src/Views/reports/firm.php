<?php
/** @var array $report @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$c = $report['counts'];
?>
<div class="max-w-5xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">The firm</h1>
  <p class="text-sm text-slate-600 mb-6">Across every engagement, not just yours.</p>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
    <?php foreach ([
      ['Active engagements', (int) $c['active']],
      ['Clients', (int) $c['clients']],
      ['Completed', (int) $c['completed']],
      ['Seats in use', (int) $c['seats']],
    ] as [$label, $value]): ?>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-2xl font-semibold tabular-nums"><?= $value ?></div>
        <div class="text-xs text-slate-500 mt-0.5"><?= h($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($report['at_risk'] !== []): ?>
    <h2 class="text-sm font-semibold mb-2">Needs a conversation</h2>
    <p class="text-xs text-slate-500 mb-2">Weakest first. Open one to see what is actually slipping.</p>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-8">
      <?php foreach ($report['at_risk'] as $e): ?>
        <a href="<?= h(url('/engagements/' . $e['id'] . '/health')) ?>"
           class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
          <div>
            <div class="text-sm font-medium"><?= h($e['org_name']) ?></div>
            <div class="text-xs text-slate-500">
              <?= h($e['title']) ?><?php if (!empty($e['coach_name'])): ?> · <?= h($e['coach_name']) ?><?php endif; ?>
            </div>
          </div>
          <div class="text-right">
            <div class="text-sm font-semibold tabular-nums"><?= (int) round((float) $e['health']['score']) ?></div>
            <div class="text-xs text-slate-500"><?= h((string) $e['health']['band']) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 class="text-sm font-semibold mb-2">Load by coach</h2>
  <p class="text-xs text-slate-500 mb-2">
    Engagements and open commitments, not hours — Pilotage does not track time, and a utilization
    figure invented from data that cannot support one is worse than none.
  </p>
  <div class="rounded-lg border border-slate-200 bg-white overflow-x-auto mb-8">
    <table class="w-full text-sm">
      <thead class="text-xs text-slate-500 border-b border-slate-100">
        <tr>
          <th class="text-left font-medium px-4 py-2">Coach</th>
          <th class="text-right font-medium px-4 py-2">Engagements</th>
          <th class="text-right font-medium px-4 py-2">Open commitments</th>
          <th class="text-right font-medium px-4 py-2">Sessions, 30 days</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($report['coaches'] as $coach): ?>
          <tr>
            <td class="px-4 py-2"><?= h($coach['name']) ?>
              <span class="text-xs text-slate-400"><?= h(str_replace('_', ' ', (string) $coach['role'])) ?></span>
            </td>
            <td class="px-4 py-2 text-right tabular-nums"><?= (int) $coach['engagements'] ?></td>
            <td class="px-4 py-2 text-right tabular-nums"><?= (int) $coach['open_commitments'] ?></td>
            <td class="px-4 py-2 text-right tabular-nums"><?= (int) $coach['sessions_30d'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($report['playbooks'] !== []): ?>
    <h2 class="text-sm font-semibold mb-2">Playbooks in use</h2>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-8">
      <?php foreach ($report['playbooks'] as $p): ?>
        <div class="flex items-center justify-between px-4 py-3">
          <div>
            <div class="text-sm"><?= h($p['name']) ?></div>
            <div class="text-xs text-slate-500">applied <?= (int) $p['applied'] ?> time<?= (int) $p['applied'] === 1 ? '' : 's' ?></div>
          </div>
          <div class="text-sm tabular-nums">
            <?= $p['completion'] === null ? '—' : (int) $p['completion'] . '% of steps done' ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 class="text-sm font-semibold mb-2">Every active engagement</h2>
  <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
    <?php if ($report['engagements'] === []): ?>
      <div class="px-4 py-3 text-sm text-slate-500">None yet.</div>
    <?php endif; ?>
    <?php foreach ($report['engagements'] as $e): ?>
      <div class="flex items-center justify-between px-4 py-3">
        <div>
          <div class="text-sm"><?= h($e['org_name']) ?></div>
          <div class="text-xs text-slate-500"><?= h($e['title']) ?></div>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-right">
            <div class="text-sm tabular-nums">
              <?= $e['health']['score'] === null ? '—' : (int) round((float) $e['health']['score']) ?>
            </div>
            <div class="text-xs text-slate-500"><?= h((string) ($e['health']['band'] ?? 'too early')) ?></div>
          </div>
          <a href="<?= h(url('/engagements/' . $e['id'] . '/report')) ?>"
             class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50 whitespace-nowrap">Report</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($report['slipped'] !== []): ?>
    <h2 class="text-sm font-semibold mt-8 mb-2">Off their cadence</h2>
    <div class="rounded-lg border border-amber-200 bg-amber-50 divide-y divide-amber-100">
      <?php foreach ($report['slipped'] as $s): ?>
        <div class="px-4 py-2.5 text-sm text-amber-900">
          <?= h($s['org_name']) ?> — nothing booked, <?= h((string) $s['cadence']) ?> cadence
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
