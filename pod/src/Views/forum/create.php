<?php $pageTitle = 'New Discussion'; ?>

<div class="max-w-2xl">
    <div class="mb-6">
        <a href="<?= url('forum') ?>" class="text-sm text-indigo-600 hover:underline">&larr; Forum</a>
        <h1 class="text-2xl font-bold mt-1">Start a discussion</h1>
    </div>

    <form method="POST" action="<?= url('forum/new') ?>" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select name="category_id" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                <option value="">Select a category…</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"
                            <?= ($selected === $cat['slug']) ? 'selected' : '' ?>>
                        <?= h($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input type="text" name="title" required maxlength="255"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                   placeholder="What's on your mind?">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Body</label>
            <textarea name="body" rows="6" required
                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none"
                      placeholder="Share the details…"></textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                Post
            </button>
        </div>
    </form>
</div>
