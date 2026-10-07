<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'health';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $fields = [
        'm1_cash_in', 'm1_cash_out', 'm1_owner_draws', 'm1_baseline_monthly',
        'm2_insurance', 'm2_vehicle', 'm2_phone', 'm2_rent', 'm2_other',
        'm2_owner_pay', 'm2_cash_on_hand',
        'm3_cash', 'm3_ar_30', 'm3_bills_due', 'm3_ar_past_60',
    ];
    $save = [];
    foreach ($fields as $f) $save[$f] = postNum($f);
    $save['reading_note'] = post('reading_note');
    $save['next_step']    = post('next_step');
    worksheetSave($client, $assessment, KEY, $save);
}

$hc = calcHealthCheck($data);
$f  = fn(string $k) => $data[$k] ?? '';

$pageTitle = 'Three-Metric Health Check';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Three-Metric Health Check</h1>
  <p class="hint">
    A 20-minute read of whether the books are telling the truth. Work from the last
    three bank statements, not the P&amp;L and not the tax return. The three metrics are
    read together; any one of them alone will mislead you.
  </p>
</div>

<?= renderPeriodField($assessment, 'e.g. Jun–Aug 2026') ?>

<div class="grid gap-6 lg:grid-cols-2">

  <!-- Metric 1 ------------------------------------------------------------ -->
  <section class="card p-5">
    <?= sectionHead('Metric 1', 'Trailing 90-day cash flow', 'Is cash growing or shrinking?') ?>

    <div class="space-y-4">
      <div>
        <label class="label" for="m1_cash_in">1a · Cash in, last 90 days</label>
        <input class="field amount mt-1" id="m1_cash_in" name="m1_cash_in" inputmode="decimal" value="<?= h($f('m1_cash_in')) ?>">
        <p class="hint">
          Deposits only. <strong>Exclude loans, transfers between accounts, and any personal
          money the owner put in.</strong> None of that is the business earning anything.
        </p>
      </div>
      <div>
        <label class="label" for="m1_cash_out">1b · Cash out, last 90 days</label>
        <input class="field amount mt-1" id="m1_cash_out" name="m1_cash_out" inputmode="decimal" value="<?= h($f('m1_cash_out')) ?>">
        <p class="hint">Every withdrawal, <strong>including owner draws</strong>.</p>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="m1_owner_draws">Owner draws inside 1b</label>
          <input class="field amount mt-1" id="m1_owner_draws" name="m1_owner_draws" inputmode="decimal" value="<?= h($f('m1_owner_draws')) ?>">
        </div>
        <div>
          <label class="label" for="m1_baseline_monthly">Baseline owner pay, per month</label>
          <input class="field amount mt-1" id="m1_baseline_monthly" name="m1_baseline_monthly" inputmode="decimal" value="<?= h($f('m1_baseline_monthly')) ?>">
          <p class="hint">The minimum the owner needs to live on.</p>
        </div>
      </div>
    </div>

    <div class="mt-5 rounded-md border border-hairline p-4">
      <div class="flex items-center justify-between gap-3">
        <span class="text-sm font-semibold">Reading</span>
        <?= renderReading($hc['m1']['reading']) ?>
      </div>
      <p class="mt-2 text-sm"><?= h($hc['m1']['note']) ?></p>
      <?= renderSteps($hc['m1']['steps']) ?>
      <p class="mt-3 text-xs text-muted">
        Healthy: positive, with owner baseline pay drawn in full. Watch: positive only
        because owner pay was cut or skipped. Act: negative.
      </p>
    </div>
  </section>

  <!-- Metric 2 ------------------------------------------------------------ -->
  <section class="card p-5">
    <?= sectionHead('Metric 2', 'Fixed-cost burn and runway', 'How long could the business last with no new work?') ?>

    <div class="space-y-3">
      <?php foreach ([
        'm2_insurance'  => ['Insurance', ''],
        'm2_vehicle'    => ['Vehicle and equipment payments', ''],
        'm2_phone'      => ['Phone, software, subscriptions', ''],
        'm2_rent'       => ['Rent, storage, shop space', 'Enter 0 if the business runs from home.'],
        'm2_other'      => ['Other fixed (accounting, licenses)', ''],
        'm2_owner_pay'  => ['Owner baseline pay', 'A fixed cost, not a leftover. A runway figure that assumes the owner works for nothing is not a runway figure.'],
      ] as $name => [$label, $hint]): ?>
        <div>
          <label class="label" for="<?= h($name) ?>"><?= h($label) ?></label>
          <input class="field amount mt-1" id="<?= h($name) ?>" name="<?= h($name) ?>" inputmode="decimal" value="<?= h($f($name)) ?>">
          <?php if ($hint !== ''): ?><p class="hint"><?= h($hint) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="border-t border-hairline pt-3">
        <label class="label" for="m2_cash_on_hand">Cash on hand today</label>
        <input class="field amount mt-1" id="m2_cash_on_hand" name="m2_cash_on_hand" inputmode="decimal" value="<?= h($f('m2_cash_on_hand')) ?>">
      </div>
    </div>

    <div class="mt-5 rounded-md border border-hairline p-4">
      <div class="flex items-center justify-between gap-3">
        <span class="text-sm font-semibold">Reading</span>
        <?= renderReading($hc['m2']['reading']) ?>
      </div>
      <p class="mt-2 text-sm"><?= h($hc['m2']['note']) ?></p>
      <?php if ($hc['m2']['burn'] > 0 && !$hc['m2']['owner_pay_included']): ?>
        <p class="mt-2 rounded border border-warn/30 bg-warn-soft px-3 py-2 text-sm text-warn">
          Owner baseline pay is zero in this burn. Unless the owner genuinely takes
          nothing, the runway above is longer than the real one.
        </p>
      <?php endif; ?>
      <?= renderSteps($hc['m2']['steps']) ?>
      <p class="mt-3 text-xs text-muted">Healthy: 3 months or more. Watch: 1–3 months. Act: under 1 month.</p>
    </div>
  </section>

  <!-- Metric 3 ------------------------------------------------------------ -->
  <section class="card p-5 lg:col-span-2">
    <?= sectionHead('Metric 3', 'Quick liquidity', 'Can the next 30 days be paid?') ?>

    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <label class="label" for="m3_cash">Cash on hand</label>
        <input class="field amount mt-1" id="m3_cash" name="m3_cash" inputmode="decimal" value="<?= h($f('m3_cash')) ?>">
      </div>
      <div>
        <label class="label" for="m3_ar_30">Receivables likely collected in the next 30 days</label>
        <input class="field amount mt-1" id="m3_ar_30" name="m3_ar_30" inputmode="decimal" value="<?= h($f('m3_ar_30')) ?>">
        <p class="hint">Only what will realistically arrive. Aged invoices go in the next field.</p>
      </div>
      <div>
        <label class="label" for="m3_bills_due">Bills due in the next 30 days</label>
        <input class="field amount mt-1" id="m3_bills_due" name="m3_bills_due" inputmode="decimal" value="<?= h($f('m3_bills_due')) ?>">
        <p class="hint">Include estimated tax payments and payroll.</p>
      </div>
      <div>
        <label class="label" for="m3_ar_past_60">Receivables past 60 days</label>
        <input class="field amount mt-1" id="m3_ar_past_60" name="m3_ar_past_60" inputmode="decimal" value="<?= h($f('m3_ar_past_60')) ?>">
        <p class="hint">
          <strong>Never counted as available.</strong> Money that has already failed to arrive
          for two months is not liquidity. Anything past 90 days is usually a collections
          or behavioural problem, not a billing one.
        </p>
      </div>
    </div>

    <div class="mt-5 rounded-md border border-hairline p-4">
      <div class="flex items-center justify-between gap-3">
        <span class="text-sm font-semibold">Reading</span>
        <?= renderReading($hc['m3']['reading']) ?>
      </div>
      <p class="mt-2 text-sm"><?= h($hc['m3']['note']) ?></p>
      <?= renderSteps($hc['m3']['steps']) ?>
      <p class="mt-3 text-xs text-muted">Healthy: 1.5 or higher. Watch: 1.0–1.5. Act: below 1.0.</p>
    </div>
  </section>
</div>

<!-- Reading the three together -------------------------------------------- -->
<section class="card mt-6 p-5">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <?= sectionHead('', 'Reading the three together', 'Two or three sentences on what the combination means, then point to the next instrument.') ?>
    <?= renderReading($hc['overall'], 'overall') ?>
  </div>

  <?php if ($client['seasonality'] !== ''): ?>
    <p class="mb-3 rounded border border-hairline bg-sunk px-3 py-2 text-sm">
      <strong>Seasonality on file:</strong> <?= h($client['seasonality']) ?>.
      A negative cash reading in the strongest season is louder than it looks; one in the
      slow season may be normal if the strong season built the reserves.
    </p>
  <?php endif; ?>

  <textarea class="field" name="reading_note" rows="4"
            placeholder="Cash is shrinking in what should be the strongest season; runway is thin but not critical; liquidity holds only if receivables come in, and $8,300 already hasn't."><?= h($f('reading_note')) ?></textarea>

  <div class="mt-4 max-w-md">
    <label class="label" for="next_step">Next instrument</label>
    <select class="field mt-1" id="next_step" name="next_step">
      <option value="">—</option>
      <?php foreach ([
        'collections' => 'Collections Calendar (2.3)',
        'allocation'  => 'Allocation Sequence (2.1)',
        'ehr'         => 'EHR Calculator (2.2)',
        'referral'    => 'Referral to a CPA or EA',
      ] as $k => $lbl): ?>
        <option value="<?= h($k) ?>"<?= $f('next_step') === $k ? ' selected' : '' ?>><?= h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</section>

<?= renderSaveBar($assessment['status']) ?>
</form>
<?php renderFooter();
