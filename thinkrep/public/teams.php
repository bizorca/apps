<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/teams.php';
requireOnboarded();

$userId = getCurrentUserId();
$db = getDB();

// Handle create team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/teams.php'));
        exit;
    }

    $name = trim($_POST['team_name'] ?? '');
    if (strlen($name) < 2 || strlen($name) > 100) {
        setFlash('error', 'Team name must be 2-100 characters.');
        header('Location: ' . url('/teams.php'));
        exit;
    }

    $teamId = createTeam($userId, $name);

    // Get slug for redirect
    $stmt = $db->prepare("SELECT slug FROM tr_teams WHERE id = ?");
    $stmt->execute([$teamId]);
    $slug = $stmt->fetchColumn();

    setFlash('success', 'Team created! Share the invite link with your teammates.');
    header('Location: ' . url('/team-detail.php') . '?slug=' . $slug);
    exit;
}

// Handle join via invite code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'join') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid form submission.');
        header('Location: ' . url('/teams.php'));
        exit;
    }

    $inviteCode = trim($_POST['invite_code'] ?? '');
    if (!$inviteCode) {
        setFlash('error', 'Please enter an invite code.');
        header('Location: ' . url('/teams.php'));
        exit;
    }

    $team = joinTeamByInvite($inviteCode, $userId);
    if ($team) {
        setFlash('success', 'Joined team "' . $team['name'] . '"!');
        header('Location: ' . url('/team-detail.php') . '?slug=' . $team['slug']);
    } else {
        setFlash('error', 'Invalid invite code.');
        header('Location: ' . url('/teams.php'));
    }
    exit;
}

$teams = getUserTeams($userId);

$pageTitle = 'Teams';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Teams</h1>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <!-- Create Team -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Create a Team</h2>
            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div>
                    <input type="text" name="team_name" required minlength="2" maxlength="100"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border"
                           placeholder="Team name">
                </div>
                <button type="submit" class="w-full bg-tr-600 text-white py-2 rounded-lg font-semibold hover:bg-tr-700 transition text-sm">
                    Create Team
                </button>
            </form>
        </div>

        <!-- Join Team -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Join a Team</h2>
            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="join">
                <div>
                    <input type="text" name="invite_code" required
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-tr-500 focus:border-tr-500 px-3 py-2 border"
                           placeholder="Paste invite code">
                </div>
                <button type="submit" class="w-full bg-gray-800 text-white py-2 rounded-lg font-semibold hover:bg-gray-900 transition text-sm">
                    Join Team
                </button>
            </form>
        </div>
    </div>

    <!-- My Teams -->
    <?php if (!empty($teams)): ?>
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Your Teams</h2>
    <div class="grid gap-4">
        <?php foreach ($teams as $team): ?>
        <a href="<?= url('/team-detail.php') ?>?slug=<?= h($team['slug']) ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900"><?= h($team['name']) ?></h3>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $team['member_count'] ?> member<?= $team['member_count'] !== 1 ? 's' : '' ?>
                        &middot;
                        <span class="text-tr-600"><?= $team['my_role'] ?></span>
                    </p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php elseif (empty($teams)): ?>
    <div class="bg-gray-50 rounded-lg p-8 text-center text-gray-500">
        You're not part of any teams yet. Create one or join with an invite code.
    </div>
    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
