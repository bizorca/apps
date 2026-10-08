<?php
/** A membership that exists but cannot be used yet (pending) or any more (inactive). */
$pageTitle = $state === 'pending' ? 'Awaiting approval' : 'Membership inactive';
?>
<div class="max-w-lg mx-auto text-center bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
    <?php if ($state === 'pending'): ?>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Your membership is awaiting approval</h1>
        <p class="text-sm text-gray-600">An administrator of <?= e($tenant['name'] ?? 'this timebank') ?> will approve it shortly. You'll be able to take part as soon as they do.</p>
    <?php else: ?>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Your membership is inactive</h1>
        <p class="text-sm text-gray-600">Your membership of <?= e($tenant['name'] ?? 'this timebank') ?> has been deactivated. Please contact an administrator.</p>
    <?php endif; ?>
    <p class="mt-6 text-sm"><a href="/" class="text-teal-700 hover:underline">All Bizorca Tools</a></p>
</div>
