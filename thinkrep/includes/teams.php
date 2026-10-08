<?php
/**
 * Team management and aggregate analytics.
 */

/**
 * Create a new team. Returns team ID.
 */
function createTeam(int $userId, string $name): int {
    $db = getDB();
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $name)));
    $slug = $slug ?: 'team-' . time();

    // Ensure unique slug
    $stmt = $db->prepare("SELECT COUNT(*) FROM tr_teams WHERE slug = ?");
    $stmt->execute([$slug]);
    if ((int) $stmt->fetchColumn() > 0) {
        $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    $inviteCode = bin2hex(random_bytes(16));

    $stmt = $db->prepare("INSERT INTO tr_teams (name, slug, invite_code, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $slug, $inviteCode, $userId]);
    $teamId = (int) $db->lastInsertId();

    // Add creator as owner
    $stmt = $db->prepare("INSERT INTO tr_team_members (team_id, user_id, role) VALUES (?, ?, 'owner')");
    $stmt->execute([$teamId, $userId]);

    return $teamId;
}

/**
 * Get teams a user belongs to.
 */
function getUserTeams(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.*, tm.role AS my_role,
               (SELECT COUNT(*) FROM tr_team_members WHERE team_id = t.id) AS member_count
        FROM tr_teams t
        JOIN tr_team_members tm ON tm.team_id = t.id AND tm.user_id = ?
        ORDER BY t.created_at DESC, t.id DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get a team by slug, with membership check.
 */
function getTeam(string $slug, int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.*, tm.role AS my_role
        FROM tr_teams t
        JOIN tr_team_members tm ON tm.team_id = t.id AND tm.user_id = ?
        WHERE t.slug = ?
    ");
    $stmt->execute([$userId, $slug]);
    return $stmt->fetch() ?: null;
}

/**
 * Get team members with stats.
 */
function getTeamMembers(int $teamId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, tm.role, tm.joined_at,
               (SELECT COUNT(*) FROM tr_responses WHERE user_id = u.id) AS total_sessions,
               (SELECT AVG(total_score) FROM tr_responses WHERE user_id = u.id) AS avg_score,
               (SELECT AVG(model_correct) * 100 FROM tr_responses WHERE user_id = u.id) AS accuracy
        FROM tr_team_members tm
        JOIN users u ON u.id = tm.user_id
        WHERE tm.team_id = ?
        ORDER BY tm.role = 'owner' DESC, tm.joined_at ASC
    ");
    $stmt->execute([$teamId]);
    return $stmt->fetchAll();
}

/**
 * Get aggregate blind spots for an entire team.
 */
function getTeamBlindspots(int $teamId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT mm.id AS model_id, mm.name AS model_name, mm.slug AS model_slug,
               COALESCE(SUM(bc.times_presented), 0) AS total_presented,
               COALESCE(SUM(bc.times_correct), 0) AS total_correct,
               COALESCE(AVG(bc.avg_reasoning), 0) AS team_avg_reasoning,
               COALESCE(SUM(bc.overuse_count), 0) AS total_overuse
        FROM tr_mental_models mm
        LEFT JOIN tr_blindspot_cache bc ON bc.model_id = mm.id
            AND bc.user_id IN (SELECT user_id FROM tr_team_members WHERE team_id = ?)
        GROUP BY mm.id, mm.name, mm.slug
        ORDER BY mm.display_order
    ");
    $stmt->execute([$teamId]);
    $all = $stmt->fetchAll();

    $weakSpots = [];
    $strengths = [];
    $overused = [];

    foreach ($all as &$row) {
        $row['accuracy'] = $row['total_presented'] > 0
            ? ($row['total_correct'] / $row['total_presented'])
            : 0;

        if ($row['total_presented'] >= 5 && $row['accuracy'] < 0.5) {
            $weakSpots[] = $row;
        }
        if ($row['total_presented'] >= 5 && $row['accuracy'] >= 0.8) {
            $strengths[] = $row;
        }
        if ($row['total_overuse'] >= 5) {
            $overused[] = $row;
        }
    }

    return [
        'all' => $all,
        'weak_spots' => $weakSpots,
        'strengths' => $strengths,
        'overused' => $overused,
    ];
}

/**
 * Join a team via invite code.
 */
function joinTeamByInvite(string $inviteCode, int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tr_teams WHERE invite_code = ?");
    $stmt->execute([$inviteCode]);
    $team = $stmt->fetch();

    if (!$team) return null;

    // Already a member?
    $stmt = $db->prepare("SELECT COUNT(*) FROM tr_team_members WHERE team_id = ? AND user_id = ?");
    $stmt->execute([$team['id'], $userId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return $team; // Already joined
    }

    $stmt = $db->prepare("INSERT INTO tr_team_members (team_id, user_id, role) VALUES (?, ?, 'member')");
    $stmt->execute([$team['id'], $userId]);

    return $team;
}

/**
 * Start a team challenge (assign a scenario for everyone).
 */
function startTeamChallenge(int $teamId, int $startedBy, ?int $scenarioId = null): ?array {
    $db = getDB();

    // Pick a random scenario if none specified
    if (!$scenarioId) {
        $stmt = $db->prepare("SELECT id FROM tr_scenarios WHERE is_active = 1 ORDER BY RAND() LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) return null;
        $scenarioId = (int) $row['id'];
    }

    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    $stmt = $db->prepare("
        INSERT INTO tr_team_challenges (team_id, scenario_id, started_by, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$teamId, $scenarioId, $startedBy, $expiresAt]);

    return [
        'id' => (int) $db->lastInsertId(),
        'scenario_id' => $scenarioId,
        'expires_at' => $expiresAt,
    ];
}

/**
 * Get active challenges for a team.
 */
function getActiveTeamChallenges(int $teamId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT tc.*, s.title AS scenario_title, s.difficulty,
               u.name AS started_by_name
        FROM tr_team_challenges tc
        JOIN tr_scenarios s ON s.id = tc.scenario_id
        JOIN users u ON u.id = tc.started_by
        WHERE tc.team_id = ? AND tc.expires_at > NOW()
        ORDER BY tc.started_at DESC
    ");
    $stmt->execute([$teamId]);
    return $stmt->fetchAll();
}

/**
 * Get team member responses for a specific challenge.
 */
function getChallengeResponses(int $challengeId): array {
    $db = getDB();

    // Get the challenge info
    $stmt = $db->prepare("SELECT * FROM tr_team_challenges WHERE id = ?");
    $stmt->execute([$challengeId]);
    $challenge = $stmt->fetch();
    if (!$challenge) return [];

    $stmt = $db->prepare("
        SELECT r.*, u.name AS user_name
        FROM tr_responses r
        JOIN tr_team_members tm ON tm.user_id = r.user_id AND tm.team_id = ?
        JOIN users u ON u.id = r.user_id
        WHERE r.scenario_id = ? AND r.created_at >= ?
        ORDER BY r.total_score DESC
    ");
    $stmt->execute([$challenge['team_id'], $challenge['scenario_id'], $challenge['started_at']]);
    return $stmt->fetchAll();
}

/**
 * Check if user is a team owner.
 */
function isTeamOwner(int $teamId, int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT role FROM tr_team_members WHERE team_id = ? AND user_id = ?");
    $stmt->execute([$teamId, $userId]);
    $row = $stmt->fetch();
    return $row && $row['role'] === 'owner';
}
