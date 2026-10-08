<?php
require_once __DIR__ . '/_bootstrap.php';
require_login();
$user         = current_user();
$challenge_id = (int)($_GET['id'] ?? 0);

$challenge = db_row('SELECT * FROM lt_challenges WHERE id = ?', [$challenge_id]);
if (!$challenge) { http_response_code(404); include __DIR__ . '/404.php'; exit; }

// The course comes from the challenge, never from the URL. The original
// checked enrollment against ?course_id= and served whatever challenge ?id=
// named, so any challenge in any course (unpublished included) was open to
// anyone enrolled in something.
$course_id = (int)db_val(
    'SELECT u.course_id FROM lt_units u JOIN lt_challenges c ON c.unit_id = u.id WHERE c.id = ?',
    [$challenge_id]
);
$course = db_row('SELECT * FROM lt_courses WHERE id = ? AND is_published = 1', [$course_id]);
if (!$course) { http_response_code(404); include __DIR__ . '/404.php'; exit; }

if (!db_val('SELECT COUNT(*) FROM lt_enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $course_id])) {
    redirect(APP_URL . '/home.php');
}

// Locks were only a hidden link on the course page; the URL still worked.
if ($challenge['type'] === 'milestone' && !is_milestone_unlocked($user['id'], (int)$challenge['unit_id'])) {
    flash_set('info', 'Complete all challenges in this unit to unlock that milestone.');
    redirect(APP_URL . '/course.php?id=' . $course_id);
}
if ($challenge['type'] === 'final_milestone' && !is_final_milestone_unlocked($user['id'], $course_id)) {
    flash_set('info', 'Complete all unit milestones to unlock the final milestone.');
    redirect(APP_URL . '/course.php?id=' . $course_id);
}

$lessons = db_rows('SELECT * FROM lt_lessons WHERE challenge_id = ? ORDER BY sort_order', [$challenge_id]);
if (empty($lessons)) {
    flash_set('info', 'This challenge has no content yet.');
    redirect(APP_URL . '/course.php?id=' . $course_id);
}
$lesson_count = count($lessons);

$attempt = get_or_create_attempt($user['id'], $challenge_id);

// Build per-lesson status
$lessons_status = [];
$default_lesson = null;
foreach ($lessons as $lesson) {
    $slot   = db_row('SELECT * FROM lt_question_slots WHERE lesson_id = ?', [$lesson['id']]);
    $status = $slot
        ? get_lesson_status($slot['id'], $attempt['id'])
        : ['is_complete' => false, 'is_correct' => false, 'response_count' => 0,
           'next_variant_number' => 1, 'total_variants' => 0, 'is_exhausted' => false];

    $lessons_status[] = [
        'lesson'     => $lesson,
        'slot'       => $slot,
        'status'     => $status,
        'nav_status' => $status['is_complete']
            ? ($status['is_correct'] ? 'correct' : 'attempted')
            : 'not_started',
        'is_active'  => false,
    ];

    if ($default_lesson === null && !$status['is_complete']) {
        $default_lesson = $lesson;
    }
}
if ($default_lesson === null) $default_lesson = end($lessons);

// Apply requested sort order
$requested_sort = (int)($_GET['lesson'] ?? 0);
$active_lesson  = $default_lesson;
if ($requested_sort >= 1) {
    foreach ($lessons as $l) {
        if ((int)$l['sort_order'] === $requested_sort) { $active_lesson = $l; break; }
    }
}

// Mark active in status array
$active_idx = 0;
foreach ($lessons_status as $i => &$ls) {
    if ($ls['lesson']['id'] === $active_lesson['id']) {
        $ls['is_active']  = true;
        $active_idx       = $i;
        if ($ls['nav_status'] === 'not_started') $ls['nav_status'] = 'active';
    }
}
unset($ls);

$active_ls     = $lessons_status[$active_idx];
$active_slot   = $active_ls['slot'];
$active_status = $active_ls['status'];

$variant  = null;
$answers  = [];
$readonly = false;

if ($active_slot) {
    if ($active_status['is_complete']) {
        $readonly = true;
        $variant  = db_row('SELECT * FROM lt_question_variants WHERE slot_id = ? AND variant_number = 1', [$active_slot['id']]);
    } else {
        $variant = get_next_variant($active_slot['id'], $active_status['next_variant_number']);
    }
    if ($variant) $answers = get_answers_for_variant($variant['id']);
}

$next_lesson_url = null;
if (isset($lessons_status[$active_idx + 1])) {
    $ns = $lessons_status[$active_idx + 1]['lesson']['sort_order'];
    $next_lesson_url = APP_URL . "/challenge.php?id={$challenge_id}&course_id={$course_id}&lesson={$ns}";
}
$prev_lesson_url = null;
if ($active_idx > 0) {
    $ps = $lessons_status[$active_idx - 1]['lesson']['sort_order'];
    $prev_lesson_url = APP_URL . "/challenge.php?id={$challenge_id}&course_id={$course_id}&lesson={$ps}";
}

$course_url = APP_URL . "/course.php?id={$course_id}";
$done_count = count(array_filter($lessons_status, fn($ls) => $ls['status']['is_complete']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($challenge['title']) ?> — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#1B6EA0', dark: '#155E8A' } } } } }</script>
  <style>
    body { height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
    .split-pane { flex: 1; overflow: hidden; }
  </style>
</head>
<body class="bg-white text-slate-800">

<!-- Dark top bar -->
<div class="bg-slate-900 h-14 flex items-center px-5 gap-4 shrink-0 z-10">
  <a href="<?= h($course_url) ?>"
     class="w-8 h-8 rounded-full bg-primary text-white font-black text-sm flex items-center justify-center no-underline shrink-0">⬡</a>
  <div class="flex-1 text-white text-sm font-semibold truncate"><?= h(strtoupper($challenge['title'])) ?></div>
  <div class="text-slate-400 text-xs shrink-0"><?= $done_count ?>/<?= $lesson_count ?></div>
  <a href="<?= h($course_url) ?>" class="text-slate-400 hover:text-white text-xl px-1 transition-colors">✕</a>
</div>

<!-- Question nav bar -->
<div class="bg-white border-b border-slate-200 h-14 flex items-center justify-center gap-2 px-4 shrink-0">
  <?php if ($prev_lesson_url): ?>
    <a href="<?= h($prev_lesson_url) ?>" class="text-slate-400 hover:text-slate-700 text-2xl px-1 transition-colors">‹</a>
  <?php else: ?>
    <span class="text-slate-200 text-2xl px-1 cursor-not-allowed">‹</span>
  <?php endif; ?>

  <div class="flex items-center gap-1.5">
    <?php foreach ($lessons_status as $i => $ls): ?>
      <?php
      $nav = $ls['nav_status'];
      $is_active = $ls['is_active'];
      if ($nav === 'correct') {
          $cls = 'bg-green-500 border-green-500 text-white';
          $inner = '✓';
      } elseif ($nav === 'attempted') {
          $cls = 'bg-slate-400 border-slate-400 text-white';
          $inner = '✓';
      } elseif ($is_active || $nav === 'active') {
          $cls = 'border-amber-400 bg-amber-50 text-slate-700 font-bold';
          $inner = $i + 1;
      } else {
          $cls = 'border-slate-300 bg-white text-slate-500';
          $inner = $i + 1;
      }
      $sort = $ls['lesson']['sort_order'];
      ?>
      <a href="<?= APP_URL ?>/challenge.php?id=<?= $challenge_id ?>&course_id=<?= $course_id ?>&lesson=<?= $sort ?>"
         class="w-8 h-8 rounded-full border-2 text-xs flex items-center justify-center transition-all hover:border-primary <?= $cls ?>"
         title="<?= h($ls['lesson']['title']) ?>">
        <?= $inner ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($next_lesson_url): ?>
    <a href="<?= h($next_lesson_url) ?>" class="text-slate-400 hover:text-slate-700 text-2xl px-1 transition-colors">›</a>
  <?php else: ?>
    <span class="text-slate-200 text-2xl px-1 cursor-not-allowed">›</span>
  <?php endif; ?>
</div>

<!-- Split pane -->
<div class="split-pane flex">

  <!-- Left: question panel -->
  <div class="w-[42%] border-r border-slate-200 overflow-y-auto p-7 shrink-0">

    <!-- Section label -->
    <div class="text-lg font-bold text-slate-800 mb-2">
      <?= $active_idx + 1 ?> — <?= h($active_lesson['title']) ?>
    </div>

    <!-- Learning objective -->
    <?php if ($active_lesson['learning_objective']): ?>
      <p class="text-xs text-slate-500 mb-4 leading-relaxed">
        <strong class="text-slate-600">LEARNING OBJECTIVE:</strong>
        <?= h($active_lesson['learning_objective']) ?>
      </p>
    <?php endif; ?>

    <!-- Attempt dots -->
    <?php if ($active_slot && $active_status['total_variants'] > 0): ?>
      <div class="flex gap-2 mb-5">
        <?php for ($i = 1; $i <= $active_status['total_variants']; $i++):
          if ($i <= $active_status['response_count']) {
              $cls = ($active_status['is_correct'] && $i === $active_status['response_count']) ? 'bg-green-500' : 'bg-amber-400';
          } else {
              $cls = 'bg-slate-200';
          }
        ?>
          <span class="attempt-dot inline-block w-3 h-3 rounded-full <?= $cls ?>"></span>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

    <!-- No question yet -->
    <?php if (!$active_slot || !$variant): ?>
      <div class="bg-blue-50 border border-blue-200 text-blue-700 rounded-lg px-4 py-3 text-sm mt-4">
        No question has been added for this lesson yet.
      </div>

    <!-- Completed: read-only view -->
    <?php elseif ($readonly): ?>
      <p class="text-sm font-semibold text-slate-800 mb-4 leading-relaxed"><?= h($variant['question_text']) ?></p>
      <div class="space-y-2.5 mb-5">
        <?php $labels = ['a','b','c','d','e'];
        foreach ($answers as $i => $ans): ?>
          <div class="border-2 rounded-lg px-4 py-3 <?= $ans['is_correct'] ? 'border-green-500 bg-green-50' : 'border-slate-200 bg-slate-50 opacity-60' ?>">
            <label class="flex items-center gap-3 text-sm <?= $ans['is_correct'] ? 'text-green-800 font-semibold' : 'text-slate-500' ?>">
              <input type="radio" disabled <?= $ans['is_correct'] ? 'checked' : '' ?>>
              <span class="font-semibold"><?= $labels[$i] ?? $i+1 ?>.)</span>
              <?= h($ans['answer_text']) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="bg-<?= $active_status['is_correct'] ? 'green' : 'slate' ?>-50 border border-<?= $active_status['is_correct'] ? 'green' : 'slate' ?>-200 rounded-lg p-4 text-center">
        <div class="font-bold text-<?= $active_status['is_correct'] ? 'green-700' : 'slate-600' ?> mb-3">
          <?= $active_status['is_correct'] ? '✓ Correct!' : 'Lesson attempted' ?>
        </div>
        <?php if ($next_lesson_url): ?>
          <a href="<?= h($next_lesson_url) ?>"
             class="inline-block bg-primary text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90 transition-opacity">
            Next →
          </a>
        <?php else: ?>
          <a href="<?= h($course_url) ?>"
             class="inline-block bg-primary text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90 transition-opacity">
            Back to Course
          </a>
        <?php endif; ?>
      </div>

    <!-- Active question -->
    <?php else: ?>
      <p class="text-sm font-semibold text-slate-800 mb-5 leading-relaxed"><?= h($variant['question_text']) ?></p>

      <form id="answer-form"
            class="space-y-2.5"
            data-attempt-id="<?= $attempt['id'] ?>"
            data-slot-id="<?= $active_slot['id'] ?>"
            data-variant-id="<?= $variant['id'] ?>"
            data-next-lesson-url="<?= h($next_lesson_url ?? '') ?>"
            data-course-url="<?= h($course_url) ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <?php $labels = ['a','b','c','d','e'];
        foreach ($answers as $i => $ans): ?>
          <div class="answer-option border-2 border-slate-200 rounded-lg hover:border-primary hover:bg-blue-50 transition-colors cursor-pointer">
            <label class="flex items-center gap-3 px-4 py-3 text-sm cursor-pointer">
              <input type="radio" name="answer" value="<?= $ans['id'] ?>" class="accent-primary shrink-0">
              <span class="font-semibold text-slate-400"><?= $labels[$i] ?? $i+1 ?>.)</span>
              <span class="text-slate-700"><?= h($ans['answer_text']) ?></span>
            </label>
          </div>
        <?php endforeach; ?>

        <div id="answer-feedback" class="hidden rounded-lg px-4 py-3 text-sm font-medium mt-1"></div>
        <div id="lesson-actions" class="mt-1"></div>

        <button type="submit" id="submit-btn"
                class="w-full bg-primary text-white font-semibold py-3 rounded-lg text-sm hover:opacity-90 transition-opacity mt-2">
          Submit Answer
        </button>
      </form>
    <?php endif; ?>

  </div><!-- /question-panel -->

  <!-- Right: lesson content -->
  <div class="flex-1 overflow-y-auto px-9 py-7">

    <h1 class="text-2xl font-bold text-slate-800 mb-4"><?= h($active_lesson['title']) ?></h1>

    <div class="flex items-center gap-3 mb-6">
      <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-black text-lg">⬡</div>
      <span class="text-sm text-slate-600"><?= h(APP_NAME) ?></span>
    </div>

    <?php if ($active_lesson['what_covered_text']): ?>
      <div class="border border-slate-200 rounded-lg overflow-hidden mb-7">
        <div class="bg-slate-900 text-white px-4 py-2.5 text-xs font-bold tracking-widest uppercase flex items-center gap-2">
          <span>≡</span> WHAT'S COVERED
        </div>
        <div class="bg-white px-4 py-3">
          <ol class="text-sm text-primary space-y-1.5 list-decimal list-inside">
            <?php foreach (array_filter(array_map('trim', explode("\n", $active_lesson['what_covered_text']))) as $topic): ?>
              <li><?= h($topic) ?></li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>
    <?php endif; ?>

    <div class="prose prose-sm max-w-none text-slate-700 leading-relaxed">
      <?= $active_lesson['content_html'] ?? '<p class="text-slate-400 italic">No lesson content added yet.</p>' ?>
    </div>

  </div><!-- /content-panel -->

</div><!-- /split-pane -->

<script>
// Tailwind-aware answer submission
const form = document.getElementById('answer-form');
if (form) {
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const selected = form.querySelector('input[name="answer"]:checked');
    if (!selected) return;

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Checking…';

    const payload = {
      attempt_id:  form.dataset.attemptId,
      slot_id:     form.dataset.slotId,
      variant_id:  form.dataset.variantId,
      answer_id:   selected.value,
      csrf_token:  form.querySelector('[name="csrf_token"]').value,
    };

    try {
      const res  = await fetch('<?= APP_URL ?>/ajax/submit_answer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      // Lock all options
      form.querySelectorAll('.answer-option').forEach(opt => {
        opt.style.pointerEvents = 'none';
        opt.querySelector('input').disabled = true;
      });

      // Highlight correct / wrong
      form.querySelectorAll('.answer-option').forEach(opt => {
        const r = opt.querySelector('input[type="radio"]');
        if (String(r.value) === String(data.correct_answer_id)) {
          opt.classList.remove('border-slate-200');
          opt.classList.add('border-green-500','bg-green-50');
        }
      });
      const selOpt = form.querySelector(`input[value="${selected.value}"]`)?.closest('.answer-option');
      if (selOpt && !data.is_correct) {
        selOpt.classList.remove('border-slate-200');
        selOpt.classList.add('border-red-400','bg-red-50');
      }

      // Update attempt dots
      const dots = document.querySelectorAll('.attempt-dot');
      const usedBefore = Array.from(dots).filter(d => d.classList.contains('bg-amber-400') || d.classList.contains('bg-green-500')).length;
      if (dots[usedBefore]) {
        dots[usedBefore].classList.remove('bg-slate-200');
        dots[usedBefore].classList.add(data.is_correct ? 'bg-green-500' : 'bg-amber-400');
      }

      // Feedback
      const fb = document.getElementById('answer-feedback');
      fb.classList.remove('hidden');
      if (data.is_correct) {
        fb.className = 'rounded-lg px-4 py-3 text-sm font-medium mt-1 bg-green-50 text-green-700';
        fb.textContent = 'Correct!';
      } else {
        fb.className = 'rounded-lg px-4 py-3 text-sm font-medium mt-1 bg-red-50 text-red-600';
        fb.textContent = data.lesson_complete ? "Not quite — but you've used all attempts. Move on!" : 'Not quite — try again.';
      }

      // Actions
      const acts = document.getElementById('lesson-actions');
      if (data.challenge_complete) {
        acts.innerHTML = '<p class="text-green-600 font-semibold text-sm mt-2">Challenge complete! Returning to course…</p>';
        setTimeout(() => { window.location.href = data.course_url; }, 1800);
      } else if (data.lesson_complete && data.next_lesson_url) {
        acts.innerHTML = `<a href="${data.next_lesson_url}" class="inline-block mt-2 bg-primary text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90">Next →</a>`;
        btn.style.display = 'none';
      } else if (!data.lesson_complete) {
        btn.textContent = 'Try Again';
        btn.disabled = false;
        btn.onclick = () => window.location.reload();
      }

    } catch(err) {
      btn.disabled = false;
      btn.textContent = 'Submit Answer';
      console.error(err);
    }
  });
}
</script>
</body>
</html>
