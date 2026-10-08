<?php $pageTitle = 'Admin — Forum Categories'; ?>

<div class="mb-6">
    <a href="<?= url('admin') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Admin</a>
    <h1 class="text-2xl font-bold mt-1">Forum Categories</h1>
</div>

<div x-data="{ open: false }" class="mb-6">
    <button @click="open = !open"
            class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
        + Add category
    </button>

    <div x-show="open" x-cloak class="mt-4 bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="<?= url('admin/forum/categories') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                    <input type="text" name="slug" required pattern="[a-z0-9\-]+"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" name="description"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort order</label>
                    <input type="number" name="sort_order" value="0"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                    Create
                </button>
            </div>
        </form>
    </div>
</div>

<div class="divide-y divide-gray-100 bg-white rounded-xl border border-gray-200">
    <?php if (empty($categories)): ?>
        <p class="px-4 py-6 text-sm text-gray-400">No categories yet.</p>
    <?php endif; ?>
    <?php foreach ($categories as $cat): ?>
        <div class="flex items-center gap-4 px-4 py-3">
            <span class="text-xs text-gray-300 w-6 text-right"><?= $cat['sort_order'] ?></span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium"><?= h($cat['name']) ?></p>
                <p class="text-xs text-gray-400"><?= h($cat['description']) ?></p>
            </div>
            <a href="<?= url("forum/category/{$cat['slug']}") ?>" class="text-xs text-indigo-600 hover:underline">View</a>
            <form method="POST" action="<?= url("admin/forum/categories/{$cat['id']}/delete") ?>"
                  onsubmit="return confirm('Delete this category and all its posts?')">
                <?= csrf_field() ?>
                <button class="text-xs text-red-400 hover:text-red-600 transition-colors">Delete</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
