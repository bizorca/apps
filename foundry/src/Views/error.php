<?php
$title = ($code ?? 500) . ' — Bizorca Consulting';
ob_start();
?>

<div class="min-h-screen bg-slate-50 flex items-center justify-center px-6">
    <div class="text-center">
        <div class="text-6xl font-black text-slate-200 mb-4"><?= $code ?? 500 ?></div>
        <h1 class="text-xl font-semibold text-slate-700 mb-2"><?= h($message ?? 'Something went wrong.') ?></h1>
        <a href="<?= u('/') ?>" class="text-brand-600 hover:text-brand-700 text-sm">&larr; Go home</a>
    </div>
</div>

<?php
$content = ob_get_clean();
require FD_ROOT . '/src/Views/layout.php';
?>
