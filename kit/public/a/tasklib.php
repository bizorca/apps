<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'tasklib';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);
$cid = (int) $client['id'];

/** One entry's fields, in the order they print. */
const ENTRY_FIELDS = [
    'name'          => ['Task name', 'text'],
    'trigger'       => ['Trigger: what starts it', 'text'],
    'frequency'     => ['How often', 'text'],
    'duration'      => ['How long it takes', 'text'],
    'who_now'       => ['Who does it now', 'text'],
    'who_could'     => ['Who could do it once it is written', 'text'],
    'steps'         => ['The steps, one per line, as the owner actually does them', 'area'],
    'done_looks'    => ['What "done" looks like, in a form someone else could check', 'area'],
    'tools'         => ['Tools, templates, and where logins are kept (the location, never the passwords)', 'area'],
    'goes_wrong'    => ['What usually goes wrong, and what to do about it', 'area'],
    'target_date'   => ['Target draft date', 'date'],
    'last_reviewed' => ['Last reviewed', 'date'],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $index = [];
    foreach (postArray('row_task') as $i => $task) {
        $task = trim((string) $task);
        $hoursRaw = (string) (postArray('row_hours')[$i] ?? '');
        if ($task === '' && trim($hoursRaw) === '') continue;
        $index[] = [
            'task'    => $task,
            'hours'   => num($hoursRaw),
            'type'    => postPickRow('row_type', (int) $i, array_keys(CAPACITY_TYPES), 'D'),
            'only_me' => !empty($_POST['row_only_me'][$i]),
            'written' => !empty($_POST['row_written'][$i]),
            'drafted' => trim((string) ($_POST['row_drafted'][$i] ?? '')),
        ];
    }
    $entries = [];
    foreach (postArray('entry') as $e) {
        if (!is_array($e)) continue;
        $row = [];
        foreach (array_keys(ENTRY_FIELDS) as $k) $row[$k] = trim((string) ($e[$k] ?? ''));
        if (implode('', $row) !== '') $entries[] = $row;
    }
    worksheetSave($client, $assessment, KEY, [
        'index' => $index, 'entries' => $entries, 'behavior_line' => post('behavior_line'),
    ]);
}

// A fresh run copies the Capacity Audit's activities into the index.
$notes = [];
if (isFreshRun($data)) {
    $cap = latestData($cid, 'capacity');
    $capRows = [];
    foreach (jrows($cap, 'rows') as $r) {
        $capRows[] = ['task' => $r['activity'] ?? '', 'hours' => num($r['hours'] ?? 0), 'type' => $r['type'] ?? 'D',
                      'only_me' => !empty($r['only_me']), 'written' => !empty($r['written']), 'drafted' => ''];
    }
    $data = prefill($data, ['index' => ['the activity list', $capRows]], 'Capacity Audit', $notes);
}

$x = calcTaskLibrary($data);

// ---- Printable entry pages ----------------------------------------------
if (($_GET['print'] ?? '') === '1') {
    $pageTitle = 'Task Library entries';
    renderHeader(compact('pageTitle', 'client'));
    echo '<div class="no-print mb-6 flex flex-wrap items-center gap-3"><a class="btn-quiet" href="'
       . h(url('/a/tasklib.php?client=' . $cid . '&a=' . (int) $assessment['id'])) . '">← Back to the worksheet</a>'
       . '<span class="text-sm text-muted">Use your browser\'s Print command. Each entry starts a new page.</span></div>';
    foreach ($x['entries'] as $n => $e) {
        echo '<article class="mx-auto max-w-readable' . ($n > 0 ? ' print-break mt-10' : '') . '">'
           . '<p class="text-xs font-semibold uppercase tracking-wide text-primary">Task Library · ' . h($client['business_name']) . '</p>'
           . '<h1 class="text-2xl font-semibold">' . h($e['name']) . '</h1><dl class="mt-4 space-y-3 text-sm">';
        foreach (ENTRY_FIELDS as $k => [$label, $kind]) {
            if ($k === 'name' || trim($e[$k] ?? '') === '') continue;
            echo '<div><dt class="font-semibold">' . h($label) . '</dt><dd class="mt-1">';
            if ($k === 'steps') {
                echo '<ol class="list-decimal space-y-1 pl-5">';
                foreach (preg_split('/\R/', $e[$k]) as $line) {
                    $line = trim(preg_replace('/^\d+[.)]\s*/', '', $line) ?? '');
                    if ($line !== '') echo '<li>' . h($line) . '</li>';
                }
                echo '</ol>';
            } else {
                echo '<span class="whitespace-pre-line">' . h($kind === 'date' ? niceDate($e[$k]) : $e[$k]) . '</span>';
            }
            echo '</dd></div>';
        }
        echo '</dl><p class="mt-6 text-xs text-muted">A checklist gains a line every time something goes wrong.</p></article>';
    }
    if (!$x['entries']) echo '<p class="text-muted">No entries yet.</p>';
    renderFooter();
    exit;
}

$index = jrows($data, 'index');
for ($i = 0; $i < 3; $i++) $index[] = ['task' => '', 'hours' => '', 'type' => 'D', 'only_me' => false, 'written' => false, 'drafted' => ''];
$entries = jrows($data, 'entries');
$entries[] = array_fill_keys(array_keys(ENTRY_FIELDS), '');

$pageTitle = 'Modular Task Library';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Modular Task Library</h1>
  <p class="hint">
    The franchise prototype for a solo practice, after Michael Gerber's <em>The E-Myth Revisited</em>:
    the recurring work written down, so the business runs on protocols and can be handed off, sold,
    or given a week's rest. Write the most fragile task first.
  </p>
</div>

<?= renderPrefillNotes($notes) ?>
<?= renderPeriodField($assessment, 'e.g. library as of Nov 2026') ?>

<section class="card p-5">
  <?= sectionHead('Section A', 'The library index', 'Every activity, sorted below by fragility: "only me" and not written first, then by hours. Tick Written once an entry is drafted and checked.') ?>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[52rem] text-sm">
      <thead>
        <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
          <th class="pb-2 pr-3 font-semibold">Task</th>
          <th class="pb-2 pr-3 font-semibold">Hours/week</th>
          <th class="pb-2 pr-3 font-semibold">Type</th>
          <th class="pb-2 pr-3 text-center font-semibold">Only me?</th>
          <th class="pb-2 pr-3 text-center font-semibold">Written?</th>
          <th class="pb-2 font-semibold">Entry drafted</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <?php foreach ($index as $i => $r): ?>
          <tr>
            <td class="py-2 pr-3"><input class="field" name="row_task[<?= $i ?>]" value="<?= h($r['task'] ?? '') ?>"></td>
            <td class="py-2 pr-3 w-28"><input class="field amount" name="row_hours[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($r['hours'] ?? '')) ?>"></td>
            <td class="py-2 pr-3 w-44">
              <select class="field" name="row_type[<?= $i ?>]">
                <?php foreach (CAPACITY_TYPES as $tk => $tl): ?>
                  <option value="<?= h($tk) ?>"<?= ($r['type'] ?? 'D') === $tk ? ' selected' : '' ?>><?= h($tk . ' — ' . $tl) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="py-2 pr-3 text-center"><input type="checkbox" name="row_only_me[<?= $i ?>]" value="1" class="h-4 w-4 rounded border-hairline text-primary focus:ring-primary/30"<?= !empty($r['only_me']) ? ' checked' : '' ?>></td>
            <td class="py-2 pr-3 text-center"><input type="checkbox" name="row_written[<?= $i ?>]" value="1" class="h-4 w-4 rounded border-hairline text-primary focus:ring-primary/30"<?= !empty($r['written']) ? ' checked' : '' ?>></td>
            <td class="py-2"><input class="field" type="date" name="row_drafted[<?= $i ?>]" value="<?= h($r['drafted'] ?? '') ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($x['rows']): ?>
    <h3 class="mt-5 text-sm font-semibold">Write in this order</h3>
    <ol class="mt-1 list-decimal space-y-0.5 pl-5 text-sm">
      <?php foreach ($x['rows'] as $r): ?>
        <li><?= h($r['task'] ?? '') ?> <span class="text-muted">· <?= h(hours($r['hours_num'])) ?>
          <?= $r['group'] === 0 ? '· only me, not written' : ($r['group'] === 1 ? '· not written' : '· written') ?></span></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <?= renderReadingPanel($x, 'Starting points, on coverage: Healthy 60% or more. Watch 30% to under 60%. Act under 30%.') ?>
</section>

<section class="card mt-6 p-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <?= sectionHead('Section B', 'Entries', 'One entry per task. A blank entry is always at the bottom; fill it and save to add another.') ?>
    <?php if ($x['entries']): ?>
      <a class="btn-secondary no-print" href="<?= h(url('/a/tasklib.php?client=' . $cid . '&a=' . (int) $assessment['id'] . '&print=1')) ?>">Printable entry pages</a>
    <?php endif; ?>
  </div>
  <?php foreach ($entries as $n => $e): ?>
    <fieldset class="mt-4 rounded-md border border-hairline p-4">
      <legend class="px-1 text-sm font-semibold"><?= h(($e['name'] ?? '') !== '' ? $e['name'] : 'New entry') ?></legend>
      <div class="grid gap-4 lg:grid-cols-2">
        <?php foreach (ENTRY_FIELDS as $k => [$label, $kind]): ?>
          <div class="<?= $kind === 'area' ? 'lg:col-span-2' : '' ?>">
            <label class="label" for="entry_<?= $n ?>_<?= h($k) ?>"><?= h($label) ?></label>
            <?php if ($kind === 'area'): ?>
              <textarea class="field mt-1" id="entry_<?= $n ?>_<?= h($k) ?>" name="entry[<?= $n ?>][<?= h($k) ?>]" rows="<?= $k === 'steps' ? 6 : 2 ?>"><?= h($e[$k] ?? '') ?></textarea>
            <?php else: ?>
              <input class="field mt-1" type="<?= $kind === 'date' ? 'date' : 'text' ?>" id="entry_<?= $n ?>_<?= h($k) ?>" name="entry[<?= $n ?>][<?= h($k) ?>]" value="<?= h($e[$k] ?? '') ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="mt-3 text-xs text-muted">A checklist gains a line every time something goes wrong.</p>
    </fieldset>
  <?php endforeach; ?>
</section>

<?= renderBehavioralSection($data, 'Nobody else can do it the way I do.', 'Service Agreement (Unit 3.2), then the Delegation Decision Matrix (Unit 3.3).') ?>

<?= renderSaveBar($assessment['status'], 'Blank rows and blank entries are discarded on save.') ?>
</form>
<?php renderFooter();
