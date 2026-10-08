<?php $pageTitle = 'Post an Offer'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Post an Offer</h1>
        <a href="<?= url('/offers') ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Back to offers</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Share something you can offer the community. This could be a skill, a service, or anything you're willing to exchange time for.
        </p>

        <form method="POST" action="<?= url('/offers/create') ?>" enctype="multipart/form-data" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="offer">

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1.5">Title <span class="text-red-500">*</span></label>
                <input type="text" id="title" name="title"
                       value="<?= old('title') ?>"
                       required maxlength="200"
                       placeholder="e.g., Guitar lessons for beginners"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
            </div>

            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                <select id="category_id" name="category_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white">
                    <option value="">-- Select a category --</option>
                    <?php foreach ($categories ?? [] as $cat): ?>
                        <option value="<?= (int)($cat['id'] ?? 0) ?>"
                                <?= old('category_id') == ($cat['id'] ?? '') ? 'selected' : '' ?>>
                            <?= e($cat['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">Description <span class="text-red-500">*</span></label>
                <textarea id="description" name="description" rows="6"
                          required
                          placeholder="Describe what you're offering in detail. Include any relevant experience, availability, location preferences, or anything else the community should know..."
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('description') ?></textarea>
            </div>

            <div>
                <label for="image" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Image <span class="text-gray-400 font-normal">(optional, JPG/PNG, max 4MB)</span>
                </label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                       class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 file:cursor-pointer">
            </div>

            <div class="flex items-center gap-3 pt-2">
                <a href="<?= url('/offers') ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                    Post Offer
                </button>
            </div>
        </form>
    </div>
</div>
