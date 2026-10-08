<?php
require_once __DIR__ . '/_bootstrap.php';
require_login();
$user      = current_user();
$course_id = (int)($_GET['id'] ?? 0);

$course = db_row('SELECT * FROM lt_courses WHERE id = ? AND is_published = 1', [$course_id]);
if (!$course) { http_response_code(404); include __DIR__ . '/404.php'; exit; }

if (!db_val('SELECT COUNT(*) FROM lt_enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $course_id])) {
    redirect(APP_URL . '/home.php');
}

$units  = db_rows('SELECT * FROM lt_units WHERE course_id = ? ORDER BY sort_order, id', [$course_id]);
$score  = get_course_score($user['id'], $course_id);
$dots   = get_progress_dots($user['id'], $course_id);

$page_title = $course['title'] . ' — ' . APP_NAME;
include LT_ROOT . '/includes/header.php';
?>

<!-- Course banner -->
<div class="bg-white border-b border-slate-200">
  <div class="max-w-6xl mx-auto px-6 pt-8 pb-0">
    <div class="flex items-center gap-5 mb-6">
      <div class="w-24 h-16 rounded-lg bg-gradient-to-br from-sky-500 to-blue-700 overflow-hidden shrink-0">
        <?php if ($course['thumbnail_path']): ?>
          <img src="<?= h(thumb_url($course['thumbnail_path'])) ?>" alt="" class="w-full h-full object-cover">
        <?php endif; ?>
      </div>
      <h1 class="text-2xl font-bold text-slate-800"><?= h($course['title']) ?></h1>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-slate-200 -mb-px">
      <a href="<?= APP_URL ?>/course.php?id=<?= $course_id ?>"
         class="px-5 py-2.5 text-sm font-semibold text-primary border-b-2 border-primary">Dashboard</a>
      <a href="<?= APP_URL ?>/score-report.php?course_id=<?= $course_id ?>"
         class="px-5 py-2.5 text-sm font-semibold text-slate-500 border-b-2 border-transparent hover:text-primary hover:border-primary transition-colors">Score Report</a>
    </div>
  </div>
</div>

<!-- Progress strip + score widget -->
<div class="bg-white border-b border-slate-200">
  <div class="max-w-6xl mx-auto px-6 py-5">
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6 items-start">

      <!-- Progress dots -->
      <div>
        <div class="text-xs font-bold text-slate-700 mb-2.5 uppercase tracking-wide">Progress</div>
        <div class="flex flex-wrap gap-1.5 items-center bg-slate-50 border border-slate-200 rounded-lg p-4 min-h-[56px]">
          <?php foreach ($dots as $d):
            $is_ms = in_array($d['type'], ['milestone', 'final_milestone', 'practice_milestone']);
            $done  = (bool)$d['completed'];
            $prog  = (bool)$d['attempted'];
            if ($is_ms):
              $bg = $done ? 'bg-violet-600' : ($prog ? 'bg-amber-400' : 'bg-slate-300');
            ?>
              <span class="milestone-shape inline-block <?= $bg ?>" style="width:14px;height:18px;" title="<?= h($d['title']) ?>"></span>
            <?php else:
              $bg = $done ? 'bg-green-500' : ($prog ? 'bg-amber-400' : 'bg-slate-200 border-2 border-slate-300');
            ?>
              <span class="inline-block w-4 h-4 rounded-full <?= $bg ?> relative" title="<?= h($d['title']) ?>">
                <?php if ($done): ?>
                  <span class="absolute inset-0 flex items-center justify-center text-white text-[8px] font-bold">✓</span>
                <?php endif; ?>
              </span>
            <?php endif;
          endforeach; ?>
        </div>
      </div>

      <!-- Score widget -->
      <div class="bg-white border border-slate-200 rounded-lg p-4">
        <div class="flex justify-between items-center mb-3">
          <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">Score</span>
          <a href="<?= APP_URL ?>/score-report.php?course_id=<?= $course_id ?>" class="text-xs text-primary hover:underline">→ Score Report</a>
        </div>
        <!-- Bar -->
        <div class="relative mb-2">
          <div class="h-2.5 bg-slate-200 rounded-full overflow-visible relative">
            <div class="h-full rounded-full transition-all duration-500 <?= $score['pass'] ? 'bg-green-500' : 'bg-gradient-to-r from-red-400 to-orange-400' ?>"
                 style="width: <?= min($score['pct'], 100) ?>%"></div>
            <!-- 70% marker -->
            <div class="absolute top-[-5px] bottom-[-5px] w-0.5 bg-slate-700 rounded" style="left:70%">
              <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-700 whitespace-nowrap">70%</span>
            </div>
          </div>
        </div>
        <div class="text-center text-2xl font-bold <?= $score['pass'] ? 'text-green-600' : 'text-slate-700' ?> mt-1">
          <?= $score['pct'] ?>%
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Units accordion -->
<div class="max-w-6xl mx-auto px-6 py-8">
  <div class="text-sm font-bold text-slate-700 mb-4 uppercase tracking-wide">Units</div>
  <div class="flex flex-col gap-3">

    <?php foreach ($units as $unit):
      $is_final    = (bool)$unit['is_final'];
      $challenges  = db_rows('SELECT * FROM lt_challenges WHERE unit_id = ? ORDER BY sort_order', [$unit['id']]);
      $ms_unlocked = is_milestone_unlocked($user['id'], $unit['id']);
      $fm_unlocked = $is_final ? is_final_milestone_unlocked($user['id'], $course_id) : false;

      $unit_has_activity = false;
      foreach ($challenges as $ch) {
          if (db_val('SELECT COUNT(*) FROM lt_challenge_attempts WHERE user_id = ? AND challenge_id = ?', [$user['id'], $ch['id']])) {
              $unit_has_activity = true;
              break;
          }
      }
    ?>
      <div class="unit-item bg-white border border-slate-200 rounded-lg overflow-hidden <?= $unit_has_activity ? 'is-open' : '' ?>">

        <!-- Unit header / toggle -->
        <button class="unit-header w-full flex items-center justify-between px-5 py-4 hover:bg-slate-50 text-left">
          <span class="text-sm font-bold text-primary-dark uppercase tracking-wide">
            <?= h($unit['sort_order'] . '. ' . $unit['title']) ?>
          </span>
          <span class="unit-toggle text-slate-400 text-xl w-7 h-7 rounded-full border border-slate-200 flex items-center justify-center shrink-0 transition-transform">+</span>
        </button>

        <!-- Unit body: two-column -->
        <div class="unit-body border-t border-slate-200 hidden">
          <div class="flex">

            <!-- Left: challenge list -->
            <div class="flex-1 border-r border-slate-200 divide-y divide-slate-100">
              <?php foreach ($challenges as $ch):
                $attempt = db_row("SELECT * FROM lt_challenge_attempts WHERE user_id = ? AND challenge_id = ?
                                   ORDER BY attempt_number DESC LIMIT 1", [$user['id'], $ch['id']]);
                $completed = $attempt && $attempt['completed_at'];
                $started   = (bool)$attempt;
                $type      = $ch['type'];

                // Lesson stats
                $lesson_count = (int)db_val('SELECT COUNT(*) FROM lt_lessons WHERE challenge_id = ?', [$ch['id']]);
                $done_count   = 0;
                if ($attempt) {
                    foreach (db_rows('SELECT l.id FROM lt_lessons l JOIN lt_question_slots qs ON qs.lesson_id = l.id WHERE l.challenge_id = ?', [$ch['id']]) as $lrow) {
                        $slot   = db_row('SELECT id FROM lt_question_slots WHERE lesson_id = ?', [$lrow['id']]);
                        if ($slot && get_lesson_status($slot['id'], $attempt['id'])['is_complete']) $done_count++;
                    }
                }

                if ($type === 'practice_milestone' || $type === 'milestone'):
                  $locked = ($type === 'milestone') ? !$ms_unlocked : false;
                  $q_count = (int)db_val('SELECT COUNT(*) FROM lt_lessons WHERE challenge_id = ?', [$ch['id']]);
              ?>
                  <!-- Milestone row -->
                  <div class="px-5 py-3.5 <?= $locked ? 'opacity-60' : '' ?>">
                    <div class="flex items-center gap-2.5 mb-1">
                      <div class="milestone-shape <?= $locked ? 'bg-slate-300' : 'bg-violet-600' ?> shrink-0"
                           style="width:14px;height:18px;display:inline-block;"></div>
                      <?php if (!$locked): ?>
                        <a href="<?= APP_URL ?>/challenge.php?id=<?= $ch['id'] ?>&course_id=<?= $course_id ?>"
                           class="text-sm font-semibold text-primary hover:underline"><?= h(strtoupper($ch['title'])) ?></a>
                      <?php else: ?>
                        <span class="text-sm font-semibold text-slate-500"><?= h(strtoupper($ch['title'])) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="text-xs text-slate-400 ml-[22px]">
                      <?= $locked
                        ? '← Complete all challenges in this unit to unlock.'
                        : ($type === 'practice_milestone' ? 'Practice run — doesn\'t count toward your score.' : 'This one counts!') ?>
                    </div>
                    <?php if (!$locked && ($ch['time_limit_minutes'] || $q_count)): ?>
                      <div class="flex gap-4 ml-[22px] mt-1">
                        <?php if ($ch['time_limit_minutes']): ?>
                          <span class="text-xs text-slate-400">▪ <?= $ch['time_limit_minutes'] ?> minutes</span>
                        <?php endif; ?>
                        <?php if ($q_count): ?>
                          <span class="text-xs text-slate-400">▪ <?= $q_count ?> questions</span>
                        <?php endif; ?>
                        <?php if ($completed): ?>
                          <span class="text-xs text-green-600 font-semibold">▪ <?= number_format($attempt['score_pct'], 0) ?>%</span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>

                <?php elseif ($type === 'final_milestone'):
                  $locked = !$fm_unlocked;
                ?>
                  <div class="px-5 py-3.5 <?= $locked ? 'opacity-60' : '' ?>">
                    <div class="flex items-center gap-2.5 mb-1">
                      <div class="milestone-shape <?= $locked ? 'bg-slate-300' : 'bg-violet-700' ?> shrink-0"
                           style="width:14px;height:18px;display:inline-block;"></div>
                      <?php if (!$locked): ?>
                        <a href="<?= APP_URL ?>/challenge.php?id=<?= $ch['id'] ?>&course_id=<?= $course_id ?>"
                           class="text-sm font-semibold text-primary hover:underline"><?= h(strtoupper($ch['title'])) ?></a>
                      <?php else: ?>
                        <span class="text-sm font-semibold text-slate-500"><?= h(strtoupper($ch['title'])) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="text-xs text-slate-400 ml-[22px]">
                      <?= $locked ? '← Complete all unit milestones to unlock.' : 'This one counts!' ?>
                    </div>
                  </div>

                <?php else: // regular challenge ?>
                  <div class="flex items-center gap-3 px-5 py-3">
                    <!-- Status dot -->
                    <div class="w-3.5 h-3.5 rounded-full shrink-0
                      <?= $completed ? 'bg-green-500' : ($started ? 'bg-amber-400' : 'bg-slate-300') ?>
                      flex items-center justify-center">
                      <?php if ($completed): ?>
                        <span class="text-white text-[7px] font-bold">✓</span>
                      <?php endif; ?>
                    </div>
                    <a href="<?= APP_URL ?>/challenge.php?id=<?= $ch['id'] ?>&course_id=<?= $course_id ?>"
                       class="flex-1 text-sm text-primary hover:underline"><?= h($ch['title']) ?></a>
                    <?php if ($lesson_count): ?>
                      <span class="text-xs text-slate-400 bg-slate-100 rounded px-2 py-0.5 shrink-0"><?= $done_count ?>/<?= $lesson_count ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

              <?php endforeach; ?>
            </div>

            <!-- Right: about unit -->
            <div class="w-72 shrink-0 p-5">
              <?php if ($unit['about_html']): ?>
                <div class="text-[11px] font-bold text-slate-600 uppercase tracking-widest mb-2">About This Unit</div>
                <div class="text-sm text-slate-600 leading-relaxed"><?= $unit['about_html'] ?></div>
              <?php endif; ?>
              <?php if ($unit['tutorials_url']): ?>
                <div class="text-[11px] font-bold text-slate-600 uppercase tracking-widest mt-4 mb-1">Unit Tutorials</div>
                <p class="text-xs text-slate-500 mb-1.5">Complete collection covering everything in this unit.</p>
                <a href="<?= h($unit['tutorials_url']) ?>" target="_blank" class="text-xs text-primary hover:underline">→ Online Version</a>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
    <?php endforeach; ?>

  </div>
</div>

<?php include LT_ROOT . '/includes/footer.php'; ?>
