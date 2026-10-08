<?php /** Marketing layout (layouts/marketing.blade.php). Expects $title, $content, optional $meta. */ ?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?: 'Fathom — Kanban for independent professionals') ?></title>
    <meta name="description" content="<?= e($meta ?? 'Fathom is a free Kanban board built for independent service businesses. Manage clients, track work, and run your practice — no subscription required.') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-white text-gray-900 antialiased">

    <nav class="border-b border-gray-100 bg-white/95 backdrop-blur sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="<?= e(route('home')) ?>" class="text-xl font-bold text-indigo-600 tracking-tight">Fathom</a>

            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
                <a href="<?= e(route('home')) ?>" class="hover:text-indigo-600 transition <?= request()->routeIs('home') ? 'text-indigo-600' : '' ?>">Home</a>
                <a href="<?= e(route('features')) ?>" class="hover:text-indigo-600 transition <?= request()->routeIs('features') ? 'text-indigo-600' : '' ?>">Features</a>
                <a href="<?= e(route('about')) ?>" class="hover:text-indigo-600 transition <?= request()->routeIs('about') ? 'text-indigo-600' : '' ?>">About</a>
                <a href="<?= e(route('services')) ?>" class="hover:text-indigo-600 transition <?= request()->routeIs('services') ? 'text-indigo-600' : '' ?>">Services</a>
            </div>

            <div class="flex items-center gap-4">
                <a href="<?= e(route('sessions.new')) ?>" class="text-sm font-medium text-gray-600 hover:text-indigo-600 transition">Sign in</a>
                <a href="<?= e(route('signup')) ?>" class="text-sm font-medium bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">Get started free</a>
            </div>
        </div>
    </nav>

    <main>
        <?= $content ?>
    </main>

    <footer class="border-t border-gray-100 bg-gray-50 mt-24">
        <div class="max-w-6xl mx-auto px-6 py-12 flex flex-col md:flex-row items-center justify-between gap-6 text-sm text-gray-500">
            <div class="flex items-center gap-2">
                <span class="font-bold text-indigo-600">Fathom</span>
                <span>&mdash; Free forever.</span>
            </div>
            <div class="flex items-center gap-6">
                <a href="<?= e(route('home')) ?>" class="hover:text-gray-900 transition">Home</a>
                <a href="<?= e(route('features')) ?>" class="hover:text-gray-900 transition">Features</a>
                <a href="<?= e(route('about')) ?>" class="hover:text-gray-900 transition">About</a>
                <a href="<?= e(route('services')) ?>" class="hover:text-gray-900 transition">Services</a>
                <a href="<?= e(route('signup')) ?>" class="hover:text-gray-900 transition">Sign up</a>
                <a href="<?= e(route('sessions.new')) ?>" class="hover:text-gray-900 transition">Sign in</a>
                <a href="/" class="hover:text-gray-900 transition">All Bizorca Tools</a>
            </div>
        </div>
    </footer>

</body>
</html>
