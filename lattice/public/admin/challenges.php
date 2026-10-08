<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_admin();

$action  = $_GET['action']  ?? 'list';
$id      = (int)($_GET['id']      ?? 0);
$unit_id = (int)($_GET['unit_id'] ?? 0);
$error   = '';

if ($action === 'delete' && $id) {
    verify_csrf();
    $uid = (int)db_val('SELECT unit_id FROM lt_challenges WHERE id = ?', [$id]);
    db_delete('lt_challenges', 'id = ?', [$id]);
    flash_set('success', 'Challenge deleted.');
    redirect(APP_URL . '/admin/challenges.php' . ($uid ? "?unit_id={$uid}" : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    verify_csrf();
    $title      = trim($_POST['title'] ?? '');
    $uid        = (int)($_POST['unit_id'] ?? 0);
    $type       = $_POST['type'] ?? 'challenge';
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $time_limit = $_POST['time_limit_minutes'] !== '' ? (int)$_POST['time_limit_minutes'] : null;
    $max_att    = max(1, (int)($_POST['max_attempts'] ?? 2));

    $valid_types = ['challenge','practice_milestone','milestone','final_milestone'];
    if (!$title || !$uid || !in_array($type, $valid_types)) {
        $error = 'Title, unit, and valid type are required.';
    } else {
        $data = ['unit_id' => $uid, 'sort_order' => $sort_order, 'title' => $title,
                 'type' => $type, 'time_limit_minutes' => $time_limit, 'max_attempts' => $max_att];
        if ($action === 'add') db_insert('lt_challenges', $data);
        else db_update('lt_challenges', $data, 'id = ?', [$id]);
        flash_set('success', $action === 'add' ? 'Challenge created.' : 'Challenge updated.');
        redirect(APP_URL . '/admin/challenges.php?unit_id=' . $uid);
    }
}

$page_title = 'Challenges — Admin';
include LT_ROOT . '/includes/admin_header.php';

if ($msg = flash_get('success')) echo '<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">' . h($msg) . '</div>';
if ($error) echo '<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">' . h($error) . '</div>';

$units = db_rows('SELECT u.*, c.title as course_title FROM lt_units u JOIN lt_courses c ON c.id = u.course_id ORDER BY c.title, u.sort_order');
?>

<?php if ($action === 'list'): ?>
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-slate-800">Challenges<?= $unit_id ? ' — ' . h(db_val('SELECT title FROM lt_units WHERE id = ?', [$unit_id])) : '' ?></h1>
    <a href="<?= APP_URL ?>/admin/challenges.php?action=add<?= $unit_id ? '&unit_id='.$unit_id : '' ?>"
       class="bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:opacity-90">+ New Challenge</a>
  </div>

  <?php if ($units): ?>
    <div class="mb-4 flex gap-2 flex-wrap">
      <a href="<?= APP_URL ?>/admin/challenges.php"
         class="text-xs font-semibold px-3 py-1.5 rounded-full <?= !$unit_id ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">All</a>
      <?php foreach ($units as $u): ?>
        <a href="<?= APP_URL ?>/admin/challenges.php?unit_id=<?= $u['id'] ?>"
           class="text-xs font-semibold px-3 py-1.5 rounded-full <?= $unit_id === (int)$u['id'] ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
          <?= h($u['course_title']) ?>: <?= h($u['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Title</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Unit</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Type</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Lessons</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php
        $sql = $unit_id
            ? 'SELECT ch.*, u.title as unit_title FROM lt_challenges ch JOIN lt_units u ON u.id = ch.unit_id WHERE ch.unit_id = ? ORDER BY ch.sort_order'
            : 'SELECT ch.*, u.title as unit_title FROM lt_challenges ch JOIN lt_units u ON u.id = ch.unit_id ORDER BY u.sort_order, ch.sort_order';
        foreach (db_rows($sql, $unit_id ? [$unit_id] : []) as $ch):
        ?>
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-semibold text-slate-800"><?= h($ch['title']) ?></td>
            <td class="px-4 py-3 text-slate-500 text-xs"><?= h($ch['unit_title']) ?></td>
            <td class="px-4 py-3"><span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-medium"><?= h(str_replace('_',' ',$ch['type'])) ?></span></td>
            <td class="px-4 py-3 text-slate-500"><?= db_val('SELECT COUNT(*) FROM lt_lessons WHERE challenge_id = ?', [$ch['id']]) ?></td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <a href="<?= APP_URL ?>/admin/challenges.php?action=edit&id=<?= $ch['id'] ?>" class="text-xs font-semibold text-primary hover:underline">Edit</a>
                <a href="<?= APP_URL ?>/admin/lessons.php?challenge_id=<?= $ch['id'] ?>" class="text-xs font-semibold text-slate-500 hover:underline">Lessons</a>
                <form method="POST" onsubmit="return confirm('Delete this challenge?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <button formaction="<?= APP_URL ?>/admin/challenges.php?action=delete&id=<?= $ch['id'] ?>"
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
  <?php $ch = $id ? db_row('SELECT * FROM lt_challenges WHERE id = ?', [$id]) : []; ?>
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-5">
    <a href="<?= APP_URL ?>/admin/challenges.php" class="hover:text-primary">Challenges</a>
    <span class="text-slate-300">/</span>
    <span><?= $action === 'add' ? 'New Challenge' : 'Edit: ' . h($ch['title'] ?? '') ?></span>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-2xl">
    <form method="POST" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Unit *</label>
        <select name="unit_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">— select —</option>
          <?php $sel = $ch['unit_id'] ?? $unit_id;
          foreach ($units as $u): ?>
            <option value="<?= $u['id'] ?>" <?= (int)$sel === (int)$u['id'] ? 'selected' : '' ?>>
              <?= h($u['course_title']) ?>: <?= h($u['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Title *</label>
        <input name="title" required value="<?= h($ch['title'] ?? '') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Type *</label>
        <select name="type" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <?php foreach (['challenge','practice_milestone','milestone','final_milestone'] as $t): ?>
            <option value="<?= $t ?>" <?= ($ch['type'] ?? 'challenge') === $t ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Sort Order</label>
          <input name="sort_order" type="number" value="<?= $ch['sort_order'] ?? 0 ?>"
                 class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Time Limit (min)</label>
          <input name="time_limit_minutes" type="number" value="<?= h($ch['time_limit_minutes'] ?? '') ?>"
                 class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Max Attempts</label>
          <input name="max_attempts" type="number" min="1" max="5" value="<?= $ch['max_attempts'] ?? 2 ?>"
                 class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="submit" class="bg-primary text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:opacity-90">Save Challenge</button>
        <a href="<?= APP_URL ?>/admin/challenges.php" class="border border-slate-300 text-slate-700 text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-slate-50">Cancel</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php include LT_ROOT . '/includes/admin_footer.php'; ?>
