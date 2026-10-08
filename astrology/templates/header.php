<?php
$pageTitle = $pageTitle ?? APP_NAME;
$fullWidth = $fullWidth ?? false;
$flash = getFlash();
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> - <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fdf6f0',
                            100: '#fae8d4',
                            200: '#f5c9a0',
                            300: '#e8a56b',
                            400: '#d4803e',
                            500: '#c26a25',
                            600: '#a3541b',
                            700: '#8a4317',
                            800: '#6e3514',
                            900: '#4a2410',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        .print-only { display: none; }
        .element-badge { border-radius: 9999px; padding: 2px 12px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .element-wood { background: #dcfce7; color: #166534; }
        .element-fire { background: #fee2e2; color: #991b1b; }
        .element-earth { background: #fef3c7; color: #92400e; }
        .element-metal { background: #f3f4f6; color: #374151; }
        .element-water { background: #dbeafe; color: #1e40af; }
        .blur-content { filter: blur(6px); user-select: none; pointer-events: none; }
    </style>
</head>
<body class="h-full">
    <nav class="bg-white shadow-sm no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="hidden md:inline text-xs text-gray-400 hover:text-brand-600 mr-3" title="All tools">Bizorca Tools /</a>
                    <a href="<?= url('/') ?>" class="text-2xl font-bold text-brand-600">
                        <?= h(APP_NAME) ?>
                    </a>
                    <?php if (isLoggedIn()): ?>
                    <div class="hidden sm:ml-8 sm:flex sm:space-x-1">
                        <a href="<?= url('/dashboard.php') ?>" class="<?= $currentPage === 'dashboard.php' ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Dashboard</a>
                        <a href="<?= url('/reading.php') ?>" class="<?= $currentPage === 'reading.php' ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">My Reading</a>
                        <a href="<?= url('/weekly-forecasts.php') ?>" class="<?= in_array($currentPage, ['weekly-forecasts.php', 'weekly-forecast.php']) ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Forecasts</a>
                        <a href="<?= url('/meditations.php') ?>" class="<?= in_array($currentPage, ['meditations.php', 'meditation.php']) ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Meditations</a>
                        <a href="<?= url('/starseed-quiz.php') ?>" class="<?= $currentPage === 'starseed-quiz.php' ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Star Seeds</a>
                        <a href="<?= url('/work-with-jillian.php') ?>" class="<?= $currentPage === 'work-with-jillian.php' ? 'text-violet-700 bg-violet-50' : 'text-violet-700 hover:bg-violet-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Work with Jillian</a>
                        <a href="<?= url('/settings.php') ?>" class="<?= $currentPage === 'settings.php' ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Settings</a>
                    </div>
                    <?php else: ?>
                    <div class="hidden sm:ml-8 sm:flex sm:space-x-1">
                        <a href="<?= url('/features.php') ?>" class="<?= $currentPage === 'features.php' ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Features</a>
                        <a href="<?= url('/starseed-quiz.php') ?>" class="<?= $currentPage === 'starseed-quiz.php' ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Star Seed Quiz</a>
                        <a href="<?= url('/work-with-jillian.php') ?>" class="<?= $currentPage === 'work-with-jillian.php' ? 'text-violet-700 bg-violet-50' : 'text-violet-700 hover:bg-violet-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Work with Jillian</a>
                        <a href="<?= url('/pricing.php') ?>" class="<?= $currentPage === 'pricing.php' ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">Pricing</a>
                        <a href="<?= url('/about.php') ?>" class="<?= $currentPage === 'about.php' ? 'text-brand-600 bg-brand-50' : 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' ?> px-3 py-2 rounded-lg text-sm font-medium transition">About</a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-3">
                    <?php if (isLoggedIn()): ?>
                        <?php $user = getCurrentUser(); ?>
                        <span class="text-sm text-gray-600 hidden sm:inline">Hi, <?= h($user['name']) ?></span>
                        <a href="<?= '/account/logout.php' ?>" class="text-sm text-gray-500 hover:text-red-600 transition">Logout</a>
                    <?php else: ?>
                        <a href="<?= authUrl('login') ?>" class="text-sm text-gray-700 hover:text-brand-600 transition">Login</a>
                        <a href="<?= authUrl('register') ?>" class="bg-brand-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-brand-700 transition">Sign Up</a>
                    <?php endif; ?>
                    <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="sm:hidden text-gray-500 hover:text-gray-700 p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden sm:hidden border-t border-gray-200">
            <div class="px-4 py-3 space-y-2">
                <?php if (isLoggedIn()): ?>
                    <a href="<?= url('/dashboard.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">Dashboard</a>
                    <a href="<?= url('/reading.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">My Reading</a>
                    <a href="<?= url('/weekly-forecasts.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">Forecasts</a>
                    <a href="<?= url('/meditations.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">Meditations</a>
                    <a href="<?= url('/starseed-quiz.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">Star Seeds</a>
                    <a href="<?= url('/work-with-jillian.php') ?>" class="block text-violet-700 font-medium py-1">Work with Jillian</a>
                    <a href="<?= url('/settings.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">Settings</a>
                <?php else: ?>
                    <a href="<?= url('/features.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">Features</a>
                    <a href="<?= url('/starseed-quiz.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">Star Seed Quiz</a>
                    <a href="<?= url('/work-with-jillian.php') ?>" class="block text-violet-700 font-medium py-1">Work with Jillian</a>
                    <a href="<?= url('/pricing.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">Pricing</a>
                    <a href="<?= url('/about.php') ?>" class="block text-gray-700 hover:text-brand-600 py-1">About</a>
                    <a href="<?= authUrl('login') ?>" class="block text-gray-700 hover:text-brand-600 py-1">Login</a>
                    <a href="<?= authUrl('register') ?>" class="block text-brand-600 font-medium py-1">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <?php if ($flash): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 no-print">
        <div class="rounded-lg p-4 <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : ($flash['type'] === 'warning' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-red-50 text-red-800 border border-red-200') ?>">
            <?= $flash['message'] ?>
        </div>
    </div>
    <?php endif; ?>

    <main class="<?= $fullWidth ? '' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8' ?>">
