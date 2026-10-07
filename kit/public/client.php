<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/bootstrap.php';

$user   = requireAuth();
$userId = (int) $user['id'];
$client = requireClient((int) ($_GET['id'] ?? 0), $userId);
$cid    = (int) $client['id'];

// Progress record entries — added from here and from the Priority worksheet.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    if (post('action') === 'add_progress') {
        addProgress($cid, [
            'entry_date'      => post('entry_date') ?: date('Y-m-d'),
            'instrument'      => post('instrument'),
            'what_changed'    => post('what_changed'),
            'next_instrument' => post('next_instrument'),
        ]);
        flashSuccess('Progress entry added.');
    } elseif (post('action') === 'delete_progress') {
        deleteProgress((int) post('entry_id'), $cid);
        flashSuccess('Progress entry removed.');
    }
    redirect(url('/client.php?id=' . $cid . '#progress'));
}

$all      = instruments();
$progress = getProgress($cid);
[$nextKey, $nextWhy] = suggestedNext($cid);

// Group the instruments by layer so the page reads in diagnostic order.
$byLayer = [];
foreach ($all as $key => $meta) {
    $byLayer[$meta['layer']][$key] = $meta;
}

$pageTitle = '';
renderHeader(compact('pageTitle', 'client'));
?>
<div class="flex flex-wrap items-start justify-between gap-4">
  <div>
    <h1 class="text-2xl font-semibold"><?= h($client['business_name']) ?></h1>
    <p class="hint">
      <?= h(trim(implode(' · ', array_filter([
            $client['owner_name'], $client['business_type'],
            $client['county'] !== '' ? $client['county'] . ' County, ' . $client['state_code'] : '',
            $client['year_started'] !== '' ? 'since ' . $client['year_started'] : '',
            $client['employee_count'],
          ])))) ?>
    </p>
    <?php if ($client['seasonality'] !== ''): ?>
      <p class="hint">Seasonality: <?= h($client['seasonality']) ?></p>
    <?php endif; ?>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="<?= h(url('/print.php?client=' . $cid)) ?>" class="btn-secondary">Assessment packet</a>
    <a href="<?= h(url('/client-edit.php?id=' . $cid)) ?>" class="btn-quiet">Edit details</a>
  </div>
</div>

<?php if ($client['notes'] !== ''): ?>
  <div class="card mt-4 p-4">
    <h2 class="text-xs font-semibold uppercase tracking-wide text-muted">Notes</h2>
    <p class="mt-1 whitespace-pre-line text-sm"><?= h($client['notes']) ?></p>
  </div>
<?php endif; ?>

<!-- What to reach for next --------------------------------------------- -->
<div class="mt-6 rounded-lg border border-primary/30 bg-primary-soft p-4">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <span class="text-xs font-semibold uppercase tracking-wide text-primary">Next</span>
      <p class="font-semibold text-ink"><?= h($all[$nextKey]['name']) ?></p>
      <p class="text-sm text-muted"><?= h($nextWhy) ?> <?= h($all[$nextKey]['why']) ?></p>
    </div>
    <a href="<?= h(instrumentUrl($nextKey, $cid)) ?>" class="btn-primary">Open</a>
  </div>
</div>

<!-- Instruments, in four-layer order ------------------------------------ -->
<h2 class="mt-8 text-lg font-semibold">Assessment instruments</h2>
<p class="hint max-w-readable">
  Worked in this order on purpose. Regulatory first because it is the only layer where
  one problem can end the business overnight; financial next because every other
  conversation depends on true numbers.
</p>

<?php foreach (['R' => 'Regulatory', 'F' => 'Financial', 'O' => 'Offer', 'Op' => 'Operator', '' => 'Close the meeting'] as $layer => $layerName):
    if (empty($byLayer[$layer])) continue; ?>
  <section class="mt-6">
    <h3 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted">
      <?php if ($layer !== ''): ?>
        <span class="tag <?= h(layerClasses($layer)) ?>"><?= h($layer) ?></span>
      <?php endif; ?>
      <?= h($layerName) ?>
    </h3>
    <ul class="mt-2 grid gap-3 md:grid-cols-2">
      <?php foreach ($byLayer[$layer] as $key => $meta):
        $a = latestAssessment($cid, $key);
        [$reading, $headline] = instrumentSummary($key, $a);
        $history = assessmentHistory($cid, $key); ?>
        <li class="card p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <a class="font-semibold hover:underline" href="<?= h(instrumentUrl($key, $cid)) ?>">
                <?= h($meta['name']) ?>
              </a>
              <p class="hint"><?= h($meta['summary']) ?></p>
            </div>
            <?= renderReading($reading) ?>
          </div>
          <p class="mt-3 text-sm <?= $reading === READ_UNKNOWN ? 'text-muted' : 'text-ink' ?>">
            <?= h($headline) ?>
          </p>
          <div class="mt-3 flex items-center justify-between text-xs text-muted">
            <span><?= h($meta['unit']) ?> · <?= h($meta['duration']) ?></span>
            <span class="flex items-center gap-2">
              <?php if ($a): ?>
                <span>Updated <?= h(niceDate($a['updated_at'])) ?></span>
                <?php if (count($history) > 1): ?>
                  <span>· <?= count($history) ?> runs</span>
                <?php endif; ?>
              <?php endif; ?>
              <a class="underline decoration-hairline underline-offset-4 hover:text-ink"
                 href="<?= h(instrumentUrl($key, $cid)) ?>"><?= $a ? 'Open' : 'Start' ?></a>
            </span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>

<!-- Client Progress Record ---------------------------------------------- -->
<section id="progress" class="mt-10">
  <h2 class="text-lg font-semibold">Client progress record</h2>
  <p class="hint max-w-readable">
    Date, instrument completed, what changed, next instrument. This is what turns
    advisor activity into outcomes that can be reported to a funder.
  </p>

  <form method="post" class="card mt-4 p-4">
    <?= csrf() ?>
    <input type="hidden" name="action" value="add_progress">
    <div class="grid gap-3 sm:grid-cols-4">
      <div>
        <label class="label" for="entry_date">Date</label>
        <input class="field mt-1" type="date" id="entry_date" name="entry_date" value="<?= h(date('Y-m-d')) ?>">
      </div>
      <div>
        <label class="label" for="instrument">Instrument completed</label>
        <select class="field mt-1" id="instrument" name="instrument">
          <option value="">—</option>
          <?php foreach ($all as $k => $m): ?>
            <option value="<?= h($k) ?>"><?= h($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sm:col-span-2">
        <label class="label" for="what_changed">What changed</label>
        <input class="field mt-1" id="what_changed" name="what_changed"
               placeholder="Aged receivables down from $8,300 to $2,900">
      </div>
    </div>
    <div class="mt-3 flex flex-wrap items-end gap-3">
      <div class="grow sm:max-w-xs">
        <label class="label" for="next_instrument">Next instrument</label>
        <select class="field mt-1" id="next_instrument" name="next_instrument">
          <option value="">—</option>
          <?php foreach ($all as $k => $m): ?>
            <option value="<?= h($k) ?>"><?= h($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn-secondary">Add entry</button>
    </div>
  </form>

  <?php if ($progress): ?>
    <ol class="mt-4 space-y-2">
      <?php foreach ($progress as $p): ?>
        <li class="card flex flex-wrap items-baseline gap-x-3 gap-y-1 p-3 text-sm">
          <span class="tnum font-semibold"><?= h(niceDate($p['entry_date'])) ?></span>
          <?php if ($p['instrument'] !== ''): ?>
            <span class="tag bg-primary-soft text-primary"><?= h(instrumentName($p['instrument'])) ?></span>
          <?php endif; ?>
          <span class="grow"><?= h($p['what_changed']) ?></span>
          <?php if ($p['next_instrument'] !== ''): ?>
            <span class="text-muted">Next: <?= h(instrumentName($p['next_instrument'])) ?></span>
          <?php endif; ?>
          <form method="post" class="no-print" onsubmit="return confirm('Remove this entry?');">
            <?= csrf() ?>
            <input type="hidden" name="action" value="delete_progress">
            <input type="hidden" name="entry_id" value="<?= (int) $p['id'] ?>">
            <button type="submit" class="text-xs text-muted hover:text-bad" aria-label="Remove entry">Remove</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php else: ?>
    <p class="mt-4 text-sm text-muted">No entries yet.</p>
  <?php endif; ?>
</section>
<?php renderFooter();
