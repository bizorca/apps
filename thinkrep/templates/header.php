<?php
$pageTitle = $pageTitle ?? 'Thinkrep';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> - Thinkrep</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        tr: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full">
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="hidden sm:inline text-sm text-gray-400 hover:text-tr-600 mr-3" title="All tools">Bizorca Tools /</a>
                    <a href="<?= url('/') ?>" class="text-2xl font-bold text-tr-600">
                        <span class="text-3xl">🧠</span> Thinkrep
                    </a>
                    <?php if (isLoggedIn()): ?>
                    <div class="hidden lg:ml-8 lg:flex lg:space-x-4">
                        <a href="<?= url('/dashboard.php') ?>" class="text-gray-700 hover:text-tr-600 px-3 py-2 text-sm font-medium">Dashboard</a>
                        <a href="<?= url('/session.php') ?>" class="text-gray-700 hover:text-tr-600 px-3 py-2 text-sm font-medium">Train</a>
                        <a href="<?= url('/journal.php') ?>" class="text-gray-700 hover:text-tr-600 px-3 py-2 text-sm font-medium">Journal</a>
                        <a href="<?= url('/blindspots.php') ?>" class="text-gray-700 hover:text-tr-600 px-3 py-2 text-sm font-medium">Blind Spots</a>
                        <a href="<?= url('/models.php') ?>" class="text-gray-700 hover:text-tr-600 px-3 py-2 text-sm font-medium">Models</a>
                        <a href="<?= url('/teams.php') ?>" class="text-gray-700 hover:text-tr-600 px-3 py-2 text-sm font-medium">Teams</a>
                        <a href="<?= url('/benchmarks.php') ?>" class="text-gray-700 hover:text-tr-600 px-3 py-2 text-sm font-medium">Benchmarks</a>
                        <a href="<?= url('/company.php') ?>" class="text-gray-700 hover:text-tr-600 px-3 py-2 text-sm font-medium">Company</a>
                        <a href="<?= url('/settings.php') ?>" class="text-gray-700 hover:text-tr-600 px-3 py-2 text-sm font-medium">Settings</a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (isLoggedIn()): ?>
                        <?php $user = $user ?? getCurrentUser(); ?>
                        <a href="/account/settings.php" class="text-sm text-gray-600 hover:text-tr-600 hidden sm:inline">Hi, <?= h($user['name'] ?: $user['email']) ?></a>
                        <a href="/account/logout.php" class="text-sm text-gray-500 hover:text-red-600">Sign out</a>
                    <?php else: ?>
                        <a href="/account/login.php?next=<?= rawurlencode(url('/dashboard.php')) ?>" class="text-sm text-gray-700 hover:text-tr-600">Sign in</a>
                    <?php endif; ?>
                </div>
                <?php if (isLoggedIn()): ?>
                <div class="lg:hidden flex items-center">
                    <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="text-gray-500 hover:text-gray-700 p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if (isLoggedIn()): ?>
        <div id="mobile-menu" class="hidden lg:hidden border-t border-gray-200">
            <div class="px-4 py-3 space-y-2">
                <a href="<?= url('/dashboard.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Dashboard</a>
                <a href="<?= url('/session.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Train</a>
                <a href="<?= url('/journal.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Journal</a>
                <a href="<?= url('/blindspots.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Blind Spots</a>
                <a href="<?= url('/models.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Models</a>
                <a href="<?= url('/teams.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Teams</a>
                <a href="<?= url('/benchmarks.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Benchmarks</a>
                <a href="<?= url('/company.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Company</a>
                <a href="<?= url('/submit-scenario.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Submit</a>
                <a href="<?= url('/confidence.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Confidence</a>
                <a href="<?= url('/settings.php') ?>" class="block text-gray-700 hover:text-tr-600 py-1">Settings</a>
            </div>
        </div>
        <?php endif; ?>
    </nav>

    <?php if ($flash): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="rounded-lg p-4 <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= h($flash['message']) ?>
        </div>
    </div>
    <?php endif; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
