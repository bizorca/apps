<?php
/** @var array $tags @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Tags</h1>
  <p class="text-sm text-slate-600 mb-6">
    A theme running across clients. Tag a task in one engagement and a document in another,
    and this is where you see them together.
  </p>

  <?php if ($tags === []): ?>
    <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center">
      <p class="text-sm text-slate-600">Nothing tagged yet.</p>
      <p class="text-xs text-slate-500 mt-1">Add tags on a task or a document and they will collect here.</p>
    </div>
  <?php else: ?>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($tags as $t): ?>
        <a href="<?= h(url('/tags/' . $t['slug'])) ?>"
           class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">
          <?= h($t['name']) ?>
          <span class="text-xs text-slate-400"><?= (int) $t['use_count'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
