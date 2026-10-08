<?php
$admin_section = basename($_SERVER['PHP_SELF'], '.php');
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title ?? 'Admin') ?> — <?= APP_NAME ?></title>
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
</head>
<body class="bg-slate-50 text-slate-800">

<header class="bg-white border-b border-slate-200 sticky top-0 z-50 h-14 flex items-center px-6 gap-4">
  <a href="<?= APP_URL ?>/admin/" class="flex items-center gap-2.5 no-underline shrink-0">
    <div class="w-8 h-8 rounded-full bg-primary text-white font-black text-sm flex items-center justify-center">⬡</div>
    <span class="font-bold text-slate-800 text-sm"><?= h(APP_NAME) ?> <span class="text-slate-400 font-normal">/ Admin</span></span>
  </a>
  <div class="flex-1"></div>
  <a href="<?= APP_URL ?>/home.php" class="text-sm text-slate-500 hover:text-primary">← Student view</a>
  <a href="/account/logout.php" class="text-sm text-slate-500 hover:text-primary">Sign out</a>
</header>

<div class="flex min-h-[calc(100vh-3.5rem)]">

  <!-- Sidebar -->
  <aside class="w-52 bg-white border-r border-slate-200 shrink-0 py-4">
    <div class="px-4 mb-1 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Content</div>
    <?php
    $nav = [
      'index'      => ['Dashboard', APP_URL . '/admin/'],
      'courses'    => ['Courses',    APP_URL . '/admin/courses.php'],
      'units'      => ['Units',      APP_URL . '/admin/units.php'],
      'challenges' => ['Challenges', APP_URL . '/admin/challenges.php'],
      'lessons'    => ['Lessons',    APP_URL . '/admin/lessons.php'],
      'questions'  => ['Questions',  APP_URL . '/admin/questions.php'],
    ];
    foreach ($nav as $key => [$label, $url]):
      $active = $admin_section === $key || ($key === 'index' && $admin_section === 'index');
    ?>
      <a href="<?= $url ?>" class="flex items-center px-4 py-2 text-sm <?= $active ? 'text-primary font-semibold bg-primary-light border-l-2 border-primary' : 'text-slate-600 hover:bg-slate-50 border-l-2 border-transparent' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>

    <div class="px-4 mt-4 mb-1 text-[10px] font-bold text-slate-400 uppercase tracking-widest">People</div>
    <?php
    $active = $admin_section === 'members';
    ?>
    <a href="<?= APP_URL ?>/admin/members.php" class="flex items-center px-4 py-2 text-sm <?= $active ? 'text-primary font-semibold bg-primary-light border-l-2 border-primary' : 'text-slate-600 hover:bg-slate-50 border-l-2 border-transparent' ?>">
      Users
    </a>
  </aside>

  <div class="flex-1 p-7 overflow-x-auto">
