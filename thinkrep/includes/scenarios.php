<?php
/**
 * Scenario selection and retrieval with spaced repetition.
 */

/**
 * Get a scenario with its shuffled choices for display.
 */
function getScenarioWithChoices(int $scenarioId): ?array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT s.*, mm.name AS correct_model_name, mm.slug AS correct_model_slug
        FROM tr_scenarios s
        JOIN tr_mental_models mm ON mm.id = s.correct_model_id
        WHERE s.id = ?
    ");
    $stmt->execute([$scenarioId]);
    $scenario = $stmt->fetch();

    if (!$scenario) return null;

    // Get choices with model info
    $stmt = $db->prepare("
        SELECT sc.*, mm.name AS model_name, mm.slug AS model_slug, mm.short_desc AS model_desc
        FROM tr_scenario_choices sc
        JOIN tr_mental_models mm ON mm.id = sc.model_id
        WHERE sc.scenario_id = ?
        ORDER BY RAND()
    ");
    $stmt->execute([$scenarioId]);
    $scenario['choices'] = $stmt->fetchAll();

    return $scenario;
}

/**
 * Get today's daily challenge scenario (same for all users).
 * Rotates through scenarios based on day of year.
 */
function getDailyChallenge(): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM tr_scenarios WHERE is_active = 1 ORDER BY id");
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($ids)) return null;

    $dayIndex = (int) date('z') % count($ids); // day of year mod scenario count
    return getScenarioWithChoices($ids[$dayIndex]);
}

/**
 * Check if user has completed today's daily challenge.
 */
function hasCompletedDailyChallenge(int $userId): bool {
    $daily = getDailyChallenge();
    if (!$daily) return true;

    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM tr_responses
        WHERE user_id = ? AND scenario_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$userId, $daily['id']]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Get scenarios due for spaced repetition review.
 *
 * Intervals based on last result:
 * - Wrong answer: review after 1 day, then 3 days, then 7 days
 * - Right but low reasoning (<3): review after 7 days, then 14 days
 * - Right with good reasoning (>=3): review after 30 days
 */
function getSpacedRepetitionDue(int $userId): array {
    $db = getDB();

    // Get the most recent response per scenario
    $stmt = $db->prepare("
        SELECT r.scenario_id, r.model_correct, r.reasoning_score, r.created_at,
               DATEDIFF(NOW(), r.created_at) AS days_ago,
               (SELECT COUNT(*) FROM tr_responses r2
                WHERE r2.user_id = r.user_id AND r2.scenario_id = r.scenario_id
                AND r2.model_correct = 1) AS times_correct_total
        FROM tr_responses r
        INNER JOIN (
            SELECT scenario_id, MAX(created_at) AS max_created
            FROM tr_responses WHERE user_id = ?
            GROUP BY scenario_id
        ) latest ON r.scenario_id = latest.scenario_id AND r.created_at = latest.max_created
        WHERE r.user_id = ?
    ");
    $stmt->execute([$userId, $userId]);
    $lastResponses = $stmt->fetchAll();

    $dueIds = [];
    foreach ($lastResponses as $r) {
        $daysAgo = (int) $r['days_ago'];
        $correct = (bool) $r['model_correct'];
        $reasoning = (int) $r['reasoning_score'];
        $timesCorrect = (int) $r['times_correct_total'];

        if (!$correct) {
            // Wrong: review after 1, 3, 7 days based on how many times they've gotten it right before
            $interval = $timesCorrect === 0 ? 1 : ($timesCorrect === 1 ? 3 : 7);
        } elseif ($reasoning < 3) {
            // Right but weak reasoning: review after 7 or 14 days
            $interval = $timesCorrect <= 2 ? 7 : 14;
        } else {
            // Right with good reasoning: review after 30 days
            $interval = 30;
        }

        if ($daysAgo >= $interval) {
            $dueIds[] = [
                'scenario_id' => (int) $r['scenario_id'],
                'priority' => $correct ? 1 : 2, // wrong answers get higher priority
                'days_overdue' => $daysAgo - $interval,
            ];
        }
    }

    // Sort: highest priority first (wrong answers), then most overdue
    usort($dueIds, function ($a, $b) {
        if ($a['priority'] !== $b['priority']) return $b['priority'] - $a['priority'];
        return $b['days_overdue'] - $a['days_overdue'];
    });

    return $dueIds;
}

/**
 * Get the next scenario for a user with spaced repetition.
 *
 * Priority:
 * 1. Spaced repetition review (scenarios due for re-test)
 * 2. Unseen scenarios, preferring tag matches and weak models
 * 3. Fallback to not-seen-in-30-days
 * 4. Any active scenario
 */
function getNextScenario(int $userId): ?array {
    $db = getDB();

    // 0. Check for company pack scenarios the user hasn't done
    $stmt = $db->prepare("
        SELECT s.id FROM tr_scenarios s
        JOIN tr_scenario_packs sp ON sp.id = s.pack_id
        JOIN tr_company_members cm ON cm.company_id = sp.company_id AND cm.user_id = ?
        WHERE s.is_active = 1
        AND s.id NOT IN (SELECT scenario_id FROM tr_responses WHERE user_id = ?)
        ORDER BY RAND()
        LIMIT 1
    ");
    $stmt->execute([$userId, $userId]);
    $packRow = $stmt->fetch();
    if ($packRow) {
        $scenario = getScenarioWithChoices($packRow['id']);
        if ($scenario) {
            $scenario['is_company_pack'] = true;
            return $scenario;
        }
    }

    // 1. Check for spaced repetition reviews due
    $dueReviews = getSpacedRepetitionDue($userId);
    if (!empty($dueReviews)) {
        $scenarioId = $dueReviews[0]['scenario_id'];
        $scenario = getScenarioWithChoices($scenarioId);
        if ($scenario) {
            $scenario['is_review'] = true;
            return $scenario;
        }
    }

    // Get user profile for tag matching
    $stmt = $db->prepare("SELECT role_title, industry FROM tr_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Get IDs user has already completed
    $stmt = $db->prepare("SELECT DISTINCT scenario_id FROM tr_responses WHERE user_id = ?");
    $stmt->execute([$userId]);
    $doneIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get weak models from blindspot cache
    $stmt = $db->prepare("
        SELECT model_id FROM tr_blindspot_cache
        WHERE user_id = ? AND times_presented >= 3
        AND (times_correct / times_presented) < 0.5
    ");
    $stmt->execute([$userId]);
    $weakModelIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Try unseen scenarios
    $scenario = findScenario($db, $doneIds, $user, $weakModelIds, false);

    // 3. If all seen, try not-seen-in-30-days
    if (!$scenario) {
        $stmt = $db->prepare("
            SELECT DISTINCT scenario_id FROM tr_responses
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$userId]);
        $recentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $scenario = findScenario($db, $recentIds, $user, $weakModelIds, true);
    }

    // 4. Last resort: any active scenario
    if (!$scenario) {
        $stmt = $db->prepare("SELECT id FROM tr_scenarios WHERE is_active = 1 ORDER BY RAND() LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            $scenario = getScenarioWithChoices($row['id']);
        }
    }

    return $scenario;
}

/**
 * Internal: find a scenario matching criteria.
 */
function findScenario(PDO $db, array $excludeIds, array $user, array $weakModelIds, bool $isFallback): ?array {
    $params = [];
    $excludeClause = '';

    if (!empty($excludeIds)) {
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $excludeClause = "AND s.id NOT IN ($placeholders)";
        $params = $excludeIds;
    }

    // Try tag-matched first
    $roleTitle = $user['role_title'] ?? '';
    $industry = $user['industry'] ?? '';

    if ($roleTitle || $industry) {
        $tagClauses = [];
        $tagParams = $params;
        if ($roleTitle) {
            $tagClauses[] = "JSON_CONTAINS(s.role_tags, ?)";
            $tagParams[] = json_encode($roleTitle);
        }
        if ($industry) {
            $tagClauses[] = "JSON_CONTAINS(s.industry_tags, ?)";
            $tagParams[] = json_encode($industry);
        }

        $tagWhere = '(' . implode(' OR ', $tagClauses) . ')';

        $orderClause = !empty($weakModelIds)
            ? "ORDER BY FIELD(s.correct_model_id, " . implode(',', array_map('intval', $weakModelIds)) . ") DESC, RAND()"
            : "ORDER BY RAND()";

        $sql = "SELECT s.id FROM tr_scenarios s WHERE s.is_active = 1 $excludeClause AND $tagWhere $orderClause LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute($tagParams);
        $row = $stmt->fetch();

        if ($row) {
            return getScenarioWithChoices($row['id']);
        }
    }

    // Fall back to any active scenario not in exclude list
    $orderClause = !empty($weakModelIds)
        ? "ORDER BY FIELD(s.correct_model_id, " . implode(',', array_map('intval', $weakModelIds)) . ") DESC, RAND()"
        : "ORDER BY RAND()";

    $sql = "SELECT s.id FROM tr_scenarios s WHERE s.is_active = 1 $excludeClause $orderClause LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    if ($row) {
        return getScenarioWithChoices($row['id']);
    }

    return null;
}
