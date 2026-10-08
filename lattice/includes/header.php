<?php $user = current_user(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title ?? APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: { DEFAULT: '#1B6EA0', dark: '#155E8A', light: '#E8F3F9' }
          }
        }
      }
    }
  </script>
  <style>
    /* Bookmark clip-path for milestone indicators — not available in Tailwind */
    .milestone-shape { clip-path: polygon(0 0, 100% 0, 100% 72%, 50% 100%, 0 72%); }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

<header class="bg-white border-b border-slate-200 sticky top-0 z-50">
  <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">

    <div class="flex items-center gap-3">
    <a href="/" class="text-xs text-slate-400 hover:text-primary" title="All tools">Bizorca Tools</a>
    <span class="text-slate-300">/</span>
    <a href="<?= APP_URL ?>/home.php" class="flex items-center gap-3 no-underline">
      <div class="w-9 h-9 rounded-full bg-primary text-white font-black text-lg flex items-center justify-center select-none">⬡</div>
      <span class="font-bold text-slate-800 text-base tracking-tight"><?= h(APP_NAME) ?></span>
    </a>
    </div>

    <nav class="flex items-center gap-5">
      <?php if ($user): ?>
        <?php if ($user['is_admin']): ?>
          <a href="<?= APP_URL ?>/admin/" class="text-sm text-slate-500 hover:text-primary transition-colors">Admin</a>
        <?php endif; ?>

        <div class="relative group">
          <div class="w-9 h-9 rounded-full bg-primary text-white font-bold text-sm flex items-center justify-center cursor-pointer select-none">
            <?= strtoupper(mb_substr($user['name'], 0, 1)) ?>
          </div>
          <div class="hidden group-hover:block absolute right-0 top-full mt-2 w-44 bg-white border border-slate-200 rounded-lg shadow-lg py-1 z-50">
            <div class="px-4 py-2.5 text-xs font-bold text-slate-800 border-b border-slate-100"><?= h($user['name']) ?></div>
            <a href="/account/settings.php" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Account</a>
            <a href="/account/logout.php" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Sign out</a>
          </div>
        </div>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main>
<?php if ($_info = flash_get('info')): ?>
  <div class="max-w-6xl mx-auto px-6 pt-6">
    <div class="bg-blue-50 border border-blue-200 text-blue-700 rounded-lg px-5 py-3 text-sm"><?= h($_info) ?></div>
  </div>
<?php endif; ?>
