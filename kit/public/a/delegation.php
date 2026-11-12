<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'delegation';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);
$cid = (int) $client['id'];

const DELEG_TEXTS = ['role_name', 'owns', 'result_1', 'check_1', 'result_2', 'check_2', 'result_3', 'check_3',
                     'trial_who', 'trial_task', 'trial_hours', 'stop_date', 'classification',
                     'fear', 'fear_check', 'behavior_line'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $save = postTexts(DELEG_TEXTS) + postNums(['floor_rate']);
    $cands = [];
    foreach (postArray('c_task') as $i => $task) {
        $task = trim((string) $task);
        if ($task === '') continue;
        $cands[] = [
            'task'       => $task,
            'hours'      => num((string) (postArray('c_hours')[$i] ?? '')),
            'judgment'   => postPickRow('c_judgment', (int) $i, ['1', '2', '3']),
            'written'    => !empty($_POST['c_written'][$i]),
            'going_rate' => trim((string) (postArray('c_rate')[$i] ?? '')),
        ];
    }
    $save['candidates'] = $cands;
    worksheetSave($client, $assessment, KEY, $save);
}

// A fresh run takes the floor from the EHR and the activities from the Capacity Audit.
$notes = [];
if (isFreshRun($data)) {
    $ehr = latestData($cid, 'ehr');
    if ($ehr) $data = prefill($data, ['floor_rate' => ['the floor rate (D4)', round(calcEHR($ehr)['d4'], 2)]], 'EHR worksheet', $notes);
    $cap = latestData($cid, 'capacity');
    $cands = [];
    foreach (jrows($cap, 'rows') as $r) {
        $cands[] = ['task' => $r['activity'] ?? '', 'hours' => num($r['hours'] ?? 0), 'judgment' => '',
                    'written' => !empty($r['written']), 'going_rate' => ''];
    }
    $data = prefill($data, ['candidates' => ['the activity list', $cands]], 'Capacity Audit (score each one, and delete work only the owner can legally or skillfully do)', $notes);
}

$x = calcDelegation($data);
$rows = jrows($data, 'candidates');
for ($i = 0; $i < 3; $i++) $rows[] = ['task' => '', 'hours' => '', 'judgment' => '', 'written' => false, 'going_rate' => ''];

$pageTitle = 'Delegation Decision Matrix';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Delegation Decision Matrix</h1>
  <p class="hint">
    Pick one role, define it against the task library, define "done," and hand it off. The trap:
    "I can't afford help," said while doing $30-an-hour work at a $150-an-hour rate.
  </p>
</div>

<?= renderPrefillNotes($notes) ?>
<?= renderPeriodField($assessment, 'e.g. first delegation, Nov 2026') ?>

<section class="card p-5">
  <?= sectionHead('Section A', 'Candidates', 'Leave out work only the owner can legally or skillfully do. Judgment: 1 low, 3 high. Going rate: the owner\'s local estimate to hire it out.') ?>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[46rem] text-sm">
      <thead>
        <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
          <th class="pb-2 pr-3 font-semibold">Task</th>
          <th class="pb-2 pr-3 font-semibold">Hours/week</th>
          <th class="pb-2 pr-3 font-semibold">Judgment</th>
          <th class="pb-2 pr-3 text-center font-semibold">Written?</th>
          <th class="pb-2 font-semibold">Going rate/hr</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td class="py-2 pr-3"><input class="field" name="c_task[<?= $i ?>]" value="<?= h($r['task'] ?? '') ?>"></td>
            <td class="py-2 pr-3 w-28"><input class="field amount" name="c_hours[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($r['hours'] ?? '')) ?>"></td>
            <td class="py-2 pr-3 w-36">
              <select class="field" name="c_judgment[<?= $i ?>]">
                <?php foreach (['' => '—', '1' => '1 · low', '2' => '2', '3' => '3 · high'] as $jv => $jl): ?>
                  <option value="<?= h($jv) ?>"<?= (string) ($r['judgment'] ?? '') === (string) $jv ? ' selected' : '' ?>><?= h($jl) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="py-2 pr-3 text-center"><input type="checkbox" name="c_written[<?= $i ?>]" value="1" class="h-4 w-4 rounded border-hairline text-primary focus:ring-primary/30"<?= !empty($r['written']) ? ' checked' : '' ?>></td>
            <td class="py-2 w-32"><input class="field amount" name="c_rate[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($r['going_rate'] ?? '')) ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section B', 'The value of handing it off', 'Weekly value = hours × (floor rate − going rate), for judgment 1 or 2. The top task is the first delegation unless the owner names a reason.') ?>
  <div class="max-w-xs"><?= numField($data, 'floor_rate', 'B1 · Owner\'s floor rate (EHR D4)') ?></div>
  <?php if ($x['unscored']): ?>
    <p class="mt-3 text-sm text-muted">Not scored yet (needs a judgment and a going rate):
      <?= h(implode(', ', array_map(fn($r) => (string) $r['task'], $x['unscored']))) ?>.</p>
  <?php endif; ?>
  <?= renderReadingPanel($x, '') ?>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section C', 'The role', 'What the person owns outright, not "helps with," and how each result is checked on a Friday.') ?>
  <div class="grid gap-4 lg:grid-cols-2">
    <?= textField($data, 'role_name', 'Role name', 'Part-time bookkeeper') ?>
    <?= textField($data, 'owns', 'Owns outright', 'Invoicing and receivables follow-up') ?>
    <?php for ($n = 1; $n <= 3; $n++): ?>
      <?= textField($data, 'result_' . $n, 'Result ' . $n) ?>
      <?= textField($data, 'check_' . $n, 'How it is checked on Friday') ?>
    <?php endfor; ?>
  </div>
  <h3 class="mt-5 text-sm font-semibold">The paid trial: the job interview is the job</h3>
  <div class="mt-2 grid gap-4 sm:grid-cols-3">
    <?= textField($data, 'trial_who', 'Who') ?>
    <?= textField($data, 'trial_task', 'What task') ?>
    <?= textField($data, 'trial_hours', 'How many hours') ?>
  </div>
  <div class="mt-4 max-w-xs"><?= textField($data, 'stop_date', 'The owner\'s stop date', '', 'The day the owner stops doing this task.', 'date') ?></div>
  <div class="mt-4">
    <?= textField($data, 'classification', 'Employee or contractor: checked with', 'CPA or EA; L&I; ESD', 'Worker classification is a regulatory question. Washington\'s tests are generally stricter than the IRS\'s. Settle it with the agency or a CPA or EA before anyone starts.') ?>
  </div>
  <?= verifyNotice('Worker classification, workers\' comp, and unemployment insurance rules change.') ?>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section D', 'The fear and the check') ?>
  <div class="grid gap-4 lg:grid-cols-2">
    <?= areaField($data, 'fear', 'What I\'m afraid happens if I hand this off', 'They\'ll get an invoice wrong and a good customer will leave.', 2) ?>
    <?= areaField($data, 'fear_check', 'The check that would settle it', 'I review every invoice for the first two weeks, then a sample each Friday.', 2) ?>
  </div>
</section>

<?= renderBehavioralSection($data, 'It\'s faster if I just do it myself.', 'The Offer, the Client, and the Channel (Unit 4.1).') ?>

<?= renderSaveBar($assessment['status'], 'Rows without a task are discarded on save.') ?>
</form>
<?php renderFooter();
