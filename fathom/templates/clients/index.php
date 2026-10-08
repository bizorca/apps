<?php
/** Ported from resources/views (Blade). */
$__title = 'Clients';
ob_start();
?>
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Clients</h1>
        <a href="<?= e(route('clients.create')) ?>" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
            Add client
        </a>
    </div>

    <?php if ($clients->isEmpty()): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <p class="text-gray-500 text-sm mb-4">No clients yet. Add one to give them scoped access to specific cards.</p>
            <a href="<?= e(route('clients.create')) ?>" class="text-sm text-indigo-600 hover:underline">Add your first client</a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <?php foreach ($clients as $client): ?>
                <div class="px-6 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900"><?= e($client->name) ?></p>
                        <p class="text-xs text-gray-400"><?= e($client->email_address) ?></p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-gray-400"><?= e($client->cardsCount()) ?> <?= e(Str::plural('card', $client->cardsCount())) ?></span>
                        <a href="<?= e(route('clients.edit', $client)) ?>" class="text-sm text-indigo-600 hover:underline">Edit</a>
                        <form method="POST" action="<?= e(route('clients.destroy', $client)) ?>" onsubmit="return confirm('Remove <?= e(addslashes($client->name)) ?>? This revokes their portal access.')">
                            <?= csrf_field() ?> <?= method_field('DELETE') ?>
                            <button type="submit" class="text-sm text-red-400 hover:text-red-600">Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
