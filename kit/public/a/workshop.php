<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'workshop';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);

/** Section C: the follow-up sequence after each run. */
const WORKSHOP_FOLLOWUP = [
    'same_day' => ['Same day',  'Thank-you'],
    'h48'      => ['48 hours',  'Recap with the handout'],
    'h72'      => ['72 hours',  'Invitation to the offer'],
    'd7'       => ['7 days',    'A client story'],
    'd14'      => ['14 days',   'Second invitation'],
];
const WORKSHOP_TEXTS = ['problem', 'title', 'format', 'teach_1', 'teach_2', 'teach_3', 'exercise',
                        'transition', 'signup', 'cadence', 'partners', 'lists', 'attendance_target', 'behavior_line'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $save = postTexts(WORKSHOP_TEXTS);
    $save['followup'] = [];
    foreach (array_keys(WORKSHOP_FOLLOWUP) as $k) $save['followup'][$k] = trim((string) ($_POST['fu'][$k] ?? ''));
    $save['runs'] = collectRows([
        'date' => 'run_date', 'registered' => 'run_reg', 'attended' => 'run_att',
        'bookings' => 'run_book', 'revenue' => 'run_rev',
    ]);
    foreach ($save['runs'] as &$r) foreach (['registered', 'attended', 'bookings', 'revenue'] as $k) $r[$k] = num($r[$k]);
    unset($r);
    worksheetSave($client, $assessment, KEY, $save);
}

$x = calcWorkshopRun($data);
$runs = jrows($data, 'runs');
for ($i = 0; $i < 2; $i++) $runs[] = [];
$fu = is_array($data['followup'] ?? null) ? $data['followup'] : [];

$pageTitle = 'Micro-Workshop Framework';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Micro-Workshop Framework</h1>
  <p class="hint">
    Workshop-led acquisition, for buyers who don't yet know they have the problem. The teaching
    does the diagnosis and the room self-selects. Teaching, not pitching.
  </p>
</div>

<?= renderPeriodField($assessment, 'e.g. 2027 workshop series') ?>

<section class="card p-5">
  <?= sectionHead('Section A', 'The workshop') ?>
  <div class="grid gap-4 lg:grid-cols-2">
    <?= areaField($data, 'problem', 'The problem buyers don\'t recognize', '', 2) ?>
    <?= textField($data, 'title', 'Working title') ?>
    <?= textField($data, 'format', 'Format', '45–60 minutes, in person or online') ?>
    <div></div>
    <?= textField($data, 'teach_1', 'It teaches: 1') ?>
    <?= textField($data, 'teach_2', 'It teaches: 2') ?>
    <?= textField($data, 'teach_3', 'It teaches: 3') ?>
    <div></div>
    <?= areaField($data, 'exercise', 'The self-diagnosis exercise attendees do in the room', '', 2) ?>
    <?= areaField($data, 'transition', 'The transition line to one specific offer', '', 2) ?>
    <?= textField($data, 'signup', 'How attendees sign up on the spot', 'Paper form, QR code, or a short web address') ?>
  </div>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section B', 'Filling the room') ?>
  <div class="grid gap-4 lg:grid-cols-2">
    <?= textField($data, 'cadence', 'Cadence', 'Weekly, monthly, or quarterly') ?>
    <?= textField($data, 'attendance_target', 'Attendance target') ?>
    <?= areaField($data, 'partners', 'Partners who already gather these buyers', '', 2) ?>
    <?= areaField($data, 'lists', 'Lists and calendars', '', 2) ?>
  </div>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section C', 'Follow-up', 'Written before the first run, so it goes out every time.') ?>
  <ul class="divide-y divide-hairline">
    <?php foreach (WORKSHOP_FOLLOWUP as $k => [$when, $what]): ?>
      <li class="grid gap-3 py-3 lg:grid-cols-[10rem_1fr]">
        <div><span class="text-sm font-semibold"><?= h($when) ?></span><p class="hint"><?= h($what) ?></p></div>
        <textarea class="field" name="fu[<?= h($k) ?>]" rows="2"><?= h($fu[$k] ?? '') ?></textarea>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section D', 'The numbers, after each run', 'Tracked over runs rather than judged on one. There is no Healthy/Watch/Act here by design.') ?>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[44rem] text-sm">
      <thead>
        <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
          <th class="pb-2 pr-3 font-semibold">Date</th>
          <th class="pb-2 pr-3 font-semibold">Registered</th>
          <th class="pb-2 pr-3 font-semibold">Attended</th>
          <th class="pb-2 pr-3 font-semibold">Bookings</th>
          <th class="pb-2 font-semibold">Revenue from bookings</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <?php foreach ($runs as $i => $r): ?>
          <tr>
            <td class="py-2 pr-3"><input class="field" name="run_date[<?= $i ?>]" value="<?= h((string) ($r['date'] ?? '')) ?>"></td>
            <?php foreach (['registered' => 'run_reg', 'attended' => 'run_att', 'bookings' => 'run_book', 'revenue' => 'run_rev'] as $k => $name): ?>
              <td class="py-2 pr-3 w-32"><input class="field amount" name="<?= $name ?>[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($r[$k] ?? '')) ?>"></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= renderSteps($x['steps']) ?>
</section>

<?= renderBehavioralSection($data, 'Nobody will come. I\'m not a speaker.', 'Back to the Collections Calendar (Unit 2.3) once bookings arrive, so new revenue becomes cash.') ?>

<?= renderSaveBar($assessment['status'], 'Blank run rows are discarded on save.') ?>
</form>
<?php renderFooter();
