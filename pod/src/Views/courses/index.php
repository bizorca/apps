<?php $pageTitle = 'Courses'; ?>

<div class="mb-6">
    <h1 class="text-2xl font-bold">Courses</h1>
    <p class="text-gray-500 mt-1">Business coaching and education for Bizorca users.</p>
</div>

<?php if (empty($courses)): ?>
    <div class="text-center py-16 text-gray-400">No courses published yet.</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($courses as $course): ?>
        <a href="<?= url("courses/{$course['slug']}") ?>"
           class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-indigo-300 transition-colors">
            <?php if ($course['thumbnail_url']): ?>
                <img src="<?= h($course['thumbnail_url']) ?>" alt="" class="w-full h-40 object-cover">
            <?php else: ?>
                <div class="w-full h-40 bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
                    <span class="text-4xl">📚</span>
                </div>
            <?php endif; ?>
            <div class="p-4">
                <h2 class="font-semibold text-gray-900"><?= h($course['title']) ?></h2>
                <?php if ($course['description']): ?>
                    <p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= h($course['description']) ?></p>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-3"><?= $course['lesson_count'] ?> lessons</p>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
