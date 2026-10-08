<?php
require_once __DIR__ . '/_bootstrap.php';
require_once TR_ROOT . '/includes/scenarios.php';
require_once TR_ROOT . '/includes/teams.php';
requireOnboarded();

$userId = getCurrentUserId();
$user = getCurrentUser();
$db = getDB();

// Total sessions
$stmt = $db->prepare("SELECT COUNT(*) FROM tr_responses WHERE user_id = ?");
$stmt->execute([$userId]);
$totalSessions = (int) $stmt->fetchColumn();

// Average score
$stmt = $db->prepare("SELECT AVG(total_score) FROM tr_responses WHERE user_id = ?");
$stmt->execute([$userId]);
$avgScore = $totalSessions > 0 ? round((float) $stmt->fetchColumn(), 1) : 0;

// Accuracy rate
$stmt = $db->prepare("SELECT AVG(model_correct) * 100 FROM tr_responses WHERE user_id = ?");
$stmt->execute([$userId]);
$accuracy = $totalSessions > 0 ? round((float) $stmt->fetchColumn(), 0) : 0;

// Streak: consecutive days with at least one response
$stmt = $db->prepare("
    SELECT DISTINCT DATE(created_at) AS d
    FROM tr_responses
    WHERE user_id = ?
    ORDER BY d DESC
");
$stmt->execute([$userId]);
$dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

$streak = 0;
$today = date('Y-m-d');
$checkDate = $today;
foreach ($dates as $d) {
    if ($d === $checkDate) {
        $streak++;
        $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
    } elseif ($d === date('Y-m-d', strtotime($today . ' -1 day')) && $streak === 0) {
        $checkDate = $d;
        $streak++;
        $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
    } else {
        break;
    }
}

// Streak milestone
$streakMilestone = null;
$milestones = [100, 50, 30, 21, 14, 7, 3];
foreach ($milestones as $m) {
    if ($streak >= $m) { $streakMilestone = $m; break; }
}

// Daily challenge
$dailyChallenge = getDailyChallenge();
$dailyCompleted = hasCompletedDailyChallenge($userId);

// Spaced repetition reviews due
$reviewsDue = getSpacedRepetitionDue($userId);
$reviewCount = count($reviewsDue);

// Recent responses
$stmt = $db->prepare("
    SELECT r.id, r.total_score, r.model_correct, r.created_at, s.title AS scenario_title
    FROM tr_responses r
    JOIN tr_scenarios s ON s.id = r.scenario_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC, r.id DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$recent = $stmt->fetchAll();

// Team challenges pending
$teams = getUserTeams($userId);
$pendingChallenges = [];
foreach ($teams as $team) {
    $challenges = getActiveTeamChallenges($team['id']);
    foreach ($challenges as $ch) {
        // Check if user has completed this challenge
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM tr_responses
            WHERE user_id = ? AND scenario_id = ? AND created_at >= ?
        ");
        $stmt->execute([$userId, $ch['scenario_id'], $ch['started_at']]);
        if ((int) $stmt->fetchColumn() === 0) {
            $ch['team_name'] = $team['name'];
            $ch['team_slug'] = $team['slug'];
            $pendingChallenges[] = $ch;
        }
    }
}

// Mashup scenario count
$stmt = $db->prepare("SELECT COUNT(*) FROM tr_scenarios WHERE is_mashup = 1 AND is_active = 1");
$stmt->execute();
$mashupCount = (int) $stmt->fetchColumn();

// Mashups completed by user
$stmt = $db->prepare("SELECT COUNT(*) FROM tr_mashup_responses WHERE user_id = ?");
$stmt->execute([$userId]);
$mashupsDone = (int) $stmt->fetchColumn();

// Hindsight opportunities: responses 30+ days old without a reflection
$stmt = $db->prepare("
    SELECT r.id, r.total_score, r.reasoning_score, s.title AS scenario_title, r.created_at,
           DATEDIFF(NOW(), r.created_at) AS days_ago
    FROM tr_responses r
    JOIN tr_scenarios s ON s.id = r.scenario_id
    LEFT JOIN tr_hindsight_reflections hr ON hr.response_id = r.id
    WHERE r.user_id = ? AND DATEDIFF(NOW(), r.created_at) >= 30 AND hr.id IS NULL
    ORDER BY r.created_at ASC
    LIMIT 3
");
$stmt->execute([$userId]);
$hindsightOpps = $stmt->fetchAll();

// Active cohort enrollment
require_once TR_ROOT . '/includes/cohorts.php';
$cohortProgress = getUserCohortProgress($userId);

$pageTitle = 'Dashboard';
require TR_ROOT . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <a href="<?= url('/session.php') ?>" class="bg-tr-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-tr-700 transition">
            Start Training
        </a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-tr-600"><?= $totalSessions ?></div>
            <div class="text-sm text-gray-500">Sessions</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-tr-600"><?= $avgScore ?></div>
            <div class="text-sm text-gray-500">Avg Score</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-tr-600"><?= $accuracy ?>%</div>
            <div class="text-sm text-gray-500">Accuracy</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-<?= $streak > 0 ? 'orange' : 'gray' ?>-500"><?= $streak ?></div>
            <div class="text-sm text-gray-500">Day Streak</div>
            <?php if ($streakMilestone): ?>
            <div class="text-xs text-orange-400 mt-1">
                <?php
                $labels = [3 => 'Hat trick!', 7 => 'Full week!', 14 => 'Two weeks!', 21 => 'Three weeks!', 30 => 'Monthly master!', 50 => 'Unstoppable!', 100 => 'Legendary!'];
                echo $labels[$streakMilestone] ?? $streakMilestone . ' days!';
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($totalSessions === 0): ?>
    <!-- First-time CTA -->
    <div class="bg-tr-50 border border-tr-200 rounded-lg p-8 text-center">
        <h2 class="text-xl font-semibold text-tr-800 mb-2">Ready to train?</h2>
        <p class="text-tr-600 mb-4">You will face a real-world scenario, pick the mental model that applies, and write your reasoning. Each session takes 3-5 minutes.</p>
        <a href="<?= url('/session.php') ?>" class="inline-block bg-tr-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-tr-700 transition">
            Start Your First Session
        </a>
    </div>
    <?php else: ?>

    <!-- Active Cohort -->
    <?php if ($cohortProgress && !$cohortProgress['enrollment']['completed_at']):
        // Find today's scenario
        $todayScenario = null;
        foreach ($cohortProgress['scenarios'] as $cs) {
            if ($cs['available'] && !$cs['completed']) { $todayScenario = $cs; break; }
        }
    ?>
    <div class="bg-gradient-to-r from-green-600 to-green-700 rounded-lg p-6 mb-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold"><?= h($cohortProgress['enrollment']['cohort_name']) ?></h2>
                <p class="text-green-200 text-sm mt-1">
                    Day <?= $cohortProgress['current_day'] ?> &middot; <?= $cohortProgress['progress_pct'] ?>% complete
                    <?php if ($todayScenario): ?>&middot; Next: <?= h($todayScenario['scenario_title']) ?><?php endif; ?>
                </p>
            </div>
            <?php if ($todayScenario): ?>
            <a href="<?= url($todayScenario['is_mashup'] ? '/mashup-session.php' : '/session.php') ?>?id=<?= $todayScenario['scenario_id'] ?>"
               class="bg-white text-green-700 px-5 py-2 rounded-lg font-semibold hover:bg-green-50 transition text-sm">
                Today's Lesson
            </a>
            <?php else: ?>
            <a href="<?= url('/my-cohort.php') ?>" class="bg-white text-green-700 px-5 py-2 rounded-lg font-semibold hover:bg-green-50 transition text-sm">
                View Progress
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Daily Challenge -->
    <?php if ($dailyChallenge && !$dailyCompleted): ?>
    <div class="bg-gradient-to-r from-tr-600 to-tr-700 rounded-lg p-6 mb-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold">Daily Challenge</h2>
                <p class="text-tr-200 text-sm mt-1"><?= h($dailyChallenge['title']) ?></p>
            </div>
            <a href="<?= url('/session.php') ?>?id=<?= $dailyChallenge['id'] ?>" class="bg-white text-tr-700 px-5 py-2 rounded-lg font-semibold hover:bg-tr-50 transition text-sm">
                Take Challenge
            </a>
        </div>
    </div>
    <?php elseif ($dailyChallenge && $dailyCompleted): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 flex items-center justify-between">
        <div>
            <span class="text-green-700 font-semibold text-sm">Daily Challenge Complete</span>
            <span class="text-green-600 text-sm ml-2"><?= h($dailyChallenge['title']) ?></span>
        </div>
        <span class="text-green-500 text-lg">&#10003;</span>
    </div>
    <?php endif; ?>

    <!-- Reviews Due (Spaced Repetition) -->
    <?php if ($reviewCount > 0): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 flex items-center justify-between">
        <div>
            <span class="text-amber-800 font-semibold text-sm"><?= $reviewCount ?> Review<?= $reviewCount > 1 ? 's' : '' ?> Due</span>
            <span class="text-amber-600 text-sm ml-2">Scenarios you got wrong or scored low on are ready for another try.</span>
        </div>
        <a href="<?= url('/session.php') ?>" class="bg-amber-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-amber-700 transition text-sm">
            Review Now
        </a>
    </div>
    <?php endif; ?>

    <!-- Model Mashups -->
    <?php if ($mashupCount > 0 && $totalSessions >= 5): ?>
    <div class="bg-white rounded-lg shadow p-4 mb-6 flex items-center justify-between">
        <div>
            <span class="text-sm font-semibold text-purple-700">Model Mashups</span>
            <span class="text-sm text-gray-500 ml-2">Advanced scenarios where multiple models apply. <?= $mashupsDone ?>/<?= $mashupCount ?> completed.</span>
        </div>
        <a href="<?= url('/mashup-session.php') ?>" class="bg-purple-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-purple-700 transition text-sm">
            Try a Mashup
        </a>
    </div>
    <?php endif; ?>

    <!-- Hindsight Opportunities -->
    <?php if (!empty($hindsightOpps)): ?>
    <div class="mb-6">
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Hindsight Reflections</h2>
        <div class="space-y-3">
            <?php foreach ($hindsightOpps as $opp): ?>
            <div class="bg-white rounded-lg shadow p-4 flex items-center justify-between">
                <div>
                    <span class="font-medium text-gray-900"><?= h($opp['scenario_title']) ?></span>
                    <span class="text-sm text-gray-400 ml-2"><?= $opp['days_ago'] ?> days ago &middot; scored <?= $opp['total_score'] ?>/10</span>
                </div>
                <a href="<?= url('/hindsight.php') ?>?id=<?= $opp['id'] ?>" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg font-semibold hover:bg-purple-700 transition text-sm">
                    Reflect
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pending Team Challenges -->
    <?php if (!empty($pendingChallenges)): ?>
    <div class="mb-6">
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Team Challenges</h2>
        <div class="space-y-3">
            <?php foreach ($pendingChallenges as $ch): ?>
            <div class="bg-white rounded-lg shadow p-4 flex items-center justify-between">
                <div>
                    <span class="font-medium text-gray-900"><?= h($ch['scenario_title']) ?></span>
                    <span class="text-sm text-gray-400 ml-2">from <?= h($ch['team_name']) ?></span>
                </div>
                <a href="<?= url('/session.php') ?>?id=<?= $ch['scenario_id'] ?>" class="bg-tr-600 text-white px-4 py-1.5 rounded-lg font-semibold hover:bg-tr-700 transition text-sm">
                    Go
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Activity -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Recent Sessions</h2>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($recent as $r): ?>
            <a href="<?= url('/session-result.php') ?>?id=<?= $r['id'] ?>" class="block px-6 py-4 hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900"><?= h($r['scenario_title']) ?></span>
                        <span class="text-sm text-gray-400 ml-2"><?= date('M j', strtotime($r['created_at'])) ?></span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm <?= $r['model_correct'] ? 'text-green-600' : 'text-red-500' ?>">
                            <?= $r['model_correct'] ? '&#10003;' : '&#10007;' ?>
                        </span>
                        <span class="font-mono font-bold <?= $r['total_score'] >= 7 ? 'text-green-600' : ($r['total_score'] >= 4 ? 'text-yellow-600' : 'text-red-600') ?>">
                            <?= $r['total_score'] ?>/10
                        </span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="px-6 py-3 border-t border-gray-100">
            <a href="<?= url('/journal.php') ?>" class="text-sm text-tr-600 hover:text-tr-700 font-medium">View all &rarr;</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require TR_ROOT . '/templates/footer.php'; ?>
