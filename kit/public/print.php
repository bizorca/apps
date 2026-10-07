<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/bootstrap.php';
require_once KIT_ROOT . '/includes/reference.php';
require_once KIT_ROOT . '/templates/partials.php';

$user   = requireAuth();
$userId = (int) $user['id'];
$client = requireClient((int) ($_GET['client'] ?? 0), $userId);
$cid    = (int) $client['id'];

$a = [];
foreach (array_keys(instruments()) as $k) $a[$k] = latestAssessment($cid, $k);

$hc  = $a['health']   ? calcHealthCheck($a['health']['data'])   : null;
$ehr = $a['ehr']      ? calcEHR($a['ehr']['data'])              : null;
$cap = $a['capacity'] ? calcCapacity($a['capacity']['data'])    : null;
$pri = $a['priority'] ? calcPriority($a['priority']['data'])    : null;
$pd  = $a['priority']['data'] ?? [];

$pageTitle = 'Assessment packet';
renderHeader(compact('pageTitle', 'client'));
?>
<div class="no-print mb-6 flex flex-wrap items-center gap-3">
  <a href="<?= h(url('/client.php?id=' . $cid)) ?>" class="btn-quiet">← Back to client</a>
  <button type="button" onclick="window.print()" class="btn-primary">Print or save as PDF</button>
  <span class="text-sm text-muted">Shows the most recent run of each instrument.</span>
</div>

<article class="mx-auto max-w-readable">
  <header class="border-b border-hairline pb-4">
    <h1 class="text-2xl font-semibold"><?= h($client['business_name']) ?></h1>
    <p class="text-muted">
      <?= h(trim(implode(' · ', array_filter([
            $client['owner_name'], $client['business_type'],
            $client['county'] !== '' ? $client['county'] . ' County, ' . $client['state_code'] : '',
          ])))) ?>
    </p>
    <p class="mt-1 text-sm text-muted">
      Assessment packet · prepared <?= h(date('F j, Y')) ?> by <?= h(userDisplayName($user)) ?>
    </p>
  </header>

  <?php if ($pd && trim((string) ($pd['priority_statement'] ?? '')) !== ''): ?>
    <section class="mt-6 rounded-lg border-2 border-primary p-5">
      <h2 class="text-xs font-semibold uppercase tracking-wide text-primary">The one priority</h2>
      <p class="mt-1 text-lg font-semibold"><?= h($pd['priority_statement']) ?></p>
      <?php if (trim((string) ($pd['priority_why'] ?? '')) !== ''): ?>
        <p class="mt-2 text-sm"><?= h($pd['priority_why']) ?></p>
      <?php endif; ?>

      <?php if (trim((string) ($pd['action_what'] ?? '')) !== ''): ?>
        <div class="mt-4 border-t border-hairline pt-4">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">Committed next action</h3>
          <p class="mt-1 font-medium"><?= h($pd['action_what']) ?></p>
          <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            <?php foreach ([
              'By when'            => niceDate($pd['action_by_when'] ?? ''),
              'What "done" looks like' => $pd['action_done_looks'] ?? '',
              'The number to watch'    => $pd['action_number'] ?? '',
              'Follow-up meeting'      => niceDate($pd['action_followup'] ?? ''),
              'Referral needed'        => $pd['action_referral'] ?? '',
            ] as $k => $vv): if (trim((string) $vv) === '') continue; ?>
              <div class="flex gap-2"><dt class="text-muted"><?= h($k) ?>:</dt><dd><?= h($vv) ?></dd></div>
            <?php endforeach; ?>
          </dl>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($pri && $pri['exposure_items']): ?>
    <section class="mt-6">
      <h2 class="text-lg font-semibold">Immediate exposure</h2>
      <p class="hint">Resolve or refer this week.</p>
      <ul class="mt-2 space-y-2">
        <?php foreach ($pri['exposure_items'] as $item): ?>
          <li class="card p-3 text-sm">
            <span class="font-medium"><?= h($item['item']) ?></span>
            <span class="ml-2 text-muted">
              <?= h(($item['handler'] ?? '') !== '' ? ucfirst(str_replace('_', ' ', $item['handler'])) : 'unassigned') ?>
              <?php if (($item['by_when'] ?? '') !== ''): ?> · by <?= h(niceDate($item['by_when'])) ?><?php endif; ?>
              · <?= h(ucfirst($item['status'] ?? 'open')) ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php if ($hc && $hc['overall'] !== READ_UNKNOWN): ?>
    <section class="mt-8 print-break">
      <h2 class="text-lg font-semibold">Three-Metric Health Check</h2>
      <p class="hint"><?= h($a['health']['period_label'] !== '' ? $a['health']['period_label'] : niceDate($a['health']['updated_at'])) ?></p>
      <div class="mt-3 space-y-3">
        <?= renderMetric('Metric 1 — Trailing 90-day cash flow', 'Is cash growing or shrinking?', $hc['m1']) ?>
        <?= renderMetric('Metric 2 — Fixed-cost burn and runway', 'How long could the business last with no new work?', $hc['m2']) ?>
        <?= renderMetric('Metric 3 — Quick liquidity', 'Can the next 30 days be paid?', $hc['m3']) ?>
      </div>
      <?php if (trim((string) ($a['health']['data']['reading_note'] ?? '')) !== ''): ?>
        <div class="card mt-3 p-4">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">Reading the three together</h3>
          <p class="mt-1 text-sm"><?= h($a['health']['data']['reading_note']) ?></p>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($ehr && $ehr['b10'] > 0): ?>
    <section class="mt-8 print-break">
      <h2 class="text-lg font-semibold">Effective Hourly Rate</h2>
      <div class="mt-3 grid gap-3 sm:grid-cols-3">
        <div class="card p-4">
          <p class="text-xs uppercase tracking-wide text-muted">Effective hourly rate</p>
          <p class="tnum text-2xl font-semibold"><?= h(money($ehr['c1'])) ?></p>
        </div>
        <div class="card p-4">
          <p class="text-xs uppercase tracking-wide text-muted">Revenue per billable hour</p>
          <p class="tnum text-2xl font-semibold"><?= h($ehr['b11'] > 0 ? money($ehr['c4']) : '—') ?></p>
        </div>
        <div class="card p-4">
          <p class="text-xs uppercase tracking-wide text-muted">Floor rate</p>
          <p class="tnum text-2xl font-semibold"><?= h($ehr['b11'] > 0 ? money($ehr['d4']) : '—') ?></p>
        </div>
      </div>
      <?= renderSteps($ehr['steps'], 'The math') ?>
      <?php if (trim((string) ($a['ehr']['data']['e_surprise'] ?? '')) !== ''): ?>
        <blockquote class="mt-3 border-l-2 border-primary pl-4 text-sm italic">
          <?= h($a['ehr']['data']['e_surprise']) ?>
        </blockquote>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($cap && $cap['total'] > 0): ?>
    <section class="mt-8 print-break">
      <h2 class="text-lg font-semibold">Capacity Audit and Handoff Test</h2>
      <?= renderSteps($cap['steps'], 'Totals') ?>
      <?php if (array_sum($cap['handoff_counts']) > 0): ?>
        <p class="mt-3 text-sm">
          Handoff test: <?= (int) $cap['handoff_counts']['written'] ?> written down,
          <?= (int) $cap['handoff_counts']['in_head'] ?> in the owner's head,
          <?= (int) $cap['handoff_counts']['missing'] ?> don't exist.
        </p>
      <?php endif; ?>
      <?php if (trim((string) ($a['capacity']['data']['c_item'] ?? '')) !== ''): ?>
        <div class="card mt-3 p-4">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">First page of the operations manual</h3>
          <p class="mt-1 text-sm font-medium"><?= h($a['capacity']['data']['c_item']) ?></p>
          <p class="text-sm text-muted"><?= h($a['capacity']['data']['c_why'] ?? '') ?></p>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <footer class="mt-10 border-t border-hairline pt-4 text-xs text-muted">
    <p>
      This packet organizes an assessment. It is not tax, legal, accounting, or insurance
      advice. Verify every regulatory and tax fact at the source: the Department of Revenue,
      Labor &amp; Industries, the Employment Security Department, the Secretary of State, the
      Department of Health, and the IRS.
    </p>
    <p class="mt-2">Prepared with <?= h(APP_NAME) ?> · <?= h(APP_ORIGIN) ?></p>
  </footer>
</article>
<?php renderFooter();
