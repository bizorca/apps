<?php
/** @var string $title @var string $body */
?>
<div class="min-h-full flex flex-col justify-center px-4 py-12">
    <div class="w-full max-w-md mx-auto bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
        <h1 class="text-lg font-semibold mb-3"><?= h($title) ?></h1>
        <?= $body ?>
    </div>
</div>
