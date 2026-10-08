<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();
requireCoordinator();

$user = getCurrentUser();
$db   = getDB();
$errors = [];

// Status labels and badge colors
$statusColors = [
    'intake'              => 'bg-gray-100 text-gray-600',
    'structure_selection' => 'bg-blue-100 text-blue-700',
    'modeling'            => 'bg-purple-100 text-purple-700',
    'documents'           => 'bg-yellow-100 text-yellow-700',
    'review'              => 'bg-orange-100 text-orange-700',
    'complete'            => 'bg-emerald-100 text-emerald-700',
];

function statusLabel(string $s): string {
    $map = [
        'intake'              => 'Intake',
        'structure_selection' => 'Structure',
        'modeling'            => 'Modeling',
        'documents'           => 'Documents',
        'review'              => 'Review',
        'complete'            => 'Complete',
    ];
    return $map[$s] ?? ucfirst($s);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'claim') {
            $caseId = (int)($_POST['case_id'] ?? 0);
            $stmt = $db->prepare(
                'UPDATE cw_cases SET coordinator_id=?, updated_at=? WHERE id=? AND coordinator_id IS NULL'
            );
            $stmt->execute([getCurrentUserId(), date('Y-m-d H:i:s'), $caseId]);
            if ($stmt->rowCount() > 0) {
                setFlash('success', 'Case claimed — it\'s now in your active queue.');
            } else {
                setFlash('error', 'That case was already claimed by another coordinator.');
            }
            header('Location: ' . url('/coordinator.php'));
            exit;
        }
    }
}

// Open cases queue: unclaimed, not intake/complete
$openCases = $db->query(
    'SELECT cw_cases.*, cw_businesses.name AS business_name, cw_businesses.industry,
            cw_users.name AS client_name
     FROM cw_cases
     JOIN cw_businesses ON cw_businesses.id = cw_cases.business_id
     JOIN cw_users ON cw_users.id = cw_businesses.user_id
     WHERE cw_cases.coordinator_id IS NULL
       AND cw_cases.status NOT IN (\'intake\',\'complete\')
     ORDER BY cw_cases.created_at ASC'
)->fetchAll();

// My active cases
$stmt = $db->prepare(
    'SELECT cw_cases.*, cw_businesses.name AS business_name, cw_businesses.industry,
            cw_users.name AS client_name
     FROM cw_cases
     JOIN cw_businesses ON cw_businesses.id = cw_cases.business_id
     JOIN cw_users ON cw_users.id = cw_businesses.user_id
     WHERE cw_cases.coordinator_id = ?
       AND cw_cases.status != \'complete\'
     ORDER BY cw_cases.updated_at DESC'
);
$stmt->execute([getCurrentUserId()]);
$myCases = $stmt->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Dashboard — CoopConvert</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="bg-emerald-700 text-white px-6 py-4 flex items-center justify-between">
    <a href="<?= url('/coordinator.php') ?>" class="font-bold text-lg tracking-tight">CoopConvert</a>
    <div class="flex items-center gap-6 text-sm">
        <?php if (isAdmin()): ?>
        <a href="<?= url('/admin.php') ?>" class="hover:text-teal-300">Admin</a>
        <?php endif; ?>
        <span class="text-emerald-300"><?= h($user['name']) ?></span>
        <a href="<?= url('/logout.php') ?>" class="hover:text-teal-300">Sign out</a>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-10">

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

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Coordinator Dashboard</h1>
        <p class="text-gray-500 text-sm">Welcome back, <?= h($user['name']) ?>.</p>
    </div>

    <!-- Open cases queue -->
    <section class="mb-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">Open Queue</h2>
            <span class="text-sm text-gray-500"><?= count($openCases) ?> unclaimed</span>
        </div>

        <?php if (empty($openCases)): ?>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-8 text-center text-gray-400 text-sm">
            No unclaimed cases right now. Check back later.
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($openCases as $c): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-900"><?= h($c['business_name']) ?></p>
                        <p class="text-gray-500 text-sm"><?= h($c['client_name']) ?></p>
                        <?php if ($c['industry']): ?>
                        <p class="text-gray-400 text-xs"><?= h($c['industry']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$c['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                        <?= statusLabel($c['status']) ?>
                    </span>
                </div>
                <p class="text-gray-400 text-xs">Opened <?= h(date('M j, Y', strtotime($c['created_at']))) ?></p>
                <form method="POST" action="<?= url('/coordinator.php') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="claim">
                    <input type="hidden" name="case_id" value="<?= (int)$c['id'] ?>">
                    <button type="submit"
                            class="w-full bg-emerald-700 hover:bg-emerald-600 text-white text-sm font-semibold py-2 rounded-lg transition-colors">
                        Claim case
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- My active cases -->
    <section>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">My Active Cases</h2>
            <span class="text-sm text-gray-500"><?= count($myCases) ?> active</span>
        </div>

        <?php if (empty($myCases)): ?>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-8 text-center text-gray-400 text-sm">
            You haven't claimed any cases yet. Pick one from the queue above.
        </div>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold">Business</th>
                        <th class="text-left px-5 py-3 font-semibold">Client</th>
                        <th class="text-left px-5 py-3 font-semibold">Status</th>
                        <th class="text-left px-5 py-3 font-semibold">Last updated</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($myCases as $c): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-800"><?= h($c['business_name']) ?></p>
                            <?php if ($c['industry']): ?>
                            <p class="text-gray-400 text-xs"><?= h($c['industry']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-gray-600"><?= h($c['client_name']) ?></td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$c['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                <?= statusLabel($c['status']) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs"><?= h(date('M j, Y', strtotime($c['updated_at']))) ?></td>
                        <td class="px-5 py-3 text-right">
                            <a href="<?= url('/case.php?id=' . (int)$c['id']) ?>"
                               class="text-emerald-600 hover:underline text-sm font-medium">View &rarr;</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</div>
</body>
</html>
