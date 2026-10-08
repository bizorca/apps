<?php
/** @var array $assignment @var array $user @var array $tenant */
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
?>
<div class="max-w-xl mx-auto px-4 py-12 text-center">
  <div class="rounded-lg border border-slate-200 bg-white p-8">
    <h1 class="text-xl font-semibold mb-2">Sent back. Thank you.</h1>
    <p class="text-sm text-slate-600">
      <?php if ((int) $assignment['is_anonymous'] === 1): ?>
        Your answers went in without your name attached. There is no way back to them now — not for
        <?= h($tenant['name']) ?>, and not for you.
      <?php else: ?>
        <?= h($tenant['name']) ?> has your answers and will pick them up from here.
      <?php endif; ?>
    </p>
    <a href="<?= h(url('/')) ?>" class="inline-block mt-4 text-sm underline text-slate-600">Back to your workspace</a>
  </div>
</div>
