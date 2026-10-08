<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();

if (!isAdmin()) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$user = getCurrentUser();
$db   = getDB();
$errors = [];

// Tab
$tab = in_array($_GET['tab'] ?? '', ['users', 'cases', 'stats']) ? $_GET['tab'] : 'users';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } elseif (!isAdmin()) {
        $errors[] = 'Permission denied.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'approve_coordinator') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $stmt = $db->prepare(
                'UPDATE cw_members SET coordinator_approved=1 WHERE user_id=? AND role=\'coordinator\''
            );
            $stmt->execute([$userId]);

            if ($stmt->rowCount() > 0) {
                // Load coordinator info for email
                $stmt2 = $db->prepare('SELECT * FROM cw_users WHERE id=? LIMIT 1');
                $stmt2->execute([$userId]);
                $approvedUser = $stmt2->fetch();

                // Attempt to send approval email if mail.php exists
                $mailFile = CW_ROOT . '/includes/mail.php';
                if ($approvedUser && file_exists($mailFile)) {
                    require_once $mailFile;
                    if (function_exists('sendCoordinatorApprovalEmail')) {
                        sendCoordinatorApprovalEmail($approvedUser['email'], $approvedUser['name']);
                    }
                }

                setFlash('success', 'Coordinator approved.');
            } else {
                setFlash('error', 'Could not approve that user.');
            }
            header('Location: ' . url('/admin.php?tab=users'));
            exit;
        }
    }
}

$flash = getFlash();

// ── Data queries ────────────────────────────────────────────────

$allUsers = [];
$allCases = [];
$stats    = [];

if ($tab === 'users') {
    $allUsers = $db->query(
        'SELECT * FROM cw_users ORDER BY created_at DESC'
    )->fetchAll();

} elseif ($tab === 'cases') {
    $allCases = $db->query(
        'SELECT cw_cases.*,
                cw_businesses.name AS business_name,
                clients.name    AS client_name,
                coords.name     AS coordinator_name
         FROM cw_cases
         JOIN cw_businesses ON cw_businesses.id = cw_cases.business_id
         JOIN cw_users clients ON clients.id = cw_businesses.user_id
         LEFT JOIN cw_users coords ON coords.id = cw_cases.coordinator_id
         ORDER BY cw_cases.created_at DESC'
    )->fetchAll();

} elseif ($tab === 'stats') {
    // Users by role
    $roleRows = $db->query(
        'SELECT role, COUNT(*) AS cnt FROM cw_users GROUP BY role'
    )->fetchAll();
    $stats['users_by_role'] = [];
    foreach ($roleRows as $r) {
        $stats['users_by_role'][$r['role']] = (int)$r['cnt'];
    }

    // Cases by status
    $statusRows = $db->query(
        'SELECT status, COUNT(*) AS cnt FROM cw_cases GROUP BY status'
    )->fetchAll();
    $stats['cases_by_status'] = [];
    foreach ($statusRows as $r) {
        $stats['cases_by_status'][$r['status']] = (int)$r['cnt'];
    }

    $stats['total_users'] = array_sum($stats['users_by_role']);
    $stats['total_cases'] = array_sum($stats['cases_by_status']);
}

// Helpers
$statusColors = [
    'intake'              => 'bg-gray-100 text-gray-600',
    'structure_selection' => 'bg-blue-100 text-blue-700',
    'modeling'            => 'bg-purple-100 text-purple-700',
    'documents'           => 'bg-yellow-100 text-yellow-700',
    'review'              => 'bg-orange-100 text-orange-700',
    'complete'            => 'bg-emerald-100 text-emerald-700',
];
$roleColors = [
    'client'      => 'bg-gray-100 text-gray-600',
    'coordinator' => 'bg-teal-100 text-teal-700',
    'admin'       => 'bg-red-100 text-red-700',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — CoopConvert</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="bg-emerald-700 text-white px-6 py-4 flex items-center justify-between">
    <a href="<?= url('/admin.php') ?>" class="font-bold text-lg tracking-tight">CoopConvert <span class="text-emerald-300 font-normal text-sm ml-1">Admin</span></a>
    <div class="flex items-center gap-6 text-sm">
        <a href="<?= url('/coordinator.php') ?>" class="hover:text-teal-300">Coordinator view</a>
        <span class="text-emerald-300"><?= h($user['name']) ?></span>
        <a href="<?= url('/logout.php') ?>" class="hover:text-teal-300">Sign out</a>
    </div>
</nav>

<div class="max-w-6xl mx-auto px-4 py-10">

    <?php if ($flash): ?>
    <div class="mb-6 px-4 py-3 rounded-md text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="mb-6 bg-red-50 border border-red-200 rounded-md px-4 py-3">
        <ul class="list-disc list-inside text-red-600 text-sm space-y-0.5">
            <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 border-b border-gray-200">
        <?php foreach (['users' => 'Users', 'cases' => 'Cases', 'stats' => 'Stats'] as $key => $label): ?>
        <a href="<?= url('/admin.php?tab=' . $key) ?>"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px
                  <?= $tab === $key
                      ? 'border-emerald-700 text-emerald-700'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
            <?= h($label) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tab === 'users'): ?>
    <!-- USERS TAB -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="text-left px-5 py-3 font-semibold">Name</th>
                    <th class="text-left px-5 py-3 font-semibold">Email</th>
                    <th class="text-left px-5 py-3 font-semibold">Role</th>
                    <th class="text-left px-5 py-3 font-semibold">Coord. Approved</th>
                    <th class="text-left px-5 py-3 font-semibold">Joined</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($allUsers as $u): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-gray-800"><?= h($u['name']) ?></td>
                    <td class="px-5 py-3 text-gray-600"><?= h($u['email']) ?></td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $roleColors[$u['role']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= h(ucfirst($u['role'])) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        <?php if ($u['role'] === 'coordinator'): ?>
                            <?php if ($u['coordinator_approved']): ?>
                            <span class="text-emerald-600 text-xs font-medium">Approved</span>
                            <?php else: ?>
                            <span class="text-gray-400 text-xs">Pending</span>
                            <?php endif; ?>
                        <?php else: ?>
                        <span class="text-gray-300 text-xs">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-gray-500 text-xs"><?= h(date('M j, Y', strtotime($u['created_at']))) ?></td>
                    <td class="px-5 py-3 text-right">
                        <?php if ($u['role'] === 'coordinator' && !$u['coordinator_approved']): ?>
                        <form method="POST" action="<?= url('/admin.php?tab=users') ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="approve_coordinator">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button type="submit"
                                    class="bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">
                                Approve
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($allUsers)): ?>
        <div class="p-8 text-center text-gray-400 text-sm">No users found.</div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'cases'): ?>
    <!-- CASES TAB -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="text-left px-5 py-3 font-semibold">Business</th>
                    <th class="text-left px-5 py-3 font-semibold">Client</th>
                    <th class="text-left px-5 py-3 font-semibold">Coordinator</th>
                    <th class="text-left px-5 py-3 font-semibold">Status</th>
                    <th class="text-left px-5 py-3 font-semibold">Created</th>
                    <th class="text-left px-5 py-3 font-semibold">Updated</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($allCases as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-gray-800"><?= h($c['business_name']) ?></td>
                    <td class="px-5 py-3 text-gray-600"><?= h($c['client_name']) ?></td>
                    <td class="px-5 py-3 text-gray-600">
                        <?= $c['coordinator_name'] ? h($c['coordinator_name']) : '<span class="text-gray-400 italic text-xs">Unassigned</span>' ?>
                    </td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusColors[$c['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= h(ucwords(str_replace('_', ' ', $c['status']))) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-500 text-xs"><?= h(date('M j, Y', strtotime($c['created_at']))) ?></td>
                    <td class="px-5 py-3 text-gray-500 text-xs"><?= h(date('M j, Y', strtotime($c['updated_at']))) ?></td>
                    <td class="px-5 py-3 text-right">
                        <a href="<?= url('/case.php?id=' . (int)$c['id']) ?>"
                           class="text-emerald-600 hover:underline text-sm font-medium">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($allCases)): ?>
        <div class="p-8 text-center text-gray-400 text-sm">No cases yet.</div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'stats'): ?>
    <!-- STATS TAB -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-gray-500 text-xs uppercase font-semibold tracking-wider mb-2">Total users</p>
            <p class="text-4xl font-bold text-gray-900"><?= (int)($stats['total_users'] ?? 0) ?></p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-gray-500 text-xs uppercase font-semibold tracking-wider mb-2">Total cases</p>
            <p class="text-4xl font-bold text-gray-900"><?= (int)($stats['total_cases'] ?? 0) ?></p>
        </div>

        <?php
        $coordCount = $stats['users_by_role']['coordinator'] ?? 0;
        $approved = 0;
        try {
            $approved = (int)$db->query('SELECT COUNT(*) FROM cw_users WHERE role=\'coordinator\' AND coordinator_approved=1')->fetchColumn();
        } catch (Exception $e) {}
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-gray-500 text-xs uppercase font-semibold tracking-wider mb-2">Coordinators</p>
            <p class="text-4xl font-bold text-gray-900"><?= $coordCount ?></p>
            <p class="text-gray-500 text-xs mt-1"><?= $approved ?> approved</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Users by role</h3>
            <ul class="space-y-2">
                <?php foreach ($stats['users_by_role'] as $role => $cnt): ?>
                <li class="flex items-center justify-between">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $roleColors[$role] ?? 'bg-gray-100 text-gray-600' ?>"><?= h(ucfirst($role)) ?></span>
                    <span class="font-bold text-gray-700"><?= $cnt ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Cases by status</h3>
            <ul class="space-y-2">
                <?php foreach ($stats['cases_by_status'] as $status => $cnt): ?>
                <li class="flex items-center justify-between">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusColors[$status] ?? 'bg-gray-100 text-gray-600' ?>">
                        <?= h(ucwords(str_replace('_', ' ', $status))) ?>
                    </span>
                    <span class="font-bold text-gray-700"><?= $cnt ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </div>

    <?php endif; ?>

</div>
</body>
</html>
