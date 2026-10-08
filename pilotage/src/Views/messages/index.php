<?php
/** @var array $engagement @var array $threads @var bool $clientSide @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'messages'], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1"><?= h($engagement['title']) ?></h1>
  <p class="text-sm text-slate-600 mb-6">Messages</p>

  <?php if ($threads === []): ?>
    <p class="text-sm text-slate-500 mb-6">Nothing yet.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
      <?php foreach ($threads as $t): ?>
        <a href="<?= h(url('/threads/' . $t['id'])) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
          <div class="min-w-0">
            <div class="text-sm font-medium truncate"><?= h($t['subject']) ?></div>
            <div class="text-xs text-slate-500">
              <?= (int) $t['message_count'] ?> message<?= (int) $t['message_count'] === 1 ? '' : 's' ?>
              <?php if (!empty($t['last_message_at'])): ?>
                · <?= h(date('j M, H:i', strtotime((string) $t['last_message_at']))) ?>
              <?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/threads')) ?>"
        class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
    <?= Csrf::field() ?>
    <h2 class="text-sm font-semibold">Start a thread</h2>
    <input name="subject" required placeholder="Subject" class="<?= $field ?>">
    <textarea name="body" rows="4" required placeholder="What is on your mind? Use @name to pull someone in." class="<?= $field ?>"></textarea>
    <div class="flex items-center gap-3">
      <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Post</button>
      <?php if (!$clientSide): ?>
        <label class="text-xs text-slate-600 flex items-center gap-1">
          <input type="checkbox" name="internal" value="1"> internal only
        </label>
      <?php endif; ?>
    </div>
  </form>
</div>
