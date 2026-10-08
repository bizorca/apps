<?php
$pageTitle = $pageTitle ?? APP_NAME;
$fullWidth = $fullWidth ?? false;
$flash = $flash ?? getFlash();
// setFlash() stores one {type, message}; the markup below loops over
// type => [messages]. The original passed the raw pair, so no flash ever
// rendered (and PHP warned). Normalise to the shape the loop expects.
if (is_array($flash) && isset($flash['type'], $flash['message'])) {
    $flash = [$flash['type'] => [$flash['message']]];
}
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> &mdash; <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        },
                        accent: {
                            400: '#2dd4bf',
                            500: '#14b8a6',
                            600: '#0d9488',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">

<nav class="bg-brand-700 shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center gap-3">
                <a href="/" class="text-brand-200 hover:text-white text-xs" title="All tools">Bizorca Tools /</a>
                <a href="<?= url('/index.php') ?>" class="flex items-center gap-2 text-white font-bold text-xl tracking-tight hover:text-brand-100 transition-colors">
                    <svg class="w-7 h-7 text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    CoopConvert
                </a>
            </div>

            <!-- Desktop Nav -->
            <div class="hidden md:flex items-center gap-1">
                <?php if (!isLoggedIn()): ?>
                    <a href="<?= url('/index.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'index.php' ? 'bg-brand-800 text-white' : '' ?>">Home</a>
                    <a href="<?= url('/about.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'about.php' ? 'bg-brand-800 text-white' : '' ?>">About</a>
                    <a href="<?= url('/login.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'login.php' ? 'bg-brand-800 text-white' : '' ?>">Login</a>
                    <a href="<?= url('/register.php') ?>" class="ml-2 bg-accent-500 hover:bg-accent-600 text-white px-4 py-2 rounded-md text-sm font-semibold transition-colors shadow-sm">Start Your Conversion</a>

                <?php elseif (isAdmin()): ?>
                    <a href="<?= url('/admin.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'admin.php' ? 'bg-brand-800 text-white' : '' ?>">Admin</a>
                    <a href="<?= url('/coordinator.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'coordinator.php' ? 'bg-brand-800 text-white' : '' ?>">Coordinator Dashboard</a>
                    <a href="<?= url('/about.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'about.php' ? 'bg-brand-800 text-white' : '' ?>">About</a>
                    <a href="<?= url('/logout.php') ?>" class="ml-2 text-brand-200 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors">Logout</a>

                <?php elseif (isCoordinator()): ?>
                    <a href="<?= url('/coordinator.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'coordinator.php' ? 'bg-brand-800 text-white' : '' ?>">Coordinator Dashboard</a>
                    <a href="<?= url('/about.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'about.php' ? 'bg-brand-800 text-white' : '' ?>">About</a>
                    <a href="<?= url('/logout.php') ?>" class="ml-2 text-brand-200 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors">Logout</a>

                <?php else: ?>
                    <!-- Client -->
                    <a href="<?= url('/dashboard.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'dashboard.php' ? 'bg-brand-800 text-white' : '' ?>">Dashboard</a>
                    <a href="<?= url('/case.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'case.php' ? 'bg-brand-800 text-white' : '' ?>">My Case</a>
                    <a href="<?= url('/about.php') ?>" class="text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $currentPage === 'about.php' ? 'bg-brand-800 text-white' : '' ?>">About</a>
                    <a href="<?= url('/logout.php') ?>" class="ml-2 text-brand-200 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium transition-colors">Logout</a>
                <?php endif; ?>
            </div>

            <!-- Mobile hamburger -->
            <div class="md:hidden">
                <button id="mobile-menu-btn" type="button" class="text-brand-100 hover:text-white p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-white" aria-expanded="false" aria-controls="mobile-menu">
                    <span class="sr-only">Open main menu</span>
                    <svg id="icon-open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg id="icon-close" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden md:hidden border-t border-brand-600">
        <div class="px-4 py-3 space-y-1">
            <?php if (!isLoggedIn()): ?>
                <a href="<?= url('/index.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">Home</a>
                <a href="<?= url('/about.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">About</a>
                <a href="<?= url('/login.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">Login</a>
                <a href="<?= url('/register.php') ?>" class="block bg-accent-500 hover:bg-accent-600 text-white px-3 py-2 rounded-md text-sm font-semibold mt-2">Start Your Conversion</a>

            <?php elseif (isAdmin()): ?>
                <a href="<?= url('/admin.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">Admin</a>
                <a href="<?= url('/coordinator.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">Coordinator Dashboard</a>
                <a href="<?= url('/about.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">About</a>
                <a href="<?= url('/logout.php') ?>" class="block text-brand-200 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">Logout</a>

            <?php elseif (isCoordinator()): ?>
                <a href="<?= url('/coordinator.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">Coordinator Dashboard</a>
                <a href="<?= url('/about.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">About</a>
                <a href="<?= url('/logout.php') ?>" class="block text-brand-200 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">Logout</a>

            <?php else: ?>
                <a href="<?= url('/dashboard.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                <a href="<?= url('/case.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">My Case</a>
                <a href="<?= url('/about.php') ?>" class="block text-brand-100 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">About</a>
                <a href="<?= url('/logout.php') ?>" class="block text-brand-200 hover:text-white hover:bg-brand-600 px-3 py-2 rounded-md text-sm font-medium">Logout</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if ($flash): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <?php foreach ($flash as $type => $messages): ?>
            <?php foreach ($messages as $message): ?>
                <?php
                $alertClasses = match($type) {
                    'success' => 'bg-green-50 border-green-400 text-green-800',
                    'error'   => 'bg-red-50 border-red-400 text-red-800',
                    'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800',
                    default   => 'bg-blue-50 border-blue-400 text-blue-800',
                };
                $iconPath = match($type) {
                    'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                    'error'   => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
                    'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                    default   => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                };
                ?>
                <div class="flex items-start gap-3 border-l-4 <?= $alertClasses ?> p-4 rounded-r-md mb-2">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $iconPath ?>" />
                    </svg>
                    <p class="text-sm font-medium"><?= h($message) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main>
<?php if (!$fullWidth): ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
<?php endif; ?>

<script>
    (function () {
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');
        const iconOpen = document.getElementById('icon-open');
        const iconClose = document.getElementById('icon-close');
        btn.addEventListener('click', function () {
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', String(!expanded));
            menu.classList.toggle('hidden');
            iconOpen.classList.toggle('hidden');
            iconClose.classList.toggle('hidden');
        });
    })();
</script>
