<?php
/** @var array $staff @var array $pending @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-6">Firm settings</h1>

  <form method="post" action="<?= h(url('/firm/branding')) ?>" class="rounded-lg border border-slate-200 bg-white p-5 space-y-3 mb-6">
    <?= Csrf::field() ?>
    <h2 class="text-sm font-semibold">Branding</h2>
    <p class="text-xs text-slate-500">
      This is what your clients see. Your portal lives at
      <code><?= h(tenant_url('/')) ?></code>.
    </p>

    <div>
      <label class="block text-sm font-medium mb-1">Firm name</label>
      <input name="name" value="<?= h((string) $tenant['name']) ?>" class="<?= $field ?>">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Logo URL</label>
      <input name="logo_url" value="<?= h((string) ($tenant['logo_url'] ?? '')) ?>"
             placeholder="https://..." class="<?= $field ?>">
      <p class="text-xs text-slate-500 mt-1">Must be https. Clients should never be asked to load it over plaintext.</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium mb-1">Primary colour</label>
        <input name="primary_color" value="<?= h((string) ($tenant['primary_color'] ?? '')) ?>"
               placeholder="#0f172a" class="<?= $field ?>">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Accent colour</label>
        <input name="accent_color" value="<?= h((string) ($tenant['accent_color'] ?? '')) ?>"
               placeholder="#059669" class="<?= $field ?>">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium mb-1">Email sender name</label>
        <input name="mail_from_name" value="<?= h((string) ($tenant['mail_from_name'] ?? '')) ?>"
               placeholder="<?= h((string) $tenant['name']) ?>" class="<?= $field ?>">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Support email</label>
        <input name="support_email" type="email" value="<?= h((string) ($tenant['support_email'] ?? '')) ?>" class="<?= $field ?>">
      </div>
    </div>

    <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save</button>
  </form>

  <div class="rounded-lg border border-slate-200 bg-white p-5 mb-6">
    <h2 class="text-sm font-semibold mb-3">
      Your team
      <span class="font-normal text-xs text-slate-500">
        <?= count($staff) ?> seat<?= count($staff) === 1 ? '' : 's' ?><?php
          if ($tenant['seat_limit'] !== null): ?> of <?= (int) $tenant['seat_limit'] ?><?php endif; ?>
      </span>
    </h2>

    <ul class="divide-y divide-slate-100 -mx-5 mb-4">
      <?php foreach ($staff as $s): ?>
        <li class="px-5 py-2.5 flex items-center justify-between">
          <div>
            <div class="text-sm <?= $s['status'] === 'disabled' ? 'text-slate-400 line-through' : '' ?>"><?= h($s['name']) ?></div>
            <div class="text-xs text-slate-500"><?= h($s['email']) ?> · <?= h(str_replace('_', ' ', (string) $s['role'])) ?></div>
          </div>
          <?php if ($s['status'] !== 'disabled' && (int) $s['id'] !== (int) $user['id']): ?>
            <form method="post" action="<?= h(url('/firm/staff/disable')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="user_id" value="<?= (int) $s['id'] ?>">
              <button class="text-xs text-slate-400 hover:text-red-600">disable</button>
            </form>
          <?php elseif ((int) $s['id'] === (int) $user['id']): ?>
            <span class="text-xs text-slate-400">you</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($pending !== []): ?>
      <div class="mb-4 text-xs text-slate-500">
        Pending: <?= h(implode(', ', array_column($pending, 'email'))) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($sendingNotice)): ?>
      <?php /* The sending throttle, stated before it bites. See SendingTrust. */ ?>
      <p class="mt-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-900">
        <?= h($sendingNotice) ?>
      </p>
    <?php endif; ?>

    <form method="post" action="<?= h(url('/firm/staff')) ?>" class="border-t border-slate-100 pt-4 space-y-2">
      <?= Csrf::field() ?>
      <div class="grid grid-cols-3 gap-2">
        <input name="name" placeholder="Name" class="<?= $field ?>">
        <input name="email" type="email" required placeholder="Email" class="<?= $field ?>">
        <select name="role" class="<?= $field ?>">
          <option value="coach">Coach</option>
          <option value="associate">Associate</option>
          <option value="firm_owner">Firm owner</option>
        </select>
      </div>
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Invite</button>
    </form>
  </div>

  <form method="post" action="<?= h(url('/firm/intake')) ?>" class="rounded-lg border border-slate-200 bg-white p-5 space-y-3 mb-6">
    <?= Csrf::field() ?>
    <h2 class="text-sm font-semibold">Enquiry form</h2>
    <p class="text-xs text-slate-500">
      A public page where someone with no advisor can describe their situation. Live at
      <code><?= h(tenant_url('/apply')) ?></code> when switched on.
      Nothing is created until you read it and decide.
    </p>

    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="intake_enabled" value="1" <?= (int) ($tenant['intake_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
      Accept enquiries
    </label>

    <input name="intake_headline" value="<?= h((string) ($tenant['intake_headline'] ?? '')) ?>"
           placeholder="Headline (optional)" class="<?= $field ?>">
    <textarea name="intake_blurb" rows="3" placeholder="What to say above the form (optional)"
              class="<?= $field ?>"><?= h((string) ($tenant['intake_blurb'] ?? '')) ?></textarea>

    <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Save</button>
  </form>

  <form method="post" action="<?= h(url('/firm/digest')) ?>"
        class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
    <?= Csrf::field() ?>
    <h2 class="text-sm font-semibold">Digests</h2>
    <p class="text-xs text-slate-500">
      One summary instead of an email per event. Times are UTC — pick the hour that lands where your
      clients are, not where a server is.
    </p>

    <label class="block text-xs font-medium text-slate-600">
      Your daily summary, at
      <select name="digest_hour" class="<?= $field ?> mt-1">
        <?php for ($h = 0; $h < 24; $h++): ?>
          <option value="<?= $h ?>" <?= (int) ($tenant['digest_hour'] ?? 13) === $h ? 'selected' : '' ?>>
            <?= sprintf('%02d:00 UTC', $h) ?>
          </option>
        <?php endfor; ?>
      </select>
    </label>

    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="client_digest_enabled" value="1" class="rounded border-slate-300"
             <?= (int) ($tenant['client_digest_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
      Send your clients a weekly summary
    </label>

    <label class="block text-xs font-medium text-slate-600">
      On
      <select name="client_digest_day" class="<?= $field ?> mt-1">
        <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $dayName): ?>
          <option value="<?= $i ?>" <?= (int) ($tenant['client_digest_day'] ?? 1) === $i ? 'selected' : '' ?>>
            <?= h($dayName) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="flex items-center gap-3 pt-1">
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Save</button>
      <a href="<?= h(url('/firm/email-wording')) ?>" class="text-xs text-slate-600 underline hover:text-slate-900">
        Rewrite the emails in your own voice
      </a>
    </div>
  </form>

  <?php
  $ticks = \Bizorca\Pilotage\Core\Heartbeat::status();
  $tickBad = \Bizorca\Pilotage\Core\Heartbeat::unhealthy();
  ?>
  <div class="rounded-lg border <?= $tickBad ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white' ?> p-5">
    <h2 class="text-sm font-semibold mb-1">Background work</h2>
    <p class="text-xs <?= $tickBad ? 'text-amber-900' : 'text-slate-500' ?> mb-3">
      Reminders, digests, the accountability sweep, calendar sync and record retention all run in
      the background. They are triggered by traffic to your site rather than by a scheduler.
    </p>
    <div class="space-y-1 mb-3">
      <?php foreach ($ticks as $tick): ?>
        <div class="text-sm">
          <span class="font-mono text-xs text-slate-500"><?= h((string) $tick['mode']) ?></span>
          — <span class="<?= $tick['healthy'] ? 'text-slate-700' : 'text-amber-900 font-medium' ?>">
            <?= h((string) $tick['human']) ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($tickBad): ?>
      <p class="text-xs text-amber-900">
        A quiet site does not tick. If nobody visits for a while, nothing runs — which is usually
        harmless, because everything catches up on the next visit. To keep it running regardless,
        point any uptime monitor at this address every five minutes:
      </p>
      <code class="mt-2 block break-all rounded bg-white/70 px-2 py-1 text-xs">
        <?= h(app_url('/_tick/' . \Bizorca\Pilotage\Core\Heartbeat::token() . '/five-minute')) ?>
      </code>
    <?php endif; ?>
  </div>

  <div class="rounded-lg border border-slate-200 bg-white p-5">
    <h2 class="text-sm font-semibold mb-1">Records and compliance</h2>
    <p class="text-xs text-slate-500 mb-3">
      Who did what, how long closed engagements are kept, and what happens when someone asks to be
      forgotten.
    </p>
    <div class="flex flex-wrap gap-2">
      <a href="<?= h(url('/firm/audit')) ?>" class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Audit log</a>
      <a href="<?= h(url('/firm/retention')) ?>" class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Retention</a>
      <a href="<?= h(url('/firm/erasure')) ?>" class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Erasure requests</a>
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 bg-white p-5">
    <h2 class="text-sm font-semibold mb-1">Take your data with you</h2>
    <p class="text-xs text-slate-500 mb-3">
      Everything this firm has in Pilotage, as JSON. Credentials and tokens are excluded;
      uploaded files come out individually from the app.
    </p>
    <a href="<?= h(url('/firm/export')) ?>" class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">
      Download export
    </a>
  </div>
</div>
