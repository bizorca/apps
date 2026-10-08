<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_admin();

$page_title = 'Admin Dashboard';
include LT_ROOT . '/includes/admin_header.php';

$stats = [
    'Courses'     => [db_val('SELECT COUNT(*) FROM lt_courses'),    APP_URL . '/admin/courses.php'],
    'Units'       => [db_val('SELECT COUNT(*) FROM lt_units'),      APP_URL . '/admin/units.php'],
    'Challenges'  => [db_val('SELECT COUNT(*) FROM lt_challenges'), APP_URL . '/admin/challenges.php'],
    'Lessons'     => [db_val('SELECT COUNT(*) FROM lt_lessons'),    APP_URL . '/admin/lessons.php'],
    'Students'    => [db_val('SELECT COUNT(DISTINCT user_id) FROM lt_enrollments'), APP_URL . '/admin/members.php'],
    'Enrollments' => [db_val('SELECT COUNT(*) FROM lt_enrollments'), APP_URL . '/admin/members.php'],
];
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-xl font-bold text-slate-800">Dashboard</h1>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
  <?php foreach ($stats as $label => [$count, $url]): ?>
    <a href="<?= $url ?>" class="bg-white border border-slate-200 rounded-xl p-5 hover:border-primary hover:shadow-sm transition-all block no-underline">
      <div class="text-3xl font-bold text-slate-800 mb-1"><?= $count ?></div>
      <div class="text-sm text-slate-500"><?= $label ?></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="text-sm font-semibold text-slate-700 mb-3">Quick actions</div>
<div class="flex flex-wrap gap-2">
  <a href="<?= APP_URL ?>/admin/courses.php?action=add"    class="inline-block bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:opacity-90">+ New Course</a>
  <a href="<?= APP_URL ?>/admin/units.php?action=add"      class="inline-block border border-slate-300 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-slate-50">+ New Unit</a>
  <a href="<?= APP_URL ?>/admin/challenges.php?action=add" class="inline-block border border-slate-300 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-slate-50">+ New Challenge</a>
  <a href="<?= APP_URL ?>/admin/lessons.php?action=add"    class="inline-block border border-slate-300 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-slate-50">+ New Lesson</a>
</div>

<?php include LT_ROOT . '/includes/admin_footer.php'; ?>
