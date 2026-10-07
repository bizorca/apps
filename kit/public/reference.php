<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once KIT_ROOT . '/includes/bootstrap.php';
require_once KIT_ROOT . '/includes/reference.php';
require_once KIT_ROOT . '/templates/partials.php';

requireAuth();

$tab  = $_GET['t'] ?? 'boundary';
$tabs = [
    'boundary' => 'Referral boundary',
    'wa'       => 'Washington landscape',
    'metrics'  => 'Ratios and metrics',
    'glossary' => 'Glossary',
];
if (!isset($tabs[$tab])) $tab = 'boundary';

$pageTitle = 'Reference';
renderHeader(compact('pageTitle'));
?>
<h1 class="text-2xl font-semibold">Reference</h1>
<p class="hint max-w-readable">
  Orientation, not rules. Nothing on these pages is a current-fact citation, and nothing here
  is advice. Verify every regulatory and tax fact at the source.
</p>

<nav class="mt-5 flex flex-wrap gap-1 border-b border-hairline">
  <?php foreach ($tabs as $k => $label): $on = $tab === $k; ?>
    <a href="<?= h(url('/reference.php?t=' . $k)) ?>"
       class="-mb-px border-b-2 px-3 py-2 text-sm <?= $on ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">
      <?= h($label) ?>
    </a>
  <?php endforeach; ?>
</nav>

<div class="mt-6">
<?php if ($tab === 'boundary'): ?>
  <div class="max-w-readable">
    <p>
      The most valuable move an advisor makes is often getting a client to the right
      professional in time — getting them to a CPA before year-end, not after. Name the issue,
      explain why it matters in plain terms, and refer.
    </p>
    <p class="mt-3 rounded-md border border-bad/30 bg-bad-soft px-4 py-3 text-sm text-bad">
      When a question crosses the boundary, say so plainly: <em>"This is a question for a CPA or
      EA. Here's how to frame it for them."</em>
    </p>
  </div>

  <ul class="mt-6 grid gap-3 md:grid-cols-2">
    <?php foreach (referralBoundary() as $r): ?>
      <li class="card p-4">
        <h2 class="font-semibold"><?= h($r['who']) ?></h2>
        <p class="mt-1 text-sm text-muted"><?= h($r['when']) ?></p>
      </li>
    <?php endforeach; ?>
  </ul>

  <h2 class="mt-8 text-lg font-semibold">Concepts to name, not to advise on</h2>
  <p class="hint">The Tax Exposure Flag List. Run it against a client from the worksheet.</p>
  <dl class="mt-3 space-y-3">
    <?php foreach (taxFlags() as $flag): ?>
      <div class="card p-4">
        <dt class="font-medium"><?= h($flag['name']) ?></dt>
        <dd class="mt-1 text-sm text-muted"><?= h($flag['plain']) ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>

<?php elseif ($tab === 'wa'): ?>
  <?= verifyNotice('This section gives orientation, not rules.') ?>
  <dl class="mt-4 space-y-3">
    <?php foreach (waLandscape() as $w): ?>
      <div class="card p-4">
        <dt class="font-medium"><?= h($w['name']) ?></dt>
        <dd class="mt-1 text-sm text-muted"><?= h($w['text']) ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>
  <p class="mt-6 max-w-readable text-sm text-muted">
    When helping with a Washington question, produce a checklist of what to verify and where —
    not an answer.
  </p>

<?php elseif ($tab === 'metrics'): ?>
  <p class="max-w-readable">
    Diagnostic signals, not verdicts. Micro-business benchmarks vary widely by industry and
    season; the thresholds are starting points. Always show the formula and the inputs.
  </p>
  <?php foreach (metricDefinitions() as $group => $rows): ?>
    <section class="mt-6">
      <h2 class="font-semibold"><?= h($group) ?></h2>
      <div class="mt-2 overflow-x-auto">
        <table class="w-full min-w-[42rem] text-sm">
          <thead>
            <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-muted">
              <th class="py-2 pr-4 font-semibold">Metric</th>
              <th class="py-2 pr-4 font-semibold">Formula</th>
              <th class="py-2 font-semibold">Notes</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline">
            <?php foreach ($rows as $m): ?>
              <tr>
                <td class="py-2 pr-4 font-medium"><?= h($m['name']) ?></td>
                <td class="py-2 pr-4 font-mono text-xs"><?= h($m['formula']) ?></td>
                <td class="py-2 text-muted"><?= h($m['note']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endforeach; ?>
  <p class="mt-6 max-w-readable rounded border border-hairline bg-sunk px-4 py-3 text-sm">
    <strong>Normalize owner pay before judging profit.</strong> A business showing $85,000 in net
    profit is not profitable in any useful sense until you compare that against what the owner
    needs to live on and what their labor would cost at market. Always ask: profitable
    <em>after</em> paying the owner a baseline wage?
  </p>

<?php else: ?>
  <dl class="grid gap-3 md:grid-cols-2">
    <?php foreach (glossary() as $term => $def): ?>
      <div class="card p-4">
        <dt class="font-medium"><?= h($term) ?></dt>
        <dd class="mt-1 text-sm text-muted"><?= h($def) ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>
<?php endif; ?>
</div>
<?php renderFooter();
