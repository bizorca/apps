<?php $pageTitle = $title ?? 'Error'; ?>
<div class="max-w-md mx-auto text-center py-16">
    <p class="text-5xl font-bold text-teal-600 mb-2"><?= (int) ($code ?? 500) ?></p>
    <h1 class="text-xl font-semibold text-gray-800 mb-3"><?= e($title ?? 'Error') ?></h1>
    <p class="text-sm text-gray-500"><?= e($message ?? '') ?></p>
</div>
