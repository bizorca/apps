<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_admin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$error  = '';

if ($action === 'delete' && $id) {
    verify_csrf();
    db_delete('lt_courses', 'id = ?', [$id]);
    flash_set('success', 'Course deleted.');
    redirect(APP_URL . '/admin/courses.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    verify_csrf();
    $title       = trim($_POST['title'] ?? '');
    $slug        = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $description = trim($_POST['description'] ?? '');
    $published   = isset($_POST['is_published']) ? 1 : 0;

    if (!$title || !$slug) {
        $error = 'Title and slug are required.';
    } else {
        $thumb_path = $_POST['existing_thumb'] ?? '';
        if (!empty($_FILES['thumbnail']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $fname = uniqid('thumb_') . '.' . $ext;
                if (!is_dir(LT_UPLOADS)) @mkdir(LT_UPLOADS, 0775, true);
                // Must decode as an image too: thumb.php serves by extension.
                if (@getimagesize($_FILES['thumbnail']['tmp_name'])
                    && move_uploaded_file($_FILES['thumbnail']['tmp_name'], LT_UPLOADS . '/' . $fname)) {
                    $thumb_path = 'uploads/' . $fname;
                }
            }
        }
        $data = compact('title', 'slug', 'description', 'published') + ['thumbnail_path' => $thumb_path, 'is_published' => $published];
        unset($data['published']);
        if ($action === 'add') db_insert('lt_courses', $data);
        else db_update('lt_courses', $data, 'id = ?', [$id]);
        flash_set('success', $action === 'add' ? 'Course created.' : 'Course updated.');
        redirect(APP_URL . '/admin/courses.php');
    }
}

$page_title = 'Courses — Admin';
include LT_ROOT . '/includes/admin_header.php';

if ($msg = flash_get('success')) echo '<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">' . h($msg) . '</div>';
if ($error) echo '<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">' . h($error) . '</div>';
?>

<?php if ($action === 'list'): ?>
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-slate-800">Courses</h1>
    <a href="<?= APP_URL ?>/admin/courses.php?action=add" class="bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:opacity-90">+ New Course</a>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Title</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Slug</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Units</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Published</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach (db_rows('SELECT * FROM lt_courses ORDER BY title') as $c): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-semibold text-slate-800"><?= h($c['title']) ?></td>
            <td class="px-4 py-3 text-slate-500"><?= h($c['slug']) ?></td>
            <td class="px-4 py-3 text-slate-500"><?= db_val('SELECT COUNT(*) FROM lt_units WHERE course_id = ?', [$c['id']]) ?></td>
            <td class="px-4 py-3"><?= $c['is_published'] ? '<span class="text-green-600 font-semibold text-xs">Yes</span>' : '<span class="text-slate-400 text-xs">No</span>' ?></td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <a href="<?= APP_URL ?>/admin/courses.php?action=edit&id=<?= $c['id'] ?>" class="text-xs font-semibold text-primary hover:underline">Edit</a>
                <a href="<?= APP_URL ?>/admin/units.php?course_id=<?= $c['id'] ?>" class="text-xs font-semibold text-slate-500 hover:underline">Units</a>
                <form method="POST" onsubmit="return confirm('Delete this course and all its content?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <button formaction="<?= APP_URL ?>/admin/courses.php?action=delete&id=<?= $c['id'] ?>"
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
  <?php $course = $id ? db_row('SELECT * FROM lt_courses WHERE id = ?', [$id]) : []; ?>
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-5">
    <a href="<?= APP_URL ?>/admin/courses.php" class="hover:text-primary">Courses</a>
    <span class="text-slate-300">/</span>
    <span><?= $action === 'add' ? 'New Course' : 'Edit: ' . h($course['title'] ?? '') ?></span>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-2xl">
    <form method="POST" enctype="multipart/form-data" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="existing_thumb" value="<?= h($course['thumbnail_path'] ?? '') ?>">

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Title *</label>
        <input name="title" required value="<?= h($course['title'] ?? '') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Slug *</label>
        <input name="slug" required value="<?= h($course['slug'] ?? '') ?>" pattern="[a-z0-9\-]+"
               class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
        <div class="text-xs text-slate-400 mt-1">Lowercase, hyphens only. e.g. intro-to-databases</div>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Description</label>
        <textarea name="description" rows="3"
                  class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"><?= h($course['description'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Thumbnail</label>
        <?php if (!empty($course['thumbnail_path'])): ?>
          <img src="<?= h(thumb_url($course['thumbnail_path'])) ?>" class="w-28 h-16 object-cover rounded mb-2">
        <?php endif; ?>
        <input type="file" name="thumbnail" accept="image/*"
               class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-600 hover:file:bg-slate-200">
      </div>
      <div>
        <label class="flex items-center gap-2.5 cursor-pointer">
          <input type="checkbox" name="is_published" value="1" <?= ($course['is_published'] ?? 1) ? 'checked' : '' ?> class="accent-primary">
          <span class="text-sm text-slate-700 font-medium">Published (visible to students)</span>
        </label>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="submit" class="bg-primary text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90">Save Course</button>
        <a href="<?= APP_URL ?>/admin/courses.php" class="border border-slate-300 text-slate-700 text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-slate-50">Cancel</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php include LT_ROOT . '/includes/admin_footer.php'; ?>
