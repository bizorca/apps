<?php
/** @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
$clientSide = ($user['client_org_id'] ?? null) !== null;
$isOwner = ($user['role'] ?? '') === 'firm_owner';
$logo = $tenant['logo_url'] ?? null;
?>
<header class="border-b border-slate-200 bg-white">
  <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-4">
      <a href="<?= h(url('/')) ?>" class="flex items-center gap-2">
        <?php if (is_string($logo) && str_starts_with($logo, 'https://')): ?>
          <img src="<?= h($logo) ?>" alt="<?= h($tenant['name']) ?>" class="h-6 w-auto max-w-[10rem] object-contain">
        <?php else: ?>
          <span class="font-semibold tracking-tight text-brand"><?= h($tenant['name']) ?></span>
        <?php endif; ?>
      </a>
      <?php if (!$clientSide): ?>
        <a href="<?= h(url('/clients')) ?>" class="text-sm text-slate-600 hover:text-slate-900">Clients</a>
        <a href="<?= h(url('/playbooks')) ?>" class="text-sm text-slate-600 hover:text-slate-900">Playbooks</a>
        <a href="<?= h(url('/library')) ?>" class="text-sm text-slate-600 hover:text-slate-900">Library</a>
        <a href="<?= h(url('/worksheets')) ?>" class="text-sm text-slate-600 hover:text-slate-900">Worksheets</a>
        <a href="<?= h(url('/cohorts')) ?>" class="text-sm text-slate-600 hover:text-slate-900">Cohorts</a>
        <?php if ($isOwner): ?>
          <a href="<?= h(url('/firm/dashboard')) ?>" class="text-sm text-slate-600 hover:text-slate-900">Firm</a>
        <?php endif; ?>
        <a href="<?= h(url('/tags')) ?>" class="text-sm text-slate-600 hover:text-slate-900">Tags</a>
        <?php
        $newEnquiries = \Bizorca\Pilotage\Services\Intake::countNew((int) $tenant['id']);
        if ($newEnquiries > 0 || (int) ($tenant['intake_enabled'] ?? 0) === 1): ?>
          <a href="<?= h(url('/enquiries')) ?>" class="text-sm text-slate-600 hover:text-slate-900">
            Enquiries<?php if ($newEnquiries > 0): ?>
              <span class="ml-1 rounded-full bg-amber-500 text-white px-1.5 text-xs"><?= (int) $newEnquiries ?></span>
            <?php endif; ?>
          </a>
        <?php endif; ?>
      <?php endif; ?>
      <a href="<?= h(url('/tasks')) ?>" class="text-sm text-slate-600 hover:text-slate-900">Tasks</a>
    </div>
    <div class="flex items-center gap-3 text-sm text-slate-600">
      <?php $unread = \Bizorca\Pilotage\Services\Notifications::unreadCount((int) $tenant['id'], (int) $user['id']); ?>
      <a href="<?= h(url('/notifications')) ?>" class="hover:text-slate-900" title="Notifications">
        Updates<?php if ($unread > 0): ?>
          <span class="ml-1 rounded-full bg-slate-900 text-white px-1.5 text-xs"><?= $unread > 99 ? '99+' : (int) $unread ?></span>
        <?php endif; ?>
      </a>
      <?php if (!$clientSide): ?>
        <a href="<?= h(url('/calendar')) ?>" class="hover:text-slate-900">Calendar</a>
      <?php endif; ?>
      <?php if ($isOwner): ?>
        <a href="<?= h(url('/billing')) ?>" class="hover:text-slate-900">Billing</a>
        <a href="<?= h(url('/firm')) ?>" class="hover:text-slate-900">Settings</a>
      <?php endif; ?>
      <span><?= h($user['name']) ?></span>
      <form method="post" action="<?= h(url('/logout')) ?>">
        <?= Csrf::field() ?>
        <button class="underline hover:text-slate-900">Sign out</button>
      </form>
    </div>
  </div>
</header>
