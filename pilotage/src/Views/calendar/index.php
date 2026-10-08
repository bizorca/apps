<?php
/** @var array $providers @var array $connections @var ?string $error @var ?string $connected
 *  @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\CalendarSync;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);

$byProvider = [];
foreach ($connections as $c) { $byProvider[(string) $c['provider']] = $c; }
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Calendar</h1>
  <p class="text-sm text-slate-600 mb-6">
    Sessions appear in your own calendar, and moving one there moves it here. Your clients are not
    asked to connect anything — they keep getting the invitation attached to the session.
  </p>

  <?php if ($error !== null): ?>
    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 mb-5"><?= h((string) $error) ?></div>
  <?php endif; ?>
  <?php if ($connected !== null): ?>
    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 mb-5">
      <?= h((string) $connected) ?>
    </div>
  <?php endif; ?>

  <?php if ($providers === []): ?>
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
      Calendar sync is not set up on this installation yet. It needs OAuth credentials for Google or
      Microsoft, and an encryption key to keep the resulting tokens safe.
      <span class="block mt-1 text-xs text-slate-500">See docs/calendar-sync.md.</span>
    </div>
  <?php else: ?>
    <div class="space-y-3 mb-8">
      <?php foreach ($providers as $key => $provider): ?>
        <?php $conn = $byProvider[$key] ?? null; ?>
        <div class="rounded-lg border <?= $conn !== null && $conn['status'] === 'active' ? 'border-emerald-200' : 'border-slate-200' ?> bg-white p-5">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="text-sm font-semibold"><?= h($provider->label()) ?></div>
              <?php if ($conn === null): ?>
                <p class="text-sm text-slate-600 mt-0.5">Not connected.</p>
              <?php else: ?>
                <p class="text-sm text-slate-600 mt-0.5">
                  <?= h((string) ($conn['account_email'] ?? 'connected')) ?>
                </p>
                <p class="text-xs text-slate-500 mt-1">
                  <?php if ($conn['status'] === 'needs_reauth'): ?>
                    <span class="text-amber-700">Needs reconnecting.</span>
                    <?= h((string) ($conn['last_error'] ?? '')) ?>
                  <?php else: ?>
                    <?= (int) $conn['event_count'] ?> session<?= (int) $conn['event_count'] === 1 ? '' : 's' ?> linked
                    <?php if ($conn['last_sync_at'] !== null): ?>
                      · last checked <?= h(date('j M, H:i', strtotime((string) $conn['last_sync_at']))) ?> UTC
                    <?php endif; ?>
                  <?php endif; ?>
                </p>
              <?php endif; ?>
            </div>

            <div class="flex items-center gap-2 whitespace-nowrap">
              <?php if ($conn !== null): ?>
                <form method="post" action="<?= h(url('/calendar/disconnect')) ?>">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="provider" value="<?= h($key) ?>">
                  <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Disconnect</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= h(url('/calendar/connect/' . $key)) ?>">
                <?= Csrf::field() ?>
                <button class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">
                  <?= $conn === null ? 'Connect' : 'Reconnect' ?>
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($connections !== []): ?>
      <form method="post" action="<?= h(url('/calendar/sync')) ?>" class="mb-8">
        <?= Csrf::field() ?>
        <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Check now</button>
        <span class="ml-2 text-xs text-slate-500">Otherwise this happens on its own every few minutes.</span>
      </form>
    <?php endif; ?>
  <?php endif; ?>

  <div class="rounded-lg border border-slate-200 bg-white p-5 text-sm text-slate-600 space-y-2">
    <h2 class="text-sm font-semibold text-slate-900">How it behaves</h2>
    <p>Sessions you are running go into your calendar, from now until
       <?= (int) CalendarSync::HORIZON_DAYS ?> days out. Past ones are left alone — nobody wants nine
       months of finished meetings appearing at once.</p>
    <p>Move a session in your calendar and it moves here, and everyone on the engagement is told.
       If it was changed in both places, the more recent change wins and the other is noted rather
       than silently lost.</p>
    <p>Deleting the event from your calendar does <strong>not</strong> cancel the session — it only
       unlinks it, because "off my view" and "call this off" are different things. Cancelling the
       session here does remove the event, because that one is not ambiguous.</p>
  </div>
</div>
