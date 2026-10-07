<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';
require_once KIT_ROOT . '/includes/reference.php';

const KEY = 'priority';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);
$cid = (int) $client['id'];

$HANDLERS = ['owner' => 'Owner', 'cpa' => 'CPA or EA', 'attorney' => 'Attorney',
             'agency' => 'The agency directly', 'insurance' => 'Insurance agent',
             'bookkeeper' => 'Bookkeeper', 'clinician' => 'Licensed clinician', 'advisor' => 'Advisor'];

/**
 * Seed the exposure list from the regulatory screen and the tax flag list.
 *
 * Immediate exposure is step 1 of this worksheet and it has already been
 * collected upstream. Asking the advisor to retype it invites transcription
 * errors on exactly the items where an error costs the most.
 */
function seedExposures(int $clientId): array
{
    $seeded = [];

    $reg = latestAssessment($clientId, 'regulatory');
    if ($reg) {
        foreach (regulatoryItems() as $rows) {
            foreach ($rows as $row) {
                $item = $reg['data']['items'][$row['key']] ?? null;
                if (($item['status'] ?? '') !== 'exposure') continue;
                $seeded[] = [
                    'item'    => $row['q'] . ($item['note'] !== '' ? ' — ' . $item['note'] : ''),
                    'handler' => '',
                    'by_when' => '',
                    'status'  => 'open',
                    'source'  => 'Regulatory Intake Screen',
                ];
            }
        }
    }

    $tax = latestAssessment($clientId, 'tax_flags');
    if ($tax) {
        foreach (taxFlags() as $flag) {
            $f = $tax['data']['flags'][$flag['key']] ?? null;
            if (empty($f['flagged'])) continue;
            $seeded[] = [
                'item'    => $flag['name'] . (($f['note'] ?? '') !== '' ? ' — ' . $f['note'] : ''),
                'handler' => !empty($f['referred']) ? 'cpa' : '',
                'by_when' => '',
                'status'  => !empty($f['referred']) ? 'resolved' : 'open',
                'source'  => 'Tax Exposure Flag List',
            ];
        }
    }
    return $seeded;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();

    if (post('action') === 'reseed') {
        $data['exposure_items'] = seedExposures($cid);
        saveAssessment((int) $assessment['id'], $data, $assessment['status'], $assessment['period_label']);
        flashSuccess('Exposure list refreshed from the regulatory screen and tax flags.');
        redirect(url('/a/priority.php?client=' . $cid . '&a=' . (int) $assessment['id']));
    }

    $exposures = collectRows([
        'item' => 'exp_item', 'handler' => 'exp_handler',
        'by_when' => 'exp_by_when', 'status' => 'exp_status', 'source' => 'exp_source',
    ], fn($r) => trim($r['item']) === '');

    $issues = collectRows([
        'issue' => 'issue_text', 'evidence' => 'issue_evidence', 'layer' => 'issue_layer',
        'exposure' => 'issue_exposure', 'readiness' => 'issue_readiness', 'gap' => 'issue_gap',
    ], fn($r) => trim($r['issue']) === '');

    $save = [
        'exposure_items'      => $exposures,
        'issues'              => $issues,
        'priority_statement'  => post('priority_statement'),
        'priority_layer'      => post('priority_layer'),
        'priority_why'        => post('priority_why'),
        'priority_gap'        => post('priority_gap'),
        'action_what'         => post('action_what'),
        'action_by_when'      => post('action_by_when'),
        'action_done_looks'   => post('action_done_looks'),
        'action_number'       => post('action_number'),
        'action_followup'     => post('action_followup'),
        'action_referral'     => post('action_referral'),
    ];

    // Step 6 of the worksheet is the progress record. Writing it here means
    // the funder-facing report fills itself as the advisor works, rather than
    // depending on them remembering a second screen.
    if (post('log_progress') === '1' && post('progress_what_changed') !== '') {
        addProgress($cid, [
            'assessment_id'   => (int) $assessment['id'],
            'entry_date'      => post('progress_date') ?: date('Y-m-d'),
            'instrument'      => KEY,
            'what_changed'    => post('progress_what_changed'),
            'next_instrument' => post('progress_next'),
        ]);
    }

    worksheetSave($client, $assessment, KEY, $save);
}

// First open with nothing entered: pull the exposures forward automatically.
if (!jrows($data, 'exposure_items') && !jrows($data, 'issues')) {
    $data['exposure_items'] = seedExposures($cid);
}

$p       = calcPriority($data);
$f       = fn(string $k) => jget($data, $k);
$warning = priorityLayerWarning($p, (string) $f('priority_layer'));

$exposureRows = jrows($data, 'exposure_items');
for ($i = 0; $i < 2; $i++) $exposureRows[] = ['item' => '', 'handler' => '', 'by_when' => '', 'status' => 'open', 'source' => ''];

$issueRows = $p['issues'];
for ($i = 0; $i < 3; $i++) $issueRows[] = ['issue' => '', 'evidence' => '', 'layer' => '', 'exposure' => 2, 'readiness' => 2, 'gap' => ''];

$pageTitle = 'Priority and Next Action';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Priority and Next Action</h1>
  <p class="hint">
    Closes every assessment meeting. The best assessment ends with one priority, not a
    report — and one homework assignment, not a list.
  </p>
</div>

<?= renderPeriodField($assessment, 'e.g. meeting of 23 Sep 2026') ?>

<!-- 1. Immediate exposure ---------------------------------------------------- -->
<section class="card p-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <?= sectionHead('Step 1', 'Immediate exposure', 'Resolve or refer this week. Regulatory items first.') ?>
    <button type="submit" name="action" value="reseed" class="btn-quiet text-sm">
      Refresh from regulatory screen
    </button>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full min-w-[48rem] text-sm">
      <thead>
        <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
          <th class="pb-2 pr-3 font-semibold">Item</th>
          <th class="pb-2 pr-3 font-semibold">Who handles it</th>
          <th class="pb-2 pr-3 font-semibold">By when</th>
          <th class="pb-2 font-semibold">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <?php foreach ($exposureRows as $r): ?>
          <tr>
            <td class="py-2 pr-3">
              <input class="field" name="exp_item[]" value="<?= h($r['item'] ?? '') ?>" placeholder="What needs resolving">
              <?php if (($r['source'] ?? '') !== ''): ?>
                <span class="mt-1 block text-xs text-muted">from the <?= h($r['source']) ?></span>
              <?php endif; ?>
              <input type="hidden" name="exp_source[]" value="<?= h($r['source'] ?? '') ?>">
            </td>
            <td class="py-2 pr-3 w-48">
              <select class="field" name="exp_handler[]">
                <option value="">—</option>
                <?php foreach ($HANDLERS as $hk => $hl): ?>
                  <option value="<?= h($hk) ?>"<?= ($r['handler'] ?? '') === $hk ? ' selected' : '' ?>><?= h($hl) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="py-2 pr-3 w-40">
              <input class="field" type="date" name="exp_by_when[]" value="<?= h($r['by_when'] ?? '') ?>">
            </td>
            <td class="py-2 w-36">
              <select class="field" name="exp_status[]">
                <?php foreach (['open' => 'Open', 'referred' => 'Referred', 'resolved' => 'Resolved'] as $sk => $sl): ?>
                  <option value="<?= h($sk) ?>"<?= ($r['status'] ?? 'open') === $sk ? ' selected' : '' ?>><?= h($sl) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- 2 + 3. Candidate issues and the sort ------------------------------------- -->
<section class="card mt-6 p-5">
  <?= sectionHead('Steps 2 and 3', 'Candidate issues, and the sort', 'Every claim about the business needs a number, an example, or a name. A score of 3 counts as high; 1 or 2 counts as low.') ?>

  <div class="overflow-x-auto">
    <table class="w-full min-w-[56rem] text-sm">
      <thead>
        <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
          <th class="pb-2 pr-3 font-semibold">Issue</th>
          <th class="pb-2 pr-3 font-semibold">Evidence</th>
          <th class="pb-2 pr-3 font-semibold">Layer</th>
          <th class="pb-2 pr-3 font-semibold">Exposure</th>
          <th class="pb-2 pr-3 font-semibold">Readiness</th>
          <th class="pb-2 font-semibold">Gap</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <?php foreach ($issueRows as $r): ?>
          <tr>
            <td class="py-2 pr-3"><input class="field" name="issue_text[]" value="<?= h($r['issue'] ?? '') ?>"></td>
            <td class="py-2 pr-3"><input class="field" name="issue_evidence[]" value="<?= h($r['evidence'] ?? '') ?>" placeholder="A number, an example, or a name"></td>
            <td class="py-2 pr-3 w-40"><?= layerSelect('issue_layer[]', (string) ($r['layer'] ?? ''), 'field') ?></td>
            <td class="py-2 pr-3 w-24">
              <select class="field" name="issue_exposure[]">
                <?php foreach ([1, 2, 3] as $n): ?>
                  <option value="<?= $n ?>"<?= (int) ($r['exposure'] ?? 2) === $n ? ' selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="py-2 pr-3 w-24">
              <select class="field" name="issue_readiness[]">
                <?php foreach ([1, 2, 3] as $n): ?>
                  <option value="<?= $n ?>"<?= (int) ($r['readiness'] ?? 2) === $n ? ' selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="py-2 w-40">
              <select class="field" name="issue_gap[]">
                <option value="">—</option>
                <option value="informational"<?= ($r['gap'] ?? '') === 'informational' ? ' selected' : '' ?>>Informational</option>
                <option value="behavioral"<?= ($r['gap'] ?? '') === 'behavioral' ? ' selected' : '' ?>>Behavioral</option>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="hint">
    <strong>Exposure</strong> is how much harm the issue can do if it waits.
    <strong>Readiness</strong> is how likely the owner is to act on it this month.
    <strong>Informational</strong> means the owner doesn't know; <strong>behavioral</strong> means
    they know and avoid. Collections, price increases, and delegation are the classic avoidances,
    and more information will not fix any of them.
  </p>

  <?php if ($p['issues']): ?>
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
      <?php foreach (QUADRANTS as $qk => $qm): $bucket = $p['sorted'][$qk] ?? []; ?>
        <div class="rounded-md border <?= $qk === 'advisor_work' ? 'border-primary bg-primary-soft' : 'border-hairline' ?> p-4">
          <h3 class="text-sm font-semibold"><?= h($qm['name']) ?></h3>
          <p class="text-xs text-muted"><?= h($qm['hint']) ?></p>
          <?php if ($bucket): ?>
            <ul class="mt-2 space-y-1.5 text-sm">
              <?php foreach ($bucket as $issue): ?>
                <li class="flex items-start gap-2">
                  <?= renderLayerTag((string) ($issue['layer'] ?? '')) ?>
                  <span><?= h($issue['issue']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="mt-2 text-sm text-muted">Nothing here.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- 4. The one priority ------------------------------------------------------ -->
<section class="card mt-6 p-5">
  <?= sectionHead('Step 4', 'The one priority for this period', 'Why this one, and not the others.') ?>

  <?php if ($warning !== ''): ?>
    <p class="mb-4 rounded-md border border-warn bg-warn-soft px-4 py-3 text-sm text-warn">
      <strong>Check the layer order.</strong> <?= h($warning) ?>
    </p>
  <?php endif; ?>

  <div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-4">
      <div class="sm:col-span-3">
        <label class="label" for="priority_statement">The priority</label>
        <input class="field mt-1" id="priority_statement" name="priority_statement"
               value="<?= h($f('priority_statement')) ?>"
               placeholder="Collect the $8,300 in aged receivables and install a written follow-up routine">
      </div>
      <div>
        <label class="label" for="priority_layer">Layer</label>
        <?= layerSelect('priority_layer', (string) $f('priority_layer'), 'field mt-1') ?>
      </div>
    </div>
    <div>
      <label class="label" for="priority_why">Why this one and not the others</label>
      <textarea class="field mt-1" id="priority_why" name="priority_why" rows="3"><?= h($f('priority_why')) ?></textarea>
    </div>
    <div class="max-w-xs">
      <label class="label" for="priority_gap">Is the gap informational or behavioral?</label>
      <select class="field mt-1" id="priority_gap" name="priority_gap">
        <option value="">—</option>
        <option value="informational"<?= $f('priority_gap') === 'informational' ? ' selected' : '' ?>>Informational — the owner needs to know</option>
        <option value="behavioral"<?= $f('priority_gap') === 'behavioral' ? ' selected' : '' ?>>Behavioral — the owner knows and avoids</option>
      </select>
      <?php if ($f('priority_gap') === 'behavioral'): ?>
        <p class="hint">Make the next action small, specific, dated, and measurable. Plan for avoidance rather than assuming more information will help.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- 5. Committed next action ------------------------------------------------- -->
<section class="card mt-6 p-5">
  <?= sectionHead('Step 5', 'Committed next action', "In the owner's words, with the owner's date. One homework assignment. Only one.") ?>

  <div class="grid gap-4 lg:grid-cols-2">
    <div class="lg:col-span-2">
      <label class="label" for="action_what">What the owner will do</label>
      <input class="field mt-1" id="action_what" name="action_what" value="<?= h($f('action_what')) ?>"
             placeholder="Contact the five oldest accounts by Friday">
    </div>
    <div>
      <label class="label" for="action_by_when">By when</label>
      <input class="field mt-1" type="date" id="action_by_when" name="action_by_when" value="<?= h($f('action_by_when')) ?>">
    </div>
    <div>
      <label class="label" for="action_done_looks">What "done" looks like</label>
      <input class="field mt-1" id="action_done_looks" name="action_done_looks" value="<?= h($f('action_done_looks')) ?>">
    </div>
    <div>
      <label class="label" for="action_number">The number to watch</label>
      <input class="field mt-1" id="action_number" name="action_number" value="<?= h($f('action_number')) ?>"
             placeholder="Under $3,000 past 60 days within a month">
    </div>
    <div>
      <label class="label" for="action_followup">Follow-up meeting date</label>
      <input class="field mt-1" type="date" id="action_followup" name="action_followup" value="<?= h($f('action_followup')) ?>">
    </div>
    <div class="lg:col-span-2">
      <label class="label" for="action_referral">Referral needed</label>
      <input class="field mt-1" id="action_referral" name="action_referral" value="<?= h($f('action_referral')) ?>"
             placeholder="CPA or EA, attorney, agency, clinician — and how to frame the question">
    </div>
  </div>
</section>

<!-- 6. Progress record ------------------------------------------------------- -->
<section class="card mt-6 p-5">
  <?= sectionHead('Step 6', 'Progress record', 'What turns advisor activity into outcomes that can be reported to a funder.') ?>

  <label class="flex items-center gap-2 text-sm font-medium">
    <input type="checkbox" name="log_progress" value="1" class="h-4 w-4 rounded border-hairline text-primary focus:ring-primary/30">
    Add an entry to this client's progress record when I save
  </label>

  <div class="mt-3 grid gap-3 sm:grid-cols-4">
    <div>
      <label class="label" for="progress_date">Date</label>
      <input class="field mt-1" type="date" id="progress_date" name="progress_date" value="<?= h(date('Y-m-d')) ?>">
    </div>
    <div class="sm:col-span-2">
      <label class="label" for="progress_what_changed">What changed</label>
      <input class="field mt-1" id="progress_what_changed" name="progress_what_changed">
    </div>
    <div>
      <label class="label" for="progress_next">Next instrument</label>
      <select class="field mt-1" id="progress_next" name="progress_next">
        <option value="">—</option>
        <?php foreach (instruments() as $k => $m): ?>
          <option value="<?= h($k) ?>"><?= h($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</section>

<?= renderSaveBar($assessment['status'], 'Blank rows are discarded on save.') ?>
</form>
<?php renderFooter();
