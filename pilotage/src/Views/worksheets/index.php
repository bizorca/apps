<?php
/** @var array $worksheets @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Worksheets;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Worksheets</h1>
  <p class="text-sm text-slate-600 mb-6">
    Questionnaires, reflection exercises, and scored assessments. Write one once and send it to any client.
  </p>

  <?php if ($worksheets !== []): ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
      <?php foreach ($worksheets as $w): ?>
        <a href="<?= h(url('/worksheets/' . $w['id'])) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
          <div>
            <div class="text-sm font-medium">
              <?= h($w['title']) ?>
              <?php if ((int) $w['is_anonymous'] === 1): ?>
                <span class="ml-1 text-xs rounded bg-slate-100 px-1 text-slate-600">anonymous</span>
              <?php endif; ?>
            </div>
            <div class="text-xs text-slate-500">
              <?= h(Worksheets::KINDS[$w['kind']] ?? '') ?> · <?= (int) $w['field_count'] ?> field<?= (int) $w['field_count'] === 1 ? '' : 's' ?>
            </div>
          </div>
          <span class="text-xs <?= $w['status'] === 'published' ? 'text-emerald-600' : 'text-amber-600' ?>">
            <?= h((string) $w['status']) ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= h(url('/worksheets')) ?>" class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
    <?= Csrf::field() ?>
    <h2 class="text-sm font-semibold">New worksheet</h2>
    <input name="title" required placeholder="Title" class="<?= $field ?>">
    <textarea name="description" rows="2" placeholder="What it is for (optional)" class="<?= $field ?>"></textarea>
    <select name="kind" class="<?= $field ?>">
      <?php foreach (Worksheets::KINDS as $k => $lbl): ?>
        <option value="<?= h($k) ?>"><?= h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="text-xs text-slate-500">
      A <strong>form</strong> is read as written. An <strong>assessment</strong> is scored and comparable over time.
      A <strong>team survey</strong> is always anonymous — you see aggregates, never who said what.
    </p>
    <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Create</button>
  </form>
</div>
