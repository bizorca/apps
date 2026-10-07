<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';

const KEY = 'swot';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $save = [];
    foreach (array_keys(SWOT_QUADRANTS) as $q) {
        $save[$q] = collectRows([
            'entry'    => $q . '_entry',
            'evidence' => $q . '_evidence',
            'layer'    => $q . '_layer',
        ], fn($r) => trim($r['entry']) === '');
    }
    foreach (array_keys(TOWS_PAIRS) as $p) {
        $save['tows_' . $p] = post('tows_' . $p);
    }
    worksheetSave($client, $assessment, KEY, $save);
}

$s = calcSwot($data);

/** Render one quadrant's rows plus three blanks to keep typing. */
function swotRows(array $data, string $q, int $blanks = 3): array
{
    $rows = array_values(array_filter(jrows($data, $q), fn($r) => trim((string) ($r['entry'] ?? '')) !== ''));
    for ($i = 0; $i < $blanks; $i++) $rows[] = ['entry' => '', 'evidence' => '', 'layer' => ''];
    return $rows;
}

$pageTitle = 'SWOT and TOWS';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">SWOT with the evidence rule, and TOWS</h1>
  <p class="hint">
    A familiar, low-pressure opener that aims the sharper tools. It is a conversation tool,
    not a deliverable.
  </p>
  <div class="mt-3 rounded-md border border-primary/30 bg-primary-soft px-4 py-3 text-sm">
    <strong class="text-primary">The evidence rule.</strong>
    Every entry needs a number, an example, or a name. If the owner cannot supply one,
    strike the entry. "Great customer service" becomes "40% of last year's jobs came from
    repeat customers." Watch for aspirations listed as strengths ("we work hard") and
    anxieties listed as threats ("the economy", "competition").
  </div>
</div>

<?php if ($s['unevidenced']): ?>
  <div class="mb-6 rounded-lg border border-warn bg-warn-soft p-4">
    <p class="text-sm font-semibold text-warn">
      <?= count($s['unevidenced']) ?> entr<?= count($s['unevidenced']) === 1 ? 'y has' : 'ies have' ?> no evidence
    </p>
    <ul class="mt-2 list-inside list-disc text-sm text-warn">
      <?php foreach ($s['unevidenced'] as $u): ?>
        <li><?= h(SWOT_QUADRANTS[$u['quadrant']]) ?>: &ldquo;<?= h($u['entry']) ?>&rdquo;</li>
      <?php endforeach; ?>
    </ul>
    <p class="mt-2 text-sm text-warn">Get a number, an example, or a name — or strike it.</p>
  </div>
<?php endif; ?>

<div class="grid gap-4 lg:grid-cols-2">
  <?php foreach (SWOT_QUADRANTS as $q => $label): ?>
    <section class="card p-5">
      <div class="flex items-baseline justify-between">
        <h2 class="font-semibold"><?= h($label) ?></h2>
        <span class="text-xs text-muted"><?= (int) ($s['counts'][$q] ?? 0) ?> entries</span>
      </div>
      <?php if ($q === 'weaknesses'): ?>
        <p class="hint">Usually cluster in the financial and operator layers.</p>
      <?php elseif ($q === 'threats'): ?>
        <p class="hint">Often hide in the regulatory layer.</p>
      <?php endif; ?>

      <div class="mt-3 space-y-3">
        <?php foreach (swotRows($data, $q) as $i => $row): ?>
          <div class="grid gap-2 sm:grid-cols-12">
            <input class="field sm:col-span-5" name="<?= h($q) ?>_entry[]" placeholder="Entry"
                   value="<?= h($row['entry'] ?? '') ?>">
            <input class="field sm:col-span-4" name="<?= h($q) ?>_evidence[]"
                   placeholder="Evidence — a number, an example, or a name"
                   value="<?= h($row['evidence'] ?? '') ?>">
            <div class="sm:col-span-3">
              <?= layerSelect($q . '_layer[]', (string) ($row['layer'] ?? ''), 'field') ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="mt-2 text-xs text-muted">Save to add more rows.</p>
    </section>
  <?php endforeach; ?>
</div>

<!-- TOWS -------------------------------------------------------------------- -->
<section class="card mt-6 p-5">
  <?= sectionHead('', 'TOWS conversion', 'Pair entries and write one concrete move per pairing. Two or three good moves beat four weak ones — leave a quadrant empty rather than force it.') ?>

  <div class="grid gap-4 lg:grid-cols-2">
    <?php foreach (TOWS_PAIRS as $p => $meta): ?>
      <div>
        <label class="label" for="tows_<?= h($p) ?>"><?= h($meta['name']) ?></label>
        <p class="hint"><?= h($meta['hint']) ?></p>
        <textarea class="field mt-1" id="tows_<?= h($p) ?>" name="tows_<?= h($p) ?>" rows="3"><?= h(jget($data, 'tows_' . $p)) ?></textarea>
      </div>
    <?php endforeach; ?>
  </div>

  <p class="mt-4 rounded border border-hairline bg-sunk px-3 py-2 text-sm text-muted">
    TOWS moves wait until the four-layer order says it is their turn. If regulatory exposure
    or a cash problem is open, the move goes on the list — it does not go first.
  </p>
</section>

<?= renderSaveBar($assessment['status'], 'Blank rows are discarded on save.') ?>
</form>
<?php renderFooter();
