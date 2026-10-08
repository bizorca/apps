<?php
/**
 * Scoring for multi-model mashup scenarios.
 *
 * Total: 0-10 points
 * - Primary model identified: 5 pts
 * - At least one secondary model identified: 2 pts
 * - Reasoning quality (0-3): interplay language, length, specificity
 */

/**
 * Get the correct models for a mashup scenario.
 */
function getMashupCorrectModels(int $scenarioId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT scm.*, mm.name AS model_name, mm.slug AS model_slug
        FROM tr_scenario_correct_models scm
        JOIN tr_mental_models mm ON mm.id = scm.model_id
        WHERE scm.scenario_id = ?
        ORDER BY scm.rank_level = 'primary' DESC
    ");
    $stmt->execute([$scenarioId]);
    return $stmt->fetchAll();
}

/**
 * Score a mashup response.
 *
 * @param array $scenario The scenario with correct models loaded
 * @param int $primaryModelId The model the user ranked as primary
 * @param array $selectedModelIds All models the user selected (including primary)
 * @param string $reasoningText The user's written reasoning
 * @return array Scoring result
 */
function scoreMashupResponse(array $scenario, int $primaryModelId, array $selectedModelIds, string $reasoningText): array {
    $correctModels = getMashupCorrectModels($scenario['id']);

    $primaryCorrectId = null;
    $secondaryCorrectIds = [];
    foreach ($correctModels as $cm) {
        if ($cm['rank_level'] === 'primary') {
            $primaryCorrectId = (int) $cm['model_id'];
        } else {
            $secondaryCorrectIds[] = (int) $cm['model_id'];
        }
    }

    $notes = [];

    // 1. Primary model correct? (5 pts)
    $primaryCorrect = ($primaryModelId === $primaryCorrectId);
    $primaryPoints = $primaryCorrect ? 5 : 0;
    $notes[] = [
        'criterion' => 'primary_model',
        'score' => $primaryPoints,
        'detail' => $primaryCorrect ? 'correct' : 'incorrect',
    ];

    // 2. At least one secondary identified? (2 pts)
    $secondaryFound = false;
    $foundSecondaries = [];
    foreach ($selectedModelIds as $id) {
        if (in_array((int) $id, $secondaryCorrectIds)) {
            $secondaryFound = true;
            $foundSecondaries[] = $id;
        }
    }
    $secondaryPoints = $secondaryFound ? 2 : 0;
    $notes[] = [
        'criterion' => 'secondary_model',
        'score' => $secondaryPoints,
        'detail' => $secondaryFound ? 'found ' . count($foundSecondaries) : 'none found',
    ];

    // 3. Reasoning quality (0-3 pts)
    $reasoningLower = strtolower($reasoningText);

    // 3a. Interplay language — discusses how models relate to each other (1 pt)
    $interplayTerms = ['interplay', 'reinforc', 'compound', 'both', 'together', 'combined',
                       'while also', 'at the same time', 'in addition', 'layers on',
                       'primary.*secondary', 'first bias.*second', 'one.*other',
                       'explains why.*shows how', 'why.*what', 'trap.*framework'];
    $interplayPoint = 0;
    $matchedInterplay = null;
    foreach ($interplayTerms as $term) {
        if (preg_match('/' . $term . '/i', $reasoningText)) {
            $interplayPoint = 1;
            $matchedInterplay = $term;
            break;
        }
    }
    $notes[] = [
        'criterion' => 'interplay',
        'score' => $interplayPoint,
        'detail' => $matchedInterplay ?? 'no interplay language found',
    ];

    // 3b. Length (1 pt) — mashups need more explanation, require 150+ chars
    $lengthPoint = (strlen(trim($reasoningText)) >= 150) ? 1 : 0;
    $notes[] = [
        'criterion' => 'length',
        'score' => $lengthPoint,
        'detail' => strlen(trim($reasoningText)) . ' chars (150+ required)',
    ];

    // 3c. Specificity — references details from the scenario (1 pt)
    preg_match_all('/\$[\d,]+|\d+%|\d+[,.]?\d*/', $scenario['situation'], $scenarioNumbers);
    $matchedSpecifics = [];
    foreach (array_unique($scenarioNumbers[0] ?? []) as $num) {
        if (strpos($reasoningText, $num) !== false) {
            $matchedSpecifics[] = $num;
        }
    }
    $specificityPoint = (count($matchedSpecifics) >= 1) ? 1 : 0;
    $notes[] = [
        'criterion' => 'specificity',
        'score' => $specificityPoint,
        'detail' => implode(', ', $matchedSpecifics) ?: 'none found',
    ];

    $reasoningScore = $interplayPoint + $lengthPoint + $specificityPoint;

    // If primary wrong, cap reasoning at 1/3
    if (!$primaryCorrect && $reasoningScore > 1) {
        $reasoningScore = 1;
        $notes[] = ['criterion' => 'cap', 'score' => 0, 'detail' => 'reasoning capped at 1/3 (wrong primary)'];
    }

    $totalScore = $primaryPoints + $secondaryPoints + $reasoningScore;

    return [
        'primary_correct' => $primaryCorrect,
        'secondary_found' => $secondaryFound,
        'primary_points' => $primaryPoints,
        'secondary_points' => $secondaryPoints,
        'reasoning_score' => $reasoningScore,
        'total_score' => $totalScore,
        'scoring_notes' => $notes,
        'correct_models' => $correctModels,
    ];
}
