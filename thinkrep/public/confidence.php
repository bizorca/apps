<?php
require_once __DIR__ . '/_bootstrap.php';
requireOnboarded();

$userId = getCurrentUserId();
$db = getDB();

// Handle POST: log daily confidence
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/confidence.php'));
        exit;
    }

    $score = max(1, min(10, (int) ($_POST['score'] ?? 5)));
    $notes = trim($_POST['notes'] ?? '');
    $today = date('Y-m-d');

    $stmt = $db->prepare("
        INSERT INTO tr_confidence_logs (user_id, score, notes, logged_date, created_at)
        VALUES (?, ?, ?, ?, NOW()) AS new
        ON DUPLICATE KEY UPDATE score = new.score, notes = new.notes
    ");
    $stmt->execute([$userId, $score, $notes ?: null, $today]);

    setFlash('success', 'Confidence logged.');
    header('Location: ' . url('/confidence.php'));
    exit;
}

// Check if already logged today
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT * FROM tr_confidence_logs WHERE user_id = ? AND logged_date = ?");
$stmt->execute([$userId, $today]);
$todayLog = $stmt->fetch();

// Get last 30 days of logs
$stmt = $db->prepare("
    SELECT * FROM tr_confidence_logs
    WHERE user_id = ? AND logged_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY logged_date ASC
");
$stmt->execute([$userId]);
$logs = $stmt->fetchAll();

// Build chart data (fill gaps with null)
$chartData = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartData[$date] = null;
}
foreach ($logs as $log) {
    $chartData[$log['logged_date']] = (int) $log['score'];
}

$pageTitle = 'Confidence Log';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Daily Confidence</h1>

    <!-- Log Form -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <?= $todayLog ? 'Update Today\'s Rating' : 'How confident do you feel in your decision-making today?' ?>
        </h2>
        <form method="POST" x-data="{ score: <?= $todayLog ? $todayLog['score'] : 5 ?> }">
            <?= csrfField() ?>

            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-500">Low confidence</span>
                    <span class="text-sm text-gray-500">High confidence</span>
                </div>
                <div class="flex items-center justify-between space-x-1">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="score" value="<?= $i ?>" x-model.number="score" class="sr-only">
                        <div class="h-10 rounded flex items-center justify-center text-sm font-bold border-2 transition"
                             :class="score == <?= $i ?> ? 'bg-tr-600 text-white border-tr-600' : 'bg-gray-50 text-gray-500 border-gray-200 hover:border-tr-300'">
                            <?= $i ?>
                        </div>
                    </label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="mb-4">
                <textarea name="notes" rows="2"
                          class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border text-sm"
                          placeholder="Any notes? (optional)"><?= h($todayLog['notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="bg-tr-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-tr-700 transition text-sm">
                <?= $todayLog ? 'Update' : 'Log' ?>
            </button>
        </form>
    </div>

    <!-- 30-Day Chart -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Last 30 Days</h2>

        <?php if (empty($logs)): ?>
        <p class="text-gray-500 text-center py-8">No data yet. Start logging daily to see your trend.</p>
        <?php else: ?>
        <div class="flex items-end space-x-1" style="height: 200px;">
            <?php foreach ($chartData as $date => $val): ?>
            <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                <?php if ($val !== null): ?>
                <div class="w-full bg-tr-500 rounded-t transition-all hover:bg-tr-600"
                     style="height: <?= ($val / 10) * 100 ?>%"
                     title="<?= date('M j', strtotime($date)) ?>: <?= $val ?>/10">
                </div>
                <?php else: ?>
                <div class="w-full bg-gray-100 rounded-t" style="height: 2px"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="flex justify-between mt-2 text-xs text-gray-400">
            <span><?= date('M j', strtotime('-29 days')) ?></span>
            <span>Today</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Logs -->
    <?php if (!empty($logs)): ?>
    <div class="bg-white rounded-lg shadow mt-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Recent Entries</h2>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach (array_reverse($logs) as $log): ?>
            <div class="px-6 py-3 flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-900"><?= date('M j, Y', strtotime($log['logged_date'])) ?></span>
                    <?php if ($log['notes']): ?>
                    <p class="text-sm text-gray-500 mt-0.5"><?= h($log['notes']) ?></p>
                    <?php endif; ?>
                </div>
                <span class="text-lg font-bold text-tr-600"><?= $log['score'] ?>/10</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
