<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/companies.php';
require_once TR_ROOT . '/includes/cohorts.php';
requireOnboarded();

$userId = getCurrentUserId();
$cohortId = (int) ($_GET['id'] ?? 0);

if (!$cohortId) {
    header('Location: ' . url('/company-cohorts.php'));
    exit;
}

$db = getDB();

// Get cohort
$stmt = $db->prepare("SELECT * FROM tr_cohorts WHERE id = ?");
$stmt->execute([$cohortId]);
$cohort = $stmt->fetch();

if (!$cohort) {
    setFlash('error', 'Cohort not found.');
    header('Location: ' . url('/company-cohorts.php'));
    exit;
}

// Verify access: a manager of THIS cohort's company, or someone enrolled in it.
// The original computed $isManager against the viewer's own company and never
// enforced the check, so any signed-in user could read any cohort's roster,
// and a manager anywhere could enroll people into another company's cohort.
$company = $cohort['company_id'] ? getUserCompany($userId) : null;
$isManager = $company && (int) $company['id'] === (int) $cohort['company_id']
    && isCompanyManager($company['id'], $userId);
$stmt = $db->prepare("SELECT 1 FROM tr_cohort_enrollments WHERE cohort_id = ? AND user_id = ?");
$stmt->execute([$cohortId, $userId]);
$isEnrolled = (bool) $stmt->fetchColumn();
if (!$isManager && !$isEnrolled && empty($cohort['is_template'])) {
    setFlash('error', 'Cohort not found.');
    header('Location: ' . url('/company-cohorts.php'));
    exit;
}

// Handle enroll POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isManager) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/cohort-detail.php') . '?id=' . $cohortId);
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    $startDate = $_POST['start_date'] ?? date('Y-m-d');

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        setFlash('error', 'User not found. They need a Bizorca Tools account first.');
    } else {
        if (enrollInCohort($cohortId, $targetUser['id'], $startDate)) {
            setFlash('success', 'Enrolled.');
        } else {
            setFlash('error', 'Already enrolled.');
        }
    }
    header('Location: ' . url('/cohort-detail.php') . '?id=' . $cohortId);
    exit;
}

$enrollments = getCohortEnrollments($cohortId);

// Get scenario list
$stmt = $db->prepare("
    SELECT cs.*, s.title AS scenario_title, s.difficulty, s.is_mashup
    FROM tr_cohort_scenarios cs
    JOIN tr_scenarios s ON s.id = cs.scenario_id
    WHERE cs.cohort_id = ?
    ORDER BY cs.day_number, cs.display_order
");
$stmt->execute([$cohortId]);
$scenarios = $stmt->fetchAll();

$pageTitle = $cohort['name'];
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <a href="<?= url('/company-cohorts.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 mb-4 inline-block">&larr; All Cohorts</a>

    <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= h($cohort['name']) ?></h1>
    <?php if ($cohort['description']): ?>
    <p class="text-gray-500 mb-6"><?= h($cohort['description']) ?></p>
    <?php endif; ?>

    <!-- Enroll -->
    <?php if ($isManager): ?>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Enroll a Team Member</h2>
        <form method="POST" class="flex items-end space-x-4">
            <?= csrfField() ?>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required placeholder="user@company.com"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>"
                       class="border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
            </div>
            <button type="submit" class="bg-tr-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-tr-700 transition">Enroll</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Enrolled Members Progress -->
    <?php if (!empty($enrollments)): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Progress</h2>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left bg-gray-50">
                    <th class="px-6 py-3 font-medium text-gray-500">Name</th>
                    <th class="px-6 py-3 font-medium text-gray-500">Started</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Day</th>
                    <th class="px-6 py-3 font-medium text-gray-500">Progress</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($enrollments as $e): ?>
                <tr>
                    <td class="px-6 py-3 font-medium text-gray-900"><?= h($e['name'] ?: $e['email']) ?></td>
                    <td class="px-6 py-3 text-gray-600"><?= date('M j', strtotime($e['started_at'])) ?></td>
                    <td class="px-6 py-3 text-center"><?= $e['current_day'] ?>/<?= $cohort['duration_days'] ?></td>
                    <td class="px-6 py-3">
                        <div class="flex items-center space-x-2">
                            <div class="flex-1 bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full bg-tr-500" style="width: <?= $e['progress_pct'] ?>%"></div>
                            </div>
                            <span class="text-xs text-gray-500"><?= $e['completed_scenarios'] ?>/<?= $e['total_scenarios'] ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <?php if ($e['completed_at']): ?>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Complete</span>
                        <?php elseif ($e['current_day'] > $cohort['duration_days']): ?>
                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Overdue</span>
                        <?php else: ?>
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Active</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Scenario Schedule -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Scenario Schedule</h2>
        <div class="space-y-2">
            <?php foreach ($scenarios as $s): ?>
            <div class="flex items-center justify-between py-2 <?= $s['day_number'] > 1 ? 'border-t border-gray-100' : '' ?>">
                <div class="flex items-center space-x-3">
                    <span class="text-sm font-mono text-gray-400 w-12">Day <?= $s['day_number'] ?></span>
                    <span class="text-sm font-medium text-gray-900"><?= h($s['scenario_title']) ?></span>
                    <?php if ($s['is_mashup']): ?>
                    <span class="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">Mashup</span>
                    <?php endif; ?>
                </div>
                <span class="text-xs text-gray-400"><?= h($s['difficulty']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
