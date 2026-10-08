<?php $pageTitle = 'Lessons — ' . $course['title']; ?>

<div class="mb-6">
    <a href="<?= url('admin/courses') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Courses</a>
    <h1 class="text-2xl font-bold mt-1"><?= h($course['title']) ?> — Lessons</h1>
</div>

<!-- Add lesson form -->
<div x-data="{ open: false }" class="mb-6">
    <button @click="open = !open"
            class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
        + Add lesson
    </button>

    <div x-show="open" x-cloak class="mt-4 bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="<?= url("admin/courses/{$course['id']}/lessons") ?>" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="title" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                    <input type="text" name="slug" required pattern="[a-z0-9\-]+"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Video URL (YouTube or Vimeo)</label>
                    <input type="url" name="video_url"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                           placeholder="https://youtube.com/watch?v=...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort order</label>
                    <input type="number" name="sort_order" value="<?= count($lessons) + 1 ?>"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                <textarea name="content" rows="5"
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none font-mono"
                          placeholder="Lesson notes, resources, etc."></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                    Add lesson
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lessons list -->
<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php if (empty($lessons)): ?>
        <p class="px-4 py-6 text-sm text-gray-400">No lessons yet.</p>
    <?php endif; ?>
    <?php foreach ($lessons as $i => $lesson): ?>
        <div class="flex items-center gap-4 px-4 py-3">
            <span class="text-xs text-gray-300 w-6 text-right shrink-0"><?= $lesson['sort_order'] ?></span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium"><?= h($lesson['title']) ?></p>
                <p class="text-xs text-gray-400">slug: <?= h($lesson['slug']) ?></p>
            </div>
            <?php if ($lesson['video_embed']): ?>
                <span class="text-xs bg-red-50 text-red-600 px-2 py-0.5 rounded-full">Video</span>
            <?php endif; ?>
            <!-- Move up/down -->
            <div class="flex gap-1">
                <?php if ($i > 0): ?>
                    <form method="POST" action="<?= url("admin/lessons/{$lesson['id']}/move-up") ?>">
                        <?= csrf_field() ?>
                        <button class="text-xs text-gray-400 hover:text-indigo-600 transition-colors px-1" title="Move up">&#8593;</button>
                    </form>
                <?php else: ?>
                    <span class="w-5"></span>
                <?php endif; ?>
                <?php if ($i < count($lessons) - 1): ?>
                    <form method="POST" action="<?= url("admin/lessons/{$lesson['id']}/move-down") ?>">
                        <?= csrf_field() ?>
                        <button class="text-xs text-gray-400 hover:text-indigo-600 transition-colors px-1" title="Move down">&#8595;</button>
                    </form>
                <?php else: ?>
                    <span class="w-5"></span>
                <?php endif; ?>
            </div>
            <form method="POST" action="<?= url("admin/lessons/{$lesson['id']}/delete") ?>"
                  onsubmit="return confirm('Delete this lesson?')">
                <?= csrf_field() ?>
                <button class="text-xs text-red-400 hover:text-red-600 transition-colors">Delete</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
