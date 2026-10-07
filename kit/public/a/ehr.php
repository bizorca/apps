<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'ehr';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $fields = ['a1_revenue', 'a2_operating_costs', 'a4_tax_reserve_rate',
               'b1_billable', 'b2_travel', 'b3_quoting', 'b4_admin', 'b5_equipment',
               'b6_afterhours', 'b7_other', 'b9_weeks',
               'c2_quoted_rate', 'd1_target_takehome'];
    $save = [];
    foreach ($fields as $f) $save[$f] = postNum($f);
    $save['e_surprise'] = post('e_surprise');
    worksheetSave($client, $assessment, KEY, $save);
}

$e = calcEHR($data);
$f = fn(string $k) => $data[$k] ?? '';

$pageTitle = 'EHR Worksheet and Calculator';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Effective Hourly Rate</h1>
  <p class="hint">
    What an hour of the owner's life actually earns, once every unbilled hour is counted —
    and the floor an hour of billable work has to clear. The arithmetic is the easy part.
    Getting to the real hours is the skill.
  </p>
</div>

<?= renderPeriodField($assessment, 'e.g. 2025 full year') ?>

<div class="grid gap-6 lg:grid-cols-2">

  <!-- Section A ------------------------------------------------------------ -->
  <section class="card p-5">
    <?= sectionHead('Section A', 'Real take-home', 'What the business actually put in the owner\'s pocket last year.') ?>
    <div class="space-y-4">
      <div>
        <label class="label" for="a1_revenue">A1 · Annual revenue</label>
        <input class="field amount mt-1" id="a1_revenue" name="a1_revenue" inputmode="decimal" value="<?= h($f('a1_revenue')) ?>">
      </div>
      <div>
        <label class="label" for="a2_operating_costs">A2 · Operating costs</label>
        <input class="field amount mt-1" id="a2_operating_costs" name="a2_operating_costs" inputmode="decimal" value="<?= h($f('a2_operating_costs')) ?>">
        <p class="hint">Everything except owner pay and income tax.</p>
      </div>
      <div class="rounded bg-sunk px-3 py-2 text-sm">
        <span class="text-muted">A3 · Net profit</span>
        <span class="tnum ml-2 font-semibold"><?= h(money($e['a3'])) ?></span>
      </div>
      <div>
        <label class="label" for="a4_tax_reserve_rate">A4 · Tax reserve rate (%)</label>
        <input class="field amount mt-1" id="a4_tax_reserve_rate" name="a4_tax_reserve_rate" inputmode="decimal" placeholder="22" value="<?= h($f('a4_tax_reserve_rate')) ?>">
        <p class="hint">
          A planning placeholder only. The right rate comes from a CPA or EA. Federal income
          tax and self-employment tax — <strong>Washington has no personal income tax</strong>,
          so never add a state income reserve here.
        </p>
      </div>
      <div class="rounded bg-sunk px-3 py-2 text-sm">
        <span class="text-muted">A5 · Tax set-aside</span>
        <span class="tnum ml-2 font-semibold"><?= h(money($e['a5'])) ?></span>
        <span class="mx-3 text-hairline">|</span>
        <span class="text-muted">A6 · Real take-home</span>
        <span class="tnum ml-2 font-semibold"><?= h(money($e['a6'])) ?></span>
      </div>
    </div>
  </section>

  <!-- Section B ------------------------------------------------------------ -->
  <section class="card p-5">
    <?= sectionHead('Section B', 'Every hour, billed or not', 'Hours per week. Owners reliably underreport this, because unbilled time does not feel like work.') ?>
    <div class="space-y-3">
      <?php foreach ([
        'b1_billable'   => ['B1 · Billable, on-the-job hours', ''],
        'b2_travel'     => ['B2 · Travel between jobs', 'Drive time counts. Ask directly.'],
        'b3_quoting'    => ['B3 · Quoting, estimates, phone', ''],
        'b4_admin'      => ['B4 · Invoicing, bookkeeping, admin', ''],
        'b5_equipment'  => ['B5 · Equipment, supplies, errands', 'Parts runs, upkeep, setup and breakdown, laundry.'],
        'b6_afterhours' => ['B6 · After-hours calls and emergencies', 'Evenings and weekends.'],
        'b7_other'      => ['B7 · Other', 'Training, license renewal, anything left over.'],
      ] as $name => [$label, $hint]): ?>
        <div>
          <label class="label" for="<?= h($name) ?>"><?= h($label) ?></label>
          <input class="field amount mt-1" id="<?= h($name) ?>" name="<?= h($name) ?>" inputmode="decimal" value="<?= h($f($name)) ?>">
          <?php if ($hint !== ''): ?><p class="hint"><?= h($hint) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="rounded bg-sunk px-3 py-2 text-sm">
        <span class="text-muted">B8 · Total hours per week</span>
        <span class="tnum ml-2 font-semibold"><?= h(number_format($e['b8'], 1)) ?></span>
      </div>

      <div>
        <label class="label" for="b9_weeks">B9 · Weeks worked per year</label>
        <input class="field amount mt-1" id="b9_weeks" name="b9_weeks" inputmode="decimal" placeholder="50" value="<?= h($f('b9_weeks')) ?>">
        <p class="hint">Subtract vacation, illness, and slow weeks with no work.</p>
      </div>

      <div class="rounded bg-sunk px-3 py-2 text-sm">
        <span class="text-muted">B10 · Total annual hours</span>
        <span class="tnum ml-2 font-semibold"><?= h(number_format($e['b10'], 0)) ?></span>
        <span class="mx-3 text-hairline">|</span>
        <span class="text-muted">B11 · Annual billable hours</span>
        <span class="tnum ml-2 font-semibold"><?= h(number_format($e['b11'], 0)) ?></span>
      </div>
    </div>
  </section>
</div>

<!-- Section C --------------------------------------------------------------- -->
<section class="card mt-6 p-5">
  <?= sectionHead('Section C', 'The number', '') ?>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-md border border-primary/30 bg-primary-soft p-4">
      <p class="text-xs font-semibold uppercase tracking-wide text-primary">C1 · Effective hourly rate</p>
      <p class="tnum mt-1 text-3xl font-semibold text-ink"><?= h(money($e['c1'])) ?></p>
      <p class="mt-1 text-xs text-muted">Real take-home ÷ every hour worked</p>
    </div>
    <div class="rounded-md border border-hairline p-4">
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">C4 · Revenue per billable hour</p>
      <p class="tnum mt-1 text-3xl font-semibold"><?= h($e['b11'] > 0 ? money($e['c4']) : '—') ?></p>
      <p class="mt-1 text-xs text-muted">The gap against the quoted rate is discounting, unbilled time, and write-offs</p>
    </div>
    <div class="rounded-md border border-hairline p-4">
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">C3 · EHR as a share of the quoted rate</p>
      <p class="tnum mt-1 text-3xl font-semibold"><?= h($e['c2'] > 0 ? pct($e['c3']) : '—') ?></p>
      <div class="mt-2">
        <label class="sr-only" for="c2_quoted_rate">C2 · Hourly rate the owner quotes</label>
        <input class="field amount" id="c2_quoted_rate" name="c2_quoted_rate" inputmode="decimal"
               placeholder="Quoted rate" value="<?= h($f('c2_quoted_rate')) ?>">
      </div>
    </div>
    <div class="rounded-md border border-hairline p-4">
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">Unbilled share of the workweek</p>
      <p class="tnum mt-1 text-3xl font-semibold"><?= h($e['b8'] > 0 ? pct($e['unbilledShare']) : '—') ?></p>
      <p class="mt-1 text-xs text-muted"><?= h(number_format(max(0, $e['b8'] - $e['b1']), 1)) ?> of <?= h(number_format($e['b8'], 1)) ?> hours</p>
    </div>
  </div>
</section>

<!-- Section D --------------------------------------------------------------- -->
<section class="card mt-6 p-5">
  <?= sectionHead('Section D', 'The pricing floor', 'The minimum an hour of billable work must bring in.') ?>

  <div class="grid gap-6 lg:grid-cols-2">
    <div>
      <label class="label" for="d1_target_takehome">D1 · Target annual take-home</label>
      <input class="field amount mt-1" id="d1_target_takehome" name="d1_target_takehome" inputmode="decimal" value="<?= h($f('d1_target_takehome')) ?>">
      <p class="hint">The owner's number, not the advisor's.</p>

      <?php if ($e['b11'] > 0 && $e['d4'] > 0): ?>
        <div class="mt-5 rounded-md border <?= $e['gap'] > 0 ? 'border-warn bg-warn-soft' : 'border-good bg-good-soft' ?> p-4">
          <p class="text-sm font-semibold <?= $e['gap'] > 0 ? 'text-warn' : 'text-good' ?>">
            <?php if ($e['gap'] > 0): ?>
              The floor is <?= h(money($e['gap'])) ?> above what a billable hour currently brings in.
            <?php else: ?>
              A billable hour already clears the floor by <?= h(money(abs($e['gap']))) ?>.
            <?php endif; ?>
          </p>
          <?php if ($e['gap'] > 0): ?>
            <p class="mt-1 text-sm text-warn">
              That gap closes through price, fewer unbilled hours, lower costs — or, usually,
              some of each.
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="rounded-md border border-hairline p-4">
      <dl class="space-y-2 text-sm">
        <div class="flex justify-between gap-4"><dt class="text-muted">D2 · Target net profit</dt><dd class="tnum font-semibold"><?= h(money($e['d2'])) ?></dd></div>
        <div class="flex justify-between gap-4"><dt class="text-muted">D3 · Required revenue</dt><dd class="tnum font-semibold"><?= h(money($e['d3'])) ?></dd></div>
        <div class="flex justify-between gap-4 border-t border-hairline pt-2">
          <dt class="font-semibold">D4 · Floor rate per billable hour</dt>
          <dd class="tnum text-lg font-semibold"><?= h($e['b11'] > 0 ? money($e['d4']) : '—') ?></dd>
        </div>
      </dl>
    </div>
  </div>

  <?= renderSteps($e['steps'], 'The math, start to finish') ?>
</section>

<!-- Section E --------------------------------------------------------------- -->
<section class="card mt-6 p-5">
  <?= sectionHead('Section E', 'What surprised the owner?', 'Capture the owner\'s own words. This is the line that does the work in the next conversation.') ?>
  <textarea class="field" name="e_surprise" rows="3"
            placeholder="&ldquo;I'd make more working for someone else.&rdquo;"><?= h($f('e_surprise')) ?></textarea>
</section>

<?= renderSaveBar($assessment['status']) ?>
</form>
<?php renderFooter();
