<?php
require_once __DIR__ . '/_bootstrap.php';
require_login();
$user      = current_user();
$course_id = (int)($_GET['course_id'] ?? 0);

$course = db_row('SELECT * FROM lt_courses WHERE id = ? AND is_published = 1', [$course_id]);
if (!$course || !db_val('SELECT COUNT(*) FROM lt_enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $course_id])) {
    redirect(APP_URL . '/home.php');
}

$score = get_course_score($user['id'], $course_id);
$units = db_rows('SELECT * FROM lt_units WHERE course_id = ? ORDER BY sort_order', [$course_id]);

$page_title = 'Score Report — ' . $course['title'];
include LT_ROOT . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto px-6 py-8">

  <!-- Breadcrumb -->
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
    <a href="<?= APP_URL ?>/course.php?id=<?= $course_id ?>" class="hover:text-primary">← <?= h($course['title']) ?></a>
    <span class="text-slate-300">/</span>
    <span>Score Report</span>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-8 items-start">

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 text-left">
            <th class="px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide border-b border-slate-200">Assessment</th>
            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide border-b border-slate-200">Type</th>
            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide border-b border-slate-200">Best Score</th>
            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide border-b border-slate-200">Weight</th>
            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide border-b border-slate-200">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($units as $unit): ?>
            <tr class="bg-slate-50">
              <td colspan="5" class="px-5 py-2.5 text-xs font-bold text-slate-600 uppercase tracking-wide"><?= h($unit['title']) ?></td>
            </tr>
            <?php
            $challenges = db_rows(
                "SELECT * FROM lt_challenges WHERE unit_id = ? AND type != 'practice_milestone' ORDER BY sort_order",
                [$unit['id']]
            );
            foreach ($challenges as $ch):
              $attempt = db_row("
                  SELECT * FROM lt_challenge_attempts
                  WHERE user_id = ? AND challenge_id = ? AND completed_at IS NOT NULL
                  ORDER BY score_pct DESC LIMIT 1
              ", [$user['id'], $ch['id']]);
              $w    = in_array($ch['type'], ['milestone','final_milestone']) ? MILESTONE_WEIGHT : 1;
              $best = $attempt ? number_format($attempt['score_pct'], 1) . '%' : '—';
              $pass = $attempt && $attempt['score_pct'] >= PASS_THRESHOLD;
            ?>
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-5 py-3 pl-8"><?= h($ch['title']) ?></td>
                <td class="px-4 py-3 text-slate-500 capitalize"><?= h(str_replace('_', ' ', $ch['type'])) ?></td>
                <td class="px-4 py-3 font-semibold"><?= $best ?></td>
                <td class="px-4 py-3 text-slate-500"><?= $w ?>×</td>
                <td class="px-4 py-3">
                  <?php if (!$attempt): ?>
                    <span class="text-slate-400">Not started</span>
                  <?php elseif ($pass): ?>
                    <span class="text-green-600 font-semibold">✓ Pass</span>
                  <?php else: ?>
                    <span class="text-red-500 font-semibold">Below 70%</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Summary -->
    <div class="space-y-4">
      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <div class="text-xs font-bold text-slate-600 uppercase tracking-wide mb-3">Overall Score</div>
        <div class="relative mb-3">
          <div class="h-2.5 bg-slate-200 rounded-full overflow-visible relative">
            <div class="h-full rounded-full <?= $score['pass'] ? 'bg-green-500' : 'bg-gradient-to-r from-red-400 to-orange-400' ?>"
                 style="width:<?= min($score['pct'], 100) ?>%"></div>
            <div class="absolute top-[-5px] bottom-[-5px] w-0.5 bg-slate-700 rounded" style="left:70%">
              <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-700">70%</span>
            </div>
          </div>
        </div>
        <div class="text-3xl font-bold text-center <?= $score['pass'] ? 'text-green-600' : 'text-slate-700' ?> mb-1">
          <?= $score['pct'] ?>%
        </div>
        <?php if ($score['pass']): ?>
          <div class="text-center text-xs text-green-600 font-semibold">Passing — 70% threshold met</div>
        <?php else: ?>
          <div class="text-center text-xs text-red-500">Below passing threshold</div>
        <?php endif; ?>
      </div>

      <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-600 leading-relaxed space-y-1">
        <div><strong class="text-slate-800"><?= $score['completed'] ?></strong> of <strong class="text-slate-800"><?= $score['total'] ?></strong> assessments completed.</div>
        <div class="text-xs text-slate-400">Milestones weighted <?= MILESTONE_WEIGHT ?>× vs. challenges.</div>
      </div>

      <a href="<?= APP_URL ?>/course.php?id=<?= $course_id ?>"
         class="block text-center border border-slate-300 text-slate-700 text-sm font-semibold px-4 py-2.5 rounded-lg hover:bg-slate-50 transition-colors">
        ← Back to Course
      </a>
    </div>

  </div>
</div>

<?php include LT_ROOT . '/includes/footer.php'; ?>
