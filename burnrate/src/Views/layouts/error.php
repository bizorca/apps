<?php $title = "Error {$code}"; ?>
<?php ob_start(); ?>
<div class="flex min-h-[60vh] items-center justify-center">
    <div class="text-center">
        <h1 class="text-6xl font-bold text-red-600"><?= $code ?></h1>
        <p class="mt-4 text-xl text-gray-600"><?= htmlspecialchars($message) ?></p>
        <a href="<?= url('/') ?>" class="mt-6 inline-block rounded-md bg-red-600 px-6 py-3 text-white hover:bg-red-700">Go Home</a>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/main.php'; ?>
