<?php $pageTitle = 'Home'; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left: Feed -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Recent Forum Activity -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">Recent Discussions</h2>
                <a href="<?= url('forum') ?>" class="text-sm text-indigo-600 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
                <?php if (empty($recentPosts)): ?>
                    <p class="px-4 py-6 text-sm text-gray-400">No discussions yet. Be the first to post!</p>
                <?php endif; ?>
                <?php foreach ($recentPosts as $post): ?>
                    <a href="<?= url("forum/post/{$post['id']}") ?>"
                       class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors block">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate"><?= h($post['title']) ?></p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                <?= h($post['first_name']) ?> in <span class="text-indigo-500"><?= h($post['category_name']) ?></span>
                                &middot; <?= timeAgo($post['created_at']) ?>
                            </p>
                        </div>
                        <?php if ($post['reply_count'] > 0): ?>
                            <span class="text-xs text-gray-400 shrink-0"><?= $post['reply_count'] ?> replies</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Courses -->
        <?php if (!empty($courses)): ?>
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">Courses</h2>
                <a href="<?= url('courses') ?>" class="text-sm text-indigo-600 hover:underline">All courses</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach ($courses as $course): ?>
                    <a href="<?= url("courses/{$course['slug']}") ?>"
                       class="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-300 transition-colors">
                        <?php if ($course['thumbnail_url']): ?>
                            <img src="<?= h($course['thumbnail_url']) ?>" alt="" class="w-full h-32 object-cover rounded-lg mb-3">
                        <?php endif; ?>
                        <p class="font-medium text-gray-900"><?= h($course['title']) ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?= $course['lesson_count'] ?> lessons</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Right: Events sidebar -->
    <div class="space-y-6">
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">Upcoming Events</h2>
                <a href="<?= url('events') ?>" class="text-sm text-indigo-600 hover:underline">All</a>
            </div>
            <div class="space-y-3">
                <?php if (empty($upcomingEvents)): ?>
                    <p class="text-sm text-gray-400">No upcoming events.</p>
                <?php endif; ?>
                <?php foreach ($upcomingEvents as $event): ?>
                    <a href="<?= url("events/{$event['id']}") ?>"
                       class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-300 transition-colors">
                        <p class="font-medium text-sm text-gray-900"><?= h($event['title']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">
                            <?= (new DateTime($event['starts_at']))->format('D, M j \a\t g:ia') ?>
                        </p>
                        <?php if ($event['zoom_join_url']): ?>
                            <span class="mt-2 inline-block text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">Zoom</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick links -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-2">
            <p class="text-sm font-medium text-gray-700 mb-3">Quick links</p>
            <a href="<?= url('forum/new') ?>" class="block w-full text-center text-sm bg-indigo-600 text-white rounded-lg px-4 py-2 hover:bg-indigo-700 transition-colors">Start a discussion</a>
            <a href="<?= url('tickets/new') ?>" class="block w-full text-center text-sm bg-white border border-gray-200 text-gray-700 rounded-lg px-4 py-2 hover:bg-gray-50 transition-colors">Open a support ticket</a>
        </div>
    </div>

</div>
