<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_admin();

$action    = $_GET['action']    ?? 'list';
$id        = (int)($_GET['id']        ?? 0);
$course_id = (int)($_GET['course_id'] ?? 0);
$error     = '';

if ($action === 'delete' && $id) {
    verify_csrf();
    db_delete('lt_units', 'id = ?', [$id]);
    flash_set('success', 'Unit deleted.');
    redirect(APP_URL . '/admin/units.php' . ($course_id ? "?course_id={$course_id}" : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    verify_csrf();
    $title       = trim($_POST['title'] ?? '');
    $cid         = (int)($_POST['course_id'] ?? 0);
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $about_html  = $_POST['about_html'] ?? '';
    $tutorials   = trim($_POST['tutorials_url'] ?? '');
    $is_final    = isset($_POST['is_final']) ? 1 : 0;

    if (!$title || !$cid) { $error = 'Title and course are required.'; }
    else {
        $data = ['course_id' => $cid, 'sort_order' => $sort_order, 'title' => $title,
                 'about_html' => $about_html, 'tutorials_url' => $tutorials, 'is_final' => $is_final];
        if ($action === 'add') db_insert('lt_units', $data);
        else db_update('lt_units', $data, 'id = ?', [$id]);
        flash_set('success', $action === 'add' ? 'Unit created.' : 'Unit updated.');
        redirect(APP_URL . '/admin/units.php?course_id=' . $cid);
    }
}

$page_title = 'Units — Admin';
include LT_ROOT . '/includes/admin_header.php';

if ($msg = flash_get('success')) echo '<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">' . h($msg) . '</div>';
if ($error) echo '<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">' . h($error) . '</div>';

$courses = db_rows('SELECT * FROM lt_courses ORDER BY title');
?>

<?php if ($action === 'list'): ?>
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-slate-800">Units<?= $course_id ? ' — ' . h(db_val('SELECT title FROM lt_courses WHERE id = ?', [$course_id])) : '' ?></h1>
    <a href="<?= APP_URL ?>/admin/units.php?action=add<?= $course_id ? '&course_id='.$course_id : '' ?>"
       class="bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:opacity-90">+ New Unit</a>
  </div>

  <?php if ($courses): ?>
    <div class="mb-4 flex gap-2 flex-wrap">
      <a href="<?= APP_URL ?>/admin/units.php"
         class="text-xs font-semibold px-3 py-1.5 rounded-full <?= !$course_id ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">All</a>
      <?php foreach ($courses as $c): ?>
        <a href="<?= APP_URL ?>/admin/units.php?course_id=<?= $c['id'] ?>"
           class="text-xs font-semibold px-3 py-1.5 rounded-full <?= $course_id === (int)$c['id'] ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
          <?= h($c['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Title</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Course</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Order</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Challenges</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php
        $sql = $course_id
            ? 'SELECT u.*, c.title as course_title FROM lt_units u JOIN lt_courses c ON c.id = u.course_id WHERE u.course_id = ? ORDER BY u.sort_order'
            : 'SELECT u.*, c.title as course_title FROM lt_units u JOIN lt_courses c ON c.id = u.course_id ORDER BY c.title, u.sort_order';
        foreach (db_rows($sql, $course_id ? [$course_id] : []) as $u):
        ?>
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-semibold text-slate-800"><?= h($u['title']) ?><?= $u['is_final'] ? ' <span class="text-xs text-violet-600 font-normal">(final)</span>' : '' ?></td>
            <td class="px-4 py-3 text-slate-500 text-xs"><?= h($u['course_title']) ?></td>
            <td class="px-4 py-3 text-slate-500"><?= $u['sort_order'] ?></td>
            <td class="px-4 py-3 text-slate-500"><?= db_val('SELECT COUNT(*) FROM lt_challenges WHERE unit_id = ?', [$u['id']]) ?></td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <a href="<?= APP_URL ?>/admin/units.php?action=edit&id=<?= $u['id'] ?>" class="text-xs font-semibold text-primary hover:underline">Edit</a>
                <a href="<?= APP_URL ?>/admin/challenges.php?unit_id=<?= $u['id'] ?>" class="text-xs font-semibold text-slate-500 hover:underline">Challenges</a>
                <form method="POST" onsubmit="return confirm('Delete this unit?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <button formaction="<?= APP_URL ?>/admin/units.php?action=delete&id=<?= $u['id'] ?>&course_id=<?= $u['course_id'] ?>"
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
  <?php $unit = $id ? db_row('SELECT * FROM lt_units WHERE id = ?', [$id]) : []; ?>
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-5">
    <a href="<?= APP_URL ?>/admin/units.php" class="hover:text-primary">Units</a>
    <span class="text-slate-300">/</span>
    <span><?= $action === 'add' ? 'New Unit' : 'Edit: ' . h($unit['title'] ?? '') ?></span>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-2xl">
    <form method="POST" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Course *</label>
        <select name="course_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">— select —</option>
          <?php $sel = $unit['course_id'] ?? $course_id;
          foreach ($courses as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (int)$sel === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Title *</label>
        <input name="title" required value="<?= h($unit['title'] ?? '') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Sort Order</label>
        <input name="sort_order" type="number" value="<?= h($unit['sort_order'] ?? 0) ?>"
               class="w-32 border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">About HTML <span class="text-slate-400 font-normal">(shown in unit sidebar — basic HTML OK)</span></label>
        <textarea name="about_html" rows="5"
                  class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary"><?= h($unit['about_html'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Tutorials URL</label>
        <input name="tutorials_url" type="url" value="<?= h($unit['tutorials_url'] ?? '') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </div>
      <div>
        <label class="flex items-center gap-2.5 cursor-pointer">
          <input type="checkbox" name="is_final" value="1" <?= ($unit['is_final'] ?? 0) ? 'checked' : '' ?> class="accent-primary">
          <span class="text-sm text-slate-700 font-medium">Final Milestone unit</span>
        </label>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="submit" class="bg-primary text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90">Save Unit</button>
        <a href="<?= APP_URL ?>/admin/units.php" class="border border-slate-300 text-slate-700 text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-slate-50">Cancel</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php include LT_ROOT . '/includes/admin_footer.php'; ?>
