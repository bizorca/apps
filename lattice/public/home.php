<?php
require_once __DIR__ . '/_bootstrap.php';
require_login();
$user = current_user();

// Enroll action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course_id'])) {
    verify_csrf();
    $cid = (int)$_POST['enroll_course_id'];
    if (!db_val('SELECT COUNT(*) FROM lt_enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $cid])) {
        db_insert('lt_enrollments', ['user_id' => $user['id'], 'course_id' => $cid]);
    }
    redirect(APP_URL . '/course.php?id=' . $cid);
}

$courses = db_rows("
    SELECT c.*, e.id AS enrollment_id
    FROM lt_courses c
    LEFT JOIN lt_enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_published = 1
    ORDER BY c.title
", [$user['id']]);

$page_title = 'My Courses — ' . APP_NAME;
include LT_ROOT . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto px-6 py-10">
  <h1 class="text-2xl font-bold text-slate-800 mb-1">My Courses</h1>
  <p class="text-slate-500 mb-8">Pick up where you left off, or enroll in something new.</p>

  <?php if (empty($courses)): ?>
    <div class="bg-blue-50 border border-blue-200 text-blue-700 rounded-lg px-5 py-4 text-sm">
      No courses available yet. Check back soon.
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ($courses as $course):
        $enrolled = !empty($course['enrollment_id']);
        $dots     = $enrolled ? get_progress_dots($user['id'], $course['id']) : [];
        $score    = $enrolled ? get_course_score($user['id'], $course['id'])  : null;
      ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">

          <!-- Thumbnail -->
          <div class="h-36 bg-gradient-to-br from-sky-500 to-blue-700 flex items-center justify-center text-4xl text-white/40 overflow-hidden">
            <?php if ($course['thumbnail_path']): ?>
              <img src="<?= h(thumb_url($course['thumbnail_path'])) ?>" alt="" class="w-full h-full object-cover">
            <?php else: ?>
              📚
            <?php endif; ?>
          </div>

          <div class="p-5 flex flex-col flex-1">
            <h3 class="font-bold text-slate-800 mb-2 leading-snug"><?= h($course['title']) ?></h3>

            <?php if ($course['description']): ?>
              <p class="text-xs text-slate-500 mb-3 line-clamp-2"><?= h($course['description']) ?></p>
            <?php endif; ?>

            <!-- Mini progress dots -->
            <?php if ($enrolled && !empty($dots)): ?>
              <div class="flex flex-wrap gap-1 mb-3">
                <?php foreach ($dots as $d):
                  $is_ms = in_array($d['type'], ['milestone', 'final_milestone', 'practice_milestone']);
                  $bg    = $d['completed'] ? 'bg-green-500' : ($d['attempted'] ? 'bg-amber-400' : 'bg-slate-200');
                  if ($is_ms): ?>
                    <span class="milestone-shape inline-block <?= $d['completed'] ? 'bg-violet-600' : ($d['attempted'] ? 'bg-amber-400' : 'bg-slate-300') ?>"
                          style="width:10px;height:13px;" title="<?= h($d['title']) ?>"></span>
                  <?php else: ?>
                    <span class="inline-block w-2.5 h-2.5 rounded-full <?= $bg ?>" title="<?= h($d['title']) ?>"></span>
                  <?php endif;
                endforeach; ?>
              </div>
              <div class="text-xs text-slate-500 mb-4">
                <?= $score['completed'] > 0 ? $score['pct'] . '% — ' . $score['completed'] . '/' . $score['total'] . ' complete' : 'Not started' ?>
              </div>
            <?php endif; ?>

            <div class="mt-auto">
              <?php if ($enrolled): ?>
                <a href="<?= APP_URL ?>/course.php?id=<?= $course['id'] ?>"
                   class="inline-block bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:opacity-90 transition-opacity">
                  Continue →
                </a>
              <?php else: ?>
                <form method="POST">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="enroll_course_id" value="<?= $course['id'] ?>">
                  <button type="submit"
                          class="inline-block border border-slate-300 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors">
                    Enroll
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include LT_ROOT . '/includes/footer.php'; ?>
