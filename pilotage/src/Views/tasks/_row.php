<?php
/** @var array $t @var bool $sub */
$overdue = $t['due_on'] !== null && $t['status'] !== 'done' && strtotime((string) $t['due_on']) < strtotime(date('Y-m-d'));
$done = $t['status'] === 'done';
?>
<a href="<?= h(url('/tasks/' . $t['id'])) ?>"
   class="flex items-start justify-between gap-3 px-4 py-2.5 hover:bg-slate-50 <?= !empty($sub) ? 'pl-10' : '' ?>">
  <div class="min-w-0">
    <div class="text-sm <?= $done ? 'line-through text-slate-400' : '' ?>"><?= h($t['title']) ?></div>
    <div class="text-xs text-slate-500">
      <?php if (!empty($t['owner_name'])): ?><?= h($t['owner_name']) ?> · <?php endif; ?>
      <?php if (!empty($t['org_name'])): ?><?= h($t['org_name']) ?> · <?php endif; ?>
      <?php if ($t['due_on'] !== null): ?>
        <span class="<?= $overdue ? 'text-red-600 font-medium' : '' ?>">
          due <?= h(date('j M', strtotime((string) $t['due_on']))) ?>
        </span>
      <?php else: ?>
        no date
      <?php endif; ?>
      <?php if ((int) $t['miss_count'] > 0 && !$done): ?>
        <span class="ml-1 rounded bg-red-50 text-red-700 px-1">missed &times;<?= (int) $t['miss_count'] ?></span>
      <?php endif; ?>
      <?php if (!empty($t['escalated_issue_id'])): ?>
        <span class="ml-1 rounded bg-amber-50 text-amber-700 px-1">on the issues list</span>
      <?php endif; ?>
    </div>
  </div>
  <span class="text-xs text-slate-400 flex-shrink-0"><?= h(str_replace('_', ' ', (string) $t['status'])) ?></span>
</a>
