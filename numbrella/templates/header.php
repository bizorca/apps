<?php
// Requires config.php to already be loaded. Never starts a session.
$currentUser = getCurrentUser();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<header class="bg-white border-b border-gray-200 sticky top-0 z-10">
    <div class="max-w-5xl mx-auto px-4 flex items-center justify-between h-14 gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <a href="<?= h(url('/')) ?>" class="flex items-center gap-2">
                <span class="text-lg font-extrabold text-indigo-600 tracking-tight"><?= h(APP_NAME) ?></span>
                <span class="text-xs text-gray-400 font-medium hidden sm:block">Business Valuation</span>
            </a>
            <a href="/" class="text-xs text-gray-400 hover:text-indigo-600 hidden md:block" title="All tools">Bizorca Tools</a>
        </div>

        <nav class="flex items-center gap-4 text-sm">
            <?php if ($currentUser): ?>
                <a href="<?= h(url('/dashboard.php')) ?>"
                    class="font-medium <?= str_contains($currentPath, 'dashboard') ? 'text-indigo-600' : 'text-gray-600 hover:text-gray-900' ?>">
                    My Reports
                </a>
                <a href="<?= h(url('/wizard.php?step=1')) ?>"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-1.5 rounded-lg text-xs transition-colors">
                    New Report
                </a>
                <?php if ((int) $currentUser['is_admin'] === 1): ?>
                    <a href="<?= h(url('/admin.php')) ?>" class="text-gray-500 hover:text-gray-700 text-xs">Admin</a>
                <?php endif; ?>
                <a href="/account/settings.php" class="text-gray-500 hover:text-indigo-600 text-xs hidden sm:inline"><?= h($currentUser['name']) ?></a>
                <a href="/account/logout.php" class="text-gray-400 hover:text-gray-600 text-xs">Sign out</a>
            <?php else: ?>
                <a href="/account/login.php?next=<?= rawurlencode(url('/dashboard.php')) ?>" class="text-gray-600 hover:text-indigo-600 text-xs">Sign in</a>
                <a href="/account/register.php?next=<?= rawurlencode(url('/dashboard.php')) ?>"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-1.5 rounded-lg text-xs transition-colors">
                    Get started
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>
