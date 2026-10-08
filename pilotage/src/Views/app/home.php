<?php
/**
 * Placeholder dashboard. M3 replaces this with the real coach and client views.
 *
 * @var array $user @var array $tenant @var array $orgs
 */
use Bizorca\Pilotage\Auth\Csrf;
?>
<div class="max-w-3xl mx-auto px-4 py-10">
    <div class="flex items-start justify-between mb-8">
        <div>
            <h1 class="text-xl font-semibold"><?= h($tenant['name']) ?></h1>
            <p class="text-sm text-slate-600 mt-1">
                Signed in as <?= h($user['name']) ?> &middot; <?= h($user['role']) ?>
            </p>
        </div>
        <form method="post" action="<?= h(url('/logout')) ?>">
            <?= Csrf::field() ?>
            <button class="text-sm text-slate-600 underline hover:text-slate-900">Sign out</button>
        </form>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-6">
        <h2 class="font-medium mb-3">Client organizations</h2>
        <?php if ($orgs === []): ?>
            <p class="text-sm text-slate-500">None yet. M3 brings the real thing.</p>
        <?php else: ?>
            <ul class="text-sm space-y-1">
                <?php foreach ($orgs as $org): ?>
                    <li><?= h($org['name']) ?> <span class="text-slate-400">&middot; <?= h($org['status']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
