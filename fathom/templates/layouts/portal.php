<?php /** Client portal layout (layouts/portal.blade.php). Expects $title, $content. */ ?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> — Fathom</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full">

<div class="min-h-full">
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-14">
                <div class="flex items-center gap-6">
                    <a href="<?= e(route('portal.dashboard')) ?>" class="text-lg font-bold text-indigo-600 tracking-tight">
                        Fathom
                    </a>
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Client Portal</span>
                </div>

                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600"><?= e(fm_client()?->name) ?></span>
                    <form method="POST" action="<?= e(route('portal.logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="text-sm text-gray-400 hover:text-gray-600">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <?php if (session('success')): ?>
        <div class="bg-green-50 border-b border-green-200 px-4 py-3 text-sm text-green-800">
            <?= e(session('success')) ?>
        </div>
    <?php endif; ?>
    <?php if (session('info')): ?>
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3 text-sm text-blue-800">
            <?= e(session('info')) ?>
        </div>
    <?php endif; ?>
    <?php if ($errors->any()): ?>
        <div class="bg-red-50 border-b border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside">
                <?php foreach ($errors->all() as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <main>
        <?= $content ?>
    </main>
</div>

</body>
</html>
