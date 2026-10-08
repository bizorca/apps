<?php
/**
 * Navigation partial — included by layout/base.php
 * Available: $auth (Auth instance), $tenant, $member (current user), $unreadMessages
 */
$currentPath = \TimeBank\Core\Url::tenantPath();   // the route inside the community; REQUEST_URI holds only /timebank/
$currencyName = $tenant['currency_name'] ?? 'Hour';
$unread = $unreadMessages ?? 0;

$navLinks = [
    ['href' => '/dashboard',     'label' => 'Dashboard'],
    ['href' => '/offers',        'label' => 'Offers'],
    ['href' => '/requests',      'label' => 'Requests'],
    ['href' => '/members',       'label' => 'Members'],
    ['href' => '/announcements', 'label' => 'Announcements'],
    ['href' => '/groups',        'label' => 'Groups'],
];

function navIsActive(string $href, string $currentPath): bool {
    if ($href === '/dashboard') {
        return $currentPath === '/dashboard';
    }
    return str_starts_with($currentPath, $href);
}
?>
<nav class="bg-white shadow-sm border-b border-gray-200" x-data="{ mobileOpen: false, profileOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">

            <!-- Left: Logo / Tenant name -->
            <div class="flex items-center flex-shrink-0">
                <a href="<?= url('/dashboard') ?>" class="flex items-center gap-2 group">
                    <?php if (!empty($tenant['logo_path'])): ?>
                        <img src="<?= e(media_url($tenant['logo_path'])) ?>" alt="<?= e($tenant['name'] ?? 'TimeBank') ?>" class="h-8 w-auto">
                    <?php else: ?>
                        <div class="h-8 w-8 rounded-full bg-teal-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <span class="text-lg font-semibold text-gray-900 group-hover:text-teal-700 transition-colors">
                        <?= e($tenant['name'] ?? 'TimeBank') ?>
                    </span>
                </a>
            </div>

            <!-- Center: Desktop nav links -->
            <div class="hidden md:flex items-center space-x-1">
                <?php foreach ($navLinks as $link): ?>
                    <?php $active = navIsActive($link['href'], $currentPath); ?>
                    <a href="<?= url($link['href']) ?>"
                       class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $active
                           ? 'bg-teal-50 text-teal-700'
                           : 'text-gray-600 hover:text-teal-700 hover:bg-gray-50' ?>">
                        <?= e($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Right: bell + profile dropdown -->
            <div class="hidden md:flex items-center gap-3">

                <!-- Notification bell -->
                <a href="<?= url('/messages') ?>" class="relative p-2 text-gray-500 hover:text-teal-700 rounded-full hover:bg-gray-100 transition-colors" title="Messages">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <?php if ($unread > 0): ?>
                        <span class="absolute top-0.5 right-0.5 h-4 w-4 rounded-full bg-orange-500 text-white text-xs flex items-center justify-center font-bold leading-none">
                            <?= $unread > 9 ? '9+' : $unread ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- Profile dropdown -->
                <?php if (\TimeBank\Core\Auth::isLoggedIn() && ($currentUser = \TimeBank\Core\Auth::user())): ?>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false"
                                class="flex items-center gap-2 p-1 rounded-full hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <?php if (!empty($currentUser['avatar_path'])): ?>
                                <img src="<?= e(media_url($currentUser['avatar_path'])) ?>" alt="" class="h-8 w-8 rounded-full object-cover ring-2 ring-teal-100">
                            <?php else: ?>
                                <div class="h-8 w-8 rounded-full bg-teal-100 flex items-center justify-center text-teal-700 font-semibold text-sm">
                                    <?= e(mb_strtoupper(mb_substr($currentUser['first_name'] ?? 'M', 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <span class="text-sm font-medium text-gray-700 max-w-[120px] truncate">
                                <?= e($currentUser['display_name'] ?: trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))) ?>
                            </span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-transition
                             class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">

                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-xs text-gray-500">Signed in as</p>
                                <p class="text-sm font-medium text-gray-800 truncate"><?= e($currentUser['email'] ?? '') ?></p>
                                <p class="text-xs text-teal-600 font-semibold mt-0.5">
                                    <?= number_format((float)($currentUser['balance'] ?? 0), 2) ?> <?= e($currencyName) ?><?= (float)($currentUser['balance'] ?? 0) != 1 ? 's' : '' ?>
                                </p>
                            </div>

                            <a href="<?= url('/members/' . ($currentUser['id'] ?? '')) ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                My Profile
                            </a>
                            <a href="<?= url('/transactions') ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                My Transactions
                            </a>
                            <a href="<?= url('/transactions/statement') ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                My Statement
                            </a>
                            <a href="<?= url('/profile/edit') ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Settings
                            </a>

                            <?php if (\TimeBank\Core\Auth::isAdmin()): ?>
                                <div class="border-t border-gray-100 mt-1 pt-1">
                                    <a href="<?= url('/admin') ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-teal-700 font-medium hover:bg-teal-50 transition-colors">
                                        <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        Admin Panel
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="border-t border-gray-100 mt-1 pt-1">
                                <a href="<?= url('/logout') ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= url('/login') ?>" class="btn-primary text-sm px-4 py-2 rounded-lg bg-teal-600 text-white font-medium hover:bg-teal-700 transition-colors">Sign In</a>
                <?php endif; ?>
            </div>

            <!-- Mobile hamburger -->
            <div class="md:hidden flex items-center gap-2">
                <a href="<?= url('/messages') ?>" class="relative p-2 text-gray-500 hover:text-teal-700 rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <?php if ($unread > 0): ?>
                        <span class="absolute top-0.5 right-0.5 h-4 w-4 rounded-full bg-orange-500 text-white text-xs flex items-center justify-center font-bold leading-none"><?= $unread > 9 ? '9+' : $unread ?></span>
                    <?php endif; ?>
                </a>
                <button @click="mobileOpen = !mobileOpen" class="p-2 rounded-md text-gray-500 hover:text-teal-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div x-show="mobileOpen" x-transition class="md:hidden border-t border-gray-200 bg-white pb-3">
        <div class="px-4 pt-3 space-y-1">
            <?php foreach ($navLinks as $link): ?>
                <?php $active = navIsActive($link['href'], $currentPath); ?>
                <a href="<?= url($link['href']) ?>"
                   class="block px-3 py-2 rounded-md text-sm font-medium <?= $active ? 'bg-teal-50 text-teal-700' : 'text-gray-600 hover:text-teal-700 hover:bg-gray-50' ?>">
                    <?= e($link['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (\TimeBank\Core\Auth::isLoggedIn() && ($currentUser = \TimeBank\Core\Auth::user())): ?>
            <div class="border-t border-gray-200 mt-3 px-4 pt-3 space-y-1">
                <div class="flex items-center gap-3 px-3 py-2">
                    <?php if (!empty($currentUser['avatar_path'])): ?>
                        <img src="<?= e(media_url($currentUser['avatar_path'])) ?>" alt="" class="h-9 w-9 rounded-full object-cover">
                    <?php else: ?>
                        <div class="h-9 w-9 rounded-full bg-teal-100 flex items-center justify-center text-teal-700 font-semibold">
                            <?= e(mb_strtoupper(mb_substr($currentUser['first_name'] ?? 'M', 0, 1))) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <p class="text-sm font-medium text-gray-800"><?= e($currentUser['display_name'] ?: trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))) ?></p>
                        <p class="text-xs text-teal-600"><?= number_format((float)($currentUser['balance'] ?? 0), 2) ?> <?= e($currencyName) ?>s</p>
                    </div>
                </div>
                <a href="<?= url('/members/' . ($currentUser['id'] ?? '')) ?>" class="block px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-md">My Profile</a>
                <a href="<?= url('/transactions') ?>" class="block px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-md">My Transactions</a>
                <a href="<?= url('/transactions/statement') ?>" class="block px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-md">My Statement</a>
                <a href="<?= url('/profile/edit') ?>" class="block px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-md">Settings</a>
                <?php if (\TimeBank\Core\Auth::isAdmin()): ?>
                    <a href="<?= url('/admin') ?>" class="block px-3 py-2 text-sm text-teal-700 font-medium hover:bg-teal-50 rounded-md">Admin Panel</a>
                <?php endif; ?>
                <a href="<?= url('/logout') ?>" class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-md">Sign Out</a>
            </div>
        <?php else: ?>
            <div class="border-t border-gray-200 mt-3 px-4 pt-3">
                <a href="<?= url('/login') ?>" class="block w-full text-center px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition-colors">Sign In</a>
            </div>
        <?php endif; ?>
    </div>
</nav>
