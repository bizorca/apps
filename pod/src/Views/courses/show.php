<?php $pageTitle = $course['title']; ?>

<div class="mb-6">
    <a href="<?= url('courses') ?>" class="text-sm text-indigo-600 hover:underline">&larr; All courses</a>
    <h1 class="text-2xl font-bold mt-2"><?= h($course['title']) ?></h1>
    <?php if ($course['description']): ?>
        <p class="text-gray-500 mt-1"><?= h($course['description']) ?></p>
    <?php endif; ?>
</div>

<?php if (empty($lessons)): ?>
    <p class="text-gray-400">No lessons published yet.</p>
<?php else: ?>

<?php
$total     = count($lessons);
$completed = count(array_intersect(array_column($lessons, 'id'), $completedIds));
$pct       = $total > 0 ? round(($completed / $total) * 100) : 0;
?>

<div class="mb-6">
    <div class="flex items-center justify-between text-sm text-gray-500 mb-1">
        <span><?= $completed ?> of <?= $total ?> lessons complete</span>
        <span><?= $pct ?>%</span>
    </div>
    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
        <div class="h-2 bg-indigo-500 rounded-full" style="width: <?= $pct ?>%"></div>
    </div>
</div>

<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php foreach ($lessons as $lesson): ?>
        <?php $done = in_array($lesson['id'], $completedIds); ?>
        <a href="<?= url("courses/{$course['slug']}/{$lesson['slug']}") ?>"
           class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
            <span class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0
                         <?= $done ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-300' ?>">
                <?php if ($done): ?>
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                <?php endif; ?>
            </span>
            <span class="text-sm <?= $done ? 'text-gray-400 line-through' : 'text-gray-900' ?>"><?= h($lesson['title']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php endif; ?>
