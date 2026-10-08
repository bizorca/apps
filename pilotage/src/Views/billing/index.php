<?php
/** @var array $status @var array $plans @var string $current @var int $seats @var int $seatLimit
 *  @var int $usage @var int $storageLimit @var bool $configured @var array $history
 *  @var ?string $error @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Billing;
use Bizorca\Pilotage\Services\Entitlements;
use Bizorca\Pilotage\Services\Storage;
$beta = Entitlements::inBeta();
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);

$tone = match ($status['tone']) {
  'ok'      => 'border-emerald-200 bg-emerald-50 text-emerald-900',
  'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
  'blocked' => 'border-red-200 bg-red-50 text-red-900',
  default   => 'border-slate-200 bg-white text-slate-800',
};
$money = static fn (int $cents): string => '$' . number_format($cents / 100, 0);
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Billing</h1>
  <p class="text-sm text-slate-600 mb-6">
    <?php if ($beta): ?>
      Pilotage is free while it is in beta. Nothing to pay, nothing to set up, no countdown.
      When that changes you will hear about it well before it happens.
    <?php else: ?>
      Pilotage bills you per active advisor seat. Your clients are never billed by us and never
      see a payment screen from us.
    <?php endif; ?>
  </p>

  <?php if ($error !== null): ?>
    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 mb-5"><?= h((string) $error) ?></div>
  <?php endif; ?>

  <div class="rounded-lg border <?= $tone ?> p-5 mb-8">
    <h2 class="text-sm font-semibold"><?= h($status['headline']) ?></h2>
    <p class="text-sm mt-1"><?= h($status['detail']) ?></p>
  </div>

  <div class="grid grid-cols-2 gap-3 mb-8">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
      <div class="text-2xl font-semibold tabular-nums"><?= (int) $seats ?><?php if (!$beta): ?><span class="text-base text-slate-400">/<?= (int) $seatLimit ?></span><?php endif; ?></div>
      <div class="text-xs text-slate-500 mt-0.5">advisor seats in use<?= $beta ? ' — no limit during beta' : '' ?></div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
      <div class="text-2xl font-semibold tabular-nums"><?= h(Storage::humanBytes($usage)) ?></div>
      <div class="text-xs text-slate-500 mt-0.5">
        <?= $beta ? 'of documents stored — no limit during beta' : 'of ' . h(Storage::humanBytes($storageLimit)) . ' stored' ?>
      </div>
    </div>
  </div>

  <?php if (!$configured && !$beta): ?>
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 mb-8 text-sm text-slate-600">
      Payments are not connected on this installation, so nothing here can be bought yet.
      Everything else works.
    </div>
  <?php endif; ?>

  <?php if ($beta): ?>
    <h2 class="text-sm font-semibold mb-2">What it is likely to cost later</h2>
    <p class="text-xs text-slate-500 mb-3">
      Shown so nothing arrives as a surprise. These are not final and there is nothing to buy today.
    </p>
  <?php else: ?>
    <h2 class="text-sm font-semibold mb-2">Plans</h2>
    <p class="text-xs text-slate-500 mb-3">Yearly is two months free. Change or cancel any time.</p>
  <?php endif; ?>

  <div class="space-y-3 mb-8">
    <?php foreach (Billing::PURCHASABLE as $key): ?>
      <?php $plan = $plans[$key]; $isCurrent = $current === $key; ?>
      <div class="rounded-lg border <?= $isCurrent ? 'border-slate-900' : 'border-slate-200' ?> bg-white p-5">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-sm font-semibold">
              <?= h($plan['name']) ?>
              <?php if ($isCurrent): ?><span class="ml-2 text-xs font-normal text-slate-500">your plan</span><?php endif; ?>
            </div>
            <p class="text-sm text-slate-600 mt-0.5"><?= h($plan['blurb']) ?></p>
            <ul class="text-xs text-slate-500 mt-2 space-y-0.5">
              <li><?= (int) $plan['seats'] ?> advisor seat<?= (int) $plan['seats'] === 1 ? '' : 's' ?>, unlimited clients</li>
              <li><?= (int) $plan['storage_gb'] ?> GB of documents</li>
              <?php if ($plan['remove_credit']): ?><li>No "powered by Pilotage" on client screens</li><?php endif; ?>
            </ul>
          </div>
          <div class="text-right whitespace-nowrap">
            <div class="text-lg font-semibold"><?= h($money((int) $plan['monthly'])) ?><span class="text-xs font-normal text-slate-500">/mo</span></div>
            <div class="text-xs text-slate-500"><?= h($money((int) $plan['yearly'])) ?>/yr</div>
          </div>
        </div>

        <?php if (!$beta && $configured && !$isCurrent): ?>
          <div class="flex items-center gap-2 mt-4">
            <?php foreach (['month' => 'Monthly', 'year' => 'Yearly'] as $interval => $label): ?>
              <form method="post" action="<?= h(url('/billing/subscribe')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="plan" value="<?= h($key) ?>">
                <input type="hidden" name="interval" value="<?= h($interval) ?>">
                <button class="rounded <?= $interval === 'year' ? 'border border-slate-300 hover:bg-slate-50' : 'bg-slate-900 text-white hover:bg-slate-700' ?> px-3 py-1.5 text-sm font-medium">
                  <?= h($label) ?>
                </button>
              </form>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($tenant['stripe_customer_id'] !== null): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-5 mb-8">
      <h2 class="text-sm font-semibold mb-1">Card, invoices, cancelling</h2>
      <p class="text-xs text-slate-500 mb-3">
        All of it lives with Stripe, including cancelling. We do not put a retention maze between
        you and the door.
      </p>
      <form method="post" action="<?= h(url('/billing/portal')) ?>">
        <?= Csrf::field() ?>
        <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Open billing portal</button>
      </form>
    </div>
  <?php endif; ?>

  <div class="rounded-lg border border-slate-200 bg-white p-5 mb-8">
    <h2 class="text-sm font-semibold mb-1"><?= $beta ? 'When beta ends' : 'If you stop paying' ?></h2>
    <?php if ($beta): ?>
      <p class="text-sm text-slate-600 mb-2">
        You will get plenty of notice and a real choice. If you decide not to continue, your
        workspace becomes read-only rather than disappearing — nothing is deleted, your clients keep
        the documents you delivered them, and
        <a href="<?= h(url('/firm/export')) ?>" class="underline hover:text-slate-900">the full export</a>
        keeps working permanently.
      </p>
    <?php endif; ?>
    <div<?= $beta ? ' class="hidden"' : '' ?>>
    <p class="text-sm text-slate-600">
      Your workspace becomes read-only. That is the whole penalty. Nothing is deleted, your clients
      keep the documents you delivered them, and
      <a href="<?= h(url('/firm/export')) ?>" class="underline hover:text-slate-900">the full export</a>
      keeps working — permanently, without asking us.
    </p>
    </div>
  </div>

  <?php if ($history !== []): ?>
    <h2 class="text-sm font-semibold mb-2">Seat changes</h2>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($history as $h): ?>
        <div class="flex items-center justify-between px-4 py-2.5 text-sm">
          <div>
            <?= (int) $h['seats_before'] ?> → <?= (int) $h['seats_after'] ?>
            <span class="text-xs text-slate-500"><?= h((string) $h['reason']) ?></span>
          </div>
          <div class="text-xs text-slate-400">
            <?= h(date('j M Y', strtotime((string) $h['created_at']))) ?>
            <?php if ($h['synced_at'] === null): ?>
              <span class="text-amber-600">not yet billed</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
