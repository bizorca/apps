<?php
/** @var array $tenant @var string $token @var bool $valid @var array|null $done */
use Bizorca\Pilotage\Auth\Csrf;
?>
<div class="min-h-full flex flex-col justify-center px-4 py-12">
  <div class="w-full max-w-md mx-auto bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
    <?php if (!$valid): ?>
      <h1 class="text-lg font-semibold mb-2">That link has expired</h1>
      <p class="text-sm text-slate-600">
        Sign in and open your notification settings to change what you receive. Every email we send
        carries a fresh link at the bottom, so the most recent one will work.
      </p>

    <?php elseif ($done !== null): ?>
      <h1 class="text-lg font-semibold mb-2">Done</h1>
      <p class="text-sm text-slate-600 mb-4">
        <?= (int) $done['switched_off'] ?> kinds of update will no longer be emailed to you. They still
        appear when you sign in.
      </p>
      <?php if ($done['still_receiving'] !== []): ?>
        <p class="text-sm text-slate-600 mb-2">
          These will keep arriving, because they are part of the work rather than news about it:
        </p>
        <ul class="text-sm text-slate-600 list-disc pl-5 space-y-1">
          <?php foreach ($done['still_receiving'] as $label): ?>
            <li><?= h($label) ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="text-xs text-slate-500 mt-4">
          To stop those too, you would need to leave the engagement — speak to
          <?= h((string) $tenant['name']) ?> about that.
        </p>
      <?php endif; ?>

    <?php else: ?>
      <h1 class="text-lg font-semibold mb-2">Stop these emails?</h1>
      <p class="text-sm text-slate-600 mb-5">
        This turns off the optional updates from <?= h((string) $tenant['name']) ?> — digests, activity
        notices, reminders about things other people did. You will still get anything your engagement
        actually requires of you, and everything stays visible when you sign in.
      </p>
      <form method="post" action="<?= h(url('/unsubscribe/' . $token)) ?>">
        <?= Csrf::field() ?>
        <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
          Yes, stop them
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
