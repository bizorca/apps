<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= PF_BASE ?>/assets/app.css">
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">

<?php
require_once __DIR__ . '/../includes/auth.php';
$currentUser = currentUser();
?>
<nav class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <a href="/" class="text-sm text-gray-400 hover:text-indigo-600" title="All tools">Bizorca Tools</a>
        <span class="text-gray-300">/</span>
        <a href="<?= PF_BASE ?><?= $currentUser ? '/dashboard.php' : '/' ?>" class="text-lg font-bold text-indigo-700 tracking-tight">ProForma</a>
    </div>
    <div class="flex items-center gap-4 text-sm">
        <?php if ($currentUser): ?>
            <a href="/account/settings.php" class="text-gray-500 hover:text-indigo-600"><?= h($currentUser['name']) ?></a>
            <a href="<?= PF_BASE ?>/dashboard.php" class="text-gray-600 hover:text-indigo-600">Dashboard</a>
            <a href="<?= PF_BASE ?>/localrev.php" class="text-gray-600 hover:text-indigo-600">Market Intel</a>
            <a href="<?= PF_BASE ?>/settings.php" class="text-gray-600 hover:text-indigo-600">Settings</a>
            <a href="/account/logout.php" class="text-gray-600 hover:text-red-600">Sign out</a>
        <?php else: ?>
            <a href="/account/login.php?next=<?= rawurlencode(PF_BASE . '/dashboard.php') ?>" class="text-gray-600 hover:text-indigo-600">Sign in</a>
            <a href="/account/register.php?next=<?= rawurlencode(PF_BASE . '/dashboard.php') ?>" class="bg-indigo-600 text-white px-4 py-1.5 rounded hover:bg-indigo-700">Get started</a>
        <?php endif; ?>
    </div>
</nav>

<main class="max-w-5xl mx-auto px-6 py-8">
<?= renderFlash() ?>
