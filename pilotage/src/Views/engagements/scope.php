<?php
/** @var array $engagement @var array $items @var array $requests @var array $summary
 *  @var bool $locked @var bool $accepted @var bool $clientSide @var bool $canEdit
 *  @var bool $canReview @var bool $canAccept @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'scope'], null);
$act = url('/engagements/' . $engagement['id'] . '/scope');
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="flex items-baseline justify-between mb-4">
    <h1 class="text-xl font-semibold">Scope</h1>
    <span class="text-xs <?= $locked ? 'text-slate-600' : 'text-amber-700' ?>">
      <?= $locked ? ($accepted ? 'locked and accepted' : 'locked, awaiting the client') : 'draft — not yet locked' ?>
    </span>
  </div>

  <?php if ((int) $engagement['scope_enabled'] !== 1): ?>
    <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center">
      <p class="text-sm text-slate-600 mb-1">Scope control is off for this engagement.</p>
      <p class="text-xs text-slate-500 mb-4">
        Useful for consulting work with a defined deliverable. Pure coaching engagements rarely need it.
      </p>
      <?php if (!$clientSide): ?>
        <form method="post" action="<?= h($act) ?>">
          <?= Csrf::field() ?><input type="hidden" name="action" value="enable">
          <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Turn it on</button>
        </form>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <p class="text-sm text-slate-600 mb-4">
      <?= (int) $summary['delivered'] ?> of <?= (int) $summary['total'] ?> delivered<?php
        if ((int) $summary['added'] > 0): ?>, <?= (int) $summary['added'] ?> added after the lock<?php endif; ?>.
    </p>

    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-4">
      <?php if ($items === []): ?>
        <div class="px-4 py-3 text-sm text-slate-500">Nothing agreed yet.</div>
      <?php endif; ?>
      <?php foreach ($items as $it): ?>
        <div class="px-4 py-3 flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-sm font-medium">
              <?= h($it['title']) ?>
              <?php if ((int) $it['is_original'] === 0): ?>
                <span class="ml-1 text-xs rounded bg-amber-50 text-amber-700 px-1">added</span>
              <?php endif; ?>
            </div>
            <?php if (!empty($it['description'])): ?>
              <div class="text-xs text-slate-600 mt-0.5 whitespace-pre-line"><?= h((string) $it['description']) ?></div>
            <?php endif; ?>
            <?php if (!empty($it['document_title'])): ?>
              <div class="text-xs text-emerald-700 mt-0.5">Delivered: <?= h((string) $it['document_title']) ?></div>
            <?php endif; ?>
          </div>
          <?php if ($canEdit && !$locked): ?>
            <form method="post" action="<?= h($act) ?>" class="flex-shrink-0">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="remove_item">
              <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
              <button class="text-xs text-slate-400 hover:text-red-600">remove</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($canEdit && !$locked): ?>
      <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-4 space-y-2">
        <?= Csrf::field() ?><input type="hidden" name="action" value="add_item">
        <input name="title" required placeholder="What we are going to deliver" class="<?= $field ?>">
        <input name="description" placeholder="Detail (optional)" class="<?= $field ?>">
        <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add to scope</button>
      </form>

      <form method="post" action="<?= h($act) ?>" class="mb-8">
        <?= Csrf::field() ?><input type="hidden" name="action" value="lock">
        <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Lock scope</button>
        <p class="text-xs text-slate-500 mt-1">
          After this, scope only changes through a change request that someone reviews.
        </p>
      </form>
    <?php endif; ?>

    <?php if ($locked && !$accepted && $canAccept): ?>
      <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-8">
        <?= Csrf::field() ?><input type="hidden" name="action" value="accept">
        <p class="text-sm text-slate-700 mb-2">This is what we have agreed to do.</p>
        <button class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Accept this scope</button>
      </form>
    <?php endif; ?>

    <h2 class="text-sm font-semibold mb-2">Change requests</h2>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-4">
      <?php if ($requests === []): ?>
        <div class="px-4 py-3 text-sm text-slate-500">None.</div>
      <?php endif; ?>
      <?php foreach ($requests as $r): ?>
        <div class="px-4 py-3">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="text-sm font-medium"><?= h($r['title']) ?></div>
              <div class="text-xs text-slate-600 mt-0.5 whitespace-pre-line"><?= h((string) $r['description']) ?></div>
              <?php if (!empty($r['justification'])): ?>
                <div class="text-xs text-slate-500 mt-1 italic"><?= h((string) $r['justification']) ?></div>
              <?php endif; ?>
              <?php if (!empty($r['review_note'])): ?>
                <div class="text-xs mt-1 <?= $r['status'] === 'declined' ? 'text-red-700' : 'text-emerald-700' ?>">
                  <?= h((string) ($r['reviewer_name'] ?? 'Reviewer')) ?>: <?= h((string) $r['review_note']) ?>
                </div>
              <?php endif; ?>
            </div>
            <span class="text-xs flex-shrink-0 <?= $r['status'] === 'pending' ? 'text-amber-600' : ($r['status'] === 'approved' ? 'text-emerald-600' : 'text-slate-400') ?>">
              <?= h((string) $r['status']) ?>
            </span>
          </div>

          <?php if ($canReview && (string) $r['status'] === 'pending'): ?>
            <form method="post" action="<?= h($act) ?>" class="mt-2 flex gap-2">
              <?= Csrf::field() ?>
              <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
              <input name="review_note" placeholder="Note" class="<?= $field ?> flex-1">
              <button name="action" value="approve" class="text-xs rounded border border-emerald-300 text-emerald-700 px-3 hover:bg-emerald-50">Approve</button>
              <button name="action" value="decline" class="text-xs rounded border border-slate-300 px-3 hover:bg-slate-50">Decline</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4 space-y-2">
      <?= Csrf::field() ?><input type="hidden" name="action" value="request_change">
      <h3 class="text-sm font-semibold">Ask for a change</h3>
      <input name="title" required placeholder="What you want added or changed" class="<?= $field ?>">
      <textarea name="description" rows="2" required placeholder="What it involves" class="<?= $field ?>"></textarea>
      <input name="justification" placeholder="Why (optional)" class="<?= $field ?>">
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Submit</button>
    </form>
  <?php endif; ?>
</div>
