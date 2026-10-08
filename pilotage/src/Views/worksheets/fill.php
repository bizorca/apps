<?php
/** @var array $assignment @var array $fields @var array $response @var array $answers
 *  @var array $missing @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
$val = static function (array $f) use ($answers): string {
    $a = $answers[(int) $f['id']] ?? null;
    if ($a === null) { return ''; }
    return (string) ($a['value_text'] ?? $a['value_number'] ?? '');
};
$anonymous = (int) $assignment['is_anonymous'] === 1;
?>
<div class="max-w-xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1"><?= h($assignment['title']) ?></h1>
  <?php if (!empty($assignment['description'])): ?>
    <p class="text-sm text-slate-600 mb-2"><?= nl2br(h((string) $assignment['description'])) ?></p>
  <?php endif; ?>

  <?php if ($anonymous): ?>
    <div class="rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 mb-4">
      <strong>This one is anonymous.</strong> Your name is not recorded against these answers —
      not hidden, not stored. <?= h($tenant['name']) ?> sees totals and averages once at least
      <?= (int) $assignment['min_responses'] ?> people have replied, and never sees written answers at all.
    </div>
  <?php endif; ?>

  <p class="text-xs text-slate-500 mb-6">
    Answers save as you go. You can close this and come back
    <?= $anonymous ? 'from this browser' : 'any time' ?>.
  </p>

  <?php if ($missing !== []): ?>
    <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
      <div class="font-medium mb-1">Still needed before this can go back:</div>
      <?php foreach ($missing as $m): ?><div><?= h($m) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= h(url('/worksheets/fill/' . $assignment['id'])) ?>" class="space-y-5">
    <?= Csrf::field() ?>

    <?php foreach ($fields as $f):
      $type = (string) $f['field_type'];
      $name = 'f[' . (int) $f['id'] . ']';
      $current = $val($f);
    ?>
      <?php if ($type === 'section'): ?>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 pt-3"><?= h($f['label']) ?></h2>

      <?php elseif ($type === 'note'): ?>
        <p class="text-sm text-slate-600"><?= nl2br(h((string) $f['label'])) ?></p>

      <?php else: ?>
        <div>
          <label class="block text-sm font-medium mb-1">
            <?= h($f['label']) ?><?php if ((int) $f['required'] === 1): ?> <span class="text-red-500">*</span><?php endif; ?>
          </label>
          <?php if (!empty($f['help'])): ?>
            <p class="text-xs text-slate-500 mb-1"><?= h((string) $f['help']) ?></p>
          <?php endif; ?>

          <?php if ($type === 'long_text'): ?>
            <textarea name="<?= h($name) ?>" rows="4" class="<?= $field ?>"><?= h($current) ?></textarea>

          <?php elseif ($type === 'scale'):
            $min = (int) ($f['scale_min'] ?? 1); $max = (int) ($f['scale_max'] ?? 10); ?>
            <div class="flex flex-wrap gap-1.5">
              <?php for ($i = $min; $i <= $max; $i++): ?>
                <label class="cursor-pointer">
                  <input type="radio" name="<?= h($name) ?>" value="<?= $i ?>" class="sr-only peer"
                         <?= $current !== '' && (int) (float) $current === $i ? 'checked' : '' ?>>
                  <span class="inline-flex h-9 w-9 items-center justify-center rounded border border-slate-300 text-sm
                               peer-checked:bg-slate-900 peer-checked:text-white peer-checked:border-slate-900"><?= $i ?></span>
                </label>
              <?php endfor; ?>
            </div>
            <?php if (!empty($f['scale_min_label']) || !empty($f['scale_max_label'])): ?>
              <div class="flex justify-between text-xs text-slate-400 mt-1">
                <span><?= h((string) ($f['scale_min_label'] ?? '')) ?></span>
                <span><?= h((string) ($f['scale_max_label'] ?? '')) ?></span>
              </div>
            <?php endif; ?>

          <?php elseif ($type === 'select_one' || $type === 'select_many'):
            $choices = array_filter(array_map('trim', explode("\n", (string) $f['options'])));
            $picked = array_filter(array_map('trim', explode("\n", $current))); ?>
            <div class="space-y-1">
              <?php foreach ($choices as $choice): ?>
                <label class="flex items-center gap-2 text-sm">
                  <input type="<?= $type === 'select_many' ? 'checkbox' : 'radio' ?>"
                         name="<?= h($name) ?><?= $type === 'select_many' ? '[]' : '' ?>"
                         value="<?= h($choice) ?>" <?= in_array($choice, $picked, true) ? 'checked' : '' ?>>
                  <?= h($choice) ?>
                </label>
              <?php endforeach; ?>
            </div>

          <?php elseif ($type === 'date'): ?>
            <input type="date" name="<?= h($name) ?>" value="<?= h($current) ?>" class="<?= $field ?>">

          <?php elseif ($type === 'number' || $type === 'currency'): ?>
            <input type="number" step="any" name="<?= h($name) ?>" value="<?= h($current) ?>" class="<?= $field ?>">

          <?php else: ?>
            <input type="text" name="<?= h($name) ?>" value="<?= h($current) ?>" class="<?= $field ?>">
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="flex gap-3 pt-2 border-t border-slate-200">
      <button name="action" value="save" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">
        Save and finish later
      </button>
      <button name="action" value="submit" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
        Send it back
      </button>
    </div>
  </form>
</div>
