<?php
/** Ported from resources/views (Blade). */
$__title = 'Add Client';
ob_start();
?>
<div class="max-w-lg mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="<?= e(route('clients.index')) ?>" class="text-sm text-gray-400 hover:text-gray-600">← Clients</a>
    </div>

    <h1 class="text-xl font-semibold text-gray-900 mb-6">Add Client</h1>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="<?= e(route('clients.store')) ?>">
            <?= csrf_field() ?>

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" id="name" name="name" value="<?= e(old('name')) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="Jane Smith">
                <?php if ($errors->has('name')): $message = $errors->first('name'); ?>
                    <p class="text-xs text-red-500 mt-1"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <div class="mb-6">
                <label for="email_address" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                <input type="email" id="email_address" name="email_address" value="<?= e(old('email_address')) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="jane@example.com">
                <?php if ($errors->has('email_address')): $message = $errors->first('email_address'); ?>
                    <p class="text-xs text-red-500 mt-1"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    Add client
                </button>
                <a href="<?= e(route('clients.index')) ?>" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
