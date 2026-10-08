<?php
/** @var array $documents @var string $search @var int $usage @var int $quota @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Storage;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="flex items-baseline justify-between mb-6">
    <h1 class="text-xl font-semibold">Document library</h1>
    <span class="text-xs text-slate-500">
      <?= h(Storage::humanBytes($usage)) ?> of <?= h(Storage::humanBytes($quota)) ?> used
    </span>
  </div>

  <form method="get" class="mb-4">
        <?= pl_route_field() ?>
    <input name="q" value="<?= h($search) ?>" placeholder="Search titles, tags, filenames"
           class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none">
  </form>

  <?php if ($documents === []): ?>
    <p class="text-sm text-slate-500">Nothing here yet.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($documents as $d): ?>
        <a href="<?= h(url('/documents/' . $d['id'])) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
          <div>
            <div class="text-sm font-medium"><?= h($d['title']) ?></div>
            <div class="text-xs text-slate-500">
              <?= h((string) ($d['original_name'] ?? '')) ?>
            </div>
          </div>
          <span class="text-xs text-slate-400"><?= h(Storage::humanBytes((int) ($d['byte_size'] ?? 0))) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
