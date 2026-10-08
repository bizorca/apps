<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/companies.php';
require_once TR_ROOT . '/includes/benchmarks.php';
requireOnboarded();

$userId = getCurrentUserId();
$db = getDB();

// Create company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/company.php'));
        exit;
    }

    $name = trim($_POST['company_name'] ?? '');
    if (strlen($name) < 2) {
        setFlash('error', 'Company name must be at least 2 characters.');
        header('Location: ' . url('/company.php'));
        exit;
    }

    $companyId = createCompany($userId, $name);
    $company = getDB()->prepare("SELECT slug FROM tr_companies WHERE id = ?");
    $company->execute([$companyId]);
    $slug = $company->fetchColumn();

    setFlash('success', 'Company created!');
    header('Location: ' . url('/company.php'));
    exit;
}

$company = getUserCompany($userId);

if (!$company) {
    // Show create form
    $pageTitle = 'Company';
    require TR_ROOT . '/templates/header.php';
?>
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Create a Company Account</h1>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-600 mb-4">A company account lets you manage teams, create custom scenarios, run onboarding cohorts, and view org-wide analytics.</p>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div>
                <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                <input type="text" name="company_name" id="company_name" required minlength="2" maxlength="200"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border">
            </div>
            <button type="submit" class="w-full bg-tr-600 text-white py-3 rounded-lg font-semibold hover:bg-tr-700 transition">
                Create Company
            </button>
        </form>
    </div>
</div>
<?php
    require TR_ROOT . '/templates/footer.php';
    exit;
}

// Company dashboard
$isAdmin = isCompanyAdmin($company['id'], $userId);
$isManager = isCompanyManager($company['id'], $userId);
$orgStats = getCompanyOrgStats($company['id']);
$members = getCompanyMembers($company['id']);
$blindspots = getCompanyBlindspots($company['id']);

$pageTitle = $company['name'];
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= h($company['name']) ?></h1>
            <p class="text-sm text-gray-500"><?= count($members) ?> / <?= $company['seat_limit'] ?> seats used</p>
        </div>
        <?php if ($isAdmin): ?>
        <div class="flex space-x-3">
            <a href="<?= url('/company-members.php') ?>" class="text-sm bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition">Members</a>
            <a href="<?= url('/company-scenarios.php') ?>" class="text-sm bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition">Scenario Packs</a>
            <a href="<?= url('/company-cohorts.php') ?>" class="text-sm bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition">Cohorts</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Org Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-tr-600"><?= (int) $orgStats['total_members'] ?></div>
            <div class="text-sm text-gray-500">Members</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-tr-600"><?= (int) $orgStats['total_sessions'] ?></div>
            <div class="text-sm text-gray-500">Total Sessions</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-tr-600"><?= $orgStats['avg_score'] ? round($orgStats['avg_score'], 1) : '-' ?></div>
            <div class="text-sm text-gray-500">Avg Score</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-tr-600"><?= $orgStats['accuracy'] ? round($orgStats['accuracy']) . '%' : '-' ?></div>
            <div class="text-sm text-gray-500">Accuracy</div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <!-- Org Blind Spots -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Org Blind Spots</h2>
                <?php if ($isManager): ?>
                <a href="<?= url('/company-report.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 font-medium">Full Report &rarr;</a>
                <?php endif; ?>
            </div>
            <div class="space-y-2">
                <?php foreach ($blindspots as $bs): if ($bs['total_presented'] < 1) continue; ?>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-700"><?= h($bs['model_name']) ?></span>
                    <div class="flex items-center space-x-2">
                        <div class="w-24 bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full <?= $bs['accuracy'] >= 0.7 ? 'bg-green-500' : ($bs['accuracy'] >= 0.4 ? 'bg-yellow-500' : 'bg-red-500') ?>"
                                 style="width: <?= round($bs['accuracy'] * 100) ?>%"></div>
                        </div>
                        <span class="text-gray-500 w-10 text-right"><?= round($bs['accuracy'] * 100) ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Benchmarks Preview -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">vs. Global Benchmark</h2>
                <a href="<?= url('/benchmarks.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 font-medium">Details &rarr;</a>
            </div>
            <?php
            $benchComp = getTeamBenchmarkComparison($company['id']);
            if (empty($benchComp['team'])): ?>
            <p class="text-sm text-gray-500">Not enough data yet. Complete more sessions to see benchmarks.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($benchComp['team'] as $t):
                    $g = $benchComp['global'][$t['model_id']] ?? null;
                    $delta = $g ? round($t['avg_accuracy'] - $g['avg_accuracy']) : null;
                ?>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-700"><?= h($t['model_name']) ?></span>
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-500"><?= round($t['avg_accuracy']) ?>%</span>
                        <?php if ($delta !== null): ?>
                        <span class="text-xs font-semibold <?= $delta >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $delta >= 0 ? '+' : '' ?><?= $delta ?>%
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Member Leaderboard -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Members</h2>
            <?php if ($isAdmin): ?>
            <a href="<?= url('/company-members.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 font-medium">Manage &rarr;</a>
            <?php endif; ?>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left bg-gray-50">
                    <th class="px-6 py-3 font-medium text-gray-500">Name</th>
                    <th class="px-6 py-3 font-medium text-gray-500">Role</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Sessions</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Avg Score</th>
                    <th class="px-6 py-3 font-medium text-gray-500 text-center">Accuracy</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($members as $m): ?>
                <tr>
                    <td class="px-6 py-3 font-medium text-gray-900"><?= h($m['name'] ?: $m['email']) ?></td>
                    <td class="px-6 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full <?= $m['role'] === 'admin' ? 'bg-tr-100 text-tr-700' : ($m['role'] === 'manager' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') ?>">
                            <?= $m['role'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center"><?= (int) $m['total_sessions'] ?></td>
                    <td class="px-6 py-3 text-center"><?= $m['avg_score'] ? round($m['avg_score'], 1) : '-' ?></td>
                    <td class="px-6 py-3 text-center"><?= $m['accuracy'] !== null ? round($m['accuracy']) . '%' : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
