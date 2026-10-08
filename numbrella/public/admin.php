<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once NB_ROOT . '/includes/multiples.php';

requireAdmin();

$db = getDB();

// Filters
$statusFilter = (string) ($_GET['status'] ?? '');
$search       = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

$where  = ['1=1'];
$params = [];

if ($statusFilter && in_array($statusFilter, ['draft', 'complete'], true)) {
    $where[]  = 'r.status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $like     = '%' . addcslashes($search, '%_\\') . '%';
    $where[]  = '(r.business_name LIKE ? OR u.email LIKE ?)';
    $params[] = $like;
    $params[] = $like;
}

$sql = 'SELECT r.id, r.business_name, r.industry_key, r.status, r.tier, r.wizard_step, r.created_at,
               u.email, u.name
        FROM nb_reports r
        JOIN users u ON u.id = r.user_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY r.created_at DESC
        LIMIT 200';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Stats
$statsStmt = $db->query('SELECT status, COUNT(*) AS n FROM nb_reports GROUP BY status');
$stats = [];
foreach ($statsStmt->fetchAll() as $row) {
    $stats[$row['status']] = (int)$row['n'];
}

$revenueStmt = $db->query("SELECT SUM(amount_cents) AS total FROM nb_payments WHERE status = 'paid'");
$totalRevenue = (int)($revenueStmt->fetch()['total'] ?? 0);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<?php include NB_ROOT . '/templates/header.php'; ?>

<main class="max-w-5xl mx-auto px-4 py-10">

    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Admin</h1>
    </div>

    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-lg <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-800' : 'bg-green-50 text-green-800' ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-2xl font-extrabold text-gray-900"><?= number_format(($stats['draft'] ?? 0)) ?></p>
            <p class="text-xs text-gray-500 mt-1">Draft reports</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-2xl font-extrabold text-indigo-600"><?= number_format($stats['complete'] ?? 0) ?></p>
            <p class="text-xs text-gray-500 mt-1">Completed reports</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-2xl font-extrabold text-gray-900"><?= number_format(array_sum($stats)) ?></p>
            <p class="text-xs text-gray-500 mt-1">Total reports</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-2xl font-extrabold text-green-600">$<?= number_format($totalRevenue / 100, 0) ?></p>
            <p class="text-xs text-gray-500 mt-1">Revenue<?= NB_PAYMENTS_ENABLED ? '' : ' (payments off)' ?></p>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?= h(url('/admin.php')) ?>" class="flex flex-wrap gap-3 mb-6">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search business or email…"
            class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        <select name="status"
            class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
            <option value="">All statuses</option>
            <option value="draft"    <?= $statusFilter === 'draft'    ? 'selected' : '' ?>>Draft</option>
            <option value="complete" <?= $statusFilter === 'complete' ? 'selected' : '' ?>>Complete</option>
        </select>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
            Filter
        </button>
        <a href="<?= h(url('/admin.php')) ?>" class="text-sm text-gray-500 hover:text-gray-700 py-2 px-2">Reset</a>
    </form>

    <!-- Reports table -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="text-left text-xs font-semibold text-gray-500 px-4 py-3">Business</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-4 py-3">User</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-4 py-3">Industry</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-4 py-3">Status</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-4 py-3">Tier</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-4 py-3">Date</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">No reports found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($reports as $r):
                    $industry = getMultiplesForIndustry($r['industry_key'])['name'];
                    $isPaid   = $r['status'] === 'complete';
                    $statusColors = [
                        'draft'    => 'bg-yellow-50 text-yellow-700',
                        'complete' => 'bg-green-50 text-green-700',
                    ];
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">
                        <?= h($r['business_name'] ?: '—') ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs">
                        <?= h($r['email']) ?><br>
                        <span class="text-gray-400"><?= h($r['name']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= h($industry) ?></td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $statusColors[$r['status']] ?? '' ?>">
                            <?= h(ucfirst($r['status'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500"><?= h(ucfirst($r['tier'])) ?></td>
                    <td class="px-4 py-3 text-xs text-gray-400"><?= h(date('M j, Y', strtotime($r['created_at'] . ' UTC'))) ?></td>
                    <td class="px-4 py-3 text-right">
                        <?php if ($isPaid): ?>
                        <a href="<?= h(url('/report.php?id=' . $r['id'])) ?>" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">Step <?= (int)$r['wizard_step'] ?>/3</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<?php include NB_ROOT . '/templates/footer.php'; ?>
</body>
</html>
