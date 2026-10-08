<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/companies.php';
require_once TR_ROOT . '/includes/cohorts.php';
requireOnboarded();

$userId = getCurrentUserId();
$company = getUserCompany($userId);

if (!$company || !isCompanyManager($company['id'], $userId)) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/company-cohorts.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_from_template') {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $name = trim($_POST['cohort_name'] ?? '');
        if (!$templateId || !$name) {
            setFlash('error', 'Please select a template and enter a name.');
        } else {
            $cohortId = createCohortFromTemplate($templateId, $company['id'], $userId, $name);
            if ($cohortId) {
                setFlash('success', 'Cohort created! Now enroll members.');
                header('Location: ' . url('/cohort-detail.php') . '?id=' . $cohortId);
                exit;
            } else {
                setFlash('error', 'Failed to create cohort.');
            }
        }
    } elseif ($action === 'enroll') {
        $cohortId = (int) ($_POST['cohort_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $startDate = $_POST['start_date'] ?? date('Y-m-d');

        // The cohort has to be this company's. The original took any posted
        // cohort_id, so a manager anywhere could enroll into anyone's cohort.
        $stmt = $db->prepare("SELECT 1 FROM tr_cohorts WHERE id = ? AND company_id = ?");
        $stmt->execute([$cohortId, $company['id']]);
        $ownCohort = (bool) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $targetUser = $stmt->fetch();

        if (!$ownCohort) {
            setFlash('error', 'Cohort not found.');
            header('Location: ' . url('/company-cohorts.php'));
            exit;
        } elseif (!$targetUser) {
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

    header('Location: ' . url('/company-cohorts.php'));
    exit;
}

$cohorts = getCompanyCohorts($company['id']);
$templates = getCohortTemplates();

$pageTitle = 'Onboarding Cohorts';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <a href="<?= url('/company.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 mb-4 inline-block">&larr; Company Dashboard</a>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Onboarding Cohorts</h1>

    <!-- Create from Template -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Create New Cohort</h2>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_from_template">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Template</label>
                    <select name="template_id" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                        <option value="">Select a template...</option>
                        <?php foreach ($templates as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= h($t['name']) ?> (<?= $t['duration_days'] ?> days)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cohort Name</label>
                    <input type="text" name="cohort_name" required placeholder="e.g., Q1 2026 New Hires"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
                </div>
            </div>
            <button type="submit" class="bg-tr-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-tr-700 transition text-sm">
                Create Cohort
            </button>
        </form>
    </div>

    <!-- Existing Cohorts -->
    <?php
    $companyCohorts = array_filter($cohorts, fn($c) => !$c['is_template']);
    if (!empty($companyCohorts)): ?>
    <div class="space-y-4">
        <?php foreach ($companyCohorts as $c): ?>
        <a href="<?= url('/cohort-detail.php') ?>?id=<?= $c['id'] ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900"><?= h($c['name']) ?></h3>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $c['scenario_count'] ?> scenarios &middot; <?= $c['duration_days'] ?> days
                        &middot; <?= $c['enrolled_count'] ?> enrolled
                        &middot; <?= $c['completed_count'] ?> completed
                    </p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-gray-50 rounded-lg p-8 text-center text-gray-500">
        No cohorts yet. Create one from a template above.
    </div>
    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
