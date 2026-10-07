<?php
declare(strict_types=1);

require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/PlaybookGenerator.php';

/**
 * Evaluates Localrev rules against a business's market profile and local organizations.
 * Generates and persists recommendations (with Claude-generated playbooks) to the DB.
 *
 * Returns the array of recommendation records that were saved.
 */
function generateRecommendations(int $businessId, array $profile, array $orgs, array $business): array
{
    $rules    = getLocalrevRules();
    $orgTypes = array_column($orgs, 'org_type'); // for has_org_type checks
    $pop      = (int)($profile['population'] ?? 0);
    $type     = $business['business_type'];

    $matched = [];

    foreach ($rules as $rule) {
        // Filter by business type
        if (!in_array($type, $rule['business_types'], true)) continue;

        // Evaluate conditions (all must pass — AND logic)
        if (!ruleConditionsMet($rule['conditions'], $orgTypes, $pop)) continue;

        // Population tier check (belt + suspenders alongside explicit conditions)
        if (!populationTierMatches($rule['population_tier'], $pop)) continue;

        $matched[] = $rule;
    }

    // Sort descending by priority_base before saving
    usort($matched, fn($a, $b) => $b['priority_base'] <=> $a['priority_base']);

    $saved = [];
    foreach ($matched as $rule) {
        // Generate playbook via Claude (non-blocking: if it fails, store without playbook)
        $playbook = generatePlaybook($rule, $profile, $orgs, $business);

        $recId = upsertRecommendation($businessId, [
            'rule_key'                  => $rule['key'],
            'title'                     => $rule['title'],
            'description'               => $rule['description'],
            'category'                  => $rule['category'],
            'estimated_monthly_revenue' => $rule['revenue_estimate'],
            'priority'                  => $rule['priority_base'],
            'playbook_json'             => $playbook !== null ? json_encode($playbook) : null,
        ]);

        if ($recId) {
            $rec         = getRecommendationById($recId);
            if ($rec) $saved[] = $rec;
        }
    }

    return $saved;
}

// ---------------------------------------------------------------------------
// Condition evaluation helpers
// ---------------------------------------------------------------------------

function ruleConditionsMet(array $conditions, array $orgTypes, int $pop): bool
{
    if (empty($conditions)) return true;

    if (isset($conditions['has_org_type'])) {
        $required = (array)$conditions['has_org_type'];
        $met = false;
        foreach ($required as $reqType) {
            if (in_array($reqType, $orgTypes, true)) { $met = true; break; }
        }
        if (!$met) return false;
    }

    if (isset($conditions['population_min']) && $pop < (int)$conditions['population_min']) {
        return false;
    }

    if (isset($conditions['population_max']) && $pop > (int)$conditions['population_max']) {
        return false;
    }

    return true;
}

function populationTierMatches(string $tier, int $pop): bool
{
    return match ($tier) {
        'under_10k'  => $pop < 10000,
        '10k_25k'    => $pop >= 10000 && $pop <= 25000,
        '25k_plus'   => $pop > 25000,
        default      => true,  // 'any'
    };
}
