<?php
/**
 * Signed-in layout (layouts/app.blade.php). Expects $title, $content.
 * window.fmUrl() builds a route URL in JS the same way fm_url() does in PHP,
 * so the board's drag-and-drop fetch follows the URL style setting.
 */
$me = auth()->user();
$unread = $me->unreadNotificationsCount();
?>
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
    <script>
        window.fmUrl = function (path) {
            return <?= json_encode(FM_CLEAN_URLS ? FM_BASE : FM_BASE . '/?r=') ?> + path;
        };
    </script>
</head>
<body class="h-full">

<?php if (fm_impersonating()): ?>
<div class="bg-amber-400 text-amber-950 text-sm font-medium px-4 py-2 flex items-center justify-between">
    <span>Impersonating <strong><?= e($me->name) ?></strong> &mdash; <?= e($me->email_address) ?> &mdash; <?= e($me->account?->name) ?></span>
    <form method="POST" action="<?= e(route('admin.impersonate.destroy')) ?>">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="underline font-semibold hover:text-amber-900">Stop impersonating</button>
    </form>
</div>
<?php endif; ?>

<div class="min-h-full">
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-14">
                <div class="flex items-center gap-6">
                    <a href="/" class="text-xs text-gray-400 hover:text-indigo-600 hidden sm:block" title="All tools">Bizorca Tools</a>
                    <a href="<?= e(route('events.index')) ?>" class="text-lg font-bold text-indigo-600 tracking-tight">
                        Fathom
                    </a>
                    <a href="<?= e(route('boards.index')) ?>" class="text-sm text-gray-600 hover:text-gray-900 <?= request()->routeIs('boards.*') ? 'font-medium text-gray-900' : '' ?>">
                        Boards
                    </a>
                    <a href="<?= e(route('card-templates.index')) ?>" class="text-sm text-gray-600 hover:text-gray-900 <?= request()->routeIs('card-templates.*') ? 'font-medium text-gray-900' : '' ?>">
                        Templates
                    </a>
                </div>

                <div class="flex items-center gap-4">
                    <form method="GET" action="<?= e(route('search')) ?>" class="hidden sm:block">
                        <?= fm_route_field('search') ?>
                        <input
                            type="search"
                            name="q"
                            value="<?= e(request('q')) ?>"
                            placeholder="Search…"
                            class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-48"
                        >
                    </form>

                    <a href="<?= e(route('notifications.index')) ?>" class="relative text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <?php if ($unread > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
                                <?= e($unread > 9 ? '9+' : $unread) ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900">
                            <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-medium">
                                <?= e($me->initials()) ?>
                            </div>
                            <span class="hidden sm:block"><?= e($me->name) ?></span>
                        </button>

                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg z-50 py-1">
                            <?php if ($me->isSysop() && !fm_impersonating()): ?>
                                <a href="<?= e(route('admin.dashboard')) ?>" class="block px-4 py-2 text-sm text-indigo-600 font-medium hover:bg-gray-50">Admin Panel</a>
                                <hr class="my-1 border-gray-200">
                            <?php endif; ?>
                            <a href="<?= e(route('account.profile')) ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Profile</a>
                            <a href="<?= e(route('account.show')) ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Account</a>
                            <a href="<?= e(route('notifications.settings')) ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Notifications</a>
                            <hr class="my-1 border-gray-200">
                            <form method="POST" action="<?= e(route('sessions.destroy')) ?>">
                                <?= csrf_field() ?>
                                <?= method_field('DELETE') ?>
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
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

    <footer class="border-t border-gray-100 mt-16 py-6">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between text-xs text-gray-400">
            <span>Fathom &mdash; Free forever.</span>
            <a href="<?= e(FM_SUPPORT_URL) ?>" class="hover:text-gray-600 transition">Support Fathom</a>
        </div>
    </footer>
</div>

<div x-data="{
    showHelp: false,
    init() {
        window.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT' || e.target.isContentEditable) return;
            if (e.key === '?') { e.preventDefault(); this.showHelp = !this.showHelp; return; }
            if (e.key === 'Escape') { this.showHelp = false; return; }
            if (this.showHelp) return;
            if (e.key === 'n') { e.preventDefault(); window.location.href = <?= e(json_encode(route('cards.create'))) ?>; }
            if (e.key === '/') { e.preventDefault(); document.querySelector('input[name=q]')?.focus(); }
            if (e.key === 'b') { e.preventDefault(); window.location.href = <?= e(json_encode(route('boards.index'))) ?>; }
        });
    }
}">
    <div x-show="showHelp" x-cloak
         class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center"
         @click.self="showHelp = false">
        <div class="bg-white rounded-xl border border-gray-200 shadow-xl w-80 p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Keyboard shortcuts</h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-600">New card</span><kbd class="text-xs bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5 font-mono">n</kbd></div>
                <div class="flex justify-between"><span class="text-gray-600">Focus search</span><kbd class="text-xs bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5 font-mono">/</kbd></div>
                <div class="flex justify-between"><span class="text-gray-600">Go to boards</span><kbd class="text-xs bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5 font-mono">b</kbd></div>
                <div class="flex justify-between"><span class="text-gray-600">Show this help</span><kbd class="text-xs bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5 font-mono">?</kbd></div>
            </div>
            <button @click="showHelp = false" class="mt-5 w-full text-sm text-gray-500 hover:text-gray-700">Close</button>
        </div>
    </div>
</div>

</body>
</html>
