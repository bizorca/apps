<?php
/** @var array $requests @var array $people @var ?string $error @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Compliance;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1">Erasure requests</h1>
  <p class="text-sm text-slate-600 mb-6">
    When someone asks to be forgotten. The hard part is not the deleting — it is that the
    commitments they made and the sessions they attended <em>are</em> the engagement record you may
    be obliged to keep. So this is a decision you make and record, not a button.
  </p>

  <?php if ($error !== null): ?>
    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 mb-5"><?= h((string) $error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= h(url('/firm/erasure')) ?>"
        class="rounded-lg border border-slate-200 bg-white p-5 mb-8 space-y-3">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="request">
    <h2 class="text-sm font-semibold">Open a request</h2>
    <select name="subject_user_id" required class="<?= $field ?>">
      <option value="">Who has asked?</option>
      <?php foreach ($people as $p): ?>
        <option value="<?= (int) $p['id'] ?>"><?= h($p['name']) ?> — <?= h($p['org_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="reason" placeholder="What did they ask for? (optional)" class="<?= $field ?>">
    <button class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">
      Assess it
    </button>
  </form>

  <?php if ($requests === []): ?>
    <p class="text-sm text-slate-500">No requests.</p>
  <?php endif; ?>

  <div class="space-y-4">
    <?php foreach ($requests as $r): ?>
      <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-sm font-semibold"><?= h((string) $r['subject_label']) ?></div>
            <div class="text-xs text-slate-500">
              Asked <?= h(date('j M Y', strtotime((string) $r['created_at']))) ?>
              <?php if (!empty($r['reason'])): ?> — <?= h((string) $r['reason']) ?><?php endif; ?>
            </div>
          </div>
          <span class="text-xs rounded px-2 py-0.5 <?= match ($r['status']) {
            'open' => 'bg-amber-100 text-amber-900',
            'refused' => 'bg-red-100 text-red-900',
            default => 'bg-emerald-100 text-emerald-900',
          } ?>"><?= h(str_replace('_', ' ', (string) $r['status'])) ?></span>
        </div>

        <?php if ($r['conflicts'] !== []): ?>
          <div class="mt-3 rounded border border-slate-200 bg-slate-50 p-3">
            <div class="text-xs font-semibold text-slate-700 mb-1">What is in the way</div>
            <ul class="text-xs text-slate-600 space-y-1">
              <?php foreach ($r['conflicts'] as $c): ?>
                <li><strong><?= (int) $c['count'] ?></strong> <?= h((string) $c['note']) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php else: ?>
          <p class="text-xs text-slate-500 mt-3">Nothing in the record depends on this person.</p>
        <?php endif; ?>

        <?php if ($r['status'] === 'open'): ?>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <form method="post" action="<?= h(url('/firm/erasure')) ?>"
                  class="rounded border border-slate-200 p-3 space-y-2">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="pseudonymise">
              <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
              <div class="text-xs font-semibold">Erase the person, keep the record</div>
              <p class="text-xs text-slate-500">
                Name, email and sign-in go. What happened stays, attributed to
                "<?= h(Compliance::ANONYMOUS_LABEL) ?>". This is usually the right answer.
              </p>
              <input type="text" name="decision" placeholder="Note for the record (optional)"
                     class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
              <button class="w-full rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">
                Pseudonymise
              </button>
            </form>

            <form method="post" action="<?= h(url('/firm/erasure')) ?>"
                  class="rounded border border-slate-200 p-3 space-y-2">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="refuse">
              <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
              <div class="text-xs font-semibold">Refuse, with a reason</div>
              <p class="text-xs text-slate-500">
                Legitimate when you are obliged to keep the record. The reason is required — an
                unexplained refusal reads as ignoring the request.
              </p>
              <input type="text" name="decision" required placeholder="Why you cannot erase this"
                     class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
              <button class="w-full rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">
                Refuse
              </button>
            </form>
          </div>
        <?php elseif (!empty($r['decision'])): ?>
          <div class="mt-3 text-xs text-slate-600">
            <strong>Decided</strong>
            <?php if (!empty($r['decided_at'])): ?>
              <?= h(date('j M Y', strtotime((string) $r['decided_at']))) ?>
            <?php endif; ?>
            <?php if (!empty($r['decided_by_name'])): ?>by <?= h((string) $r['decided_by_name']) ?><?php endif; ?>:
            <?= h((string) $r['decision']) ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
