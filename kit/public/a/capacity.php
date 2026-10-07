<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';
require_once KIT_ROOT . '/includes/reference.php';

const KEY = 'capacity';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();

    // Part A — the activity rows. Checkboxes in a repeating row can't use the
    // bare name[] form (unchecked boxes submit nothing, so the arrays fall out
    // of alignment); they are indexed explicitly instead.
    $activities = postArray('row_activity');
    $rows = [];
    foreach ($activities as $i => $activity) {
        $activity = trim((string) $activity);
        $hoursRaw = (string) (postArray('row_hours')[$i] ?? '');
        if ($activity === '' && trim($hoursRaw) === '') continue;
        $rows[] = [
            'activity' => $activity,
            'hours'    => num($hoursRaw),
            'type'     => postPickRow('row_type', $i, array_keys(CAPACITY_TYPES), 'D'),
            'only_me'  => !empty($_POST['row_only_me'][$i]),
            'written'  => !empty($_POST['row_written'][$i]),
        ];
    }

    // Part B — the handoff test.
    $handoff = [];
    foreach (array_merge(handoffItems(), array_filter(array_map('trim', postArray('handoff_custom_label')))) as $idx => $label) {
        $handoff[] = [
            'label' => $label,
            'state' => postPickRow('handoff_state', $idx, array_keys(HANDOFF_STATES)),
        ];
    }

    worksheetSave($client, $assessment, KEY, [
        'rows'          => $rows,
        'ceiling'       => postNum('ceiling'),
        'handoff'       => $handoff,
        'c_item'        => post('c_item'),
        'c_why'         => post('c_why'),
        'c_drafted_by'  => post('c_drafted_by'),
        'c_date'        => post('c_date'),
        'c_who_could'   => post('c_who_could'),
        'c_step1'       => post('c_step1'),
        'c_step2'       => post('c_step2'),
        'c_step3'       => post('c_step3'),
    ]);
}

$c = calcCapacity($data);

// Existing rows plus four blanks.
$rows = jrows($data, 'rows');
for ($i = 0; $i < 4; $i++) $rows[] = ['activity' => '', 'hours' => '', 'type' => 'D', 'only_me' => false, 'written' => false];

// Handoff: the standard items, plus anything custom already saved.
$savedHandoff = jrows($data, 'handoff');
$handoffLabels = handoffItems();
foreach ($savedHandoff as $sh) {
    if (!in_array($sh['label'], $handoffLabels, true)) $handoffLabels[] = $sh['label'];
}
$handoffState = [];
foreach ($savedHandoff as $sh) $handoffState[$sh['label']] = $sh['state'] ?? '';

$f = fn(string $k) => jget($data, $k);

$pageTitle = 'Capacity Audit and Handoff Test';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Capacity Audit and Handoff Test</h1>
  <p class="hint">
    The question underneath both halves: <strong>if you had to hand this business to someone
    next month, what would they need?</strong> The operator layer comes last not because it
    matters least, but because it holds everything else up. A business that lives only in the
    owner's head can't be handed off, sold, or given a week's rest.
  </p>
</div>

<?= renderPeriodField($assessment, 'e.g. week of 14 Sep 2026') ?>

<!-- Part A ------------------------------------------------------------------ -->
<section class="card p-5">
  <?= sectionHead('Part A', 'Capacity audit: a typical week', 'Every activity, the hours it takes, and two questions that decide how fragile the business is.') ?>

  <div class="overflow-x-auto">
    <table class="w-full min-w-[46rem] text-sm">
      <thead>
        <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
          <th class="pb-2 pr-3 font-semibold">Activity</th>
          <th class="pb-2 pr-3 font-semibold">Hours/week</th>
          <th class="pb-2 pr-3 font-semibold">Type</th>
          <th class="pb-2 pr-3 text-center font-semibold">Only me?</th>
          <th class="pb-2 text-center font-semibold">Written down?</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td class="py-2 pr-3">
              <input class="field" name="row_activity[<?= $i ?>]" placeholder="Pumping and inspection"
                     value="<?= h($r['activity'] ?? '') ?>">
            </td>
            <td class="py-2 pr-3 w-28">
              <input class="field amount" name="row_hours[<?= $i ?>]" inputmode="decimal"
                     value="<?= h((string) ($r['hours'] ?? '')) ?>">
            </td>
            <td class="py-2 pr-3 w-44">
              <select class="field" name="row_type[<?= $i ?>]">
                <?php foreach (CAPACITY_TYPES as $tk => $tl): ?>
                  <option value="<?= h($tk) ?>"<?= ($r['type'] ?? 'D') === $tk ? ' selected' : '' ?>><?= h($tk . ' — ' . $tl) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="py-2 pr-3 text-center">
              <input type="checkbox" name="row_only_me[<?= $i ?>]" value="1"
                     class="h-4 w-4 rounded border-hairline text-primary focus:ring-primary/30"<?= !empty($r['only_me']) ? ' checked' : '' ?>>
            </td>
            <td class="py-2 text-center">
              <input type="checkbox" name="row_written[<?= $i ?>]" value="1"
                     class="h-4 w-4 rounded border-hairline text-primary focus:ring-primary/30"<?= !empty($r['written']) ? ' checked' : '' ?>>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="mt-4 max-w-xs">
    <label class="label" for="ceiling">Sustainable weekly hour ceiling</label>
    <input class="field amount mt-1" id="ceiling" name="ceiling" inputmode="decimal" value="<?= h((string) $f('ceiling')) ?>">
    <p class="hint">The owner's number, not the advisor's.</p>
  </div>

  <?= renderSteps($c['steps'], 'Totals') ?>

  <?php if ($c['over_ceiling'] > 0): ?>
    <p class="mt-3 rounded border border-warn/30 bg-warn-soft px-3 py-2 text-sm text-warn">
      The week runs <?= h(number_format($c['over_ceiling'], 1)) ?> hours over the ceiling.
      Growth has no room to land until something comes off the list.
    </p>
  <?php endif; ?>
  <?php if ($c['only_me_unwritten'] > 0): ?>
    <p class="mt-2 rounded border border-bad/30 bg-bad-soft px-3 py-2 text-sm text-bad">
      <?= h(number_format($c['only_me_unwritten'], 1)) ?> hours a week are "only me" <em>and</em>
      not written down anywhere. That is where the business is most fragile.
    </p>
  <?php endif; ?>
</section>

<!-- Part B ------------------------------------------------------------------ -->
<section class="card mt-6 p-5">
  <?= sectionHead('Part B', 'The handoff test', 'For each item: written down, in the owner\'s head, or does not exist.') ?>

  <ul class="divide-y divide-hairline">
    <?php foreach ($handoffLabels as $idx => $label): $st = $handoffState[$label] ?? ''; ?>
      <li class="flex flex-wrap items-center justify-between gap-3 py-3">
        <span class="text-sm"><?= h($label) ?></span>
        <div class="flex gap-1">
          <?php foreach (HANDOFF_STATES as $sv => $sl):
            $on = $st === $sv;
            $cls = $on ? match ($sv) {
                'written' => 'bg-good-soft text-good border-good',
                'in_head' => 'bg-warn-soft text-warn border-warn',
                default   => 'bg-bad-soft text-bad border-bad',
            } : 'border-hairline text-muted hover:bg-sunk'; ?>
            <label class="cursor-pointer rounded border px-2.5 py-1 text-xs font-medium <?= h($cls) ?>">
              <input type="radio" class="sr-only" name="handoff_state[<?= $idx ?>]" value="<?= h($sv) ?>"<?= $on ? ' checked' : '' ?>>
              <?= h($sl) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if ($idx >= count(handoffItems())): ?>
          <input type="hidden" name="handoff_custom_label[]" value="<?= h($label) ?>">
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    <li class="py-3">
      <label class="sr-only" for="handoff_new">Other item specific to this business</label>
      <input class="field" id="handoff_new" name="handoff_custom_label[]"
             placeholder="Other item specific to this business — add one, then save">
    </li>
  </ul>

  <?php if (array_sum($c['handoff_counts']) > 0): ?>
    <div class="mt-4 flex flex-wrap gap-3 text-sm">
      <span class="rounded bg-good-soft px-3 py-1 text-good"><?= (int) $c['handoff_counts']['written'] ?> written down</span>
      <span class="rounded bg-warn-soft px-3 py-1 text-warn"><?= (int) $c['handoff_counts']['in_head'] ?> in the owner's head</span>
      <span class="rounded bg-bad-soft px-3 py-1 text-bad"><?= (int) $c['handoff_counts']['missing'] ?> don't exist</span>
    </div>
  <?php endif; ?>
</section>

<!-- Part C ------------------------------------------------------------------ -->
<section class="card mt-6 p-5">
  <?= sectionHead('Part C', 'The first page of the operations manual', 'One item. Not a documentation project — one page.') ?>

  <div class="grid gap-4 lg:grid-cols-2">
    <div>
      <label class="label" for="c_item">The item that would hurt most if the owner were gone next month</label>
      <input class="field mt-1" id="c_item" name="c_item" value="<?= h($f('c_item')) ?>">
    </div>
    <div>
      <label class="label" for="c_why">Why this one first</label>
      <input class="field mt-1" id="c_why" name="c_why" value="<?= h($f('c_why')) ?>">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <label class="label" for="c_drafted_by">Drafted by</label>
        <input class="field mt-1" type="date" id="c_drafted_by" name="c_date" value="<?= h($f('c_date')) ?>">
      </div>
      <div>
        <label class="label" for="c_who_could">Who could do it once it's written</label>
        <input class="field mt-1" id="c_who_could" name="c_who_could" value="<?= h($f('c_who_could')) ?>">
      </div>
    </div>
    <div></div>
  </div>

  <fieldset class="mt-4">
    <legend class="label">The first three steps, as the owner actually does them</legend>
    <div class="mt-2 space-y-2">
      <?php foreach (['c_step1' => '1.', 'c_step2' => '2.', 'c_step3' => '3.'] as $name => $n): ?>
        <div class="flex items-center gap-2">
          <span class="w-5 text-sm text-muted"><?= h($n) ?></span>
          <input class="field" name="<?= h($name) ?>" value="<?= h($f($name)) ?>">
        </div>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <p class="mt-4 text-sm text-muted">Next: a Task Library entry (Unit 3.1).</p>
</section>

<?= renderSaveBar($assessment['status'], 'Blank activity rows are discarded on save.') ?>
</form>
<?php renderFooter();
