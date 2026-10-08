<?php
$footerQuotes = [
    "The only game where bankruptcy is the high score.",
    "Financial literacy is overrated. Burn it all.",
    "Buy high, sell low, die legendary.",
    "Your portfolio called. It's filing for divorce.",
    "Compound interest? More like compound regret.",
    "Diversification is just losing money in more places.",
    "The market giveth, and Burn Rate taketh away.",
    "Save for retirement? In THIS economy?",
    "Money can't buy happiness, but it can buy a spectacular failure.",
    "Fortune favors the bold. Ruin favors our players.",
];
$randomQuote = $footerQuotes[array_rand($footerQuotes)];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Burn Rate') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3" defer></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full bg-white">
    <div class="min-h-full">
        <!-- Navigation -->
        <nav class="bg-gradient-to-r from-red-700 to-red-900 shadow-lg">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center space-x-8">
                        <a href="<?= url('/') ?>" class="flex items-center space-x-2">
                            <span class="text-2xl">&#128293;</span>
                            <span class="text-xl font-bold text-white">Burn Rate</span>
                        </a>
                        <div class="hidden md:flex items-center space-x-6">
                            <a href="<?= url('/about') ?>" class="text-red-100 hover:text-white text-sm font-medium transition-colors">About</a>
                            <a href="<?= url('/features') ?>" class="text-red-100 hover:text-white text-sm font-medium transition-colors">Features</a>
                            <a href="<?= url('/pricing') ?>" class="text-red-100 hover:text-white text-sm font-medium transition-colors">Pricing</a>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <?php if (isset($app) && $app->isLoggedIn()): ?>
                            <a href="<?= url('/account') ?>" class="text-red-100 hover:text-white text-sm font-medium">My Account</a>
                            <?php if ($app->isAdmin()): ?>
                                <a href="<?= url('/admin') ?>" class="text-yellow-300 hover:text-yellow-100 text-sm font-medium">Admin</a>
                            <?php endif; ?>
                            <a href="<?= '/account/logout.php' ?>" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500 transition-colors">Logout</a>
                        <?php else: ?>
                            <a href="<?= url('/account') ?>" class="text-red-100 hover:text-white text-sm font-medium">Login</a>
                            <a href="<?= url('/account') ?>" class="rounded-md bg-amber-500 px-4 py-1.5 text-sm font-bold text-red-900 hover:bg-amber-400 transition-colors">Join the Burn</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Flash Messages -->
        <?php if (!empty($flash)): ?>
            <?php foreach (['success' => 'green', 'error' => 'red', 'info' => 'blue'] as $type => $color): ?>
                <?php if (!empty($flash[$type])): ?>
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-4" x-data="{ show: true }" x-show="show" x-transition>
                        <div class="rounded-md bg-<?= $color ?>-50 p-4 border border-<?= $color ?>-200">
                            <div class="flex">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-<?= $color ?>-800"><?= htmlspecialchars((string) $flash[$type]) ?></p>
                                </div>
                                <button @click="show = false" class="ml-3 text-<?= $color ?>-500 hover:text-<?= $color ?>-700">
                                    <span class="text-xl">&times;</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Main Content (full-width, no container constraint) -->
        <main>
            <?= $content ?? '' ?>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-900">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <h3 class="text-lg font-bold text-white mb-3">&#128293; Burn Rate</h3>
                        <p class="text-gray-400 text-sm">A satirical anti-financial literacy game where the goal is to torch your fortune in the most spectacular way possible.</p>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">Navigate</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="<?= url('/about') ?>" class="text-gray-400 hover:text-amber-400 transition-colors">About</a></li>
                            <li><a href="<?= url('/features') ?>" class="text-gray-400 hover:text-amber-400 transition-colors">Features</a></li>
                            <li><a href="<?= url('/pricing') ?>" class="text-gray-400 hover:text-amber-400 transition-colors">Pricing</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">Get Started</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="<?= url('/account') ?>" class="text-gray-400 hover:text-amber-400 transition-colors">Join the Burn</a></li>
                            <li><a href="<?= url('/account') ?>" class="text-gray-400 hover:text-amber-400 transition-colors">Sign In</a></li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-800 mt-8 pt-8">
                    <p class="text-center text-sm text-amber-400 italic mb-2">
                        &ldquo;<?= htmlspecialchars($randomQuote) ?>&rdquo;
                    </p>
                    <p class="text-center text-sm text-gray-500">
                        &copy; <?= date('Y') ?> Burn Rate &mdash; Where Fortunes Go To Die
                    </p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
