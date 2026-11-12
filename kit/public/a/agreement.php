<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'agreement';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);
$cid = (int) $client['id'];

/** Section B: the client-facing terms, in the order they print. */
const AGREEMENT_TERMS = [
    'included'     => ['What is included', 'Pumping, a written inspection report, and disposal at standard volume'],
    'excluded'     => ['What is not included, and what it costs', 'Locating a buried lid: billed by the hour. Repairs: quoted separately'],
    'hours'        => ['Business hours', 'Monday to Friday, 8 to 5'],
    'urgent'       => ['What counts as urgent', 'Sewage backing up into the house'],
    'after_hours'  => ['After-hours policy and fee', 'Urgent calls only, with a call-out fee'],
    'channel'      => ['How clients reach the business (one channel)', 'The business phone line; texts answered the next business day'],
    'cancellation' => ['Cancellation and rescheduling', '24 hours\' notice'],
    'payment'      => ['Payment terms (from the Collections Calendar)', 'Due within 15 days of the invoice; work pauses at day 30'],
];
const AGREEMENT_NUMS  = ['a1_ceiling', 'a2_non_delivery', 'a4_hours_per_job', 'current_jobs', 'current_delivery_hours'];
const AGREEMENT_TEXTS = ['scope_script', 'behavior_line'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $save = postNums(AGREEMENT_NUMS) + postTexts(AGREEMENT_TEXTS);
    foreach (array_keys(AGREEMENT_TERMS) as $k) $save['t_' . $k] = post('t_' . $k);
    worksheetSave($client, $assessment, KEY, $save);
}

// A fresh run takes the ceiling and the non-delivery hours from the Capacity Audit.
$notes = [];
if (isFreshRun($data)) {
    $cap = latestData($cid, 'capacity');
    if ($cap) {
        $nonDelivery = 0.0;
        foreach (jrows($cap, 'rows') as $r) {
            if (in_array($r['type'] ?? 'D', ['S', 'R', 'F'], true)) $nonDelivery += num($r['hours'] ?? 0);
        }
        $data = prefill($data, [
            'a1_ceiling'      => ['the weekly ceiling', num($cap['ceiling'] ?? 0)],
            'a2_non_delivery' => ['non-delivery hours (types S, R, and F)', $nonDelivery],
        ], 'Capacity Audit', $notes);
    }
}

$x = calcCeiling($data);
$t = fn(string $k) => trim((string) ($data['t_' . $k] ?? ''));

// ---- Printable client-facing draft --------------------------------------
if (($_GET['print'] ?? '') === '1') {
    $pageTitle = 'Service Agreement draft';
    renderHeader(compact('pageTitle', 'client'));
    ?>
    <div class="no-print mb-6 flex flex-wrap items-center gap-3">
      <a class="btn-quiet" href="<?= h(url('/a/agreement.php?client=' . $cid . '&a=' . (int) $assessment['id'])) ?>">← Back to the worksheet</a>
      <span class="text-sm text-muted">Use your browser's Print command.</span>
    </div>
    <article class="mx-auto max-w-readable">
      <p class="rounded border-2 border-bad bg-bad-soft px-3 py-2 text-center text-sm font-semibold uppercase tracking-wide text-bad">
        Draft for attorney review. Not for use with clients until an attorney has reviewed it.
      </p>
      <h1 class="mt-6 text-2xl font-semibold"><?= h($client['business_name']) ?>: How we work together</h1>
      <p class="hint">Business terms drafted with an advisor. An attorney reviews the agreement before it is used.</p>
      <dl class="mt-6 space-y-4 text-sm">
        <?php foreach (AGREEMENT_TERMS as $k => [$label, $ph]): if ($t($k) === '') continue; ?>
          <div><dt class="font-semibold"><?= h($label) ?></dt><dd class="mt-1 whitespace-pre-line"><?= h($t($k)) ?></dd></div>
        <?php endforeach; ?>
        <?php if ($x['a5'] > 0): ?>
          <div><dt class="font-semibold">How many jobs we take</dt>
            <dd class="mt-1">We schedule up to <?= (int) $x['a5'] ?> jobs a week so every job gets done properly. When a week is full, we offer the next open slot.</dd></div>
        <?php endif; ?>
      </dl>
      <div class="mt-10 grid gap-8 text-sm sm:grid-cols-2">
        <div class="border-t border-ink pt-1">Client signature and date</div>
        <div class="border-t border-ink pt-1">Business signature and date</div>
      </div>
    </article>
    <?php
    renderFooter();
    exit;
}

$pageTitle = 'Service Agreement';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Service Agreement</h1>
  <p class="hint">
    Bounds the work in writing before it breaks the owner: scope, response windows, and a
    client-volume ceiling set in advance. Pairs with the Capacity Audit. The advisor drafts the
    business terms with the owner; <strong>an attorney reviews the agreement</strong>.
  </p>
</div>

<?= renderPrefillNotes($notes) ?>
<?= renderPeriodField($assessment, 'e.g. agreement drafted Nov 2026') ?>

<section class="card p-5">
  <?= sectionHead('Section A', 'The ceiling', 'How many jobs a week fit inside the owner\'s sustainable hours, after the work that isn\'t delivery.') ?>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?= numField($data, 'a1_ceiling', 'A1 · Sustainable weekly hours', 'The Capacity Audit ceiling. The owner\'s number.') ?>
    <?= numField($data, 'a2_non_delivery', 'A2 · Fixed non-delivery hours per week', 'Selling, running the business, fixing.') ?>
    <?= numField($data, 'a4_hours_per_job', 'A4 · Average hours per job or session', 'Including travel and setup.') ?>
    <?= numField($data, 'current_jobs', 'Current jobs per week', 'Or leave blank and enter delivery hours.') ?>
    <?= numField($data, 'current_delivery_hours', 'Current delivery hours per week', 'Used only when current jobs is blank.') ?>
  </div>
  <?= renderReadingPanel($x, 'Starting points: Act when current jobs are over the ceiling (A5). Watch within 10% of it. Healthy below that.') ?>
</section>

<section class="card mt-6 p-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <?= sectionHead('Section B', 'The terms, in plain language', 'These print as a one-page client-facing draft with an attorney-review banner.') ?>
    <a class="btn-secondary no-print" href="<?= h(url('/a/agreement.php?client=' . $cid . '&a=' . (int) $assessment['id'] . '&print=1')) ?>">Printable draft</a>
  </div>
  <div class="grid gap-4 lg:grid-cols-2">
    <?php foreach (AGREEMENT_TERMS as $k => [$label, $ph]): ?>
      <?= areaField($data, 't_' . $k, $label, $ph, 2) ?>
    <?php endforeach; ?>
  </div>
  <p class="mt-3 rounded border border-warn/30 bg-warn-soft px-3 py-2 text-sm text-warn">
    <strong>Referral boundary.</strong> Contracts and service agreements go to an attorney for review before use.
  </p>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section C', 'What the owner says', 'When a client asks for something outside scope. Word for word, so it doesn\'t get improvised under pressure.') ?>
  <?= areaField($data, 'scope_script', 'The script', 'That\'s outside what the visit covers. I can quote it separately, or book it as its own job next week.', 3) ?>
</section>

<?= renderBehavioralSection($data, 'They\'ll think I don\'t care.', 'Delegation Decision Matrix (Unit 3.3).') ?>

<?= renderSaveBar($assessment['status']) ?>
</form>
<?php renderFooter();
