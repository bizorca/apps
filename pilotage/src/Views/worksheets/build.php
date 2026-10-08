<?php
/** @var array $worksheet @var array $fields @var array $bands @var bool $canEdit @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Worksheets;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$act = url('/worksheets/' . $worksheet['id']);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <p class="text-xs text-slate-500 mb-1"><a href="<?= h(url('/worksheets')) ?>" class="hover:underline">Worksheets</a></p>
  <div class="flex items-start justify-between mb-6">
    <div>
      <h1 class="text-xl font-semibold"><?= h($worksheet['title']) ?></h1>
      <p class="text-sm text-slate-500">
        <?= h(Worksheets::KINDS[$worksheet['kind']] ?? '') ?> · <?= h((string) $worksheet['status']) ?>
        <?php if ((int) $worksheet['is_anonymous'] === 1): ?>
          · anonymous, shown only above <?= (int) $worksheet['min_responses'] ?> responses
        <?php endif; ?>
      </p>
    </div>
    <?php if ($canEdit): ?>
      <form method="post" action="<?= h($act) ?>">
        <?= Csrf::field() ?><input type="hidden" name="action" value="publish">
        <button class="rounded bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Publish</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
    <?php if ($fields === []): ?>
      <div class="px-4 py-3 text-sm text-slate-500">No fields yet.</div>
    <?php endif; ?>
    <?php foreach ($fields as $f): ?>
      <div class="px-4 py-3">
        <?php if ((string) $f['field_type'] === 'section'): ?>
          <div class="text-sm font-semibold uppercase tracking-wide text-slate-500"><?= h($f['label']) ?></div>
        <?php elseif ((string) $f['field_type'] === 'note'): ?>
          <div class="text-sm text-slate-600 italic"><?= h($f['label']) ?></div>
        <?php else: ?>
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-sm"><?= h($f['label']) ?><?php if ((int) $f['required'] === 1): ?> <span class="text-red-500">*</span><?php endif; ?></div>
              <div class="text-xs text-slate-500">
                <?= h(Worksheets::FIELD_TYPES[$f['field_type']] ?? '') ?>
                <?php if ((string) $f['field_type'] === 'scale'): ?>
                  <?= (int) $f['scale_min'] ?>–<?= (int) $f['scale_max'] ?>
                <?php endif; ?>
                <?php if (!empty($f['category'])): ?> · <?= h($f['category']) ?><?php endif; ?>
                <?php if ((float) $f['weight'] != 1.0): ?> · weight <?= rtrim(rtrim((string) $f['weight'], '0'), '.') ?><?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($canEdit): ?>
    <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-5 space-y-3 mb-4">
      <?= Csrf::field() ?><input type="hidden" name="action" value="add_field">
      <h2 class="text-sm font-semibold">Add a field</h2>
      <div class="grid grid-cols-3 gap-2">
        <select name="field_type" class="<?= $field ?>">
          <?php foreach (Worksheets::FIELD_TYPES as $k => $lbl): ?>
            <option value="<?= h($k) ?>"><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <input name="label" required placeholder="Question or heading" class="<?= $field ?> col-span-2">
      </div>
      <input name="options" placeholder="Choices, one per line (for choice fields)" class="<?= $field ?>">
      <div class="grid grid-cols-4 gap-2">
        <input name="scale_min" type="number" value="1" placeholder="Scale min" class="<?= $field ?>">
        <input name="scale_max" type="number" value="10" placeholder="Scale max" class="<?= $field ?>">
        <input name="category" placeholder="Category" class="<?= $field ?>">
        <input name="weight" type="number" step="0.5" value="1" placeholder="Weight" class="<?= $field ?>">
      </div>
      <label class="text-xs text-slate-600 flex items-center gap-1">
        <input type="checkbox" name="required" value="1"> required
      </label>
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add field</button>
    </form>

    <?php if ((string) $worksheet['kind'] === 'assessment'): ?>
      <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
        <?= Csrf::field() ?><input type="hidden" name="action" value="add_band">
        <h2 class="text-sm font-semibold">What a score means</h2>
        <p class="text-xs text-slate-500">
          A number with no reading attached is a number the client will read wrongly. Write it once here.
        </p>
        <?php if ($bands !== []): ?>
          <ul class="text-xs text-slate-600 space-y-0.5">
            <?php foreach ($bands as $b): ?>
              <li><?= (int) $b['min_percent'] ?>–<?= (int) $b['max_percent'] ?>%: <strong><?= h($b['label']) ?></strong>
                <?php if (!empty($b['category'])): ?> <span class="text-slate-400">(<?= h($b['category']) ?>)</span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <div class="grid grid-cols-4 gap-2">
          <input name="min_percent" type="number" min="0" max="100" placeholder="From %" class="<?= $field ?>">
          <input name="max_percent" type="number" min="0" max="100" placeholder="To %" class="<?= $field ?>">
          <input name="label" placeholder="Label" class="<?= $field ?>">
          <input name="category" placeholder="Category (blank = overall)" class="<?= $field ?>">
        </div>
        <textarea name="interpretation" rows="2" placeholder="What this means, in your words" class="<?= $field ?>"></textarea>
        <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add band</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
