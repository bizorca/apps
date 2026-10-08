<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/companies.php';
requireOnboarded();

$userId = getCurrentUserId();
$company = getUserCompany($userId);

if (!$company || !isCompanyAdmin($company['id'], $userId)) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/company-members.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'member';
        if (!$email) {
            setFlash('error', 'Email required.');
        } else {
            $err = addCompanyMember($company['id'], $email, $role);
            if ($err) {
                setFlash('error', $err);
            } else {
                setFlash('success', 'Member added.');
            }
        }
    } elseif ($action === 'remove') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId && $targetId !== $userId) {
            removeCompanyMember($company['id'], $targetId);
            setFlash('success', 'Member removed.');
        }
    } elseif ($action === 'role') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? 'member';
        if ($targetId && $targetId !== $userId) {
            updateMemberRole($company['id'], $targetId, $newRole);
            setFlash('success', 'Role updated.');
        }
    }

    header('Location: ' . url('/company-members.php'));
    exit;
}

$members = getCompanyMembers($company['id']);

$pageTitle = 'Manage Members';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <a href="<?= url('/company.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 mb-4 inline-block">&larr; Company Dashboard</a>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Manage Members</h1>

    <!-- Add Member -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Add Member</h2>
        <form method="POST" class="flex items-end space-x-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required placeholder="user@company.com"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select name="role" class="border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                    <option value="member">Member</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="bg-tr-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-tr-700 transition">Add</button>
        </form>
        <p class="text-xs text-gray-400 mt-2"><?= count($members) ?> / <?= $company['seat_limit'] ?> seats used. Users must sign in via SSO before they can be added.</p>
    </div>

    <!-- Member List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left bg-gray-50">
                    <th class="px-6 py-3 font-medium text-gray-500">Name</th>
                    <th class="px-6 py-3 font-medium text-gray-500">Email</th>
                    <th class="px-6 py-3 font-medium text-gray-500">Role</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Sessions</th>
                    <th class="px-6 py-3 font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($members as $m): ?>
                <tr>
                    <td class="px-6 py-3 font-medium text-gray-900"><?= h($m['name'] ?: '-') ?></td>
                    <td class="px-6 py-3 text-gray-600"><?= h($m['email']) ?></td>
                    <td class="px-6 py-3">
                        <?php if ((int)$m['id'] !== $userId): ?>
                        <form method="POST" class="inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                            <select name="role" onchange="this.form.submit()" class="text-xs border-gray-300 rounded px-2 py-1">
                                <option value="member" <?= $m['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                <option value="manager" <?= $m['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                                <option value="admin" <?= $m['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="text-xs text-tr-600 font-semibold"><?= $m['role'] ?> (you)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-center"><?= (int) $m['total_sessions'] ?></td>
                    <td class="px-6 py-3">
                        <?php if ((int)$m['id'] !== $userId): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Remove this member?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs">Remove</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
