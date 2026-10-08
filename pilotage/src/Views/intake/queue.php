<?php
/** @var array $submissions @var bool $showingSpam @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="flex items-baseline justify-between mb-6">
    <h1 class="text-xl font-semibold">Enquiries</h1>
    <a href="<?= h(url('/enquiries' . ($showingSpam ? '' : '?spam=1'))) ?>" class="text-xs text-slate-500 hover:text-slate-900">
      <?= $showingSpam ? 'hide spam and handled' : 'show everything' ?>
    </a>
  </div>

  <?php if ((int) ($tenant['intake_enabled'] ?? 0) !== 1): ?>
    <div class="rounded-lg border border-dashed border-slate-300 p-6 mb-6">
      <p class="text-sm text-slate-600 mb-1">Your enquiry form is switched off.</p>
      <p class="text-xs text-slate-500">
        Turn it on in <a href="<?= h(url('/firm')) ?>" class="underline">firm settings</a> and it goes live at
        <code><?= h(tenant_url('/apply')) ?></code>.
      </p>
    </div>
  <?php endif; ?>

  <?php if ($submissions === []): ?>
    <p class="text-sm text-slate-500">Nothing waiting.</p>
  <?php endif; ?>

  <?php foreach ($submissions as $s): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-5 mb-4">
      <div class="flex items-start justify-between gap-4 mb-2">
        <div>
          <div class="text-sm font-semibold"><?= h($s['company_name']) ?></div>
          <div class="text-xs text-slate-500">
            <?= h($s['name']) ?> · <?= h($s['email']) ?>
            <?php if (!empty($s['phone'])): ?> · <?= h($s['phone']) ?><?php endif; ?>
          </div>
        </div>
        <span class="text-xs flex-shrink-0 <?= $s['status'] === 'new' ? 'text-amber-600' : 'text-slate-400' ?>">
          <?= h((string) $s['status']) ?> · <?= h(date('j M', strtotime((string) $s['created_at']))) ?>
        </span>
      </div>

      <div class="text-xs text-slate-500 mb-3">
        <?php if (!empty($s['revenue_band'])): ?>
          <?= h(ClientOrgRepository::REVENUE_BANDS[$s['revenue_band']] ?? '') ?>
        <?php endif; ?>
        <?php if (!empty($s['employee_count'])): ?> · <?= (int) $s['employee_count'] ?> staff<?php endif; ?>
        <?php if (!empty($s['timeline'])): ?> · wants to start <?= h($s['timeline']) ?><?php endif; ?>
      </div>

      <?php if (!empty($s['situation'])): ?>
        <div class="text-sm whitespace-pre-line mb-2"><?= h((string) $s['situation']) ?></div>
      <?php endif; ?>
      <?php if (!empty($s['desired_outcome'])): ?>
        <div class="text-sm text-slate-600 whitespace-pre-line mb-2">
          <span class="text-xs font-medium text-slate-500">What good looks like:</span><br>
          <?= h((string) $s['desired_outcome']) ?>
        </div>
      <?php endif; ?>

      <?php if (in_array((string) $s['status'], ['new', 'reviewing'], true)): ?>
        <div class="flex gap-2 mt-3 pt-3 border-t border-slate-100">
          <form method="post" action="<?= h(url('/enquiries/' . $s['id'])) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="accept">
            <button class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
              Take it on
            </button>
          </form>
          <form method="post" action="<?= h(url('/enquiries/' . $s['id'])) ?>" class="flex gap-2 flex-1">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="decline">
            <input name="note" placeholder="Reason (for your own records)" class="<?= $field ?>">
            <button class="rounded border border-slate-300 px-3 text-sm hover:bg-slate-50 whitespace-nowrap">Not a fit</button>
          </form>
        </div>
        <p class="text-xs text-slate-500 mt-2">
          Taking it on creates a prospect record and their first contact. Nothing is sent to them.
        </p>
      <?php elseif (!empty($s['client_org_id'])): ?>
        <a href="<?= h(url('/clients/' . $s['client_org_id'])) ?>" class="text-xs underline text-slate-600">
          View the client record
        </a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
