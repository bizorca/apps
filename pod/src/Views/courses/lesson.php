<?php $pageTitle = $lesson['title'] . ' — ' . $course['title']; ?>

<div class="flex gap-8">

    <!-- Sidebar -->
    <aside class="hidden lg:block w-64 shrink-0">
        <a href="<?= url("courses/{$course['slug']}") ?>" class="text-sm text-indigo-600 hover:underline block mb-4">&larr; <?= h($course['title']) ?></a>
        <nav class="space-y-1">
            <?php foreach ($allLessons as $l): ?>
                <a href="<?= url("courses/{$course['slug']}/{$l['slug']}") ?>"
                   class="block px-3 py-2 rounded-lg text-sm
                          <?= $l['id'] == $lesson['id'] ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-100' ?>">
                    <?= h($l['title']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Main content -->
    <div class="flex-1 min-w-0">
        <h1 class="text-2xl font-bold mb-4"><?= h($lesson['title']) ?></h1>

        <?php if ($lesson['video_embed']): ?>
            <div class="mb-6"><?= $lesson['video_embed'] ?></div>
        <?php endif; ?>

        <?php if ($lesson['content']): ?>
            <div class="prose text-gray-700 mb-8">
                <?= nl2br(h($lesson['content'])) ?>
            </div>
        <?php endif; ?>

        <?php if (!$completed): ?>
            <form method="POST" action="<?= url("courses/{$course['slug']}/{$lesson['slug']}/complete") ?>">
                <?= csrf_field() ?>
                <button type="submit"
                        class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-indigo-700 transition-colors">
                    Mark as complete
                </button>
            </form>
        <?php else: ?>
            <p class="text-sm text-green-600 font-medium">Lesson complete!</p>
        <?php endif; ?>
    </div>

</div>
