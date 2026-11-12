<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'collections';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);
$cid = (int) $client['id'];

/** The written follow-up sequence. Key => [day label, what happens, template prompt]. */
const COLL_SEQUENCE = [
    'd0'  => ['Day 0',   'Invoice goes out with the delivered work',            'What the invoice says, and how it is delivered'],
    'd7'  => ['Day 7',   'Written reminder',                                    'Template: friendly, the amount, how to pay'],
    'd14' => ['Day 14',  'Second notice',                                       'Template: the amount, the date it was due, the next step'],
    'd21' => ['Day 21',  'The call',                                            'Script: what the owner says, word for word'],
    'd30' => ['Day 30',  'Work pauses until the balance is settled',            'Script: how the owner says it'],
    'd60' => ['Past 60', 'Decide per account: payment plan in writing, write-off, or attorney', 'Who decides, and by when'],
];
const COLL_TEXTS = ['as_of', 'terms_label', 'invoice_when', 'due_when', 'deposit_rule', 'late_step',
                    'target_date', 'behavior_line'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $save = postTexts(COLL_TEXTS) + postNums(['revenue_annual', 'terms_days', 'target_past60']);
    $save['invoices'] = collectRows([
        'client' => 'inv_client', 'amount' => 'inv_amount', 'invoice_date' => 'inv_date',
        'days' => 'inv_days', 'last_contact' => 'inv_last', 'next_step' => 'inv_next', 'contact_by' => 'inv_by',
    ], fn($r) => $r['client'] === '' && $r['amount'] === '');
    foreach ($save['invoices'] as &$r) { $r['amount'] = num($r['amount']); $r['days'] = num($r['days']); }
    unset($r);
    $save['sequence'] = [];
    foreach (array_keys(COLL_SEQUENCE) as $k) {
        $save['sequence'][$k] = ['date' => trim((string) ($_POST['seq_date'][$k] ?? '')),
                                 'text' => trim((string) ($_POST['seq_text'][$k] ?? ''))];
    }
    worksheetSave($client, $assessment, KEY, $save);
}

$notes = [];
if (isFreshRun($data)) {
    $ehr = latestData($cid, 'ehr');
    if ($ehr) $data = prefill($data, ['revenue_annual' => ['annual revenue (A1)', num($ehr['a1_revenue'] ?? 0)]], 'EHR worksheet', $notes);
}

$x = calcCollections($data);
$rows = jrows($data, 'invoices');
for ($i = 0; $i < 4; $i++) $rows[] = [];
$seq = is_array($data['sequence'] ?? null) ? $data['sequence'] : [];

$pageTitle = 'Collections Calendar';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Collections Calendar</h1>
  <p class="hint">
    Turns "I'll chase it when I have time" into invoice timing, a written follow-up sequence, and
    terms the owner will actually enforce. This is where the behavioral block shows up most
    reliably, so the plan assumes avoidance: small steps, specific dates, and words already written.
  </p>
</div>

<?= renderPrefillNotes($notes) ?>
<?= renderPeriodField($assessment, 'e.g. receivables as of 11 Nov 2026') ?>

<section class="card p-5">
  <?= sectionHead('Section A', 'Where the money is', 'One row per open invoice. With an "as of" date and invoice dates, days outstanding are computed; otherwise type the days.') ?>
  <div class="mb-4 grid gap-4 sm:grid-cols-3">
    <?= textField($data, 'as_of', 'As of', '', '', 'date') ?>
    <?= numField($data, 'revenue_annual', 'Annual revenue', 'For days sales outstanding.') ?>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[60rem] text-sm">
      <thead>
        <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
          <th class="pb-2 pr-3 font-semibold">Client</th>
          <th class="pb-2 pr-3 font-semibold">Amount</th>
          <th class="pb-2 pr-3 font-semibold">Invoice date</th>
          <th class="pb-2 pr-3 font-semibold">Days out</th>
          <th class="pb-2 pr-3 font-semibold">Last contact</th>
          <th class="pb-2 pr-3 font-semibold">Next step</th>
          <th class="pb-2 font-semibold">Contact by</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td class="py-2 pr-3"><input class="field" name="inv_client[<?= $i ?>]" value="<?= h($r['client'] ?? '') ?>"></td>
            <td class="py-2 pr-3 w-28"><input class="field amount" name="inv_amount[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($r['amount'] ?? '')) ?>"></td>
            <td class="py-2 pr-3"><input class="field" type="date" name="inv_date[<?= $i ?>]" value="<?= h($r['invoice_date'] ?? '') ?>"></td>
            <td class="py-2 pr-3 w-24"><input class="field amount" name="inv_days[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($r['days'] ?? '')) ?>"></td>
            <td class="py-2 pr-3"><input class="field" type="date" name="inv_last[<?= $i ?>]" value="<?= h($r['last_contact'] ?? '') ?>"></td>
            <td class="py-2 pr-3"><input class="field" name="inv_next[<?= $i ?>]" value="<?= h($r['next_step'] ?? '') ?>"></td>
            <td class="py-2"><input class="field" type="date" name="inv_by[<?= $i ?>]" value="<?= h($r['contact_by'] ?? '') ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section B', 'The readings', 'Receivables past 60 days are reported on their own. The Health Check already refuses to count them as available cash.') ?>
  <div class="grid gap-4 sm:grid-cols-3">
    <?= textField($data, 'terms_label', 'Stated terms today', 'net 15, net 30, due at delivery') ?>
    <?= numField($data, 'terms_days', 'Stated terms in days', 'Due at delivery = 0.') ?>
  </div>
  <?= renderReadingPanel($x, 'Starting points, on the share past 60 days: Healthy under 10%. Watch 10% to 25%. Act over 25%.') ?>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section C', 'The terms the owner will actually enforce', 'Pick a late-payment step the owner will really take (pausing work), not a fee the owner will never charge.') ?>
  <div class="grid gap-4 lg:grid-cols-2">
    <?= textField($data, 'invoice_when', 'When an invoice goes out', 'At delivery, not month-end') ?>
    <?= textField($data, 'due_when', 'When it is due', 'Within 15 days') ?>
    <?= textField($data, 'deposit_rule', 'Deposit or card-on-file rule', 'Card on file for maintenance-plan customers') ?>
    <?= textField($data, 'late_step', 'Late-payment step', 'Work pauses at day 30') ?>
  </div>
  <p class="mt-3 rounded border border-warn/30 bg-warn-soft px-3 py-2 text-sm text-warn">
    <strong>Referral boundary.</strong> Anything headed for collections litigation goes to an attorney.
  </p>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section D', 'The sequence', 'The owner\'s own dates and wording. Written once, so nothing has to be composed on a bad day.') ?>
  <ul class="divide-y divide-hairline">
    <?php foreach (COLL_SEQUENCE as $k => [$day, $what, $prompt]): ?>
      <li class="grid gap-3 py-3 lg:grid-cols-[8rem_1fr_10rem]">
        <div><span class="text-sm font-semibold"><?= h($day) ?></span><p class="hint"><?= h($what) ?></p></div>
        <textarea class="field" name="seq_text[<?= h($k) ?>]" rows="2" placeholder="<?= h($prompt) ?>"><?= h($seq[$k]['text'] ?? '') ?></textarea>
        <input class="field" name="seq_date[<?= h($k) ?>]" placeholder="date or rule" value="<?= h($seq[$k]['date'] ?? '') ?>">
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section E', 'This week', 'The five oldest accounts, each with a date for the first contact. Set the "contact by" dates in Section A.') ?>
  <?php if ($x['oldest']): ?>
    <ol class="list-decimal space-y-1 pl-5 text-sm">
      <?php foreach ($x['oldest'] as $o): ?>
        <li>
          <span class="font-medium"><?= h($o['client'] ?? '') ?></span>
          <span class="text-muted">· <?= h(money($o['amount_num'])) ?> · <?= (int) $o['days_calc'] ?> days</span>
          <?php if (($o['contact_by'] ?? '') !== ''): ?>
            <span>· contact by <?= h(niceDate($o['contact_by'])) ?></span>
          <?php else: ?>
            <span class="text-bad">· no contact date yet</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php else: ?>
    <p class="text-sm text-muted">Add the open invoices above.</p>
  <?php endif; ?>
  <div class="mt-4 grid gap-4 sm:grid-cols-3">
    <?= numField($data, 'target_past60', 'Target: past 60 days under ($)', 'The owner\'s number.', '3000') ?>
    <?= textField($data, 'target_date', 'By', '', '', 'date') ?>
  </div>
  <?php if ($x['target'] > 0 && $x['b1'] > 0): ?>
    <p class="mt-2 text-sm text-muted">
      Today: <?= h(money($x['past60'])) ?> past 60 days. To reach the target,
      <?= h(money(max(0.0, $x['past60'] - $x['target']))) ?> has to be collected, settled into a written plan, or written off.
    </p>
  <?php endif; ?>
</section>

<?= renderBehavioralSection($data, 'If I call them, they\'ll stop using me.', 'Allocation Sequence (Unit 2.1) once cash stops shrinking; the EHR Calculator (Unit 2.2) for pricing.') ?>

<?= renderSaveBar($assessment['status'], 'Blank invoice rows are discarded on save.') ?>
</form>
<?php renderFooter();
