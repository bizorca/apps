<?php
/** @var array $tenant @var array $input @var array $errors @var int $renderedAt */
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Services\Intake;
$v = static fn (string $k): string => h((string) ($input[$k] ?? ''));
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900';
?>
<div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
  <h1 class="text-xl font-semibold mb-2">
    <?= h((string) ($tenant['intake_headline'] ?: 'Tell us about your business')) ?>
  </h1>
  <p class="text-sm text-slate-600 mb-6">
    <?= nl2br(h((string) ($tenant['intake_blurb']
      ?: 'A few questions so we arrive at a first conversation already knowing something useful. It takes about three minutes, and a real person reads every one.'))) ?>
  </p>

  <?php if ($errors !== []): ?>
    <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="space-y-4">
    <input type="hidden" name="_t" value="<?= (int) $renderedAt ?>">

    <?php /* Honeypot. Hidden from people, irresistible to bots. Not type=hidden,
             because a bot that reads the DOM skips those. */ ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true">
      <label>Company website URL
        <input type="text" name="<?= h(Intake::HONEYPOT_FIELD) ?>" tabindex="-1" autocomplete="off">
      </label>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium mb-1" for="name">Your name</label>
        <input id="name" name="name" required value="<?= $v('name') ?>" class="<?= $field ?>">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1" for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= $v('email') ?>" class="<?= $field ?>">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium mb-1" for="company_name">Business name</label>
        <input id="company_name" name="company_name" required value="<?= $v('company_name') ?>" class="<?= $field ?>">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1" for="phone">Phone <span class="text-slate-400 font-normal">optional</span></label>
        <input id="phone" name="phone" value="<?= $v('phone') ?>" class="<?= $field ?>">
      </div>
    </div>

    <div class="grid grid-cols-3 gap-3">
      <div class="col-span-1">
        <label class="block text-sm font-medium mb-1" for="employee_count">Staff</label>
        <input id="employee_count" name="employee_count" inputmode="numeric" value="<?= $v('employee_count') ?>" class="<?= $field ?>">
      </div>
      <div class="col-span-2">
        <label class="block text-sm font-medium mb-1" for="revenue_band">Revenue</label>
        <select id="revenue_band" name="revenue_band" class="<?= $field ?>">
          <option value="">Prefer not to say</option>
          <?php foreach (ClientOrgRepository::REVENUE_BANDS as $k => $lbl): ?>
            <option value="<?= h($k) ?>" <?= ($input['revenue_band'] ?? '') === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1" for="situation">What is going on?</label>
      <textarea id="situation" name="situation" rows="4" required class="<?= $field ?>"><?= $v('situation') ?></textarea>
      <p class="text-xs text-slate-500 mt-1">The honest version is more useful than the tidy one.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1" for="desired_outcome">
        What would make the next twelve months worth it? <span class="text-slate-400 font-normal">optional</span>
      </label>
      <textarea id="desired_outcome" name="desired_outcome" rows="3" class="<?= $field ?>"><?= $v('desired_outcome') ?></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1" for="timeline">
        When would you want to start? <span class="text-slate-400 font-normal">optional</span>
      </label>
      <input id="timeline" name="timeline" placeholder="e.g. next month, after year end" value="<?= $v('timeline') ?>" class="<?= $field ?>">
    </div>

    <button class="w-full rounded bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
      Send it
    </button>
    <p class="text-xs text-slate-500 text-center">
      No obligation, and nothing is signed up for. We will read it and reply.
    </p>
  </form>
</div>
