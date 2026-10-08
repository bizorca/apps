<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_admin();

$action       = $_GET['action']       ?? 'list';
$id           = (int)($_GET['id']           ?? 0);
$challenge_id = (int)($_GET['challenge_id'] ?? 0);
$error        = '';

if ($action === 'delete' && $id) {
    verify_csrf();
    $cid = (int)db_val('SELECT challenge_id FROM lt_lessons WHERE id = ?', [$id]);
    db_delete('lt_lessons', 'id = ?', [$id]);
    flash_set('success', 'Lesson deleted.');
    redirect(APP_URL . '/admin/lessons.php' . ($cid ? "?challenge_id={$cid}" : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    verify_csrf();
    $title        = trim($_POST['title'] ?? '');
    $cid          = (int)($_POST['challenge_id'] ?? 0);
    $sort_order   = (int)($_POST['sort_order'] ?? 0);
    $obj          = trim($_POST['learning_objective'] ?? '');
    $covered      = $_POST['what_covered_text'] ?? '';
    $content_html = $_POST['content_html'] ?? '';

    if (!$title || !$cid) { $error = 'Title and challenge are required.'; }
    else {
        $data = ['challenge_id' => $cid, 'sort_order' => $sort_order, 'title' => $title,
                 'learning_objective' => $obj, 'what_covered_text' => $covered, 'content_html' => $content_html];
        if ($action === 'add') {
            $lid = db_insert('lt_lessons', $data);
            // Auto-create question slot
            db_insert('lt_question_slots', ['lesson_id' => $lid]);
        } else {
            db_update('lt_lessons', $data, 'id = ?', [$id]);
            // Ensure slot exists
            if (!db_val('SELECT id FROM lt_question_slots WHERE lesson_id = ?', [$id])) {
                db_insert('lt_question_slots', ['lesson_id' => $id]);
            }
        }
        flash_set('success', $action === 'add' ? 'Lesson created.' : 'Lesson updated.');
        redirect(APP_URL . '/admin/lessons.php?challenge_id=' . $cid);
    }
}

$page_title = 'Lessons — Admin';
include LT_ROOT . '/includes/admin_header.php';

if ($msg = flash_get('success')) echo '<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">' . h($msg) . '</div>';
if ($error) echo '<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">' . h($error) . '</div>';

$challenges = db_rows('SELECT ch.*, u.title as unit_title, c.title as course_title
                       FROM lt_challenges ch JOIN lt_units u ON u.id = ch.unit_id JOIN lt_courses c ON c.id = u.course_id
                       ORDER BY c.title, u.sort_order, ch.sort_order');
?>

<?php if ($action === 'list'): ?>
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-slate-800">Lessons<?= $challenge_id ? ' — ' . h(db_val('SELECT title FROM lt_challenges WHERE id = ?', [$challenge_id])) : '' ?></h1>
    <a href="<?= APP_URL ?>/admin/lessons.php?action=add<?= $challenge_id ? '&challenge_id='.$challenge_id : '' ?>"
       class="bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:opacity-90">+ New Lesson</a>
  </div>

  <?php if ($challenges): ?>
    <div class="mb-4 flex gap-2 flex-wrap">
      <a href="<?= APP_URL ?>/admin/lessons.php" class="text-xs font-semibold px-3 py-1.5 rounded-full <?= !$challenge_id ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">All</a>
      <?php foreach ($challenges as $ch): ?>
        <a href="<?= APP_URL ?>/admin/lessons.php?challenge_id=<?= $ch['id'] ?>"
           class="text-xs font-semibold px-3 py-1.5 rounded-full <?= $challenge_id === (int)$ch['id'] ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
          <?= h($ch['course_title']) ?>: <?= h($ch['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Title</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Challenge</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Order</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Questions</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php
        $sql = $challenge_id
            ? 'SELECT l.*, ch.title as challenge_title FROM lt_lessons l JOIN lt_challenges ch ON ch.id = l.challenge_id WHERE l.challenge_id = ? ORDER BY l.sort_order'
            : 'SELECT l.*, ch.title as challenge_title FROM lt_lessons l JOIN lt_challenges ch ON ch.id = l.challenge_id ORDER BY ch.sort_order, l.sort_order';
        foreach (db_rows($sql, $challenge_id ? [$challenge_id] : []) as $lesson):
          $slot      = db_row('SELECT id FROM lt_question_slots WHERE lesson_id = ?', [$lesson['id']]);
          $var_count = $slot ? (int)db_val('SELECT COUNT(*) FROM lt_question_variants WHERE slot_id = ?', [$slot['id']]) : 0;
        ?>
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-semibold text-slate-800"><?= h($lesson['title']) ?></td>
            <td class="px-4 py-3 text-slate-500 text-xs"><?= h($lesson['challenge_title']) ?></td>
            <td class="px-4 py-3 text-slate-500"><?= $lesson['sort_order'] ?></td>
            <td class="px-4 py-3">
              <span class="text-xs <?= $var_count ? 'text-green-600 font-semibold' : 'text-slate-400' ?>"><?= $var_count ?>/3 variants</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <a href="<?= APP_URL ?>/admin/lessons.php?action=edit&id=<?= $lesson['id'] ?>" class="text-xs font-semibold text-primary hover:underline">Edit</a>
                <a href="<?= APP_URL ?>/admin/questions.php?lesson_id=<?= $lesson['id'] ?>" class="text-xs font-semibold text-slate-500 hover:underline">Questions</a>
                <form method="POST" onsubmit="return confirm('Delete this lesson?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <button formaction="<?= APP_URL ?>/admin/lessons.php?action=delete&id=<?= $lesson['id'] ?>"
                          class="text-xs font-semibold text-red-500 hover:underline">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>
  <?php $lesson = $id ? db_row('SELECT * FROM lt_lessons WHERE id = ?', [$id]) : []; ?>
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-5">
    <a href="<?= APP_URL ?>/admin/lessons.php" class="hover:text-primary">Lessons</a>
    <span class="text-slate-300">/</span>
    <span><?= $action === 'add' ? 'New Lesson' : 'Edit: ' . h($lesson['title'] ?? '') ?></span>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-3xl">
    <form method="POST" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Challenge *</label>
        <select name="challenge_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">— select —</option>
          <?php $sel = $lesson['challenge_id'] ?? $challenge_id;
          foreach ($challenges as $ch): ?>
            <option value="<?= $ch['id'] ?>" <?= (int)$sel === (int)$ch['id'] ? 'selected' : '' ?>>
              <?= h($ch['course_title']) ?>: <?= h($ch['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Lesson Title *</label>
          <input name="title" required value="<?= h($lesson['title'] ?? '') ?>"
                 class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Sort Order</label>
          <input name="sort_order" type="number" value="<?= $lesson['sort_order'] ?? 0 ?>"
                 class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Learning Objective</label>
        <input name="learning_objective" value="<?= h($lesson['learning_objective'] ?? '') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">What's Covered <span class="text-slate-400 font-normal">(one topic per line — shown in right panel)</span></label>
        <textarea name="what_covered_text" rows="4"
                  class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary"><?= h($lesson['what_covered_text'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Lesson Content HTML <span class="text-slate-400 font-normal">(HTML, shown in right panel)</span></label>
        <textarea name="content_html" rows="14"
                  class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary"><?= h($lesson['content_html'] ?? '') ?></textarea>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="submit" class="bg-primary text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90">Save Lesson</button>
        <a href="<?= APP_URL ?>/admin/lessons.php" class="border border-slate-300 text-slate-700 text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-slate-50">Cancel</a>
        <?php if ($id): ?>
          <a href="<?= APP_URL ?>/admin/questions.php?lesson_id=<?= $id ?>"
             class="border border-primary text-primary text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-primary-light">
            → Manage Questions
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php include LT_ROOT . '/includes/admin_footer.php'; ?>
