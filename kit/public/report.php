<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/bootstrap.php';
require_once KIT_ROOT . '/templates/partials.php';

$user   = requireAuth();
$userId = (int) $user['id'];

$from = (string) ($_GET['from'] ?? date('Y-01-01'));
$to   = (string) ($_GET['to']   ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-01-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$db = getDb();

// Instruments completed in the window
$stmt = $db->prepare(
    "SELECT a.instrument, COUNT(*) AS n
     FROM kit_assessments a JOIN kit_clients c ON c.id = a.client_id
     WHERE c.user_id = ? AND a.status = 'complete'
       AND date(a.updated_at) BETWEEN ? AND ?
     GROUP BY a.instrument"
);
$stmt->execute([$userId, $from, $to]);
$byInstrument = [];
foreach ($stmt->fetchAll() as $r) $byInstrument[$r['instrument']] = (int) $r['n'];

// Progress entries in the window, with the client name
$stmt = $db->prepare(
    'SELECT p.*, c.business_name
     FROM kit_progress_entries p JOIN kit_clients c ON c.id = p.client_id
     WHERE c.user_id = ? AND p.entry_date BETWEEN ? AND ?
     ORDER BY p.entry_date DESC, p.id DESC'
);
$stmt->execute([$userId, $from, $to]);
$entries = $stmt->fetchAll();

// Clients touched in the window
$stmt = $db->prepare(
    'SELECT COUNT(DISTINCT x.client_id) FROM (
        SELECT a.client_id FROM kit_assessments a JOIN kit_clients c ON c.id = a.client_id
          WHERE c.user_id = ? AND date(a.updated_at) BETWEEN ? AND ?
        UNION
        SELECT p.client_id FROM kit_progress_entries p JOIN kit_clients c ON c.id = p.client_id
          WHERE c.user_id = ? AND p.entry_date BETWEEN ? AND ?
     ) x'
);
$stmt->execute([$userId, $from, $to, $userId, $from, $to]);
$clientsTouched = (int) $stmt->fetchColumn();

// CSV export of the same window — what actually goes to a funder.
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="advisor-field-kit-activity-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');

    // escape: '' is both RFC 4180 behaviour and forward-compatible. Left at the
    // default, PHP 8.4+ raises a deprecation notice that is written into the
    // response body — i.e. into the middle of the file the advisor downloads.
    $row = fn(array $cells) => fputcsv($out, $cells, ',', '"', '');

    $row(['Date', 'Client', 'Instrument completed', 'What changed', 'Next instrument']);
    foreach ($entries as $e) {
        $row([
            $e['entry_date'], $e['business_name'],
            $e['instrument'] !== '' ? instrumentName($e['instrument']) : '',
            $e['what_changed'], $e['next_instrument'] !== '' ? instrumentName($e['next_instrument']) : '',
        ]);
    }
    fclose($out);
    exit;
}

$totalComplete = array_sum($byInstrument);

$pageTitle = 'Activity report';
renderHeader(compact('pageTitle'));
?>
<div class="flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-semibold">Activity report</h1>
    <p class="hint max-w-readable">
      Advisor activity as outcomes, which is the form a funder can read. Built from the client
      progress records, so it fills itself as you work.
    </p>
  </div>
  <form method="get" class="no-print flex flex-wrap items-end gap-2">
    <div>
      <label class="label text-xs" for="from">From</label>
      <input class="field mt-1" type="date" id="from" name="from" value="<?= h($from) ?>">
    </div>
    <div>
      <label class="label text-xs" for="to">To</label>
      <input class="field mt-1" type="date" id="to" name="to" value="<?= h($to) ?>">
    </div>
    <button type="submit" class="btn-secondary">Apply</button>
    <a href="<?= h(url('/report.php?from=' . $from . '&to=' . $to . '&export=csv')) ?>" class="btn-quiet">Export CSV</a>
  </form>
</div>

<div class="mt-6 grid gap-3 sm:grid-cols-3">
  <div class="card p-5">
    <p class="text-xs font-semibold uppercase tracking-wide text-muted">Clients worked with</p>
    <p class="tnum mt-1 text-3xl font-semibold"><?= $clientsTouched ?></p>
  </div>
  <div class="card p-5">
    <p class="text-xs font-semibold uppercase tracking-wide text-muted">Instruments completed</p>
    <p class="tnum mt-1 text-3xl font-semibold"><?= $totalComplete ?></p>
  </div>
  <div class="card p-5">
    <p class="text-xs font-semibold uppercase tracking-wide text-muted">Progress entries logged</p>
    <p class="tnum mt-1 text-3xl font-semibold"><?= count($entries) ?></p>
  </div>
</div>

<?php if ($byInstrument): ?>
  <section class="mt-8">
    <h2 class="text-lg font-semibold">Instruments completed, by type</h2>
    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
      <?php foreach (instruments() as $k => $m): $n = $byInstrument[$k] ?? 0; if ($n === 0) continue; ?>
        <li class="card flex items-center justify-between p-3 text-sm">
          <span><?= h($m['name']) ?></span>
          <span class="tnum font-semibold"><?= $n ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<section class="mt-8">
  <h2 class="text-lg font-semibold">Progress record</h2>
  <?php if (!$entries): ?>
    <p class="hint">No entries in this window.</p>
  <?php else: ?>
    <div class="mt-3 overflow-x-auto">
      <table class="w-full min-w-[46rem] text-sm">
        <thead>
          <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
            <th class="py-2 pr-4 font-semibold">Date</th>
            <th class="py-2 pr-4 font-semibold">Client</th>
            <th class="py-2 pr-4 font-semibold">Instrument</th>
            <th class="py-2 pr-4 font-semibold">What changed</th>
            <th class="py-2 font-semibold">Next</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-hairline">
          <?php foreach ($entries as $e): ?>
            <tr>
              <td class="tnum py-2 pr-4 whitespace-nowrap"><?= h(niceDate($e['entry_date'])) ?></td>
              <td class="py-2 pr-4">
                <a class="hover:underline" href="<?= h(url('/client.php?id=' . (int) $e['client_id'])) ?>"><?= h($e['business_name']) ?></a>
              </td>
              <td class="py-2 pr-4 text-muted"><?= h($e['instrument'] !== '' ? instrumentName($e['instrument']) : '—') ?></td>
              <td class="py-2 pr-4"><?= h($e['what_changed']) ?></td>
              <td class="py-2 text-muted"><?= h($e['next_instrument'] !== '' ? instrumentName($e['next_instrument']) : '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php renderFooter();
