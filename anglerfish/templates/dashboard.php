<?php
$tiles = [
  ['Books',      $counts['books']      ?? 0, ($counts['books_press'] ?? 0) . ' in press scope'],
  ['Chapters',   $counts['chapters']   ?? 0, ''],
  ['Concepts',   $counts['concepts']   ?? 0, ''],
  ['Artifacts',  $counts['artifacts']  ?? 0, ($counts['flagged'] ?? 0) . ' flagged for review'],
  ['Assets',     $counts['assets']     ?? 0, 'ready'],
  ['Posts',      $counts['posts']      ?? 0, ($counts['published'] ?? 0) . ' published'],
];
?>
<h1 class="text-2xl font-bold text-navy">Dashboard</h1>
<div class="mt-1 h-[3px] w-32 bg-gradient-to-r from-gold to-navy"></div>

<div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($tiles as [$label, $n, $note]): ?>
  <div class="rounded border border-black/10 bg-white p-5">
    <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold"><?= h($label) ?></p>
    <p class="mt-1 text-4xl font-bold text-navy"><?= number_format((int) $n) ?></p>
    <?php if ($note !== ''): ?><p class="mt-1 text-xs text-muted"><?= h($note) ?></p><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<div class="mt-8 grid gap-4 sm:grid-cols-3">
  <div class="rounded border border-black/10 bg-white p-5">
    <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Write queue</p>
    <p class="mt-1 text-3xl font-bold text-navy"><?= number_format((int) ($counts['queue'] ?? 0)) ?></p>
    <p class="mt-1 text-xs text-muted">marked “Write About This”</p>
  </div>
  <div class="rounded border border-black/10 bg-white p-5">
    <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Jobs</p>
    <p class="mt-1 text-3xl font-bold text-navy"><?= number_format((int) ($counts['jobs_open'] ?? 0)) ?></p>
    <p class="mt-1 text-xs text-muted">
      open<?= ($counts['jobs_dead'] ?? 0) ? ' · ' . (int) $counts['jobs_dead'] . ' dead' : '' ?>
    </p>
  </div>
  <div class="rounded border border-black/10 bg-white p-5">
    <p class="text-[11px] font-bold uppercase tracking-[2px] text-gold">Spend this month</p>
    <p class="mt-1 text-3xl font-bold text-navy">$<?= number_format($spend, 2) ?></p>
    <p class="mt-1 text-xs text-muted">Gemini image generation</p>
  </div>
</div>

<?php if (($counts['intake'] ?? 0) > 0): ?>
<div class="mt-8 rounded border-l-4 border-gold bg-cream p-4 text-sm">
  <strong class="text-navy"><?= (int) $counts['intake'] ?></strong>
  book(s) awaiting scope confirmation in intake.
</div>
<?php endif; ?>

<p class="mt-10 text-xs text-muted">
  Phase 1 · schema live, ingestion worker next. See <code>SPEC.md</code> §16.
</p>
