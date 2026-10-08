<?php
/** @var array $engagement @var array $deliverables @var array $clientFiles @var array $requests
 *  @var bool $clientSide @var int $maxBytes @var string $maxLabel @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Storage;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
echo View::render('orgs._engnav', ['engagement' => $engagement, 'active' => 'documents'], null);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';

$row = static function (array $d): string {
    $badge = match ((string) $d['status']) {
        'delivered'    => 'bg-emerald-50 text-emerald-700',
        'acknowledged' => 'bg-emerald-100 text-emerald-800',
        'in_review'    => 'bg-amber-50 text-amber-700',
        default        => 'bg-slate-100 text-slate-600',
    };
    return '<a href="' . h(url('/documents/' . $d['id'])) . '" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">'
        . '<div class="min-w-0"><div class="text-sm font-medium truncate">' . h($d['title']) . '</div>'
        . '<div class="text-xs text-slate-500">' . h((string) ($d['original_name'] ?? '')) . ' · '
        . h(Storage::humanBytes((int) ($d['byte_size'] ?? 0)))
        . (($d['version_number'] ?? 1) > 1 ? ' · v' . (int) $d['version_number'] : '') . '</div></div>'
        . '<span class="text-xs rounded-full px-2 py-0.5 ' . $badge . '">' . h(str_replace('_', ' ', (string) $d['status'])) . '</span></a>';
};
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-1"><?= h($engagement['title']) ?></h1>
  <p class="text-sm text-slate-600 mb-6">Documents</p>

  <?php if ($requests !== []): ?>
    <h2 class="text-sm font-semibold mb-2">What we need from you</h2>
    <div class="space-y-3 mb-8">
      <?php foreach ($requests as $r): if ($r['status'] === 'cancelled') continue; ?>
        <div class="rounded-lg border <?= $r['status'] === 'complete' ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40' ?> p-4">
          <div class="flex items-baseline justify-between mb-2">
            <div class="text-sm font-medium"><?= h($r['title']) ?></div>
            <div class="text-xs text-slate-600">
              <?= (int) $r['done_count'] ?> of <?= (int) $r['total_count'] ?>
              <?php if (!empty($r['due_on'])): ?> · due <?= h(date('j M', strtotime((string) $r['due_on']))) ?><?php endif; ?>
            </div>
          </div>
          <ul class="space-y-2">
            <?php foreach ($r['items'] as $item): ?>
              <li class="text-sm flex items-start gap-2">
                <span class="mt-1 h-2 w-2 rounded-full flex-shrink-0 <?= $item['document_id'] ? 'bg-emerald-500' : 'bg-slate-300' ?>"></span>
                <div class="flex-1">
                  <span class="<?= $item['document_id'] ? 'line-through text-slate-400' : '' ?>"><?= h($item['label']) ?></span>
                  <?php if (!$item['required']): ?><span class="text-xs text-slate-400"> (optional)</span><?php endif; ?>
                  <?php if (!$item['document_id']): ?>
                    <form method="post" enctype="multipart/form-data"
                          action="<?= h(url('/engagements/' . $engagement['id'] . '/documents')) ?>" class="mt-1 flex gap-2">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="request_item_id" value="<?= (int) $item['id'] ?>">
                      <input type="hidden" name="title" value="<?= h($item['label']) ?>">
                      <input type="hidden" name="context" value="client_file">
                      <input type="file" name="file" required class="text-xs">
                      <button class="text-xs rounded border border-slate-300 px-2 py-1 bg-white hover:bg-slate-50">Upload</button>
                    </form>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 class="text-sm font-semibold mb-2">From <?= h($tenant['name']) ?></h2>
  <?php if ($deliverables === []): ?>
    <p class="text-sm text-slate-500 mb-6">Nothing yet.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-8">
      <?php foreach ($deliverables as $d) { echo $row($d); } ?>
    </div>
  <?php endif; ?>

  <h2 class="text-sm font-semibold mb-2">From the client</h2>
  <?php if ($clientFiles === []): ?>
    <p class="text-sm text-slate-500 mb-6">Nothing yet.</p>
  <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-8">
      <?php foreach ($clientFiles as $d) { echo $row($d); } ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data"
        action="<?= h(url('/engagements/' . $engagement['id'] . '/documents')) ?>"
        class="rounded-lg border border-slate-200 bg-white p-5 space-y-3">
    <?= Csrf::field() ?>
    <h2 class="text-sm font-semibold">Upload</h2>
    <input name="title" placeholder="Title (optional — defaults to the filename)" class="<?= $field ?>">
    <input type="file" name="file" required class="text-sm">
    <?php if (!$clientSide): ?>
      <select name="context" class="<?= $field ?>">
        <option value="deliverable">A deliverable for the client</option>
        <option value="client_file">A file the client gave us</option>
      </select>
    <?php endif; ?>
    <p class="text-xs text-slate-500">Up to <?= h($maxLabel) ?>. PDFs, Office documents, images, CSV, ZIP.</p>
    <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Upload</button>
  </form>

  <?php if (!$clientSide): ?>
    <form method="post" action="<?= h(url('/engagements/' . $engagement['id'] . '/document-requests')) ?>"
          class="rounded-lg border border-slate-200 bg-white p-5 space-y-3 mt-4">
      <?= Csrf::field() ?>
      <h2 class="text-sm font-semibold">Ask the client for documents</h2>
      <input name="title" required placeholder="e.g. Financial pack" class="<?= $field ?>">
      <?php for ($i = 0; $i < 4; $i++): ?>
        <input name="items[]" placeholder="<?= $i === 0 ? 'Trailing 12 P&amp;L' : 'Another item (optional)' ?>" class="<?= $field ?>">
      <?php endfor; ?>
      <input name="due_on" type="date" class="<?= $field ?>">
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Send the request</button>
    </form>
  <?php endif; ?>
</div>
