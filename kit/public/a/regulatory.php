<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';
require_once KIT_ROOT . '/includes/reference.php';

const KEY = 'regulatory';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);

$STATUSES = [
    ''         => '—',
    'ok'       => 'In order',
    'exposure' => 'Exposure — resolve or refer',
    'unknown'  => "Don't know yet",
    'na'       => 'Not applicable',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $items = [];
    foreach (regulatoryItems() as $group => $rows) {
        foreach ($rows as $row) {
            $k = $row['key'];
            $items[$k] = [
                'key'      => $k,
                'status'   => postPick('status', $k, array_keys($STATUSES)),
                'note'     => trim((string) ($_POST['note'][$k] ?? '')),
                'refer_to' => trim((string) ($_POST['refer_to'][$k] ?? '')),
            ];
        }
    }
    worksheetSave($client, $assessment, KEY, [
        'items'   => $items,
        'summary' => post('summary'),
    ]);
}

$items    = jget($data, 'items', []);
$get      = fn(string $k, string $field) => (string) ($items[$k][$field] ?? '');
$exposures = array_filter($items, fn($i) => ($i['status'] ?? '') === 'exposure');
$unknowns  = array_filter($items, fn($i) => ($i['status'] ?? '') === 'unknown');

$pageTitle = 'Regulatory Intake Screen';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Regulatory Intake Screen</h1>
  <p class="hint">
    First-meeting checklist. Regulatory comes first because it is the only layer where one
    problem can end the business overnight — an unlicensed trade, a misclassified worker, a
    missing insurance policy. Your job here is to <strong>spot these and refer them out</strong>,
    not to solve them.
  </p>
  <?= verifyNotice('Washington rules change, and this screen is orientation only.') ?>
</div>

<?php if ($exposures || $unknowns): ?>
  <div class="mb-6 grid gap-3 sm:grid-cols-2">
    <?php if ($exposures): ?>
      <div class="rounded-lg border border-bad bg-bad-soft p-4">
        <p class="text-sm font-semibold text-bad"><?= count($exposures) ?> exposure item<?= count($exposures) === 1 ? '' : 's' ?> flagged</p>
        <p class="mt-1 text-sm text-bad">These carry into the Priority &amp; Next Action worksheet as this week's work.</p>
      </div>
    <?php endif; ?>
    <?php if ($unknowns): ?>
      <div class="rounded-lg border border-warn bg-warn-soft p-4">
        <p class="text-sm font-semibold text-warn"><?= count($unknowns) ?> item<?= count($unknowns) === 1 ? '' : 's' ?> still unknown</p>
        <p class="mt-1 text-sm text-warn">Ask for these before the next meeting. An unknown is not the same as an all-clear.</p>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php foreach (regulatoryItems() as $group => $rows): ?>
  <section class="card mb-4 p-5">
    <h2 class="font-semibold"><?= h($group) ?></h2>
    <ul class="mt-3 divide-y divide-hairline">
      <?php foreach ($rows as $row): $k = $row['key']; $st = $get($k, 'status'); ?>
        <li class="py-4 first:pt-0 last:pb-0">
          <div class="grid gap-3 lg:grid-cols-12">
            <div class="lg:col-span-6">
              <p class="text-sm font-medium text-ink"><?= h($row['q']) ?></p>
              <?php if (!empty($row['note'])): ?>
                <p class="hint"><?= h($row['note']) ?></p>
              <?php endif; ?>
              <?php if ($row['verify'] !== ''): ?>
                <p class="mt-1 text-xs text-muted">Verify with: <?= h($row['verify']) ?></p>
              <?php endif; ?>
            </div>
            <div class="lg:col-span-3">
              <label class="sr-only" for="status_<?= h($k) ?>">Status</label>
              <select class="field <?= $st === 'exposure' ? 'border-bad bg-bad-soft' : ($st === 'unknown' ? 'border-warn bg-warn-soft' : '') ?>"
                      id="status_<?= h($k) ?>" name="status[<?= h($k) ?>]">
                <?php foreach ($STATUSES as $sv => $sl): ?>
                  <option value="<?= h($sv) ?>"<?= $st === $sv ? ' selected' : '' ?>><?= h($sl) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($st === 'exposure'): ?>
                <label class="sr-only" for="refer_<?= h($k) ?>">Refer to</label>
                <input class="field mt-2 text-sm" id="refer_<?= h($k) ?>" name="refer_to[<?= h($k) ?>]"
                       placeholder="Refer to…" value="<?= h($get($k, 'refer_to')) ?>">
              <?php endif; ?>
            </div>
            <div class="lg:col-span-3">
              <label class="sr-only" for="note_<?= h($k) ?>">Note</label>
              <textarea class="field text-sm" id="note_<?= h($k) ?>" name="note[<?= h($k) ?>]" rows="2"
                        placeholder="What you found"><?= h($get($k, 'note')) ?></textarea>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>

<section class="card p-5">
  <?= sectionHead('', 'Summary for the client', 'Name the issue and why it matters, in plain terms. Not a solution — a referral.') ?>
  <textarea class="field" name="summary" rows="4"
            placeholder="Two things to sort out before we talk about anything else…"><?= h(jget($data, 'summary')) ?></textarea>
</section>

<?= renderSaveBar($assessment['status']) ?>
</form>
<?php renderFooter();
