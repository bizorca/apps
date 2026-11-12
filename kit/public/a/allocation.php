<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'allocation';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);
$cid = (int) $client['id'];

const ALLOC_NUMS  = ['deposits_90', 'tax_setaside_annual', 'revenue_annual', 'baseline_monthly',
                     'operating_annual', 'profit_pct'];
const ALLOC_TEXTS = ['day_1', 'day_2', 'first_date', 'first_amounts', 'commit_rule', 'behavior_line'];
const ALLOC_ACCOUNTS = ['income' => 'Income', 'tax' => 'Tax', 'owner_pay' => 'Owner Pay',
                        'operating' => 'Operating', 'profit' => 'Profit (optional)'];
const ACCOUNT_STATES = ['' => '—', 'have' => 'Has it', 'will_open' => 'Will open', 'not_now' => 'Not now'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $save = postNums(ALLOC_NUMS) + postTexts(ALLOC_TEXTS);
    $save['accounts'] = [];
    foreach (array_keys(ALLOC_ACCOUNTS) as $k) {
        $save['accounts'][$k] = postPick('account', $k, array_keys(ACCOUNT_STATES));
    }
    worksheetSave($client, $assessment, KEY, $save);
}

// A fresh run borrows what the EHR and Health Check already know.
$notes = [];
if (isFreshRun($data)) {
    $ehr = latestData($cid, 'ehr');
    if ($ehr) {
        $e = calcEHR($ehr);
        $data = prefill($data, [
            'revenue_annual'      => ['annual revenue (A1)', $e['a1']],
            'operating_annual'    => ['operating costs (A2)', $e['a2']],
            'tax_setaside_annual' => ['tax set-aside (A5)', round($e['a5'], 2)],
        ], 'EHR worksheet', $notes);
    }
    $hc = latestData($cid, 'health');
    if ($hc) {
        $data = prefill($data, [
            'deposits_90'      => ['90-day deposits (1a)', num($hc['m1_cash_in'] ?? 0)],
            'baseline_monthly' => ['baseline owner pay', num($hc['m1_baseline_monthly'] ?? 0)],
        ], 'Health Check', $notes);
    }
}

$x = calcAllocation($data);
$acc = is_array($data['accounts'] ?? null) ? $data['accounts'] : [];

$pageTitle = 'Allocation Sequence';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Allocation Sequence</h1>
  <p class="hint">
    Ends residual pay. Tax reserve and baseline owner pay come off the top of every deposit on a
    fixed schedule, and operating spend has to fit inside what remains. When it doesn't, the fix is
    price, collections, or costs, never skipping pay. The multi-account structure derives from
    <em>Profit First</em> by Mike Michalowicz.
  </p>
</div>

<?= renderPrefillNotes($notes) ?>
<?= renderPeriodField($assessment, 'e.g. Aug–Oct 2026 deposits') ?>

<div class="grid gap-6 lg:grid-cols-2">
  <section class="card p-5">
    <?= sectionHead('Section A', 'The schedule', 'Two fixed allocation days a month. The 10th and 25th are Profit First\'s common rhythm; the owner may choose others.') ?>
    <div class="grid gap-4 sm:grid-cols-2">
      <?= textField($data, 'day_1', 'A1 · First allocation day', '10th') ?>
      <?= textField($data, 'day_2', 'Second allocation day', '25th') ?>
    </div>
    <fieldset class="mt-5">
      <legend class="label">A2 · Accounts the owner has or will open</legend>
      <ul class="mt-2 divide-y divide-hairline">
        <?php foreach (ALLOC_ACCOUNTS as $k => $label): ?>
          <li class="flex items-center justify-between gap-3 py-2">
            <span class="text-sm"><?= h($label) ?></span>
            <select class="field w-40" name="account[<?= h($k) ?>]">
              <?php foreach (ACCOUNT_STATES as $sv => $sl): ?>
                <option value="<?= h($sv) ?>"<?= ($acc[$k] ?? '') === $sv ? ' selected' : '' ?>><?= h($sl) ?></option>
              <?php endforeach; ?>
            </select>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="hint">The Profit account is optional in this version: the first job is proving the owner can be paid.</p>
    </fieldset>
  </section>

  <section class="card p-5">
    <?= sectionHead('Section B', 'The numbers per allocation') ?>
    <div class="space-y-4">
      <?= numField($data, 'deposits_90', 'Deposits, trailing 90 days', 'Exclude loans, transfers between accounts, and owner contributions. Same figure as Health Check 1a.') ?>
      <div class="grid gap-4 sm:grid-cols-2">
        <?= numField($data, 'tax_setaside_annual', 'Annual tax set-aside (EHR A5)') ?>
        <?= numField($data, 'revenue_annual', 'Annual revenue (EHR A1)') ?>
      </div>
      <p class="hint -mt-2">B2 is a planning estimate. The right reserve comes from a CPA or EA.</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <?= numField($data, 'baseline_monthly', 'Baseline owner pay, per month', 'A cost, never a leftover.') ?>
        <?= numField($data, 'operating_annual', 'Annual operating costs (EHR A2)') ?>
      </div>
      <?= numField($data, 'profit_pct', 'Profit share of deposits, % (optional)', 'Leave at 0 unless the owner has opened a Profit account.', '0') ?>
    </div>
    <?= renderReadingPanel($x, 'Starting points: Healthy when B7 is zero or positive. Watch when B7 is short by less than 10% of B6. Act when short by 10% or more.') ?>
  </section>
</div>

<section class="card mt-6 p-5">
  <?= sectionHead('Section C', 'The first allocation', 'The date, the amounts moved, and the one rule the owner commits to.') ?>
  <div class="grid gap-4 lg:grid-cols-2">
    <?= textField($data, 'first_date', 'Date of the first allocation', '', '', 'date') ?>
    <?= textField($data, 'first_amounts', 'Amounts moved', 'Tax $667, Owner Pay $2,250, Operating the rest') ?>
  </div>
  <div class="mt-4">
    <?= textField($data, 'commit_rule', 'The one rule', 'Owner pay moves first, even when the operating account is tight.') ?>
  </div>
</section>

<?= renderBehavioralSection($data, 'If I pay myself first, the truck payment bounces.', 'Collections Calendar (Unit 2.3), then the EHR Calculator if the gap comes from price.') ?>

<?= renderSaveBar($assessment['status']) ?>
</form>
<?php renderFooter();
