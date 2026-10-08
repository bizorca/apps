<?php
/** @var array $document @var ?array $engagement @var array $versions @var array $links
 *  @var bool $clientSide @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Storage;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$act = url('/documents/' . $document['id']);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
$delivered = in_array((string) $document['status'], ['delivered', 'acknowledged'], true);
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <?php if ($engagement !== null): ?>
    <p class="text-xs text-slate-500 mb-1"><?= h($engagement['title']) ?></p>
  <?php endif; ?>
  <h1 class="text-xl font-semibold mb-1"><?= h($document['title']) ?></h1>
  <p class="text-sm text-slate-500 mb-6">
    <?= h(str_replace('_', ' ', (string) $document['status'])) ?>
    <?php if (!empty($document['description'])): ?> · <?= h((string) $document['description']) ?><?php endif; ?>
  </p>

  <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-6">
    <?php foreach ($versions as $i => $v): ?>
      <div class="flex items-center justify-between px-4 py-3">
        <div class="min-w-0">
          <div class="text-sm font-medium truncate"><?= h((string) $v['original_name']) ?></div>
          <div class="text-xs text-slate-500">
            v<?= (int) $v['version_number'] ?> · <?= h(Storage::humanBytes((int) $v['byte_size'])) ?>
            · <?= h(date('j M Y', strtotime((string) $v['created_at']))) ?>
          </div>
        </div>
        <a href="<?= h(url('/documents/' . $document['id'] . '/download' . ($clientSide ? '' : '/' . (int) $v['id']))) ?>"
           class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-50">Download</a>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$clientSide && (string) $document['context'] === 'deliverable'): ?>
    <?php if (!$delivered): ?>
      <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-4 space-y-3">
        <?= Csrf::field() ?><input type="hidden" name="action" value="deliver">
        <input name="note" placeholder="A note for the client (optional)" class="<?= $field ?>">
        <button class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Deliver to the client</button>
      </form>
    <?php else: ?>
      <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 mb-4 text-sm text-emerald-900">
        Delivered<?= (string) $document['status'] === 'acknowledged' ? ' and acknowledged' : ', awaiting acknowledgment' ?>.
      </div>
    <?php endif; ?>

    <div class="rounded-lg border border-slate-200 bg-white p-4 mb-4">
      <h2 class="text-sm font-semibold mb-1">Share with someone outside</h2>
      <p class="text-xs text-slate-500 mb-3">A banker, an attorney. The link expires and every view is logged.</p>
      <form method="post" action="<?= h($act) ?>" class="space-y-2">
        <?= Csrf::field() ?><input type="hidden" name="action" value="share">
        <div class="grid grid-cols-3 gap-2">
          <input name="label" placeholder="Who is it for?" class="<?= $field ?> col-span-2">
          <input name="days" type="number" min="1" max="90" value="14" class="<?= $field ?>">
        </div>
        <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Create a link</button>
      </form>

      <?php if ($links !== []): ?>
        <ul class="mt-3 pt-3 border-t border-slate-100 space-y-2">
          <?php foreach ($links as $l): $dead = $l['revoked_at'] !== null || strtotime((string) $l['expires_at']) <= time(); ?>
            <li class="flex items-center justify-between text-xs">
              <span class="<?= $dead ? 'text-slate-400 line-through' : '' ?>">
                <?= h((string) ($l['label'] ?? 'Unnamed')) ?>
                · <?= (int) $l['view_count'] ?> view<?= (int) $l['view_count'] === 1 ? '' : 's' ?>
                · expires <?= h(date('j M', strtotime((string) $l['expires_at']))) ?>
              </span>
              <?php if (!$dead): ?>
                <form method="post" action="<?= h($act) ?>">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="revoke_share">
                  <input type="hidden" name="link_id" value="<?= (int) $l['id'] ?>">
                  <button class="underline text-slate-500">revoke</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($clientSide && $delivered && (string) $document['status'] !== 'acknowledged'): ?>
    <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4">
      <?= Csrf::field() ?><input type="hidden" name="action" value="acknowledge">
      <p class="text-sm text-slate-600 mb-2">Let your advisor know you have this.</p>
      <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Acknowledge receipt</button>
    </form>
  <?php endif; ?>
</div>
