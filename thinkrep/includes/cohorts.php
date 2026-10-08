<?php
/**
 * Onboarding cohort management.
 */

/**
 * Get all cohort templates (system-wide).
 */
function getCohortTemplates(): array {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM tr_cohorts WHERE is_template = 1 ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Get cohorts for a company or team.
 */
function getCompanyCohorts(int $companyId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM tr_cohort_enrollments WHERE cohort_id = c.id) AS enrolled_count,
               (SELECT COUNT(*) FROM tr_cohort_enrollments WHERE cohort_id = c.id AND completed_at IS NOT NULL) AS completed_count,
               (SELECT COUNT(*) FROM tr_cohort_scenarios WHERE cohort_id = c.id) AS scenario_count
        FROM tr_cohorts c
        WHERE c.company_id = ? OR c.is_template = 1
        ORDER BY c.is_template DESC, c.created_at DESC
    ");
    $stmt->execute([$companyId]);
    return $stmt->fetchAll();
}

/**
 * Create a cohort from a template (copies scenarios).
 */
function createCohortFromTemplate(int $templateId, int $companyId, int $createdBy, string $name): int {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM tr_cohorts WHERE id = ? AND is_template = 1");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    if (!$template) return 0;

    $stmt = $db->prepare("
        INSERT INTO tr_cohorts (company_id, name, description, created_by, duration_days, is_template)
        VALUES (?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([$companyId, $name, $template['description'], $createdBy, $template['duration_days']]);
    $cohortId = (int) $db->lastInsertId();

    // Copy scenarios
    $stmt = $db->prepare("SELECT scenario_id, day_number, display_order FROM tr_cohort_scenarios WHERE cohort_id = ?");
    $stmt->execute([$templateId]);
    $scenarios = $stmt->fetchAll();

    $insert = $db->prepare("INSERT INTO tr_cohort_scenarios (cohort_id, scenario_id, day_number, display_order) VALUES (?, ?, ?, ?)");
    foreach ($scenarios as $s) {
        $insert->execute([$cohortId, $s['scenario_id'], $s['day_number'], $s['display_order']]);
    }

    return $cohortId;
}

/**
 * Enroll a user in a cohort.
 */
function enrollInCohort(int $cohortId, int $userId, ?string $startDate = null): bool {
    $db = getDB();

    // Already enrolled?
    $stmt = $db->prepare("SELECT COUNT(*) FROM tr_cohort_enrollments WHERE cohort_id = ? AND user_id = ?");
    $stmt->execute([$cohortId, $userId]);
    if ((int) $stmt->fetchColumn() > 0) return false;

    $start = $startDate ?: date('Y-m-d');
    $stmt = $db->prepare("INSERT INTO tr_cohort_enrollments (cohort_id, user_id, started_at) VALUES (?, ?, ?)");
    $stmt->execute([$cohortId, $userId, $start]);
    return true;
}

/**
 * Get a user's active cohort enrollment with progress.
 */
function getUserCohortProgress(int $userId): ?array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT ce.*, c.name AS cohort_name, c.duration_days, c.description
        FROM tr_cohort_enrollments ce
        JOIN tr_cohorts c ON c.id = ce.cohort_id
        WHERE ce.user_id = ? AND ce.completed_at IS NULL
        ORDER BY ce.started_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $enrollment = $stmt->fetch();
    if (!$enrollment) return null;

    $cohortId = (int) $enrollment['cohort_id'];
    $startDate = $enrollment['started_at'];
    $currentDay = (int) ((strtotime(date('Y-m-d')) - strtotime($startDate)) / 86400) + 1;

    // Get all cohort scenarios
    $stmt = $db->prepare("
        SELECT cs.*, s.title AS scenario_title, s.difficulty, s.is_mashup
        FROM tr_cohort_scenarios cs
        JOIN tr_scenarios s ON s.id = cs.scenario_id
        WHERE cs.cohort_id = ?
        ORDER BY cs.day_number, cs.display_order
    ");
    $stmt->execute([$cohortId]);
    $scenarios = $stmt->fetchAll();

    // Get completed scenario IDs
    $stmt = $db->prepare("SELECT DISTINCT scenario_id FROM tr_responses WHERE user_id = ? AND created_at >= ?");
    $stmt->execute([$userId, $startDate]);
    $completedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Mark completion status
    $totalScenarios = count($scenarios);
    $completedCount = 0;
    foreach ($scenarios as &$s) {
        $s['completed'] = in_array((int) $s['scenario_id'], $completedIds);
        $s['available'] = ($s['day_number'] <= $currentDay);
        if ($s['completed']) $completedCount++;
    }

    // Check if cohort should be marked complete
    if ($completedCount >= $totalScenarios && !$enrollment['completed_at']) {
        $db->prepare("UPDATE tr_cohort_enrollments SET completed_at = NOW() WHERE id = ?")->execute([$enrollment['id']]);
        $enrollment['completed_at'] = date('Y-m-d H:i:s');
    }

    return [
        'enrollment' => $enrollment,
        'current_day' => min($currentDay, (int) $enrollment['duration_days']),
        'scenarios' => $scenarios,
        'total' => $totalScenarios,
        'completed' => $completedCount,
        'progress_pct' => $totalScenarios > 0 ? round(($completedCount / $totalScenarios) * 100) : 0,
    ];
}

/**
 * Get cohort enrollment details for a manager view.
 */
function getCohortEnrollments(int $cohortId): array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT ce.*, u.name, u.email, up.role_title
        FROM tr_cohort_enrollments ce
        JOIN users u ON u.id = ce.user_id
        LEFT JOIN tr_profiles up ON up.user_id = u.id
        WHERE ce.cohort_id = ?
        ORDER BY ce.started_at DESC
    ");
    $stmt->execute([$cohortId]);
    $enrollments = $stmt->fetchAll();

    // Get scenario count
    $stmt = $db->prepare("SELECT COUNT(*) FROM tr_cohort_scenarios WHERE cohort_id = ?");
    $stmt->execute([$cohortId]);
    $totalScenarios = (int) $stmt->fetchColumn();

    foreach ($enrollments as &$e) {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT r.scenario_id)
            FROM tr_responses r
            JOIN tr_cohort_scenarios cs ON cs.scenario_id = r.scenario_id AND cs.cohort_id = ?
            WHERE r.user_id = ? AND r.created_at >= ?
        ");
        $stmt->execute([$cohortId, $e['user_id'], $e['started_at']]);
        $e['completed_scenarios'] = (int) $stmt->fetchColumn();
        $e['total_scenarios'] = $totalScenarios;
        $e['progress_pct'] = $totalScenarios > 0 ? round(($e['completed_scenarios'] / $totalScenarios) * 100) : 0;

        $currentDay = (int) ((strtotime(date('Y-m-d')) - strtotime($e['started_at'])) / 86400) + 1;
        $e['current_day'] = $currentDay;
    }

    return $enrollments;
}
