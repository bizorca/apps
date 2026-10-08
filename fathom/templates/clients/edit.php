<?php
/** Ported from resources/views (Blade). */
$__title = 'Edit Client — ' . $client->name;
ob_start();
?>
<div class="max-w-lg mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="<?= e(route('clients.index')) ?>" class="text-sm text-gray-400 hover:text-gray-600">← Clients</a>
    </div>

    <h1 class="text-xl font-semibold text-gray-900 mb-6"><?= e($client->name) ?></h1>

    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="POST" action="<?= e(route('clients.update', $client)) ?>">
            <?= csrf_field() ?> <?= method_field('PATCH') ?>

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" id="name" name="name" value="<?= e(old('name', $client->name)) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?php if ($errors->has('name')): $message = $errors->first('name'); ?>
                    <p class="text-xs text-red-500 mt-1"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <div class="mb-6">
                <label for="email_address" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                <input type="email" id="email_address" name="email_address" value="<?= e(old('email_address', $client->email_address)) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?php if ($errors->has('email_address')): $message = $errors->first('email_address'); ?>
                    <p class="text-xs text-red-500 mt-1"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                Save changes
            </button>
        </form>
    </div>

    
    <?php if ($client->cards->isNotEmpty()): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Linked cards</h2>
            <div class="space-y-2">
                <?php foreach ($client->cards as $card): ?>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="<?= e(route('cards.show', $card)) ?>" class="text-sm text-indigo-600 hover:underline">
                                <?= e($card->title) ?>
                            </a>
                            <?php if ($card->board): ?>
                                <span class="text-xs text-gray-400 ml-2"><?= e($card->board->name) ?></span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="<?= e(route('cards.clients.destroy', [$card, $client])) ?>">
                            <?= csrf_field() ?> <?= method_field('DELETE') ?>
                            <button type="submit" class="text-xs text-gray-300 hover:text-red-500">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php fm_layout('app', (string) $__title, (string) ob_get_clean()); ?>
