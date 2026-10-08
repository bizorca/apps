<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_admin();

$lesson_id = (int)($_GET['lesson_id'] ?? 0);
$lesson    = $lesson_id ? db_row('SELECT * FROM lt_lessons WHERE id = ?', [$lesson_id]) : null;
if (!$lesson) redirect(APP_URL . '/admin/lessons.php');

$challenge   = db_row('SELECT ch.*, u.title as unit_title, c.title as course_title
                        FROM lt_challenges ch JOIN lt_units u ON u.id = ch.unit_id JOIN lt_courses c ON c.id = u.course_id
                        WHERE ch.id = ?', [$lesson['challenge_id']]);

$slot = db_row('SELECT * FROM lt_question_slots WHERE lesson_id = ?', [$lesson_id]);
if (!$slot) {
    $sid  = db_insert('lt_question_slots', ['lesson_id' => $lesson_id]);
    $slot = db_row('SELECT * FROM lt_question_slots WHERE id = ?', [$sid]);
}

$error = '';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // All three variants save or none do — a half-written question would serve
    // students a variant whose answer options don't match the question text.
    db()->beginTransaction();
    try {
        for ($vn = 1; $vn <= 3; $vn++) {
            $q_text  = trim($_POST['variant'][$vn]['question_text'] ?? '');
            $correct = (int)($_POST['variant'][$vn]['correct_answer'] ?? 0);
            if (!$q_text) continue; // skip empty variants

            // Upsert variant
            $variant = db_row('SELECT * FROM lt_question_variants WHERE slot_id = ? AND variant_number = ?', [$slot['id'], $vn]);
            if ($variant) {
                db_update('lt_question_variants', ['question_text' => $q_text], 'id = ?', [$variant['id']]);
                $vid = $variant['id'];
            } else {
                $vid = db_insert('lt_question_variants', ['slot_id' => $slot['id'], 'variant_number' => $vn, 'question_text' => $q_text]);
            }

            // Upsert answers in place, one row per slot position. Never a blanket
            // DELETE: question_responses references answers(id), so wiping the
            // options would fail the moment a student had attempted the lesson.
            for ($ai = 1; $ai <= 4; $ai++) {
                $atxt     = trim($_POST['variant'][$vn]['answer'][$ai]['text'] ?? '');
                $existing = db_row('SELECT id FROM lt_answers WHERE variant_id = ? AND sort_order = ?', [$vid, $ai]);

                if ($atxt === '') {
                    // Option cleared — drop it, or retire it if it's referenced.
                    if ($existing) retire_answer((int)$existing['id']);
                    continue;
                }

                $fields = [
                    'answer_text' => $atxt,
                    'is_correct'  => ($correct === $ai) ? 1 : 0,
                    'sort_order'  => $ai,
                    'is_active'   => 1, // un-retires an option that comes back
                ];
                if ($existing) {
                    db_update('lt_answers', $fields, 'id = ?', [$existing['id']]);
                } else {
                    db_insert('lt_answers', $fields + ['variant_id' => $vid]);
                }
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('questions.php: save failed for lesson ' . $lesson_id . ': ' . $e->getMessage());
        $error = 'Could not save these questions. Nothing was changed — please try again.';
    }

    if (!$error) {
        flash_set('success', 'Questions saved.');
        redirect(APP_URL . '/admin/questions.php?lesson_id=' . $lesson_id);
    }
}

// Load existing variants + answers
$variants_data = [];
for ($vn = 1; $vn <= 3; $vn++) {
    $v = db_row('SELECT * FROM lt_question_variants WHERE slot_id = ? AND variant_number = ?', [$slot['id'], $vn]);
    $answers = $v ? get_answers_for_variant($v['id']) : [];
    $correct = 0;
    // Key by slot position, not list position — a retired option leaves a gap.
    $by_slot = [];
    foreach ($answers as $a) {
        $by_slot[(int)$a['sort_order']] = $a;
        if ($a['is_correct']) $correct = (int)$a['sort_order'];
    }
    $variants_data[$vn] = ['variant' => $v, 'answers' => $by_slot, 'correct' => $correct];
}

$page_title = 'Questions — Admin';
include LT_ROOT . '/includes/admin_header.php';

if ($msg = flash_get('success')) echo '<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">' . h($msg) . '</div>';
if ($error) echo '<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">' . h($error) . '</div>';
?>

<div class="flex items-center gap-2 text-sm text-slate-500 mb-5">
  <a href="<?= APP_URL ?>/admin/lessons.php?challenge_id=<?= $lesson['challenge_id'] ?>" class="hover:text-primary">Lessons</a>
  <span class="text-slate-300">/</span>
  <a href="<?= APP_URL ?>/admin/lessons.php?action=edit&id=<?= $lesson_id ?>" class="hover:text-primary"><?= h($lesson['title']) ?></a>
  <span class="text-slate-300">/</span>
  <span>Questions</span>
</div>

<h1 class="text-xl font-bold text-slate-800 mb-1">Questions: <?= h($lesson['title']) ?></h1>
<p class="text-sm text-slate-500 mb-6">
  Each lesson has one question slot with up to 3 variants. The student gets Variant 1 first; a wrong answer serves the next variant.
</p>

<form method="POST" class="space-y-6">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <?php
  $variant_hints = [1 => 'shown on first attempt', 2 => 'shown if Variant 1 wrong', 3 => 'shown if Variant 2 wrong'];
  for ($vn = 1; $vn <= 3; $vn++):
    $vdata   = $variants_data[$vn];
    $v       = $vdata['variant'];
    $answers = $vdata['answers'];
    $correct = $vdata['correct'];
    $labels  = ['a','b','c','d'];
  ?>
    <div class="bg-white border border-slate-200 rounded-xl p-6">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-7 h-7 rounded-full <?= $vn === 1 ? 'bg-primary' : 'bg-slate-300' ?> text-white text-xs font-bold flex items-center justify-center"><?= $vn ?></div>
        <h3 class="font-bold text-slate-800">Variant <?= $vn ?>
          <span class="text-xs text-slate-400 font-normal ml-1">(<?= $variant_hints[$vn] ?>)</span>
        </h3>
        <?php if ($v): ?>
          <span class="ml-auto text-xs text-green-600 font-semibold">✓ Saved</span>
        <?php endif; ?>
      </div>

      <div class="mb-4">
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Question Text</label>
        <textarea name="variant[<?= $vn ?>][question_text]" rows="3"
                  class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary"><?= h($v['question_text'] ?? '') ?></textarea>
      </div>

      <div class="text-xs font-bold text-slate-700 mb-2">Answer Options <span class="text-slate-400 font-normal">(select the correct one)</span></div>
      <div class="space-y-2.5">
        <?php for ($ai = 1; $ai <= 4; $ai++):
          $existing = $answers[$ai] ?? [];
        ?>
          <div class="flex items-center gap-3">
            <input type="radio" name="variant[<?= $vn ?>][correct_answer]" value="<?= $ai ?>"
                   <?= $correct === $ai ? 'checked' : '' ?>
                   class="accent-green-500 mt-0.5 shrink-0">
            <div class="flex items-center gap-2 flex-1">
              <span class="text-xs font-bold text-slate-500 w-5 shrink-0"><?= $labels[$ai-1] ?>.)</span>
              <input type="text" name="variant[<?= $vn ?>][answer][<?= $ai ?>][text]"
                     value="<?= h($existing['answer_text'] ?? '') ?>"
                     placeholder="Answer option <?= $ai ?>…"
                     class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <span class="text-xs text-slate-400 w-16 shrink-0"><?= $correct === $ai ? '← correct' : '' ?></span>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  <?php endfor; ?>

  <div class="flex gap-3">
    <button type="submit" class="bg-primary text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:opacity-90">Save All Questions</button>
    <a href="<?= APP_URL ?>/admin/lessons.php?challenge_id=<?= $lesson['challenge_id'] ?>"
       class="border border-slate-300 text-slate-700 text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-slate-50">← Back to Lessons</a>
  </div>
</form>

<?php include LT_ROOT . '/includes/admin_footer.php'; ?>
