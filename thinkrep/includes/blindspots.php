<?php
/**
 * Blind spot detection and cache management.
 */

/**
 * Rebuild the blindspot_cache for a user from their responses.
 * Called after each response submission.
 */
function rebuildBlindspots(int $userId): void {
    $db = getDB();

    // Clear existing cache for this user
    $db->prepare("DELETE FROM tr_blindspot_cache WHERE user_id = ?")->execute([$userId]);

    // Aggregate from responses
    $stmt = $db->prepare("
        SELECT
            r.chosen_model_id AS model_id,
            COUNT(*) AS times_presented,
            SUM(r.model_correct) AS times_correct,
            AVG(r.reasoning_score) AS avg_reasoning,
            AVG(r.confidence) AS avg_confidence
        FROM tr_responses r
        WHERE r.user_id = ?
        GROUP BY r.chosen_model_id
    ");
    $stmt->execute([$userId]);
    $chosenStats = $stmt->fetchAll();

    // Also get stats by correct model (times it was the right answer)
    $stmt = $db->prepare("
        SELECT
            s.correct_model_id AS model_id,
            COUNT(*) AS times_presented,
            SUM(r.model_correct) AS times_correct,
            AVG(r.reasoning_score) AS avg_reasoning,
            AVG(r.confidence) AS avg_confidence
        FROM tr_responses r
        JOIN tr_scenarios s ON s.id = r.scenario_id
        WHERE r.user_id = ?
        GROUP BY s.correct_model_id
    ");
    $stmt->execute([$userId]);
    $correctModelStats = [];
    foreach ($stmt->fetchAll() as $row) {
        $correctModelStats[$row['model_id']] = $row;
    }

    // Calculate overuse: times a model was chosen incorrectly
    $stmt = $db->prepare("
        SELECT r.chosen_model_id AS model_id, COUNT(*) AS overuse_count
        FROM tr_responses r
        WHERE r.user_id = ? AND r.model_correct = 0
        GROUP BY r.chosen_model_id
    ");
    $stmt->execute([$userId]);
    $overuseStats = [];
    foreach ($stmt->fetchAll() as $row) {
        $overuseStats[$row['model_id']] = (int) $row['overuse_count'];
    }

    // Insert cache rows keyed by correct model
    $insert = $db->prepare("
        INSERT INTO tr_blindspot_cache (user_id, model_id, times_presented, times_correct, avg_reasoning, avg_confidence, overuse_count)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // Get all models
    $models = $db->query("SELECT id FROM tr_mental_models")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($models as $modelId) {
        $stats = $correctModelStats[$modelId] ?? null;
        $timesPres = $stats ? (int) $stats['times_presented'] : 0;
        $timesCorr = $stats ? (int) $stats['times_correct'] : 0;
        $avgReas = $stats ? round((float) $stats['avg_reasoning'], 1) : 0.0;
        $avgConf = $stats ? round((float) $stats['avg_confidence'], 1) : 0.0;
        $overuse = $overuseStats[$modelId] ?? 0;

        if ($timesPres > 0 || $overuse > 0) {
            $insert->execute([$userId, $modelId, $timesPres, $timesCorr, $avgReas, $avgConf, $overuse]);
        }
    }
}

/**
 * Get blind spot analysis for a user.
 */
function getBlindspots(int $userId): array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT bc.*, mm.name AS model_name, mm.slug AS model_slug
        FROM tr_blindspot_cache bc
        JOIN tr_mental_models mm ON mm.id = bc.model_id
        WHERE bc.user_id = ?
        ORDER BY mm.display_order
    ");
    $stmt->execute([$userId]);
    $all = $stmt->fetchAll();

    $weakSpots = [];
    $overused = [];
    $confidenceGaps = [];
    $strengths = [];

    foreach ($all as $row) {
        $accuracy = $row['times_presented'] > 0
            ? ($row['times_correct'] / $row['times_presented'])
            : 0;

        $row['accuracy'] = $accuracy;

        // Weak spots: accuracy < 50% with >= 3 presentations
        if ($row['times_presented'] >= 3 && $accuracy < 0.5) {
            $weakSpots[] = $row;
        }

        // Overused: picked incorrectly >= 3 times
        if ($row['overuse_count'] >= 3) {
            $overused[] = $row;
        }

        // Confidence-accuracy gap: high confidence (>= 3.5) + low accuracy (< 50%)
        if ($row['times_presented'] >= 3 && $row['avg_confidence'] >= 3.5 && $accuracy < 0.5) {
            $confidenceGaps[] = $row;
        }

        // Strengths: accuracy >= 80% with >= 3 presentations
        if ($row['times_presented'] >= 3 && $accuracy >= 0.8) {
            $strengths[] = $row;
        }
    }

    return [
        'all' => $all,
        'weak_spots' => $weakSpots,
        'overused' => $overused,
        'confidence_gaps' => $confidenceGaps,
        'strengths' => $strengths,
    ];
}
