<?php
/** The period report (FR-12.5) — the renewal conversation in a document.
 *
 * Rendered as an HTML page with a print stylesheet rather than through DOMPDF.
 * The reasoning is in Reports.php: this project carries zero Composer
 * dependencies so deployment stays "rsync the files", and browser print output
 * is better than DOMPDF's anyway. Print → Save as PDF.
 *
 * @var array $engagement @var array $report @var bool $print @var array $user @var array $tenant
 */
use Bizorca\Pilotage\Core\View;
$r = $report;
$fmt = static fn (?string $d): string => $d === null || $d === '' ? '—' : date('j M Y', strtotime($d));

if (!$print) {
    echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
    echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'report'], null);
}
?>
<?php if ($print): ?>
<!-- A print run is a document, not a screen: no nav, no chrome, black on white,
     and page breaks that do not slice a table in half. -->
<style>
  body { background: #fff; }
  @page { margin: 18mm 14mm; }
  @media print {
    .no-print { display: none !important; }
    section { break-inside: avoid; }
    a { text-decoration: none; color: inherit; }
  }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; padding: 4px 8px 4px 0; vertical-align: top; }
  th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; border-bottom: 1px solid #e2e8f0; }
  td { font-size: 13px; border-bottom: 1px solid #f1f5f9; }
  h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #475569; margin: 22px 0 6px; }
</style>
<?php endif; ?>

<div class="<?= $print ? 'max-w-3xl mx-auto px-6 py-8' : 'max-w-3xl mx-auto px-4 py-8' ?>">

  <div class="no-print mb-6 flex flex-wrap items-end gap-3">
    <form method="get" class="flex items-end gap-2">
        <?= pl_route_field() ?>
      <label class="text-xs text-slate-600">From
        <input type="date" name="from" value="<?= h($r['from']) ?>"
               class="block rounded border border-slate-300 px-2 py-1 text-sm">
      </label>
      <label class="text-xs text-slate-600">To
        <input type="date" name="to" value="<?= h($r['to']) ?>"
               class="block rounded border border-slate-300 px-2 py-1 text-sm">
      </label>
      <button class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Show</button>
    </form>
    <a href="<?= h(url('/engagements/' . $engagement['id'] . '/report?print=1&from=' . urlencode($r['from']) . '&to=' . urlencode($r['to']))) ?>"
       class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Print / save as PDF</a>
    <a href="<?= h(url('/engagements/' . $engagement['id'] . '/report.csv?from=' . urlencode($r['from']) . '&to=' . urlencode($r['to']))) ?>"
       class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">CSV</a>
  </div>

  <header class="mb-6 pb-4 border-b border-slate-200">
    <h1 class="text-xl font-semibold"><?= h((string) $engagement['org_name']) ?></h1>
    <p class="text-sm text-slate-600"><?= h((string) $engagement['title']) ?></p>
    <p class="text-xs text-slate-500 mt-1">
      <?= h($fmt($r['from'])) ?> to <?= h($fmt($r['to'])) ?>
      <?php if (!empty($r['engagement']['coach_name'])): ?> · <?= h((string) $r['engagement']['coach_name']) ?><?php endif; ?>
      · prepared by <?= h((string) $tenant['name']) ?>
    </p>
  </header>

  <section>
    <h2 class="text-sm font-semibold mb-2">Where this stands</h2>
    <?= View::render('reports._health', ['health' => $r['health'], 'compact' => $print], null) ?>
  </section>

  <section>
    <h2 class="text-sm font-semibold mt-8 mb-2">Sessions held</h2>
    <?php if ($r['sessions'] === []): ?>
      <p class="text-sm text-slate-500">None in this period.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Date</th><th>Session</th><th>Status</th><th>Attended</th></tr></thead>
        <tbody>
          <?php foreach ($r['sessions'] as $s): ?>
            <tr>
              <td class="whitespace-nowrap"><?= h($fmt(substr((string) $s['held_at'], 0, 10))) ?></td>
              <td><?= h((string) $s['title']) ?></td>
              <td><?= h(str_replace('_', ' ', (string) $s['status'])) ?></td>
              <td class="tabular-nums"><?= (int) $s['attendees'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section>
    <h2 class="text-sm font-semibold mt-8 mb-2">
      Commitments
      <span class="font-normal text-slate-500 text-xs">
        <?= (int) $r['commitments']['kept'] ?> of <?= (int) $r['commitments']['total'] ?> kept
      </span>
    </h2>
    <?php if ($r['commitments']['rows'] === []): ?>
      <p class="text-sm text-slate-500">Nothing came due in this period.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Due</th><th>Commitment</th><th>Owner</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($r['commitments']['rows'] as $t): ?>
            <tr>
              <td class="whitespace-nowrap"><?= h($fmt((string) $t['due_on'])) ?></td>
              <td><?= h((string) $t['title']) ?></td>
              <td><?= h((string) ($t['owner_name'] ?? '—')) ?></td>
              <td>
                <?= h(str_replace('_', ' ', (string) $t['status'])) ?>
                <?php if ((int) $t['miss_count'] > 0): ?>
                  <span class="text-xs text-slate-500">(missed <?= (int) $t['miss_count'] ?>×)</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section>
    <h2 class="text-sm font-semibold mt-8 mb-2">Delivered</h2>
    <?php if ($r['deliverables'] === []): ?>
      <p class="text-sm text-slate-500">Nothing delivered in this period.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Date</th><th>Deliverable</th><th>Acknowledged</th></tr></thead>
        <tbody>
          <?php foreach ($r['deliverables'] as $d): ?>
            <tr>
              <td class="whitespace-nowrap"><?= h($fmt(substr((string) $d['delivered_at'], 0, 10))) ?></td>
              <td><?= h((string) $d['title']) ?></td>
              <td><?= $d['acknowledged_at'] === null ? 'not yet' : h($fmt(substr((string) $d['acknowledged_at'], 0, 10))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <?php if ($r['goals'] !== []): ?>
    <section>
      <h2 class="text-sm font-semibold mt-8 mb-2">Goals</h2>
      <table>
        <thead><tr><th>Quarter</th><th>Goal</th><th>Owner</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($r['goals'] as $g): ?>
            <tr>
              <td class="whitespace-nowrap"><?= h((string) $g['quarter_label']) ?></td>
              <td><?= h((string) $g['title']) ?></td>
              <td><?= h((string) ($g['owner_name'] ?? '—')) ?></td>
              <td><?= h(str_replace('_', ' ', (string) $g['status'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>

  <?php if ($r['metrics'] !== []): ?>
    <section>
      <h2 class="text-sm font-semibold mt-8 mb-2">The numbers</h2>
      <table>
        <thead><tr><th>Metric</th><th>Start</th><th>End</th><th>Change</th><th>Entries</th></tr></thead>
        <tbody>
          <?php foreach ($r['metrics'] as $m): ?>
            <tr>
              <td><?= h((string) $m['name']) ?>
                <?php if (!empty($m['unit'])): ?><span class="text-xs text-slate-400"><?= h((string) $m['unit']) ?></span><?php endif; ?>
              </td>
              <td class="tabular-nums"><?= $m['period_first'] === null ? '—' : h(num($m['period_first'])) ?></td>
              <td class="tabular-nums"><?= $m['period_last'] === null ? '—' : h(num($m['period_last'])) ?></td>
              <td class="tabular-nums <?= $m['improved'] === null ? '' : ($m['improved'] ? 'text-emerald-700' : 'text-red-700') ?>">
                <?php if ($m['delta'] === null): ?>—<?php else: ?>
                  <?= (float) $m['delta'] > 0 ? '+' : '' ?><?= h(num($m['delta'])) ?>
                <?php endif; ?>
              </td>
              <td class="tabular-nums"><?= (int) $m['entries'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="text-xs text-slate-500 mt-2">
        Good and bad are read against each metric's own direction — days-to-collect falling is progress,
        revenue falling is not.
      </p>
    </section>
  <?php endif; ?>

  <?php if ($r['assessments'] !== []): ?>
    <section>
      <h2 class="text-sm font-semibold mt-8 mb-2">Assessments over time</h2>
      <table>
        <thead><tr><th>Assessment</th><th>Runs</th><th>First</th><th>Latest</th><th>Change</th></tr></thead>
        <tbody>
          <?php foreach ($r['assessments'] as $a): ?>
            <tr>
              <td><?= h((string) $a['title']) ?></td>
              <td class="tabular-nums"><?= (int) $a['runs'] ?></td>
              <td class="tabular-nums"><?= $a['first'] === null ? '—' : (int) round((float) $a['first']) . '%' ?></td>
              <td class="tabular-nums"><?= $a['last'] === null ? '—' : (int) round((float) $a['last']) . '%' ?></td>
              <td class="tabular-nums <?= $a['delta'] === null ? '' : ((float) $a['delta'] >= 0 ? 'text-emerald-700' : 'text-red-700') ?>">
                <?php if ($a['delta'] === null): ?>—<?php else: ?>
                  <?= (float) $a['delta'] > 0 ? '+' : '' ?><?= h((string) $a['delta']) ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>

  <?php if ($r['issues'] !== []): ?>
    <section>
      <h2 class="text-sm font-semibold mt-8 mb-2">Issues raised</h2>
      <table>
        <thead><tr><th>Raised</th><th>Issue</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($r['issues'] as $i): ?>
            <tr>
              <td class="whitespace-nowrap"><?= h($fmt(substr((string) $i['created_at'], 0, 10))) ?></td>
              <td><?= h((string) $i['title']) ?></td>
              <td><?= h(str_replace('_', ' ', (string) $i['status'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>

  <?php if ($print): ?>
    <p class="text-xs text-slate-400 mt-10 pt-4 border-t border-slate-200">
      Generated <?= h(date('j M Y')) ?> by <?= h((string) $tenant['name']) ?>.
    </p>
  <?php endif; ?>
</div>
