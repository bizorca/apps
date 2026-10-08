<?php $pageTitle = 'Admin — Courses'; ?>

<div class="mb-6">
    <a href="<?= url('admin') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Admin</a>
    <h1 class="text-2xl font-bold mt-1">Courses</h1>
</div>

<!-- Add course form -->
<div x-data="{ open: false }" class="mb-6">
    <button @click="open = !open"
            class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
        + Add course
    </button>

    <div x-show="open" x-cloak class="mt-4 bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="<?= url('admin/courses') ?>" class="space-y-4">
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
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                           placeholder="e.g. getting-started">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Thumbnail URL</label>
                    <input type="url" name="thumbnail_url"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort order</label>
                    <input type="number" name="sort_order" value="0"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2"
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none"></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                    Create course
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Course list -->
<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php if (empty($courses)): ?>
        <p class="px-4 py-6 text-sm text-gray-400">No courses yet.</p>
    <?php endif; ?>
    <?php foreach ($courses as $course): ?>
        <div class="flex items-center gap-4 px-4 py-3">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900"><?= h($course['title']) ?></p>
                <p class="text-xs text-gray-400"><?= $course['lesson_count'] ?> lessons &middot; slug: <?= h($course['slug']) ?></p>
            </div>
            <span class="text-xs <?= $course['is_published'] ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' ?> px-2 py-0.5 rounded-full">
                <?= $course['is_published'] ? 'Published' : 'Draft' ?>
            </span>
            <a href="<?= url("admin/courses/{$course['id']}/lessons") ?>"
               class="text-xs text-indigo-600 hover:underline">Lessons</a>
            <form method="POST" action="<?= url("admin/courses/{$course['id']}/toggle") ?>">
                <?= csrf_field() ?>
                <button class="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                    <?= $course['is_published'] ? 'Unpublish' : 'Publish' ?>
                </button>
            </form>
            <form method="POST" action="<?= url("admin/courses/{$course['id']}/delete") ?>"
                  onsubmit="return confirm('Delete this course and all its lessons?')">
                <?= csrf_field() ?>
                <button class="text-xs text-red-400 hover:text-red-600 transition-colors">Delete</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
