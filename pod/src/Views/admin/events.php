<?php $pageTitle = 'Admin — Events'; ?>

<div class="mb-6">
    <a href="<?= url('admin') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Admin</a>
    <h1 class="text-2xl font-bold mt-1">Events</h1>
</div>

<div x-data="{ open: false }" class="mb-6">
    <button @click="open = !open"
            class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
        + Create event
    </button>

    <div x-show="open" x-cloak class="mt-4 bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="<?= url('admin/events') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="title" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Starts at</label>
                    <input type="datetime-local" name="starts_at" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ends at</label>
                    <input type="datetime-local" name="ends_at" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none"></textarea>
                </div>
                <div class="sm:col-span-2 flex items-center gap-2">
                    <input type="checkbox" name="create_zoom" id="create_zoom" value="1"
                           class="rounded border-gray-300 text-indigo-600">
                    <label for="create_zoom" class="text-sm text-gray-700">
                        Automatically create a Zoom meeting
                    </label>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                    Create event
                </button>
            </div>
        </form>
    </div>
</div>

<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php if (empty($events)): ?>
        <p class="px-4 py-6 text-sm text-gray-400">No events yet.</p>
    <?php endif; ?>
    <?php foreach ($events as $event): ?>
        <div class="flex items-center gap-4 px-4 py-3">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium"><?= h($event['title']) ?></p>
                <p class="text-xs text-gray-400">
                    <?= (new DateTime($event['starts_at']))->format('M j, Y g:ia') ?>
                    <?php if ($event['zoom_meeting_id']): ?>
                        &middot; <span class="text-blue-600">Zoom</span>
                    <?php endif; ?>
                </p>
            </div>
            <span class="text-xs <?= $event['is_published'] ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' ?> px-2 py-0.5 rounded-full">
                <?= $event['is_published'] ? 'Published' : 'Draft' ?>
            </span>
            <a href="<?= url("events/{$event['id']}") ?>" class="text-xs text-indigo-600 hover:underline">View</a>
            <form method="POST" action="<?= url("admin/events/{$event['id']}/delete") ?>"
                  onsubmit="return confirm('Delete this event?')">
                <?= csrf_field() ?>
                <button class="text-xs text-red-400 hover:text-red-600 transition-colors">Delete</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
