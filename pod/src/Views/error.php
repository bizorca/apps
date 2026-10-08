<?php $pageTitle = 'Error ' . ($code ?? ''); ?>
<div class="text-center py-24">
    <p class="text-6xl font-bold text-gray-200"><?= h((string)($code ?? 500)) ?></p>
    <p class="mt-4 text-lg text-gray-600"><?= h($message ?? 'Something went wrong.') ?></p>
    <a href="<?= url('/') ?>" class="mt-6 inline-block text-indigo-600 hover:underline">Go home</a>
</div>
