<?php
/** @var array $task @var array $engagement @var array $subtasks @var array $comments @var bool $clientSide @var array $user @var array $tenant */
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Core\View;
echo View::render('orgs._nav', ['user' => $user, 'tenant' => $tenant], null);
$act = url('/tasks/' . $task['id']);
$field = 'w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none';
$done = $task['status'] === 'done';
$needsEvidence = $task['evidence_required'] !== 'none';
?>
<div class="max-w-2xl mx-auto px-4 py-8">
  <p class="text-xs text-slate-500 mb-1"><?= h($engagement['title']) ?></p>
  <h1 class="text-xl font-semibold mb-2 <?= $done ? 'line-through text-slate-400' : '' ?>"><?= h($task['title']) ?></h1>

  <div class="text-sm text-slate-600 mb-4">
    <?php if ($task['due_on'] !== null): ?>Due <?= h(date('j M Y', strtotime((string) $task['due_on']))) ?><?php endif; ?>
    <?php if ((int) $task['miss_count'] > 0): ?>
      · <span class="text-red-600">missed &times;<?= (int) $task['miss_count'] ?></span>
    <?php endif; ?>
  </div>

  <?php if (!empty($task['escalated_issue_id'])): ?>
    <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
      This has come and gone more than once, so it is on the issues list now — worth asking what is actually in the way
      rather than resending the reminder.
    </div>
  <?php endif; ?>

  <?php if (!empty($task['definition_of_done'])): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-4 mb-4">
      <div class="text-xs font-semibold text-slate-500 mb-1">Done means</div>
      <div class="text-sm"><?= h((string) $task['definition_of_done']) ?></div>
    </div>
  <?php endif; ?>

  <?php if ($subtasks !== []): ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-4">
      <?php foreach ($subtasks as $s): ?>
        <?= View::render('tasks._row', ['t' => $s, 'sub' => false], null) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$done): ?>
    <form method="post" action="<?= h($act) ?>" class="rounded-lg border border-slate-200 bg-white p-4 mb-6 space-y-3">
      <?= Csrf::field() ?><input type="hidden" name="action" value="complete">
      <?php if ($needsEvidence): ?>
        <label class="block text-sm font-medium">
          <?= $task['evidence_required'] === 'note' ? 'What happened?' : 'Evidence' ?>
        </label>
        <textarea name="evidence" rows="3" required class="<?= $field ?>"></textarea>
      <?php endif; ?>
      <button class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Mark done</button>
    </form>
  <?php else: ?>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 mb-6">
      <div class="text-sm text-emerald-900">Done<?= $task['completed_at'] ? ' on ' . h(date('j M Y', strtotime((string) $task['completed_at']))) : '' ?>.</div>
      <?php if (!empty($task['evidence_note'])): ?>
        <div class="text-sm text-emerald-800 mt-1 whitespace-pre-line"><?= h((string) $task['evidence_note']) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= h($act) ?>" class="mt-2">
        <?= Csrf::field() ?><input type="hidden" name="action" value="reopen">
        <button class="text-xs underline text-emerald-900">Reopen</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if (!$clientSide): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-4 mb-6">
      <h2 class="text-sm font-semibold mb-2">Tags</h2>
      <?php if (!empty($tags)): ?>
        <div class="flex flex-wrap gap-1.5 mb-3">
          <?php foreach ($tags as $tg): ?>
            <a href="<?= h(url('/tags/' . $tg['slug'])) ?>"
               class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 hover:bg-slate-200">
              <?= h($tg['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form method="post" action="<?= h($act) ?>" class="flex gap-2">
        <?= Csrf::field() ?><input type="hidden" name="action" value="tags">
        <input name="tags" value="<?= h((string) ($tagString ?? '')) ?>"
               placeholder="Comma separated, e.g. bank financing, hiring"
               class="<?= $field ?> flex-1">
        <button class="rounded border border-slate-300 px-3 text-sm hover:bg-slate-50">Save</button>
      </form>
      <p class="text-xs text-slate-500 mt-1">Only you and your colleagues see these.</p>
    </div>
  <?php endif; ?>

  <h2 class="text-sm font-semibold mb-2">Discussion</h2>
  <?php if ($comments === []): ?>
    <p class="text-sm text-slate-500 mb-3">Nothing yet.</p>
  <?php else: ?>
    <div class="space-y-3 mb-3">
      <?php foreach ($comments as $c): ?>
        <div class="rounded-lg border <?= (int) $c['client_visible'] === 0 ? 'border-amber-200 bg-amber-50/50' : 'border-slate-200 bg-white' ?> p-3">
          <div class="text-xs text-slate-500 mb-1">
            <?= h((string) ($c['author_label'] ?? 'Someone')) ?>
            · <?= h(date('j M, H:i', strtotime((string) $c['created_at']))) ?>
            <?php if ((int) $c['client_visible'] === 0): ?><span class="ml-1 rounded bg-amber-100 px-1">internal</span><?php endif; ?>
          </div>
          <div class="text-sm whitespace-pre-line"><?= h((string) $c['body']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= h($act) ?>" class="space-y-2">
    <?= Csrf::field() ?><input type="hidden" name="action" value="comment">
    <textarea name="body" rows="3" required placeholder="Add a comment" class="<?= $field ?>"></textarea>
    <div class="flex items-center gap-3">
      <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Comment</button>
      <?php if (!$clientSide): ?>
        <label class="text-xs text-slate-600 flex items-center gap-1">
          <input type="checkbox" name="internal" value="1"> internal only
        </label>
      <?php endif; ?>
    </div>
  </form>
</div>
