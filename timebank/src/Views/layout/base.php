<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($tenant['tagline'] ?? 'A community timebank') ?>">
    <title><?= e($tenant['name'] ?? 'TimeBank') ?> &mdash; <?= e($pageTitle ?? 'Home') ?></title>

    <!-- Tailwind CSS v4 CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50:  '#f0fdfa',
                            100: '#ccfbf1',
                            200: '#99f6e4',
                            300: '#5eead4',
                            400: '#2dd4bf',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                        },
                        accent: {
                            50:  '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        },
                    },
                    fontFamily: {
                        sans: ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
                    },
                },
            },
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .prose p { margin-bottom: 1rem; }
        .prose p:last-child { margin-bottom: 0; }
    </style>
</head>
<body class="h-full bg-gray-50 font-sans antialiased text-gray-800">

    <!-- Navigation -->
    <?php require __DIR__ . '/nav.php'; ?>

    <!-- Flash messages -->
    <?php if (!empty($flash)): ?>
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)" x-cloak>
            <?php foreach ($flash as $item): $type = $item['type'] ?? 'info'; $message = $item['message'] ?? ''; ?>
                <?php
                $styles = match($type) {
                    'success' => 'bg-teal-50 border-teal-400 text-teal-800',
                    'error'   => 'bg-red-50 border-red-400 text-red-800',
                    'warning' => 'bg-amber-50 border-amber-400 text-amber-800',
                    default   => 'bg-blue-50 border-blue-400 text-blue-800',
                };
                $iconPath = match($type) {
                    'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                    'error'   => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
                    'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                    default   => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                };
                ?>
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
                    <div class="flex items-start justify-between gap-3 p-4 rounded-xl border <?= $styles ?>">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $iconPath ?>"/>
                            </svg>
                            <p class="text-sm font-medium"><?= e($message) ?></p>
                        </div>
                        <button @click="show = false" class="flex-shrink-0 opacity-60 hover:opacity-100 transition-opacity">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Main content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <footer class="mt-12 border-t border-gray-200 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <div class="h-6 w-6 rounded-full bg-teal-600 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-semibold text-gray-700"><?= e($tenant['name'] ?? 'TimeBank') ?></span>
                </div>
                <p class="text-xs text-gray-400">
                    &copy; <?= date('Y') ?> <?= e($tenant['name'] ?? 'TimeBank') ?>. Powered by <span class="text-teal-600 font-medium">TimeBank</span>.
                </p>
                <nav class="flex items-center gap-4 text-xs text-gray-500">
                    <a href="<?= url('/dashboard') ?>" class="hover:text-teal-600 transition-colors">Dashboard</a>
                    <a href="<?= url('/offers') ?>" class="hover:text-teal-600 transition-colors">Offers</a>
                    <a href="<?= url('/members') ?>" class="hover:text-teal-600 transition-colors">Members</a>
                </nav>
            </div>
        </div>
    </footer>

</body>
</html>
