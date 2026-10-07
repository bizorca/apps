<?php
declare(strict_types=1);

/**
 * Generates actionable playbooks for Localrev recommendations via the Anthropic API.
 * Returns a structured array with concrete steps for executing each opportunity.
 */
function generatePlaybook(array $rule, array $profile, array $orgs, array $business): ?array
{
    if (!defined('ANTHROPIC_API_KEY') || ANTHROPIC_API_KEY === '') {
        return null;
    }

    $bizType    = ucfirst($business['business_type'] === 'dojo' ? 'martial arts dojo' : $business['business_type']);
    $bizName    = $business['business_name'];
    $town       = $profile['town_name'] . (($profile['state_code'] ?? '') !== '' ? ', ' . $profile['state_code'] : '');
    $pop        = number_format((int)($profile['population'] ?? 0));
    $orgList    = '';
    foreach ($orgs as $org) {
        if ($org['org_type'] === ($rule['conditions']['has_org_type'] ?? '')) {
            $orgList .= '- ' . $org['org_name'];
            if (!empty($org['employee_count'])) {
                $orgList .= ' (~' . $org['employee_count'] . ' employees/staff)';
            }
            $orgList .= "\n";
        }
    }
    if ($orgList === '') {
        // Include all orgs as context even if none match the rule's specific condition
        foreach ($orgs as $org) {
            $orgList .= '- ' . $org['org_name'] . ' (' . $org['org_type'] . ")\n";
        }
    }

    $prompt = <<<PROMPT
You are a small-business growth advisor specializing in community-based wellness and health businesses.

Business context:
- Type: {$bizType}
- Name: {$bizName}
- Location: {$town}
- Town population: {$pop}
- Nearby organizations:
{$orgList}

Revenue opportunity: {$rule['title']}
Description: {$rule['description']}
Estimated potential: {$rule['revenue_estimate']}/month

Generate a practical, actionable playbook for this specific business to pursue this opportunity. Be concrete and specific to this town size and business type. Return ONLY a valid JSON object (no markdown, no explanation) with exactly these keys:

{
  "who_to_contact": "Specific job title and where to find them (e.g., 'Athletic director at the district office — look up the school district website or call the main office')",
  "what_to_offer": "Concrete program description: format, frequency, duration, group size, and what's included",
  "sample_pricing": "Specific price ranges appropriate for a {$pop}-person market (give 2-3 options if applicable)",
  "outreach_template": "A ready-to-send email subject line and 3-4 sentence pitch body. Use [Business Name] and [Contact Name] as placeholders.",
  "timeline": "When to pitch relative to their budget cycles, school calendars, or fiscal year — be specific",
  "success_signals": ["First concrete milestone", "Second milestone", "Third milestone that confirms it's working"]
}
PROMPT;

    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 900,
        'messages'   => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ]);

    $ch = curl_init(ANTHROPIC_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['content']) || !isset($data['content'][0]['text'])) return null;
    $text = (string)$data['content'][0]['text'];
    if ($text === '') return null;

    // Strip any accidental markdown code fences
    $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
    $text = preg_replace('/\s*```$/m', '', $text);

    $playbook = json_decode(trim($text), true);
    if (!is_array($playbook)) return null;

    return $playbook;
}
