<?php
/** @var array $notices @var int $noticeDays @var bool $saved @var ?string $error @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$months = (int) ($tenant['retention_months'] ?? 0);
$pending = array_values(array_filter($notices, static fn (array $n): bool => $n['purged_at'] === null && $n['cancelled_at'] === null));
$settled = array_values(array_filter($notices, static fn (array $n): bool => $n['purged_at'] !== null || $n['cancelled_at'] !== null));
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Retention</h1>
  <p class="text-sm text-slate-600 mb-6">
    How long closed engagements are kept before they are permanently destroyed. Retention periods
    are jurisdiction-specific, so this is your decision and not ours.
  </p>

  <?php if ($saved): ?>
    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 mb-5">Saved.</div>
  <?php endif; ?>
  <?php if ($error !== null): ?>
    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 mb-5"><?= h((string) $error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= h(url('/firm/retention')) ?>"
        class="rounded-lg border border-slate-200 bg-white p-5 mb-8">
    <?= Csrf::field() ?>
    <label class="block text-sm font-medium mb-1">Keep closed engagements for</label>
    <select name="retention_months" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
      <option value="0" <?= $months === 0 ? 'selected' : '' ?>>Forever — nothing is ever destroyed</option>
      <?php foreach ([12, 24, 36, 60, 84, 120] as $m): ?>
        <option value="<?= $m ?>" <?= $months === $m ? 'selected' : '' ?>><?= (int) ($m / 12) ?> years</option>
      <?php endforeach; ?>
    </select>
    <p class="text-xs text-slate-500 mt-2">
      The default is forever, deliberately. Nothing is ever deleted because you did not visit this page.
      When you do set a period, you get <?= (int) $noticeDays ?> days' warning by email before anything
      is destroyed, and stopping it takes one click.
    </p>
    <button class="mt-3 rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Save</button>
  </form>

  <?php if ($pending !== []): ?>
    <h2 class="text-sm font-semibold mb-2">Scheduled for deletion</h2>
    <div class="rounded-lg border border-amber-200 bg-amber-50 divide-y divide-amber-100 mb-8">
      <?php foreach ($pending as $n): ?>
        <div class="px-4 py-3">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="text-sm font-medium text-amber-900">
                <?= h((string) ($n['org_name'] ?? 'Unknown')) ?> — <?= h((string) ($n['title'] ?? '')) ?>
              </div>
              <div class="text-xs text-amber-800 mt-0.5">
                Closed <?= h(date('j M Y', strtotime((string) $n['closed_at']))) ?>.
                Deletes <?= h(date('j M Y', strtotime((string) $n['purge_after']))) ?>.
                <?php if ($n['notified_at'] === null): ?>
                  <span class="font-medium">You have not been emailed about this yet — nothing will be
                  destroyed until you have been.</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <form method="post" action="<?= h(url('/firm/retention/keep')) ?>" class="mt-2 flex items-center gap-2">
            <?= Csrf::field() ?>
            <input type="hidden" name="notice_id" value="<?= (int) $n['id'] ?>">
            <input type="text" name="reason" required placeholder="Why keep this one?"
                   class="flex-1 rounded border border-amber-300 bg-white px-2 py-1 text-sm">
            <button class="rounded border border-amber-400 bg-white px-3 py-1 text-sm hover:bg-amber-100">Keep it</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($settled !== []): ?>
    <h2 class="text-sm font-semibold mb-2">Already decided</h2>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($settled as $n): ?>
        <div class="px-4 py-2.5 text-sm">
          <?php if ($n['purged_at'] !== null): ?>
            <span class="text-slate-500">Deleted <?= h(date('j M Y', strtotime((string) $n['purged_at']))) ?></span>
            — <?= h((string) ($n['title'] ?? 'an engagement')) ?>
          <?php else: ?>
            <span class="text-emerald-700">Kept</span>
            — <?= h((string) ($n['title'] ?? 'an engagement')) ?>
            <div class="text-xs text-slate-500">
              <?= h((string) $n['cancel_reason']) ?>
              <?php if (!empty($n['cancelled_by_name'])): ?> — <?= h((string) $n['cancelled_by_name']) ?><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
