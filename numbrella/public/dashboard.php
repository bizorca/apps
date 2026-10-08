<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once NB_ROOT . '/includes/multiples.php';

$user   = requireLogin();
$userId = (int) $user['id'];
$db     = getDB();

// Delete one of your own reports (and its cached PDF).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf'] ?? null)) {
        setFlash('error', 'Invalid form submission. Please try again.');
        redirect('/dashboard.php');
    }
    $id = (int) ($_POST['delete_id'] ?? 0);
    $stmt = $db->prepare('DELETE FROM nb_reports WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() > 0) {
        require_once NB_ROOT . '/includes/pdf.php';
        deleteReportPdf($userId, $id);
        setFlash('success', 'Report deleted.');
    }
    redirect('/dashboard.php');
}

$stmt = $db->prepare('SELECT * FROM nb_reports WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$userId]);
$reports = $stmt->fetchAll();

$flash = getFlash();

$statusLabels = [
    'draft'    => 'Draft',
    'complete' => 'Complete',
];
$statusColors = [
    'draft'    => 'bg-yellow-50 text-yellow-700 border border-yellow-200',
    'complete' => 'bg-green-50 text-green-700 border border-green-200',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports — <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<?php include NB_ROOT . '/templates/header.php'; ?>

<main class="max-w-3xl mx-auto px-4 py-10">

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">My Valuation Reports</h1>
            <p class="text-gray-500 text-sm mt-0.5"><?= h($user['name'] ?: $user['email']) ?></p>
        </div>
        <a href="<?= h(url('/wizard.php?step=1')) ?>"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors">
            + New Report
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-lg <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($reports)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">No reports yet</h2>
            <p class="text-gray-500 text-sm mb-6 max-w-sm mx-auto">
                Enter the financials for a business you're considering and get a valuation range, risk flags and a PDF in about ten minutes.
            </p>
            <a href="<?= h(url('/wizard.php?step=1')) ?>"
                class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Start your first valuation
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($reports as $r):
                $status     = $r['status'];
                $isPaid     = $status === 'complete';
                $industry   = getMultiplesForIndustry($r['industry_key'])['name'];
                $editUrl    = url('/wizard.php?step=' . max(1, (int)$r['wizard_step']) . '&id=' . $r['id']);
                $reportUrl  = url('/report.php?id=' . $r['id']);
                $valuation  = $r['valuation_json'] ? json_decode($r['valuation_json'], true) : null;
                $range      = $valuation ? $valuation['summary'] : null;
            ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 flex items-start gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="font-semibold text-gray-900 truncate">
                            <?= h($r['business_name'] ?: 'Unnamed Business') ?>
                        </h3>
                        <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full <?= $statusColors[$status] ?? '' ?>">
                            <?= h($statusLabels[$status] ?? $status) ?>
                        </span>
                        <?php if (NB_PAYMENTS_ENABLED && $r['tier'] === 'premium' && $isPaid): ?>
                            <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">Premium</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-500">
                        <?= h($industry) ?>
                        &middot;
                        <?= h(date('M j, Y', strtotime($r['created_at'] . ' UTC'))) ?>
                    </p>
                    <?php if ($range && $isPaid): ?>
                        <p class="text-sm text-gray-700 mt-2 font-medium">
                            Estimated value:
                            <span class="text-indigo-700">
                                <?= money((float)$range['consensus_low']) ?> – <?= money((float)$range['consensus_high']) ?>
                            </span>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col gap-2 items-end shrink-0">
                    <?php if ($isPaid): ?>
                        <a href="<?= h($reportUrl) ?>"
                            class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                            View Report →
                        </a>
                    <?php else: ?>
                        <a href="<?= h($editUrl) ?>"
                            class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                            Continue →
                        </a>
                        <span class="text-xs text-gray-400">Step <?= (int)$r['wizard_step'] ?> of 3</span>
                    <?php endif; ?>
                    <form method="POST" action="<?= h(url('/dashboard.php')) ?>" onsubmit="return confirm('Delete this report? This cannot be undone.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="delete_id" value="<?= (int) $r['id'] ?>">
                        <button type="submit" class="text-xs text-gray-400 hover:text-red-600">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include NB_ROOT . '/templates/footer.php'; ?>
</body>
</html>
