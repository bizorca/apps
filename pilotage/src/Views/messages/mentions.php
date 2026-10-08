<?php
/** @var array $mentions @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-6">Mentions</h1>
  <?php if ($mentions === []): ?>
    <p class="text-sm text-slate-500">Nobody has pulled you into anything.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($mentions as $m): ?>
        <div class="px-4 py-3">
          <div class="text-sm">
            You were mentioned on a <?= h((string) $m['object_type']) ?>
            <?php if (!empty($m['context_label'])): ?> — <?= h((string) $m['context_label']) ?><?php endif; ?>
          </div>
          <div class="text-xs text-slate-500"><?= h(date('j M, H:i', strtotime((string) $m['created_at']))) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
