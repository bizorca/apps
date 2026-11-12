<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'offer';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);
$cid = (int) $client['id'];

const OFFER_TIER_DEFAULTS = ['Diagnostic', 'Intensive', 'Retainer'];
const OFFER_TEXTS = ['awareness_evidence', 'top_client_name', 'decline_list', 'decline_script', 'behavior_line'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $save = postTexts(OFFER_TEXTS) + postNums(['floor_rate', 'revenue_annual', 'top_client_revenue']);
    $save['awareness'] = in_array(post('awareness'), ['yes', 'no'], true) ? post('awareness') : '';

    $save['clients'] = [];
    foreach (postArray('cl_client') as $i => $name) {
        $name = trim((string) $name);
        if ($name === '') continue;
        $row = ['client' => $name, 'urgency' => trim((string) (postArray('cl_urgency')[$i] ?? ''))];
        foreach (array_keys(FIT_CRITERIA) as $k) $row[$k] = postPickRow('cl_' . $k, (int) $i, ['1', '2', '3'], '1');
        $save['clients'][] = $row;
    }
    $save['tiers'] = collectRows([
        'name' => 'tier_name', 'delivered' => 'tier_delivered', 'hours' => 'tier_hours',
        'price' => 'tier_price', 'qualifies' => 'tier_qualifies',
    ], fn($r) => $r['name'] === '' && $r['price'] === '' && $r['delivered'] === '');
    foreach ($save['tiers'] as &$tr) { $tr['hours'] = num($tr['hours']); $tr['price'] = num($tr['price']); }
    unset($tr);
    worksheetSave($client, $assessment, KEY, $save);
}

$notes = [];
if (isFreshRun($data)) {
    $ehr = latestData($cid, 'ehr');
    if ($ehr) {
        $e = calcEHR($ehr);
        $data = prefill($data, [
            'floor_rate'     => ['the floor rate (D4)', round($e['d4'], 2)],
            'revenue_annual' => ['annual revenue (A1)', $e['a1']],
        ], 'EHR worksheet', $notes);
    }
}

$x = calcOffer($data);
$clients = jrows($data, 'clients');
for ($i = 0; $i < 3; $i++) $clients[] = ['client' => ''];
$tiers = jrows($data, 'tiers');
while (count($tiers) < 3) $tiers[] = ['name' => '', 'delivered' => '', 'hours' => '', 'price' => '', 'qualifies' => ''];
$tierCalc = [];
foreach ($x['tiers'] as $tc) $tierCalc[$tc['name']] = $tc;

$pageTitle = 'The Offer, the Client, and the Channel';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">The Offer, the Client, and the Channel</h1>
  <p class="hint">
    Most marketing problems are offer problems. Settle how buyers find the business, who it serves
    best, and a few standard tiers priced at or above the floor, before anyone spends a dollar on
    marketing.
  </p>
</div>

<?= renderPrefillNotes($notes) ?>
<?= renderPeriodField($assessment, 'e.g. offer review, Nov 2026') ?>

<section class="card p-5">
  <?= sectionHead('Section A', 'The Awareness Question', 'Does the buyer know they have this problem before they meet you?') ?>
  <div class="flex flex-wrap gap-4 text-sm">
    <?php foreach (['yes' => 'Yes: they are already looking', 'no' => 'No: they don\'t recognize it yet'] as $v => $l): ?>
      <label class="flex items-center gap-2"><input type="radio" name="awareness" value="<?= h($v) ?>"<?= ($data['awareness'] ?? '') === $v ? ' checked' : '' ?>> <?= h($l) ?></label>
    <?php endforeach; ?>
  </div>
  <?php if ($x['channel'] !== ''): ?>
    <p class="mt-3 text-sm"><strong>Channel:</strong> <?= h($x['channel']) ?></p>
  <?php endif; ?>
  <div class="mt-4"><?= areaField($data, 'awareness_evidence', 'The evidence: how the last five clients found the business', '', 3) ?></div>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section B', 'Client alignment', 'Score current top clients 1 to 3 on each fit criterion. Urgency is noted, never scored as fit.') ?>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[56rem] text-sm">
      <thead>
        <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
          <th class="pb-2 pr-3 font-semibold">Client</th>
          <?php foreach (FIT_CRITERIA as $k => $label): ?><th class="pb-2 pr-3 font-semibold"><?= h($label) ?></th><?php endforeach; ?>
          <th class="pb-2 pr-3 font-semibold">Urgency (note only)</th>
          <th class="pb-2 font-semibold">Fit</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <?php $ci = 0; foreach ($clients as $i => $c):
          $scored = (trim((string) ($c['client'] ?? '')) !== '') ? ($x['clients'][$ci++] ?? null) : null; ?>
          <tr>
            <td class="py-2 pr-3"><input class="field" name="cl_client[<?= $i ?>]" value="<?= h($c['client'] ?? '') ?>"></td>
            <?php foreach (array_keys(FIT_CRITERIA) as $k): ?>
              <td class="py-2 pr-3 w-24">
                <select class="field" name="cl_<?= h($k) ?>[<?= $i ?>]">
                  <?php foreach (['1', '2', '3'] as $sv): ?><option<?= (string) ($c[$k] ?? '1') === $sv ? ' selected' : '' ?>><?= $sv ?></option><?php endforeach; ?>
                </select>
              </td>
            <?php endforeach; ?>
            <td class="py-2 pr-3"><input class="field" name="cl_urgency[<?= $i ?>]" value="<?= h($c['urgency'] ?? '') ?>"></td>
            <td class="py-2 whitespace-nowrap"><?= $scored ? h($scored['band'] . ' (' . $scored['score'] . ')') : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="mt-2 text-xs text-muted">Starting points: Ideal 10–12, Acceptable 7–9, Poor 4–6.</p>

  <h3 class="mt-5 text-sm font-semibold">Customer concentration</h3>
  <div class="mt-2 grid gap-4 sm:grid-cols-3">
    <?= textField($data, 'top_client_name', 'Top client') ?>
    <?= numField($data, 'top_client_revenue', 'Top client revenue, last 12 months') ?>
    <?= numField($data, 'revenue_annual', 'Total revenue, last 12 months') ?>
  </div>
  <?php if ($x['conc_reading'] !== READ_UNKNOWN): ?>
    <p class="mt-3 flex items-center gap-3 text-sm"><?= renderReading($x['conc_reading']) ?> Top client is <?= h(pct($x['conc'])) ?> of revenue.</p>
  <?php endif; ?>
  <p class="mt-2 text-xs text-muted">Starting points, from the framework's "above roughly 25–30% is a dependency risk": Healthy under 25%. Watch 25% to 30%. Act over 30%.</p>
</section>

<section class="card mt-6 p-5">
  <?= sectionHead('Section C', 'Fixed-scope offers', 'Up to three standard tiers. Each must bring in at least the floor rate per hour of delivery.') ?>
  <div class="max-w-xs"><?= numField($data, 'floor_rate', 'Floor rate (EHR D4)') ?></div>
  <div class="mt-4 space-y-4">
    <?php foreach ($tiers as $i => $tr): $tc = $tierCalc[$tr['name'] ?? ''] ?? null; ?>
      <fieldset class="rounded-md border border-hairline p-4">
        <legend class="flex items-center gap-2 px-1 text-sm font-semibold">
          Tier <?= $i + 1 ?> <?= $tc ? renderReading($tc['reading'], $tc['rate'] > 0 ? money($tc['rate']) . '/hr' : '') : '' ?>
        </legend>
        <div class="grid gap-4 lg:grid-cols-4">
          <div><label class="label">Name</label><input class="field mt-1" name="tier_name[<?= $i ?>]" placeholder="<?= h(OFFER_TIER_DEFAULTS[$i] ?? '') ?>" value="<?= h($tr['name'] ?? '') ?>"></div>
          <div class="lg:col-span-3"><label class="label">What's delivered</label><input class="field mt-1" name="tier_delivered[<?= $i ?>]" value="<?= h($tr['delivered'] ?? '') ?>"></div>
          <div><label class="label">Hours to deliver</label><input class="field amount mt-1" name="tier_hours[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($tr['hours'] ?? '')) ?>"></div>
          <div><label class="label">Price</label><input class="field amount mt-1" name="tier_price[<?= $i ?>]" inputmode="decimal" value="<?= h((string) ($tr['price'] ?? '')) ?>"></div>
          <div class="lg:col-span-2"><label class="label">Who qualifies</label><input class="field mt-1" name="tier_qualifies[<?= $i ?>]" value="<?= h($tr['qualifies'] ?? '') ?>"></div>
        </div>
      </fieldset>
    <?php endforeach; ?>
  </div>
  <?= renderSteps($x['steps']) ?>
  <p class="mt-2 text-xs text-muted">Healthy: price ÷ hours to deliver is at or above the floor. Act: below it.</p>

  <h3 class="mt-5 text-sm font-semibold">The decline list</h3>
  <div class="mt-2 grid gap-4 lg:grid-cols-2">
    <?= areaField($data, 'decline_list', 'Work the business no longer quotes custom', '', 3) ?>
    <?= areaField($data, 'decline_script', 'What the owner says', '', 3) ?>
  </div>
</section>

<?= renderBehavioralSection($data, 'If I stop custom-quoting, I\'ll lose the jobs that pay the bills.', ($data['awareness'] ?? '') === 'no' ? 'Micro-Workshop Framework (Unit 4.2).' : (($data['awareness'] ?? '') === 'yes' ? 'Three-Source Referral System (Unit 4.3).' : 'Micro-Workshop Framework (4.2) if buyers don\'t know they have the problem; Three-Source Referral System (4.3) if they do.')) ?>

<?= renderSaveBar($assessment['status'], 'Blank client and tier rows are discarded on save.') ?>
</form>
<?php renderFooter();
