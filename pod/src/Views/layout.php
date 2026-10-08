<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Bizorca Pod') ?> — Bizorca Pod</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .prose { max-width: 65ch; }
        .prose p { margin-bottom: 1rem; line-height: 1.75; }
    </style>
</head>
<body class="h-full bg-gray-50 text-gray-900">

<nav class="bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-14">
        <div class="flex items-center gap-6">
            <a href="<?= url('/') ?>" class="font-bold text-indigo-600 text-lg tracking-tight">Bizorca Pod</a>
            <a href="<?= url('courses') ?>" class="text-sm text-gray-600 hover:text-gray-900">Courses</a>
            <a href="<?= url('forum') ?>" class="text-sm text-gray-600 hover:text-gray-900">Forum</a>
            <a href="<?= url('tickets') ?>" class="text-sm text-gray-600 hover:text-gray-900">Support</a>
            <a href="<?= url('events') ?>" class="text-sm text-gray-600 hover:text-gray-900">Events</a>
            <a href="<?= url('members') ?>" class="text-sm text-gray-600 hover:text-gray-900">Members</a>
            <?php if (!empty($user['is_admin'])): ?>
                <a href="<?= url('admin') ?>" class="text-sm text-amber-600 hover:text-amber-800 font-medium">Admin</a>
            <?php elseif (!empty($user['is_staff'])): ?>
                <?php /* The original linked staff to /admin, which is admin-only (403). */ ?>
                <a href="<?= url('tickets') ?>" class="text-sm text-amber-600 hover:text-amber-800 font-medium">Staff</a>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-4">
            <?php if (!empty($user)): ?>
                <?php
                $unreadCount = (int)(\Bizorca\Pod\Core\Database::fetchOne(
                    'SELECT COUNT(*) AS n FROM pd_notifications WHERE user_id = ? AND is_read = 0',
                    [$user['id']]
                )['n'] ?? 0);
                ?>
                <a href="<?= url('notifications') ?>" class="relative text-gray-400 hover:text-gray-700 text-sm" title="Notifications">
                    &#128276;
                    <?php if ($unreadCount > 0): ?>
                        <span class="absolute -top-1 -right-2 min-w-[1.1rem] h-4 rounded-full bg-red-500 text-white text-xs flex items-center justify-center font-semibold px-0.5 leading-none">
                            <?= $unreadCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= url('profile/edit') ?>" class="text-sm text-gray-500 hover:text-indigo-600">
                    <?= h($user['first_name']) ?>
                </a>
                <a href="/account/logout.php" class="text-sm text-gray-400 hover:text-gray-700">Sign out</a>
            <?php else: ?>
                <a href="/account/login.php?next=<?= h(rawurlencode(url('/'))) ?>" class="text-sm text-indigo-600 hover:text-indigo-800">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="max-w-6xl mx-auto px-4 py-8">
    <?php if ($flash = \Bizorca\Pod\Auth\Session::getFlash('error')): ?>
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            <?= h($flash) ?>
        </div>
    <?php endif; ?>
    <?php if ($flash = \Bizorca\Pod\Auth\Session::getFlash('success')): ?>
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            <?= h($flash) ?>
        </div>
    <?php endif; ?>

    <?= $content ?? '' ?>
</main>

<footer class="mt-16 border-t border-gray-200 py-6 text-center text-xs text-gray-400">
    &copy; <?= date('Y') ?> Bizorca &mdash; All rights reserved.
</footer>

</body>
</html>
