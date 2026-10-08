<?php $pageTitle = 'Post a Request'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Post a Request</h1>
        <a href="<?= url('/requests') ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Back to requests</a>
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
            Let the community know what you're looking for. Don't be shy &mdash; that's the whole point of a timebank.
        </p>

        <form method="POST" action="<?= url('/requests/create') ?>" enctype="multipart/form-data" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="request">

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1.5">What do you need? <span class="text-red-500">*</span></label>
                <input type="text" id="title" name="title"
                       value="<?= old('title') ?>"
                       required maxlength="200"
                       placeholder="e.g., Help moving furniture this Saturday"
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
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">Details <span class="text-red-500">*</span></label>
                <textarea id="description" name="description" rows="6"
                          required
                          placeholder="Give as much context as helpful. When do you need it? Where? Any specific requirements? The more detail, the easier it is for someone to step up and help..."
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('description') ?></textarea>
            </div>

            <div>
                <label for="image" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Image <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                       class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100 file:cursor-pointer">
            </div>

            <div class="flex items-center gap-3 pt-2">
                <a href="<?= url('/requests') ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                    Post Request
                </button>
            </div>
        </form>
    </div>
</div>
