<?php
/** A health score with its factors. Never rendered as a bare number.
 * @var array $health @var bool $compact */
$compact = $compact ?? false;
$band = $health['band'];
$tone = match ($band) {
  'Healthy'  => 'bg-emerald-50 border-emerald-200 text-emerald-900',
  'Steady'   => 'bg-sky-50 border-sky-200 text-sky-900',
  'Drifting' => 'bg-amber-50 border-amber-200 text-amber-900',
  'At risk'  => 'bg-red-50 border-red-200 text-red-900',
  default    => 'bg-slate-50 border-slate-200 text-slate-700',
};
?>
<div class="rounded-lg border <?= $tone ?> p-4">
  <div class="flex items-baseline justify-between gap-3">
    <div class="text-sm font-semibold">
      <?= $health['score'] === null ? 'Not enough to go on' : h($band) ?>
    </div>
    <?php if ($health['score'] !== null): ?>
      <div class="text-2xl font-semibold tabular-nums"><?= (int) round((float) $health['score']) ?></div>
    <?php endif; ?>
  </div>
  <p class="text-xs mt-1"><?= h($health['note']) ?></p>
</div>

<?php if (!$compact): ?>
  <div class="mt-4 rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
    <?php foreach ($health['factors'] as $f): ?>
      <div class="px-4 py-3">
        <div class="flex items-center justify-between gap-4">
          <div class="text-sm font-medium"><?= h($f['label']) ?></div>
          <div class="text-sm tabular-nums <?= $f['counted'] ? '' : 'text-slate-400' ?>">
            <?= $f['counted'] ? (int) $f['percent'] . '%' : 'not measured' ?>
          </div>
        </div>
        <?php if ($f['counted']): ?>
          <div class="mt-1.5 h-1.5 w-full rounded bg-slate-100">
            <div class="h-1.5 rounded bg-slate-800" style="width: <?= (int) $f['percent'] ?>%"></div>
          </div>
        <?php endif; ?>
        <p class="text-xs text-slate-500 mt-1.5"><?= h($f['detail']) ?></p>
        <?php if (!$f['counted']): ?>
          <p class="text-xs text-slate-400 mt-0.5">Left out of the score — no evidence is not bad evidence.</p>
        <?php else: ?>
          <p class="text-xs text-slate-400 mt-0.5">Weight <?= h((string) $f['weight']) ?>.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
