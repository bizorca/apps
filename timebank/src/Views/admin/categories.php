<?php $pageTitle = 'Categories'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Categories</h1>
    <p class="text-sm text-gray-500"><?= count($categories ?? []) ?> categories</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
        <ul class="space-y-1">
            <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Name</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Offers</th>
                <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Active</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($categories ?? [] as $cat): ?>
                <tr class="hover:bg-gray-50 transition-colors" x-data="{ editing: false, name: '<?= e(addslashes($cat['name'] ?? '')) ?>' }">
                    <td class="px-5 py-3.5">
                        <div x-show="!editing" class="flex items-center gap-2">
                            <span class="font-medium text-gray-800"><?= e($cat['name'] ?? '') ?></span>
                            <button @click="editing = true"
                                    class="text-gray-400 hover:text-teal-600 transition-colors focus:outline-none">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                        </div>
                        <div x-show="editing" x-cloak class="flex items-center gap-2">
                            <form method="POST" action="<?= url('/admin/categories/' . ($cat['id'] ?? '') . '/update') ?>"
                                  class="flex items-center gap-2">
                                <?= csrf_field() ?>
                                <input type="text" name="name" x-model="name"
                                       class="px-3 py-1.5 border border-teal-400 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 w-48">
                                <button type="submit"
                                        class="px-3 py-1.5 bg-teal-600 text-white text-xs font-medium rounded-lg hover:bg-teal-700 transition-colors">
                                    Save
                                </button>
                                <button type="button" @click="editing = false; name = '<?= e(addslashes($cat['name'] ?? '')) ?>'"
                                        class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-200 transition-colors">
                                    Cancel
                                </button>
                            </form>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-right text-gray-600"><?= number_format((int)($cat['offer_count'] ?? 0)) ?></td>
                    <td class="px-5 py-3.5 text-center">
                        <form method="POST" action="<?= url('/admin/categories/' . ($cat['id'] ?? '') . '/toggle') ?>">
                            <?= csrf_field() ?>
                            <button type="submit"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none <?= !empty($cat['is_active']) ? 'bg-teal-500' : 'bg-gray-300' ?>">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform <?= !empty($cat['is_active']) ? 'translate-x-4' : 'translate-x-0.5' ?>"></span>
                            </button>
                        </form>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <?php if ((int)($cat['offer_count'] ?? 0) === 0): ?>
                            <form method="POST" action="<?= url('/admin/categories/' . ($cat['id'] ?? '') . '/delete') ?>"
                                  onsubmit="return confirm('Delete this category?')">
                                <?= csrf_field() ?>
                                <button type="submit"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium transition-colors">
                                    Delete
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-xs text-gray-300">In use</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add category -->
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-gray-800 mb-4">Add Category</h3>
    <form method="POST" action="<?= url('/admin/categories/create') ?>" class="flex items-center gap-3">
        <?= csrf_field() ?>
        <input type="text" name="name" required maxlength="100"
               placeholder="Category name..."
               class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
        <button type="submit"
                class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors whitespace-nowrap">
            Add Category
        </button>
    </form>
</div>
