<?php
/**
 * Role-based benchmarking.
 * Aggregates anonymized performance data by role and industry.
 */

/**
 * Rebuild all benchmark data from responses.
 * Call periodically (e.g., daily cron) or on-demand.
 */
function rebuildBenchmarks(): void {
    $db = getDB();

    // Clear existing
    $db->exec("TRUNCATE TABLE tr_benchmarks");

    // Aggregate by role_title + model
    $stmt = $db->query("
        SELECT up.role_title, NULL AS industry, s.correct_model_id AS model_id,
               COUNT(*) AS sample_size,
               AVG(r.model_correct) * 100 AS avg_accuracy,
               AVG(r.reasoning_score) AS avg_reasoning,
               AVG(r.total_score) AS avg_score
        FROM tr_responses r
        JOIN users u ON u.id = r.user_id
        LEFT JOIN tr_profiles up ON up.user_id = u.id
        JOIN tr_scenarios s ON s.id = r.scenario_id
        WHERE up.role_title IS NOT NULL AND up.role_title != ''
        GROUP BY up.role_title, s.correct_model_id
        HAVING sample_size >= 5
    ");
    $roleRows = $stmt->fetchAll();

    // Aggregate by industry + model
    $stmt = $db->query("
        SELECT NULL AS role_title, up.industry, s.correct_model_id AS model_id,
               COUNT(*) AS sample_size,
               AVG(r.model_correct) * 100 AS avg_accuracy,
               AVG(r.reasoning_score) AS avg_reasoning,
               AVG(r.total_score) AS avg_score
        FROM tr_responses r
        JOIN users u ON u.id = r.user_id
        LEFT JOIN tr_profiles up ON up.user_id = u.id
        JOIN tr_scenarios s ON s.id = r.scenario_id
        WHERE up.industry IS NOT NULL AND up.industry != ''
        GROUP BY up.industry, s.correct_model_id
        HAVING sample_size >= 5
    ");
    $industryRows = $stmt->fetchAll();

    // Aggregate overall per model (global benchmark)
    $stmt = $db->query("
        SELECT NULL AS role_title, NULL AS industry, s.correct_model_id AS model_id,
               COUNT(*) AS sample_size,
               AVG(r.model_correct) * 100 AS avg_accuracy,
               AVG(r.reasoning_score) AS avg_reasoning,
               AVG(r.total_score) AS avg_score
        FROM tr_responses r
        JOIN tr_scenarios s ON s.id = r.scenario_id
        GROUP BY s.correct_model_id
        HAVING sample_size >= 3
    ");
    $globalRows = $stmt->fetchAll();

    $insert = $db->prepare("
        INSERT INTO tr_benchmarks (role_title, industry, model_id, sample_size, avg_accuracy, avg_reasoning, avg_score)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach (array_merge($roleRows, $industryRows, $globalRows) as $row) {
        $insert->execute([
            $row['role_title'],
            $row['industry'],
            $row['model_id'],
            $row['sample_size'],
            round($row['avg_accuracy'], 2),
            round($row['avg_reasoning'], 1),
            round($row['avg_score'], 1),
        ]);
    }
}

/**
 * Get benchmarks for comparison.
 * Returns global, role-based, and industry-based benchmarks alongside the user/team stats.
 */
function getBenchmarkComparison(int $userId): array {
    $db = getDB();

    // Get user profile
    $stmt = $db->prepare("SELECT role_title, industry FROM tr_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Global benchmarks
    $stmt = $db->prepare("
        SELECT b.*, mm.name AS model_name
        FROM tr_benchmarks b
        JOIN tr_mental_models mm ON mm.id = b.model_id
        WHERE b.role_title IS NULL AND b.industry IS NULL
        ORDER BY mm.display_order
    ");
    $stmt->execute();
    $global = $stmt->fetchAll();

    // Role benchmarks
    $role = [];
    if (!empty($user['role_title'])) {
        $stmt = $db->prepare("
            SELECT b.*, mm.name AS model_name
            FROM tr_benchmarks b
            JOIN tr_mental_models mm ON mm.id = b.model_id
            WHERE b.role_title = ? AND b.industry IS NULL
            ORDER BY mm.display_order
        ");
        $stmt->execute([$user['role_title']]);
        $role = $stmt->fetchAll();
    }

    // Industry benchmarks
    $industry = [];
    if (!empty($user['industry'])) {
        $stmt = $db->prepare("
            SELECT b.*, mm.name AS model_name
            FROM tr_benchmarks b
            JOIN tr_mental_models mm ON mm.id = b.model_id
            WHERE b.role_title IS NULL AND b.industry = ?
            ORDER BY mm.display_order
        ");
        $stmt->execute([$user['industry']]);
        $industry = $stmt->fetchAll();
    }

    // User's own stats per model
    $stmt = $db->prepare("
        SELECT s.correct_model_id AS model_id, mm.name AS model_name,
               COUNT(*) AS sample_size,
               AVG(r.model_correct) * 100 AS avg_accuracy,
               AVG(r.reasoning_score) AS avg_reasoning,
               AVG(r.total_score) AS avg_score
        FROM tr_responses r
        JOIN tr_scenarios s ON s.id = r.scenario_id
        JOIN tr_mental_models mm ON mm.id = s.correct_model_id
        WHERE r.user_id = ?
        GROUP BY s.correct_model_id, mm.name
        ORDER BY mm.display_order
    ");
    $stmt->execute([$userId]);
    $personal = $stmt->fetchAll();

    return [
        'user' => $user,
        'personal' => $personal,
        'global' => $global,
        'role' => $role,
        'industry' => $industry,
    ];
}

/**
 * Get team benchmarks comparison vs global.
 */
function getTeamBenchmarkComparison(int $companyId): array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT s.correct_model_id AS model_id, mm.name AS model_name,
               COUNT(*) AS sample_size,
               AVG(r.model_correct) * 100 AS avg_accuracy,
               AVG(r.reasoning_score) AS avg_reasoning,
               AVG(r.total_score) AS avg_score
        FROM tr_responses r
        JOIN tr_company_members cm ON cm.user_id = r.user_id AND cm.company_id = ?
        JOIN tr_scenarios s ON s.id = r.scenario_id
        JOIN tr_mental_models mm ON mm.id = s.correct_model_id
        GROUP BY s.correct_model_id, mm.name
        ORDER BY mm.display_order
    ");
    $stmt->execute([$companyId]);
    $team = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT b.model_id, b.avg_accuracy, b.avg_reasoning, b.avg_score, b.sample_size
        FROM tr_benchmarks b
        WHERE b.role_title IS NULL AND b.industry IS NULL
    ");
    $stmt->execute();
    $global = [];
    foreach ($stmt->fetchAll() as $row) {
        $global[$row['model_id']] = $row;
    }

    return ['team' => $team, 'global' => $global];
}
