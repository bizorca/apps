<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/teams.php';
requireOnboarded();

$userId = getCurrentUserId();
$slug = trim($_GET['slug'] ?? '');

if (!$slug) {
    header('Location: ' . url('/teams.php'));
    exit;
}

$team = getTeam($slug, $userId);
if (!$team) {
    setFlash('error', 'Team not found or you are not a member.');
    header('Location: ' . url('/teams.php'));
    exit;
}

$db = getDB();
$isOwner = ($team['my_role'] === 'owner');

// Handle start challenge (owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/team-detail.php') . '?slug=' . $slug);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'start_challenge') {
        $scenarioId = (int) ($_POST['scenario_id'] ?? 0) ?: null;
        $challenge = startTeamChallenge($team['id'], $userId, $scenarioId);
        if ($challenge) {
            setFlash('success', 'Challenge started! Team members have 7 days to complete it.');
        }
    }

    header('Location: ' . url('/team-detail.php') . '?slug=' . $slug);
    exit;
}

$members = getTeamMembers($team['id']);
$blindspots = getTeamBlindspots($team['id']);
$challenges = getActiveTeamChallenges($team['id']);

// Get challenge responses for display
$challengeResponses = [];
foreach ($challenges as $ch) {
    $challengeResponses[$ch['id']] = getChallengeResponses($ch['id']);
}

$pageTitle = $team['name'];
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <a href="<?= url('/teams.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 mb-4 inline-block">&larr; All Teams</a>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900"><?= h($team['name']) ?></h1>
        <?php if ($isOwner): ?>
        <span class="text-xs bg-tr-100 text-tr-700 font-semibold px-2 py-1 rounded-full">Owner</span>
        <?php endif; ?>
    </div>

    <!-- Invite Link -->
    <?php if ($isOwner): ?>
    <div class="bg-tr-50 border border-tr-200 rounded-lg p-4 mb-6" x-data="{ copied: false }">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-tr-800">Invite Code</p>
                <code class="text-sm text-tr-600 mt-1 block"><?= h($team['invite_code']) ?></code>
            </div>
            <button @click="navigator.clipboard.writeText('<?= h($team['invite_code']) ?>'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="text-sm bg-tr-600 text-white px-3 py-1.5 rounded-lg hover:bg-tr-700 transition">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak>Copied!</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Active Challenges -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Challenges</h2>
            <?php if ($isOwner): ?>
            <form method="POST" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="start_challenge">
                <button type="submit" class="text-sm bg-tr-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-tr-700 transition">
                    Start New Challenge
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (empty($challenges)): ?>
        <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500 text-sm">
            No active challenges. <?= $isOwner ? 'Start one to get the team practicing together.' : 'Ask the team owner to start a challenge.' ?>
        </div>
        <?php else: ?>
        <?php foreach ($challenges as $ch):
            $responses = $challengeResponses[$ch['id']] ?? [];
            $completedCount = count($responses);
            $memberCount = count($members);
            // Check if current user has completed it
            $userCompleted = false;
            foreach ($responses as $resp) {
                if ((int)$resp['user_id'] === $userId) { $userCompleted = true; break; }
            }
        ?>
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-semibold text-gray-900"><?= h($ch['scenario_title']) ?></h3>
                    <p class="text-sm text-gray-500">
                        Started by <?= h($ch['started_by_name']) ?>
                        &middot; <?= h($ch['difficulty']) ?>
                        &middot; Expires <?= date('M j', strtotime($ch['expires_at'])) ?>
                    </p>
                </div>
                <span class="text-sm text-gray-400"><?= $completedCount ?>/<?= $memberCount ?> completed</span>
            </div>

            <?php if (!$userCompleted): ?>
            <a href="<?= url('/session.php') ?>?id=<?= $ch['scenario_id'] ?>" class="inline-block bg-tr-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-tr-700 transition">
                Take This Challenge
            </a>
            <?php endif; ?>

            <?php if (!empty($responses)): ?>
            <div class="mt-4 border-t border-gray-100 pt-3">
                <h4 class="text-xs font-semibold text-gray-400 uppercase mb-2">Leaderboard</h4>
                <div class="space-y-2">
                    <?php foreach ($responses as $i => $resp): ?>
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center space-x-2">
                            <span class="text-gray-400 w-5">#<?= $i + 1 ?></span>
                            <span class="font-medium text-gray-700"><?= h($resp['user_name'] ?: 'Anonymous') ?></span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="<?= $resp['model_correct'] ? 'text-green-600' : 'text-red-500' ?>">
                                <?= $resp['model_correct'] ? '&#10003;' : '&#10007;' ?>
                            </span>
                            <span class="font-mono font-bold <?= $resp['total_score'] >= 7 ? 'text-green-600' : ($resp['total_score'] >= 4 ? 'text-yellow-600' : 'text-red-600') ?>">
                                <?= $resp['total_score'] ?>/10
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Members -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Members</h2>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left bg-gray-50">
                        <th class="px-6 py-3 font-medium text-gray-500">Name</th>
                        <th class="px-6 py-3 font-medium text-gray-500 text-center">Sessions</th>
                        <th class="px-6 py-3 font-medium text-gray-500 text-center">Avg Score</th>
                        <th class="px-6 py-3 font-medium text-gray-500 text-center">Accuracy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td class="px-6 py-3">
                            <span class="font-medium text-gray-900"><?= h($m['name'] ?: $m['email']) ?></span>
                            <?php if ($m['role'] === 'owner'): ?>
                            <span class="text-xs text-tr-600 ml-1">owner</span>
                            <?php endif; ?>
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

    <!-- Team Blind Spots -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Team Blind Spots</h2>

        <?php if (!empty($blindspots['weak_spots'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-4">
            <h3 class="text-sm font-semibold text-red-800 mb-3">Team Weak Spots</h3>
            <div class="space-y-2">
                <?php foreach ($blindspots['weak_spots'] as $weak): ?>
                <div class="flex items-center justify-between bg-white rounded p-3">
                    <span class="font-medium text-red-700"><?= h($weak['model_name']) ?></span>
                    <span class="text-sm text-red-600">
                        <?= $weak['total_correct'] ?>/<?= $weak['total_presented'] ?> correct (<?= round($weak['accuracy'] * 100) ?>%)
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($blindspots['strengths'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-4">
            <h3 class="text-sm font-semibold text-green-800 mb-3">Team Strengths</h3>
            <div class="space-y-2">
                <?php foreach ($blindspots['strengths'] as $strong): ?>
                <div class="flex items-center justify-between bg-white rounded p-3">
                    <span class="font-medium text-green-700"><?= h($strong['model_name']) ?></span>
                    <span class="text-sm text-green-600"><?= round($strong['accuracy'] * 100) ?>% accuracy</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Full table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left bg-gray-50">
                        <th class="px-6 py-3 font-medium text-gray-500">Model</th>
                        <th class="px-6 py-3 font-medium text-gray-500 text-center">Presented</th>
                        <th class="px-6 py-3 font-medium text-gray-500 text-center">Correct</th>
                        <th class="px-6 py-3 font-medium text-gray-500 text-center">Accuracy</th>
                        <th class="px-6 py-3 font-medium text-gray-500 text-center">Avg Reasoning</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($blindspots['all'] as $row): ?>
                    <tr>
                        <td class="px-6 py-3 font-medium text-gray-900"><?= h($row['model_name']) ?></td>
                        <td class="px-6 py-3 text-center"><?= $row['total_presented'] ?></td>
                        <td class="px-6 py-3 text-center"><?= $row['total_correct'] ?></td>
                        <td class="px-6 py-3 text-center"><?= $row['total_presented'] > 0 ? round($row['accuracy'] * 100) . '%' : '-' ?></td>
                        <td class="px-6 py-3 text-center"><?= round($row['team_avg_reasoning'], 1) ?>/5</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
