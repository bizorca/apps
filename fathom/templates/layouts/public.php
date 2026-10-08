<?php /** Public read-only layout (layouts/public.blade.php). Expects $title, $content. Sets no cookie. */ ?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full">
    <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <span class="text-lg font-bold text-indigo-600">Fathom</span>
        <a href="<?= e(route('sessions.new')) ?>" class="text-sm text-indigo-600 hover:underline">Sign in</a>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        <?= $content ?>
    </main>
</body>
</html>
