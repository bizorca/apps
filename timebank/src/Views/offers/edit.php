<?php
$isOffer = ($offer['type'] ?? 'offer') === 'offer';
$pageTitle = 'Edit ' . ($isOffer ? 'Offer' : 'Request');
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900"><?= $isOffer ? 'Edit Offer' : 'Edit Request' ?></h1>
        <a href="<?= url('/offers/' . ($offer['id'] ?? '')) ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Back</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <form method="POST" action="<?= url('/offers/' . ($offer['id'] ?? '') . '/edit') ?>" enctype="multipart/form-data" class="space-y-5">
            <?= csrf_field() ?>

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1.5">Title <span class="text-red-500">*</span></label>
                <input type="text" id="title" name="title"
                       value="<?= old('title', $offer['title'] ?? '') ?>"
                       required maxlength="200"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
            </div>

            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                <select id="category_id" name="category_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white">
                    <option value="">-- Select a category --</option>
                    <?php foreach ($categories ?? [] as $cat): ?>
                        <?php $selected = (string)old('category_id', $offer['category_id'] ?? '') === (string)($cat['id'] ?? ''); ?>
                        <option value="<?= (int)($cat['id'] ?? 0) ?>" <?= $selected ? 'selected' : '' ?>><?= e($cat['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">Description <span class="text-red-500">*</span></label>
                <textarea id="description" name="description" rows="7"
                          required
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('description', $offer['description'] ?? '') ?></textarea>
            </div>

            <!-- Current image -->
            <?php if (!empty($offer['image_path'])): ?>
                <div>
                    <p class="text-sm font-medium text-gray-700 mb-2">Current image</p>
                    <div class="relative inline-block">
                        <img src="<?= e(media_url($offer['image_path'])) ?>" alt="" class="h-36 w-auto rounded-xl object-cover border border-gray-200">
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="checkbox" id="remove_image" name="remove_image" value="1"
                               class="h-4 w-4 text-red-500 border-gray-300 rounded focus:ring-red-400">
                        <label for="remove_image" class="text-sm text-gray-600">Remove current image</label>
                    </div>
                </div>
            <?php endif; ?>

            <div>
                <label for="image" class="block text-sm font-medium text-gray-700 mb-1.5">
                    <?= !empty($offer['image_path']) ? 'Replace image' : 'Add image' ?>
                    <span class="text-gray-400 font-normal">(optional, JPG/PNG, max 4MB)</span>
                </label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                       class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 file:cursor-pointer">
            </div>

            <div class="flex items-center gap-3 pt-2">
                <a href="<?= url('/offers/' . ($offer['id'] ?? '')) ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
