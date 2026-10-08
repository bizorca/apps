<?php /** Error page in the style of Laravel's minimal error views. Expects $status, $title, $message. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="antialiased bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="flex items-center justify-center gap-4 text-lg text-gray-500 uppercase tracking-wider">
            <span class="pr-4 border-r border-gray-400"><?= (int) $status ?></span>
            <span><?= e($message ?: $title) ?></span>
        </div>
        <p class="mt-6 text-sm"><a href="<?= e(route('home')) ?>" class="text-indigo-600 hover:underline">Back to Fathom</a></p>
    </div>
</body>
</html>
