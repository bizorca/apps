<?php
/**
 * Centred card used by every unauthenticated screen.
 *
 * @var string $heading
 * @var string $inner
 * @var string|null $sub
 * @var array<int,string> $errors
 * @var string|null $notice
 */
$tenant = \Bizorca\Pilotage\Core\Tenant::current();
?>
<div class="min-h-full flex flex-col justify-center px-4 py-12">
    <div class="w-full max-w-sm mx-auto">

        <div class="mb-8 text-center">
            <div class="text-lg font-semibold tracking-tight">
                <?= $tenant !== null ? h($tenant['name']) : 'Pilotage' ?>
            </div>
            <?php if ($tenant !== null): ?>
                <div class="text-xs text-slate-500 mt-1">powered by Pilotage</div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
            <h1 class="text-xl font-semibold mb-1"><?= h($heading) ?></h1>
            <?php if (!empty($sub)): ?>
                <p class="text-sm text-slate-600 mb-5"><?= h($sub) ?></p>
            <?php else: ?>
                <div class="mb-5"></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    <?php foreach ($errors as $error): ?>
                        <div><?= h($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($notice)): ?>
                <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    <?= h($notice) ?>
                </div>
            <?php endif; ?>

            <?= $inner ?>
        </div>
    </div>
</div>
