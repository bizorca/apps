<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'referrals';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);

const RESPONSE_WINDOWS = [
    'immediate'  => 'Acute and immediate: won by being the first name recalled and answering the phone',
    'deliberate' => 'Acute but deliberated: won by the specificity of the endorsement',
    'both'       => 'Both, depending on the job',
];
const REF_TEXTS = ['as_of', 'window_note', 'cadence', 'thank_you_rule', 'behavior_line'];
const REF_COLS  = ['name' => 'Name', 'sees' => 'What they see first', 'ask' => 'The specific ask',
                   'easy' => 'Easy way to refer', 'thanks' => 'Thank-you method'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $save = postTexts(REF_TEXTS) + postNums(['new_clients_total']);
    $save['window'] = postPick('window', 'v', array_keys(RESPONSE_WINDOWS));
    $sources = [];
    foreach (postArray('s_name') as $i => $name) {
        if (trim((string) $name) === '') continue;
        $row = ['type' => postPickRow('s_type', (int) $i, array_keys(REFERRAL_SOURCE_TYPES), 'pro')];
        foreach (array_keys(REF_COLS) as $k) $row[$k] = trim((string) (postArray('s_' . $k)[$i] ?? ''));
        $row['last_contact'] = trim((string) (postArray('s_last')[$i] ?? ''));
        $row['referrals'] = num((string) (postArray('s_refs')[$i] ?? ''));
        $row['clients']   = num((string) (postArray('s_clients')[$i] ?? ''));
        $sources[] = $row;
    }
    $save['sources'] = $sources;
    worksheetSave($client, $assessment, KEY, $save);
}

$x = calcReferralShare($data);
$sources = jrows($data, 'sources');
foreach (array_keys(REFERRAL_SOURCE_TYPES) as $t) $sources[] = ['type' => $t];
$late = [];
foreach ($x['sources'] as $s) if ($s['overdue']) $late[$s['name']] = true;

$pageTitle = 'Three-Source Referral System';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Three-Source Referral System</h1>
  <p class="hint">
    Replaces "network more" with named sources, a specific ask, an easy way to refer, and a
    consistent thank-you.
  </p>
</div>

<?= renderPeriodField($assessment, 'e.g. Q4 2026') ?>

<section class="card p-5">
  <?= sectionHead('Section A', 'The response window', 'Nearly every service has distress triggers. What differs is how fast the buyer decides.') ?>
  <div class="space-y-2 text-sm">
    <?php foreach (RESPONSE_WINDOWS as $v => $l): ?>
      <label class="flex items-start gap-2"><input type="radio" class="mt-1" name="window[v]" value="<?= h($v) ?>"<?= ($data['window'] ?? '') === $v ? ' checked' : '' ?>> <?= h($l) ?></label>
    <?php endforeach; ?>
  </div>
  <div class="mt-4"><?= areaField($data, 'window_note', 'What follows from it for this business', '', 2) ?></div>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section B', 'The source map', 'Rows grouped by the three source types. A blank row for each type is always at the bottom.') ?>
  <div class="mb-4 grid gap-4 sm:grid-cols-3">
    <?= textField($data, 'as_of', 'As of', '', 'For the monthly-touch check.', 'date') ?>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[80rem] text-sm">
      <thead>
        <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
          <th class="pb-2 pr-3 font-semibold">Type</th>
          <?php foreach (REF_COLS as $label): ?><th class="pb-2 pr-3 font-semibold"><?= h($label) ?></th><?php endforeach; ?>
          <th class="pb-2 pr-3 font-semibold">Last contact</th>
          <th class="pb-2 pr-3 font-semibold">Referrals this quarter</th>
          <th class="pb-2 font-semibold">Became clients</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <?php foreach ($sources as $i => $s): ?>
          <tr>
            <td class="py-2 pr-3 w-48">
              <select class="field" name="s_type[<?= $i ?>]">
                <?php foreach (REFERRAL_SOURCE_TYPES as $tk => $tl): ?>
                  <option value="<?= h($tk) ?>"<?= ($s['type'] ?? 'pro') === $tk ? ' selected' : '' ?>><?= h($tl) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <?php foreach (array_keys(REF_COLS) as $k): ?>
              <td class="py-2 pr-3"><input class="field" name="s_<?= h($k) ?>[<?= $i ?>]" value="<?= h($s[$k] ?? '') ?>"></td>
            <?php endforeach; ?>
            <td class="py-2 pr-3">
              <input class="field<?= isset($late[$s['name'] ?? '']) ? ' border-bad' : '' ?>" type="date" name="s_last[<?= $i ?>]" value="<?= h($s['last_contact'] ?? '') ?>">
            </td>
            <td class="py-2 pr-3 w-24"><input class="field amount" name="s_refs[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($s['referrals'] ?? '')) ?>"></td>
            <td class="py-2 w-24"><input class="field amount" name="s_clients[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($s['clients'] ?? '')) ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section C', 'The cadence') ?>
  <div class="grid gap-4 lg:grid-cols-2">
    <?= textField($data, 'cadence', 'Touch per source', 'One touch per source per month, with dates') ?>
    <?= textField($data, 'thank_you_rule', 'Thank-you rule', 'Sent within 48 hours of every referral') ?>
  </div>
  <?php if ($x['overdue'] > 0): ?>
    <p class="mt-3 rounded border border-warn/30 bg-warn-soft px-3 py-2 text-sm text-warn">
      <?= (int) $x['overdue'] ?> source<?= $x['overdue'] === 1 ? ' is' : 's are' ?> overdue for a monthly touch (last contact more than 31 days ago).
    </p>
  <?php endif; ?>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section D', 'The numbers') ?>
  <div class="max-w-xs"><?= numField($data, 'new_clients_total', 'New clients this quarter, all sources') ?></div>
  <?= renderReadingPanel($x, 'Starting points, on the share of new clients from named sources: Healthy 50% or more. Watch 25% to under 50%. Act under 25%.') ?>
</section>

<?= renderBehavioralSection($data, 'Asking makes me look needy.', 'Collections Calendar (Unit 2.3) for the new work, and the Service Agreement ceiling (Unit 3.2) before volume outgrows it.') ?>

<?= renderSaveBar($assessment['status'], 'Rows without a name are discarded on save.') ?>
</form>
<?php renderFooter();
