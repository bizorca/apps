<?php
/** @var array $thread @var array $engagement @var array $messages @var bool $clientSide @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <p class="text-xs text-slate-500 mb-1">
    <a href="<?= h(url('/engagements/' . $engagement['id'] . '/messages')) ?>" class="hover:underline"><?= h($engagement['title']) ?></a>
  </p>
  <h1 class="text-xl font-semibold mb-6"><?= h($thread['subject']) ?></h1>

  <div class="space-y-3 mb-6">
    <?php foreach ($messages as $m): $internal = (int) $m['client_visible'] === 0; ?>
      <div class="rounded-lg border <?= $internal ? 'border-amber-200 bg-amber-50/50' : 'border-slate-200 bg-white' ?> p-4">
        <div class="text-xs text-slate-500 mb-1">
          <?= h((string) ($m['author_label'] ?? 'Someone')) ?>
          · <?= h(date('j M, H:i', strtotime((string) $m['created_at']))) ?>
          <?php if ((string) $m['via'] === 'email'): ?><span class="ml-1 text-slate-400">by email</span><?php endif; ?>
          <?php if ($internal): ?><span class="ml-1 rounded bg-amber-100 px-1">internal</span><?php endif; ?>
        </div>
        <div class="text-sm whitespace-pre-line"><?= h((string) $m['body']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="post" action="<?= h(url('/threads/' . $thread['id'])) ?>" class="space-y-2">
    <?= Csrf::field() ?>
    <textarea name="body" rows="4" required placeholder="Reply" class="<?= $field ?>"></textarea>
    <div class="flex items-center gap-3">
      <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Reply</button>
      <?php if (!$clientSide): ?>
        <label class="text-xs text-slate-600 flex items-center gap-1">
          <input type="checkbox" name="internal" value="1"> internal only
        </label>
      <?php endif; ?>
    </div>
  </form>
</div>
