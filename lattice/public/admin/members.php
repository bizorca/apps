<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_admin();

/*
 * Lattice's people: anyone enrolled in a course or holding the Lattice admin
 * role. The account is shared with every other tool, so this deliberately does
 * not list every account on the site, and "Make admin" grants the Lattice role
 * (lt_admins) only. The site-wide users.is_admin is never touched from here;
 * site admins show as such and cannot be demoted.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_admin'])) {
    verify_csrf();
    $uid = (int)$_POST['user_id'];
    $me  = (int)current_user()['id'];
    $known = (bool)db_val(
        'SELECT (SELECT COUNT(*) FROM lt_enrollments WHERE user_id = ?) + (SELECT COUNT(*) FROM lt_admins WHERE user_id = ?)',
        [$uid, $uid]
    );
    $site_admin = (bool)db_val('SELECT is_admin FROM users WHERE id = ?', [$uid]);
    if ($uid !== $me && $known && !$site_admin) { // can't change your own role
        if (db_val('SELECT COUNT(*) FROM lt_admins WHERE user_id = ?', [$uid])) {
            db_delete('lt_admins', 'user_id = ?', [$uid]);
        } else {
            db_insert('lt_admins', ['user_id' => $uid]);
        }
    }
    flash_set('success', 'User updated.');
    redirect(APP_URL . '/admin/members.php');
}

$page_title = 'Users — Admin';
include LT_ROOT . '/includes/admin_header.php';

if ($msg = flash_get('success')) echo '<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">' . h($msg) . '</div>';

$users = db_rows("
    SELECT u.id, u.name, u.email, u.is_admin AS site_admin, u.created_at,
           (a.user_id IS NOT NULL) AS lattice_admin,
           (SELECT COUNT(*) FROM lt_enrollments e WHERE e.user_id = u.id) AS enrollment_count
    FROM users u
    LEFT JOIN lt_admins a ON a.user_id = u.id
    WHERE a.user_id IS NOT NULL
       OR EXISTS (SELECT 1 FROM lt_enrollments e WHERE e.user_id = u.id)
    ORDER BY u.created_at DESC
");
$me = (int)current_user()['id'];
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-xl font-bold text-slate-800">Users</h1>
  <span class="text-sm text-slate-500"><?= count($users) ?> total</span>
</div>
<p class="text-sm text-slate-500 mb-4">Everyone enrolled in a course or holding the Lattice admin role. Accounts are shared across Bizorca Tools; this page only changes the Lattice role.</p>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 border-b border-slate-200">
      <tr>
        <th class="px-5 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Name</th>
        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Email</th>
        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Role</th>
        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Enrollments</th>
        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Joined</th>
        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach ($users as $u): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-5 py-3 font-semibold text-slate-800"><?= h($u['name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= h($u['email']) ?></td>
          <td class="px-4 py-3">
            <?php if ($u['site_admin']): ?>
              <span class="text-xs bg-violet-100 text-violet-700 font-semibold px-2 py-0.5 rounded-full">Site admin</span>
            <?php elseif ($u['lattice_admin']): ?>
              <span class="text-xs bg-violet-100 text-violet-700 font-semibold px-2 py-0.5 rounded-full">Admin</span>
            <?php else: ?>
              <span class="text-xs text-slate-400">Student</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-slate-500"><?= $u['enrollment_count'] ?></td>
          <td class="px-4 py-3 text-slate-400 text-xs"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td class="px-4 py-3">
            <?php if ((int)$u['id'] === $me): ?>
              <span class="text-xs text-slate-300">You</span>
            <?php elseif ($u['site_admin']): ?>
              <span class="text-xs text-slate-300">—</span>
            <?php else: ?>
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button name="toggle_admin" class="text-xs font-semibold <?= $u['lattice_admin'] ? 'text-red-500 hover:underline' : 'text-primary hover:underline' ?>">
                  <?= $u['lattice_admin'] ? 'Remove admin' : 'Make admin' ?>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include LT_ROOT . '/includes/admin_footer.php'; ?>
