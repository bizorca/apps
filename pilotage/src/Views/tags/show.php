<?php
/** @var array $tag @var array $objects @var array $allTags @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$act = url('/tags/' . $tag['slug']);
$field = 'rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-900 focus:outline-none';

$href = static function (array $o): string {
    return match ((string) $o['object_type']) {
        'task'       => url('/tasks/' . $o['id']),
        'document'   => url('/documents/' . $o['id']),
        'issue'      => url('/engagements/' . $o['engagement_id'] . '/scoreboard'),
        'client_org' => url('/clients/' . $o['id']),
        'engagement' => url('/engagements/' . $o['id'] . '/journey'),
        default      => url('/'),
    };
};
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <p class="text-xs text-slate-500 mb-1"><a href="<?= h(url('/tags')) ?>" class="hover:underline">Tags</a></p>
  <h1 class="text-xl font-semibold mb-1"><?= h($tag['name']) ?></h1>
  <p class="text-sm text-slate-600 mb-6">
    <?= count($objects) ?> thing<?= count($objects) === 1 ? '' : 's' ?> across your clients.
  </p>

  <?php
  $byOrg = [];
  foreach ($objects as $o) { $byOrg[(string) ($o['org_name'] ?? 'Unassigned')][] = $o; }
  ksort($byOrg);
  ?>

  <?php foreach ($byOrg as $orgName => $items): ?>
    <h2 class="text-sm font-semibold mb-2 mt-5"><?= h($orgName) ?></h2>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
      <?php foreach ($items as $o): ?>
        <a href="<?= h($href($o)) ?>" class="flex items-center justify-between px-4 py-2.5 hover:bg-slate-50">
          <span class="text-sm"><?= h((string) $o['title']) ?></span>
          <span class="text-xs text-slate-400">
            <?= h(str_replace('_', ' ', (string) $o['object_type'])) ?>
            <?php if (!empty($o['status'])): ?> · <?= h(str_replace('_', ' ', (string) $o['status'])) ?><?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <?php if ($objects === []): ?>
    <p class="text-sm text-slate-500">Nothing carries this tag any more.</p>
  <?php endif; ?>

  <div class="mt-8 pt-6 border-t border-slate-200 space-y-3">
    <h2 class="text-sm font-semibold">Tidy up</h2>

    <form method="post" action="<?= h($act) ?>" class="flex gap-2">
      <?= Csrf::field() ?><input type="hidden" name="action" value="rename">
      <input name="name" value="<?= h($tag['name']) ?>" class="<?= $field ?> flex-1">
      <button class="rounded border border-slate-300 px-3 text-sm hover:bg-slate-50">Rename</button>
    </form>

    <?php if (count($allTags) > 1): ?>
      <form method="post" action="<?= h($act) ?>" class="flex gap-2">
        <?= Csrf::field() ?><input type="hidden" name="action" value="merge">
        <select name="into" class="<?= $field ?> flex-1">
          <?php foreach ($allTags as $t): if ((int) $t['id'] === (int) $tag['id']) continue; ?>
            <option value="<?= h($t['slug']) ?>"><?= h($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="rounded border border-slate-300 px-3 text-sm hover:bg-slate-50">Fold into</button>
      </form>
      <p class="text-xs text-slate-500">
        Folding moves everything across and removes this tag. It cannot be undone.
      </p>
    <?php endif; ?>
  </div>
</div>
