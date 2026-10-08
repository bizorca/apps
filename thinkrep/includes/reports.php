<?php
/**
 * Manager insight report generation.
 */

/**
 * Generate a full insight report for a team or company.
 */
function generateInsightReport(int $companyId, ?int $teamId = null, int $days = 30): array {
    $db = getDB();

    $memberJoin = $teamId
        ? "JOIN tr_team_members tm ON tm.user_id = r.user_id AND tm.team_id = ?"
        : "JOIN tr_company_members cm ON cm.user_id = r.user_id AND cm.company_id = ?";
    $entityId = $teamId ?: $companyId;

    $dateFilter = "AND r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";

    // Overall stats
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total_sessions,
               COUNT(DISTINCT r.user_id) AS active_users,
               AVG(r.total_score) AS avg_score,
               AVG(r.model_correct) * 100 AS accuracy,
               AVG(r.reasoning_score) AS avg_reasoning
        FROM tr_responses r
        $memberJoin
        WHERE 1=1 $dateFilter
    ");
    $stmt->execute([$entityId, $days]);
    $overall = $stmt->fetch();

    // Previous period for comparison
    $stmt = $db->prepare("
        SELECT AVG(r.total_score) AS avg_score,
               AVG(r.model_correct) * 100 AS accuracy,
               AVG(r.reasoning_score) AS avg_reasoning
        FROM tr_responses r
        $memberJoin
        WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
          AND r.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$entityId, $days * 2, $days]);
    $previous = $stmt->fetch();

    // Per-model breakdown
    $stmt = $db->prepare("
        SELECT mm.name AS model_name, mm.slug AS model_slug,
               COUNT(*) AS presentations,
               SUM(r.model_correct) AS correct,
               AVG(r.model_correct) * 100 AS accuracy,
               AVG(r.reasoning_score) AS avg_reasoning
        FROM tr_responses r
        $memberJoin
        JOIN tr_scenarios s ON s.id = r.scenario_id
        JOIN tr_mental_models mm ON mm.id = s.correct_model_id
        WHERE 1=1 $dateFilter
        GROUP BY mm.id, mm.name, mm.slug
        ORDER BY accuracy ASC
    ");
    $stmt->execute([$entityId, $days]);
    $modelBreakdown = $stmt->fetchAll();

    // Top performers
    $stmt = $db->prepare("
        SELECT u.name, u.email, up.role_title,
               COUNT(*) AS sessions,
               AVG(r.total_score) AS avg_score,
               AVG(r.model_correct) * 100 AS accuracy
        FROM tr_responses r
        $memberJoin
        JOIN users u ON u.id = r.user_id
        LEFT JOIN tr_profiles up ON up.user_id = u.id
        WHERE 1=1 $dateFilter
        GROUP BY u.id, u.name, u.email, up.role_title
        HAVING sessions >= 3
        ORDER BY avg_score DESC
        LIMIT 5
    ");
    $stmt->execute([$entityId, $days]);
    $topPerformers = $stmt->fetchAll();

    // Users needing attention (low accuracy or low activity)
    $stmt = $db->prepare("
        SELECT u.name, u.email, up.role_title,
               COUNT(*) AS sessions,
               AVG(r.total_score) AS avg_score,
               AVG(r.model_correct) * 100 AS accuracy
        FROM tr_responses r
        $memberJoin
        JOIN users u ON u.id = r.user_id
        LEFT JOIN tr_profiles up ON up.user_id = u.id
        WHERE 1=1 $dateFilter
        GROUP BY u.id, u.name, u.email, up.role_title
        HAVING accuracy < 50 OR sessions < 3
        ORDER BY accuracy ASC
        LIMIT 5
    ");
    $stmt->execute([$entityId, $days]);
    $needsAttention = $stmt->fetchAll();

    // Weakest and strongest models
    $weakest = array_slice($modelBreakdown, 0, 3);
    $strongest = array_slice(array_reverse($modelBreakdown), 0, 3);

    // Recommendations
    $recommendations = [];
    foreach ($weakest as $w) {
        if ($w['accuracy'] < 50 && $w['presentations'] >= 5) {
            $recommendations[] = "Your team struggles with **" . $w['model_name'] . "** (" . round($w['accuracy']) . "% accuracy). Consider running a focused team discussion on this model before your next planning session.";
        }
    }
    if ($overall['avg_reasoning'] < 2.5) {
        $recommendations[] = "Average reasoning scores are low (" . round($overall['avg_reasoning'], 1) . "/5). Encourage team members to write longer, more specific explanations that reference scenario details.";
    }
    if ($overall['active_users'] < 3) {
        $recommendations[] = "Only " . $overall['active_users'] . " team members were active in the past {$days} days. Consider starting a team challenge to boost engagement.";
    }

    return [
        'period_days' => $days,
        'overall' => $overall,
        'previous' => $previous,
        'model_breakdown' => $modelBreakdown,
        'top_performers' => $topPerformers,
        'needs_attention' => $needsAttention,
        'weakest' => $weakest,
        'strongest' => $strongest,
        'recommendations' => $recommendations,
    ];
}
