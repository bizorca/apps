<?php
use Dispatch\Core\View;
use Dispatch\Core\Auth;
$currentPath = \Dispatch\Core\Request::uri();
$isAdmin = Auth::isAdmin();
$isSysOp = Auth::isSysOp();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title ?? 'Dashboard') ?> — <?= View::e($_appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .sidebar-link { transition: all 0.15s ease; }
        .sidebar-link.active { background: #eef2ff; color: #4f46e5; }
        .sidebar-link.active svg { color: #4f46e5; }
    </style>
</head>
<body class="bg-slate-50 antialiased">

<!-- Mobile menu toggle -->
<div class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-slate-200 px-4 h-14 flex items-center justify-between">
    <a href="<?= $_base ?>/" class="flex items-center gap-2">
        <div class="w-7 h-7 bg-indigo-600 rounded-lg flex items-center justify-center">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        <span class="font-bold text-slate-900"><?= View::e($_appName) ?></span>
    </a>
    <button onclick="document.getElementById('mobile-sidebar').classList.toggle('hidden')" class="p-2 rounded-lg hover:bg-slate-100">
        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
</div>

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside id="mobile-sidebar" class="hidden lg:flex lg:flex-col fixed lg:static inset-y-0 left-0 z-40 w-64 bg-white border-r border-slate-200 flex-col">
        <!-- Logo -->
        <div class="h-16 flex items-center px-6 border-b border-slate-200">
            <a href="<?= $_base ?>/" class="flex items-center gap-2 group">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center group-hover:bg-indigo-700 transition-colors">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="font-bold text-slate-900 text-lg tracking-tight"><?= View::e($_appName) ?></span>
            </a>
            <a href="/" class="ml-auto text-xs text-slate-400 hover:text-indigo-600" title="Bizorca Tools">All tools</a>
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            <?php
            $navItems = [
                ['href' => '/dashboard',  'label' => 'Dashboard',      'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
                ['href' => '/campaigns',  'label' => 'Campaigns',       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],
                ['href' => '/venues',     'label' => 'Venue Library',   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>'],
                ['href' => '/flyers',     'label' => 'Flyer Tracker',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                ['href' => '/documents',  'label' => 'Document Library', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>'],
                ['href' => '/profile',    'label' => 'My Profile',       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
            ];
            foreach ($navItems as $item):
                $active = str_starts_with($currentPath, $item['href']) && ($item['href'] !== '/dashboard' || $currentPath === '/dashboard');
            ?>
            <a href="<?= $_base ?><?= $item['href'] ?>" class="sidebar-link <?= $active ? 'active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium">
                <svg class="w-5 h-5 <?= $active ? 'text-indigo-600' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <?= $item['icon'] ?>
                </svg>
                <?= $item['label'] ?>
            </a>
            <?php endforeach; ?>

            <?php if ($isAdmin): ?>
            <div class="pt-4 pb-2">
                <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Admin</p>
            </div>
            <?php
            $adminItems = [
                ['href' => '/admin',             'label' => 'Admin Dashboard', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                ['href' => '/admin/venues',      'label' => 'Manage Venues',   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'],
                ['href' => '/admin/submissions', 'label' => 'Triage Queue',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>'],
                ['href' => '/admin/users',       'label' => 'Users',           'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'],
            ];
            foreach ($adminItems as $item):
                $active = str_starts_with($currentPath, $item['href']);
            ?>
            <a href="<?= $_base ?><?= $item['href'] ?>" class="sidebar-link <?= $active ? 'active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium">
                <svg class="w-5 h-5 <?= $active ? 'text-indigo-600' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <?= $item['icon'] ?>
                </svg>
                <?= $item['label'] ?>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </nav>

        <!-- User info at bottom -->
        <div class="p-4 border-t border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-indigo-700 font-semibold text-sm"><?= strtoupper(substr($_user['name'] ?? '?', 0, 1)) ?></span>
                </div>
                <div class="min-w-0 flex-1">
                    <a href="/account/settings.php" class="block text-sm font-medium text-slate-900 truncate hover:text-indigo-600" title="Account settings"><?= View::e($_user['name'] ?? '') ?></a>
                    <?php if ($_user['role'] !== 'user'): ?>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium <?= $_user['role'] === 'sysop' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700' ?>">
                        <?= strtoupper($_user['role']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <form method="POST" action="<?= $_base ?>/logout">
                    <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                    <button type="submit" class="text-slate-400 hover:text-slate-600 transition-colors" title="Sign out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 lg:ml-0 min-w-0 pt-14 lg:pt-0">
        <!-- Top bar -->
        <header class="hidden lg:flex items-center justify-between h-16 px-6 bg-white border-b border-slate-200 sticky top-0 z-30">
            <h1 class="font-semibold text-slate-900 text-lg"><?= View::e($title ?? 'Dashboard') ?></h1>
            <div class="flex items-center gap-4">
                <!-- Notification bell -->
                <?php $unreadCount = \Dispatch\Models\Notification::countUnread(Auth::userId()); ?>
                <a href="<?= $_base ?>/notifications" class="relative p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <?php if ($unreadCount > 0): ?>
                    <span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center leading-none">
                        <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="<?= $_base ?>/profile" class="flex items-center gap-2 hover:bg-slate-100 rounded-xl px-3 py-1.5 transition-colors">
                    <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center">
                        <span class="text-indigo-700 font-semibold text-xs"><?= strtoupper(substr($_user['name'] ?? '?', 0, 1)) ?></span>
                    </div>
                    <span class="text-sm font-medium text-slate-700"><?= View::e($_user['name'] ?? '') ?></span>
                </a>
            </div>
        </header>

        <!-- Flash messages -->
        <?php if (!empty($_flash)): ?>
        <div class="px-6 pt-4">
            <?php foreach ($_flash as $type => $message): ?>
                <?php
                $classes = match($type) {
                    'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                    'error'   => 'bg-red-50 border-red-200 text-red-800',
                    'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
                    default   => 'bg-blue-50 border-blue-200 text-blue-800',
                };
                ?>
                <div class="flex items-start gap-3 px-4 py-3 rounded-xl border <?= $classes ?> mb-3">
                    <p class="text-sm font-medium"><?= View::e($message) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Page content -->
        <div class="p-6">
            <?= $content ?>
        </div>
    </div>
</div>

</body>
</html>
