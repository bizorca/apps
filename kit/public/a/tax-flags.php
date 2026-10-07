<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/worksheet.php';
require_once KIT_ROOT . '/includes/reference.php';

const KEY = 'tax_flags';
[$user, $client, $assessment, $data] = worksheetBoot(KEY);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $flags = [];
    foreach (taxFlags() as $flag) {
        $k = $flag['key'];
        $flags[$k] = [
            'key'      => $k,
            'flagged'  => !empty($_POST['flagged'][$k]),
            'note'     => trim((string) ($_POST['note'][$k] ?? '')),
            'referred' => !empty($_POST['referred'][$k]),
        ];
    }
    worksheetSave($client, $assessment, KEY, [
        'flags'        => $flags,
        'framing'      => post('framing'),
        'referred_to'  => post('referred_to'),
    ]);
}

$flags   = jget($data, 'flags', []);
$get     = fn(string $k, string $f) => $flags[$k][$f] ?? '';
$flagged = array_filter($flags, fn($f) => !empty($f['flagged']));

$pageTitle = 'Tax Exposure Flag List';
renderHeader(compact('pageTitle', 'client'));
echo renderRunBar($client, $assessment, KEY);
?>
<form method="post">
<?= csrf() ?>

<div class="mb-6 max-w-readable">
  <h1 class="text-2xl font-semibold">Tax Exposure Flag List</h1>
  <p class="hint">
    Concepts an advisor should be able to <strong>name</strong>, and must not advise on. Flag
    what applies, write down how you will frame the question, and hand it to a CPA or EA. The
    most valuable move an advisor makes is often getting a client to the right professional
    in time — to a CPA before year-end, not after.
  </p>
  <div class="mt-3 rounded-md border border-bad/30 bg-bad-soft px-4 py-3 text-sm text-bad">
    <strong>Nothing on this page is advice, and nothing here should be given as advice.</strong>
    Never estimate a saving, recommend an election, or state a rate, threshold, or deadline as
    current fact. When a question crosses the boundary, say so plainly: "This is a question for
    a CPA or EA. Here's how to frame it for them."
  </div>
</div>

<?php if ($flagged): ?>
  <div class="mb-6 rounded-lg border border-warn bg-warn-soft p-4">
    <p class="text-sm font-semibold text-warn"><?= count($flagged) ?> item<?= count($flagged) === 1 ? '' : 's' ?> flagged for referral</p>
  </div>
<?php endif; ?>

<section class="card p-5">
  <ul class="divide-y divide-hairline">
    <?php foreach (taxFlags() as $flag): $k = $flag['key']; $on = !empty($get($k, 'flagged')); ?>
      <li class="py-4 first:pt-0 last:pb-0">
        <div class="grid gap-3 lg:grid-cols-12">
          <div class="lg:col-span-7">
            <label class="flex items-start gap-3">
              <input type="checkbox" name="flagged[<?= h($k) ?>]" value="1" class="mt-1 h-4 w-4 rounded border-hairline text-primary focus:ring-primary/30"<?= $on ? ' checked' : '' ?>>
              <span>
                <span class="font-medium text-ink"><?= h($flag['name']) ?></span>
                <span class="hint block"><?= h($flag['plain']) ?></span>
              </span>
            </label>
          </div>
          <div class="lg:col-span-5">
            <label class="sr-only" for="note_<?= h($k) ?>">Note</label>
            <textarea class="field text-sm" id="note_<?= h($k) ?>" name="note[<?= h($k) ?>]" rows="2"
                      placeholder="What you observed, and how to frame the question"><?= h($get($k, 'note')) ?></textarea>
            <label class="mt-2 flex items-center gap-2 text-sm text-muted">
              <input type="checkbox" name="referred[<?= h($k) ?>]" value="1" class="h-4 w-4 rounded border-hairline text-primary focus:ring-primary/30"<?= !empty($get($k, 'referred')) ? ' checked' : '' ?>>
              Referred
            </label>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
  <section class="card p-5">
    <?= sectionHead('', 'How to frame it for the professional', 'Write the question the way the CPA, EA, or attorney needs to hear it.') ?>
    <textarea class="field" name="framing" rows="5"
              placeholder="Sole proprietor, roughly $85k net, one part-time W-2 helper. No estimated payments made this year. Questions: what should quarterly payments be, and is there anything to do before year-end?"><?= h(jget($data, 'framing')) ?></textarea>
  </section>

  <section class="card p-5">
    <?= sectionHead('', 'Referred to', '') ?>
    <input class="field" name="referred_to" value="<?= h(jget($data, 'referred_to')) ?>"
           placeholder="Name of the CPA, EA, or attorney, and the date">

    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-muted">The referral boundary</h3>
    <dl class="mt-2 space-y-2 text-sm">
      <?php foreach (referralBoundary() as $r): ?>
        <div>
          <dt class="font-medium text-ink"><?= h($r['who']) ?></dt>
          <dd class="text-muted"><?= h($r['when']) ?></dd>
        </div>
      <?php endforeach; ?>
    </dl>
  </section>
</div>

<?= renderSaveBar($assessment['status']) ?>
</form>
<?php renderFooter();
