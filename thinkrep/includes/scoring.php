<?php
/**
 * Response scoring algorithm.
 * Total: 0-10 = model_points (0 or 5) + reasoning_points (0-5)
 */

function scoreResponse(array $scenario, int $chosenModelId, string $reasoningText): array {
    $modelCorrect = ($chosenModelId === (int) $scenario['correct_model_id']);
    $modelPoints = $modelCorrect ? 5 : 0;

    // Parse KEY line from ideal_reasoning
    $idealLines = explode("\n", $scenario['ideal_reasoning']);
    $keyPhrases = [];
    if (isset($idealLines[0]) && strpos($idealLines[0], 'KEY:') === 0) {
        $keyPart = substr($idealLines[0], 4);
        $keyPhrases = array_map('trim', explode('|', strtolower($keyPart)));
    }

    $reasoningLower = strtolower($reasoningText);
    $situationLower = strtolower($scenario['situation']);
    $notes = [];

    // 1. Length/effort — >= 100 characters
    $lengthPoint = (strlen(trim($reasoningText)) >= 100) ? 1 : 0;
    $notes[] = ['criterion' => 'length', 'score' => $lengthPoint, 'detail' => strlen(trim($reasoningText)) . ' chars'];

    // 2. Key concepts — mentions a phrase from KEY line
    $keyConceptPoint = 0;
    $matchedKey = null;
    foreach ($keyPhrases as $phrase) {
        if ($phrase && strpos($reasoningLower, $phrase) !== false) {
            $keyConceptPoint = 1;
            $matchedKey = $phrase;
            break;
        }
    }
    $notes[] = ['criterion' => 'key_concepts', 'score' => $keyConceptPoint, 'detail' => $matchedKey ?? 'none matched'];

    // 3. Causal language
    $causalTerms = ['because', 'therefore', 'leads to', 'results in', 'which means', 'consequently'];
    $causalPoint = 0;
    $matchedCausal = null;
    foreach ($causalTerms as $term) {
        if (strpos($reasoningLower, $term) !== false) {
            $causalPoint = 1;
            $matchedCausal = $term;
            break;
        }
    }
    $notes[] = ['criterion' => 'causal_language', 'score' => $causalPoint, 'detail' => $matchedCausal ?? 'none found'];

    // 4. Tradeoff awareness
    $tradeoffTerms = ['however', 'alternatively', 'trade-off', 'tradeoff', 'downside', 'risk', 'on the other hand'];
    $tradeoffPoint = 0;
    $matchedTradeoff = null;
    foreach ($tradeoffTerms as $term) {
        if (strpos($reasoningLower, $term) !== false) {
            $tradeoffPoint = 1;
            $matchedTradeoff = $term;
            break;
        }
    }
    $notes[] = ['criterion' => 'tradeoff_awareness', 'score' => $tradeoffPoint, 'detail' => $matchedTradeoff ?? 'none found'];

    // 5. Specificity — references 2+ concrete details from scenario (numbers or proper nouns)
    // Extract numbers and capitalized words from scenario
    preg_match_all('/\$[\d,]+|\d+%|\d+[,.]?\d*/', $scenario['situation'], $scenarioNumbers);
    $specificityPoint = 0;
    $matchedSpecifics = [];
    $scenarioNums = array_unique($scenarioNumbers[0] ?? []);
    foreach ($scenarioNums as $num) {
        if (strpos($reasoningText, $num) !== false) {
            $matchedSpecifics[] = $num;
        }
    }
    // Also check for proper nouns (words that start with uppercase in scenario, excluding sentence starters)
    preg_match_all('/(?<=[.!?]\s)[A-Z][a-z]+|(?<=\s)[A-Z][a-z]{2,}/', $scenario['situation'], $properNouns);
    $nouns = array_unique($properNouns[0] ?? []);
    foreach ($nouns as $noun) {
        if (stripos($reasoningText, $noun) !== false) {
            $matchedSpecifics[] = $noun;
        }
    }
    if (count($matchedSpecifics) >= 2) {
        $specificityPoint = 1;
    }
    $notes[] = ['criterion' => 'specificity', 'score' => $specificityPoint, 'detail' => implode(', ', array_slice($matchedSpecifics, 0, 5)) ?: 'none found'];

    $reasoningScore = $lengthPoint + $keyConceptPoint + $causalPoint + $tradeoffPoint + $specificityPoint;

    // If model wrong, cap reasoning at 2
    if (!$modelCorrect && $reasoningScore > 2) {
        $reasoningScore = 2;
        $notes[] = ['criterion' => 'cap', 'score' => 0, 'detail' => 'reasoning capped at 2/5 (wrong model)'];
    }

    $totalScore = $modelPoints + $reasoningScore;

    return [
        'model_correct' => $modelCorrect,
        'model_points' => $modelPoints,
        'reasoning_score' => $reasoningScore,
        'total_score' => $totalScore,
        'scoring_notes' => $notes,
    ];
}
