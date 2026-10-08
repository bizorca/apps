<?php
/**
 * Claude API Integration
 *
 * Generates astrology readings and forecasts via the Anthropic Messages API.
 */

require_once __DIR__ . '/astrology.php';
require_once __DIR__ . '/western-astrology.php';

function callClaudeAPI(string $systemPrompt, string $userMessage, int $maxTokens = 3000): ?string {
    if (empty(ANTHROPIC_API_KEY)) {
        error_log("Claude API: No API key configured");
        return null;
    }

    $url = 'https://api.anthropic.com/v1/messages';

    $data = [
        'model' => ANTHROPIC_MODEL,
        'max_tokens' => $maxTokens,
        // Single-shot JSON generations: keep thinking off so the whole token
        // budget goes to the reading. Sonnet 5 runs adaptive thinking by
        // default when this field is omitted.
        'thinking' => ['type' => 'disabled'],
        'system' => $systemPrompt,
        'messages' => [
            ['role' => 'user', 'content' => $userMessage]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT => 90,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Claude API curl error: $curlError");
        return null;
    }

    if ($httpCode !== 200) {
        error_log("Claude API error: HTTP $httpCode - $response");
        return null;
    }

    $decoded = json_decode($response, true);
    // Find the first text block — content can also carry thinking blocks
    foreach ($decoded['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') {
            return $block['text'];
        }
    }
    return null;
}

function parseJSONResponse(string $text): ?array {
    // Try direct parse first
    $json = json_decode($text, true);
    if ($json !== null) return $json;

    // Try extracting JSON from markdown code block
    if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
        $json = json_decode($matches[1], true);
        if ($json !== null) return $json;
    }

    // Try finding JSON object in text
    if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
        $json = json_decode($matches[0], true);
        if ($json !== null) return $json;
    }

    error_log("Claude API: Failed to parse JSON response: " . substr($text, 0, 200));
    return null;
}

function formatFourPillarsForPrompt(array $profile): string {
    if (empty($profile['four_pillars']) || $profile['four_pillars']['pillar_count'] <= 1) {
        return '';
    }

    $fp = $profile['four_pillars'];
    $info = "\n\nFour Pillars (Ba Zi) Data ({$fp['pillar_count']} pillars available):";

    $pillarNames = ['year_pillar' => 'Year (Root)', 'month_pillar' => 'Month (Trunk)', 'day_pillar' => 'Day (Flower)', 'hour_pillar' => 'Hour (Fruit)'];
    foreach ($pillarNames as $key => $label) {
        $p = $fp[$key] ?? null;
        if (!$p) continue;
        $info .= sprintf("\n- %s: %s%s (%s %s / %s %s)",
            $label,
            $p['stem']['chinese'], $p['branch']['chinese'],
            $p['stem']['name'], $p['stem']['element'],
            $p['branch']['name'], $p['branch']['animal']
        );
    }

    if (!empty($fp['day_master'])) {
        $dm = $fp['day_master'];
        $info .= "\n- Day Master (日主): {$dm['chinese']} {$dm['name']} ({$dm['element']} {$dm['polarity']})";
    }

    if (!empty($fp['element_balance'])) {
        $bal = $fp['element_balance']['counts'];
        $info .= "\n- Element balance: " . implode(', ', array_map(fn($e, $c) => "$e=$c", array_keys($bal), $bal));
        if (!empty($fp['element_balance']['missing'])) {
            $info .= "\n- Missing elements: " . implode(', ', $fp['element_balance']['missing']);
        }
    }

    if (!empty($fp['li_chun_note'])) {
        $info .= "\n- Note: {$fp['li_chun_note']}";
    }

    return $info;
}

function generateTeaserReading(array $profile): ?array {
    $tcm = getElementTCM($profile['zodiac_element']);
    $animalInfo = getAnimalInfo($profile['zodiac_animal']);
    $fourPillarsInfo = formatFourPillarsForPrompt($profile);

    $systemPrompt = "You are an expert in Chinese astrology (Ba Zi / Four Pillars of Destiny) and Traditional Chinese Medicine (TCM).\nGenerate a brief, engaging teaser reading that gives the user a compelling taste of their zodiac profile.\nReturn ONLY valid JSON with no markdown formatting.\nKeep each section to 2-3 sentences. Be specific and insightful, not generic.\nThe tone should be warm, wise, and intriguing - make them want to learn more.";

    if (!empty($profile['four_pillars']) && $profile['four_pillars']['pillar_count'] >= 3) {
        $systemPrompt .= "\nThe user has provided enough birth data for a Ba Zi (Four Pillars) analysis. Reference the Day Master and element balance naturally in your reading.";
    }

    $userMessage = sprintf(
        "Generate a teaser reading for someone born in %d.\n\nZodiac animal: %s (%s)\nElement: %s (%s)\nHeavenly stem: %s\nEarthly branch: %s\nAnimal traits: %s\nTCM organs: %s\nEmotional tendency: %s%s\n\nReturn JSON with these exact keys:\n- overview: A captivating 2-sentence overview of their zodiac identity\n- personality_glimpse: A brief personality insight that feels personal\n- element_insight: How their element shapes their inner nature\n- health_hint: A brief TCM health connection (tease, don't give full advice)\n- call_to_action: A compelling reason to get their full reading",
        $profile['birth_year'],
        $profile['zodiac_animal'],
        $animalInfo['chinese'] ?? '',
        $profile['zodiac_element'],
        $profile['yin_yang'],
        $profile['heavenly_stem'],
        $profile['earthly_branch'],
        implode(', ', $animalInfo['traits'] ?? []),
        implode(' / ', $tcm['organs'] ?? []),
        $tcm['emotion_negative'] ?? '',
        $fourPillarsInfo
    );

    $response = callClaudeAPI($systemPrompt, $userMessage, 1200);
    if (!$response) return null;

    return parseJSONResponse($response);
}

function generateFullReading(array $profile): ?array {
    $tcm = getElementTCM($profile['zodiac_element']);
    $animalInfo = getAnimalInfo($profile['zodiac_animal']);
    $compat = getCompatibility($profile['zodiac_animal']);
    $yearInfo = getCurrentYearInfo();
    $fourPillarsInfo = formatFourPillarsForPrompt($profile);
    $hasBaZi = !empty($profile['four_pillars']) && $profile['four_pillars']['pillar_count'] >= 3;

    $systemPrompt = "You are an expert in Chinese astrology (Ba Zi / Four Pillars of Destiny) and Traditional Chinese Medicine (TCM).\nGenerate a comprehensive, detailed astrological reading. Return ONLY valid JSON with no markdown formatting.\nBe specific, insightful, and practical. This is a premium reading that should feel deeply personal.\nReference TCM principles naturally. Include actionable advice.";

    if ($hasBaZi) {
        $systemPrompt .= "\nIMPORTANT: This user has full Ba Zi (Four Pillars) data including their Day Master. The Day Master is the central reference point for the entire reading. Analyze the relationships between pillars: generating cycles, controlling cycles, and clashes between the stems and branches. Discuss what their element balance (or imbalance) means for their life and health.";
    } else {
        $systemPrompt .= "\nIf the person was born in January or February, note that their zodiac animal may differ based on the exact Chinese New Year date for their birth year.";
    }

    $birthDetails = "Birth year: {$profile['birth_year']}";
    if (!empty($profile['birth_month'])) $birthDetails .= ", month: {$profile['birth_month']}";
    if (!empty($profile['birth_day'])) $birthDetails .= ", day: {$profile['birth_day']}";
    if (!empty($profile['birth_hour'])) $birthDetails .= ", hour: {$profile['birth_hour']}";

    $baziKeys = '';
    if ($hasBaZi) {
        $baziKeys = "\n- bazi_analysis: Deep analysis of the Four Pillars relationships, Day Master strength, and how the pillars interact with each other (4-5 sentences)\n- element_balance_advice: Practical advice based on which elements are dominant, weak, or missing in their chart, including TCM recommendations to balance their energy (3-4 sentences)";
    }

    $userMessage = sprintf(
        "Generate a full reading.\n\n%s\nZodiac: %s (%s)\nElement: %s (%s)\nHeavenly stem: %s\nEarthly branch: %s\nTraits: %s\nTCM organs: %s / Emotion: %s / Season: %s\nBest match: %s\nHarmonies: %s\nClash: %s\nCurrent year: %d - Year of the %s (%s)%s\n\nReturn JSON with these exact keys:\n- overview: 3-4 sentence overview of their complete zodiac identity\n- personality: Detailed personality analysis (4-5 sentences)\n- element_analysis: Deep dive into their element and how it shapes them (4-5 sentences)\n- tcm_health: TCM health profile with organ connections, dietary advice, and seasonal guidance (5-6 sentences)\n- relationships: Relationship tendencies and compatibility insights (4-5 sentences)\n- career: Career strengths and ideal work environments (3-4 sentences)\n- life_path: Life purpose and spiritual growth direction (3-4 sentences)\n- current_year: How the current year of the %s affects them specifically (3-4 sentences)\n- seasonal_guidance: Practical wellness tips for each season based on their element (object with keys: spring, summer, autumn, winter - each 2 sentences)%s",
        $birthDetails,
        $profile['zodiac_animal'],
        $animalInfo['chinese'] ?? '',
        $profile['zodiac_element'],
        $profile['yin_yang'],
        $profile['heavenly_stem'],
        $profile['earthly_branch'],
        implode(', ', $animalInfo['traits'] ?? []),
        implode(' / ', $tcm['organs'] ?? []),
        $tcm['emotion_negative'] ?? '',
        $tcm['season'] ?? '',
        $compat['best_match'],
        implode(', ', $compat['harmonies']),
        $compat['clash'],
        $yearInfo['year'],
        $yearInfo['animal'],
        $yearInfo['element'],
        $fourPillarsInfo,
        $yearInfo['animal'],
        $baziKeys
    );

    $maxTokens = $hasBaZi ? 6000 : 4500;
    $response = callClaudeAPI($systemPrompt, $userMessage, $maxTokens);
    if (!$response) return null;

    return parseJSONResponse($response);
}

function generateWesternTeaserReading(array $profile, array $signInfo): ?array {
    $systemPrompt = "You are an expert in Western astrology with deep knowledge of sun signs, elements, modalities, and planetary rulerships.\nGenerate a brief, engaging teaser reading that gives the user a compelling taste of their Western zodiac profile.\nReturn ONLY valid JSON with no markdown formatting.\nKeep each section to 2-3 sentences. Be specific and insightful, not generic.\nThe tone should be warm, direct, and intriguing — make them want to learn more.";

    $chineseContext = '';
    if (!empty($profile['zodiac_animal'])) {
        $chineseContext = sprintf("\n\nThis person is also a %s %s in Chinese astrology (%s element). You may briefly note interesting resonances or contrasts between the two systems.",
            $profile['zodiac_element'] ?? '',
            $profile['zodiac_animal'] ?? '',
            $profile['yin_yang'] ?? ''
        );
    }

    $userMessage = sprintf(
        "Generate a Western astrology teaser reading for a %s (born %s).\n\nSign: %s\nElement: %s\nModality: %s\nRuling planet: %s\nTraits: %s\nStrengths: %s\nChallenges: %s\nBody association: %s%s\n\nReturn JSON with these exact keys:\n- overview: A captivating 2-sentence overview of their sun sign identity\n- personality_glimpse: A brief personality insight that feels personal and specific\n- element_insight: How their %s element shapes their inner nature and approach to life\n- planet_insight: What being ruled by %s means for how they move through the world\n- call_to_action: A compelling reason to get their full Western reading",
        $signInfo['symbol'] ?? $profile['western_sign'],
        $signInfo['dates'] ?? '',
        $profile['western_sign'],
        $signInfo['element'] ?? '',
        $signInfo['modality'] ?? '',
        $signInfo['ruling_planet'] ?? '',
        implode(', ', $signInfo['traits'] ?? []),
        $signInfo['strengths'] ?? '',
        $signInfo['challenges'] ?? '',
        $signInfo['body_part'] ?? '',
        $chineseContext,
        $signInfo['element'] ?? '',
        $signInfo['ruling_planet'] ?? ''
    );

    $response = callClaudeAPI($systemPrompt, $userMessage, 1400);
    if (!$response) return null;

    return parseJSONResponse($response);
}

function generateWesternFullReading(array $profile, array $signInfo): ?array {
    $compat = getWesternCompatibility($profile['western_sign']);

    $systemPrompt = "You are an expert Western astrologer with deep knowledge of sun signs, elements, modalities, planetary influences, and astrological aspects.\nGenerate a comprehensive, detailed sun sign reading. Return ONLY valid JSON with no markdown formatting.\nBe specific, insightful, and practical. This is a premium reading that should feel deeply personal.\nDraw on the full richness of Western astrological tradition.";

    $chineseContext = '';
    if (!empty($profile['zodiac_animal'])) {
        $chineseContext = sprintf("\n\nThis person is also a %s %s in Chinese astrology (%s %s element). Include a section synthesizing both traditions — where they align and where they create interesting tension.",
            $profile['zodiac_element'] ?? '',
            $profile['zodiac_animal'] ?? '',
            $profile['yin_yang'] ?? '',
            $profile['zodiac_element'] ?? ''
        );
    }

    $birthDetails = "Birth year: {$profile['birth_year']}";
    if (!empty($profile['birth_month'])) $birthDetails .= ", month: {$profile['birth_month']}";
    if (!empty($profile['birth_day'])) $birthDetails .= ", day: {$profile['birth_day']}";

    $userMessage = sprintf(
        "Generate a full Western astrology reading.\n\n%s\nSun sign: %s (%s)\nElement: %s\nModality: %s\nRuling planet: %s\nSymbol: The %s\nKeywords: %s\nTraits: %s\nBody/health: %s\nBest matches (trine): %s\nGood matches (sextile): %s\nOpposition: %s\nChallenging (square): %s%s\n\nReturn JSON with these exact keys:\n- overview: 3-4 sentence overview of their complete sun sign identity\n- personality: Detailed personality analysis covering core drives, emotional style, and social patterns (4-5 sentences)\n- element_analysis: Deep dive into how their %s element shapes their worldview, decision-making, and inner life (4-5 sentences)\n- modality_insight: How being a %s sign manifests in how they initiate, sustain, or adapt in life (3-4 sentences)\n- planetary_influence: How %s as their ruling planet colors their personality and life themes (3-4 sentences)\n- relationships: Relationship tendencies, what they need from partners, and compatibility insights (4-5 sentences)\n- career: Career strengths, ideal work environments, and professional blind spots (3-4 sentences)\n- health_wellness: Health associations for their sign and body rulership, with practical wellness guidance (3-4 sentences)\n- life_path: Spiritual growth direction and soul-level lessons for this sign (3-4 sentences)\n- seasonal_guidance: How each season affects this sign (object with keys: spring, summer, autumn, winter — each 1-2 sentences)%s",
        $birthDetails,
        $profile['western_sign'],
        $signInfo['dates'] ?? '',
        $signInfo['element'] ?? '',
        $signInfo['modality'] ?? '',
        $signInfo['ruling_planet'] ?? '',
        $signInfo['symbol'] ?? '',
        implode(', ', $signInfo['keywords'] ?? []),
        implode(', ', $signInfo['traits'] ?? []),
        $signInfo['body_part'] ?? '',
        implode(', ', $compat['best_matches']),
        implode(', ', $compat['good_matches']),
        $compat['opposition'],
        implode(', ', $compat['challenging']),
        $chineseContext,
        $signInfo['element'] ?? '',
        $signInfo['modality'] ?? '',
        $signInfo['ruling_planet'] ?? '',
        !empty($profile['zodiac_animal']) ? "\n- east_west_synthesis: How their Chinese and Western signs interact — resonances, contrasts, and what the combination reveals (3-4 sentences)" : ''
    );

    $response = callClaudeAPI($systemPrompt, $userMessage, 6000);
    if (!$response) return null;

    return parseJSONResponse($response);
}

function generateMonthlyForecast(string $animal, string $monthLabel, string $monthDate): ?array {
    $animalInfo = getAnimalInfo($animal);
    $yearInfo = getCurrentYearInfo();

    $systemPrompt = <<<PROMPT
You are an expert in Chinese astrology and TCM. Generate a monthly forecast for the given zodiac animal.
Return ONLY valid JSON with no markdown formatting.
Be specific to the month and practical. Include actionable advice grounded in TCM principles.
Reference how the current year's energy interacts with this animal sign.
PROMPT;

    $userMessage = sprintf(
        "Generate a monthly forecast for the %s for %s.\n\nCurrent year: %d - Year of the %s (%s)\nAnimal traits: %s\n\nReturn JSON with these exact keys:\n- general_overview: Overall energy and theme for this month (3-4 sentences)\n- career: Work and financial outlook (2-3 sentences)\n- relationships: Love and social connections (2-3 sentences)\n- health: TCM health guidance for this month (2-3 sentences)\n- element_overlays: Object with keys wood, fire, earth, metal, water - each a paragraph of how this month specifically affects people of that element who are %s signs\n- lucky_days: Array of 3-5 specific date descriptions (e.g., \"Around the 5th - good for new beginnings\")\n- meditation_focus: One sentence suggesting a meditation or mindfulness focus for the month",
        $animal,
        $monthLabel,
        $yearInfo['year'],
        $yearInfo['animal'],
        $yearInfo['element'],
        implode(', ', $animalInfo['traits'] ?? []),
        $animal
    );

    $response = callClaudeAPI($systemPrompt, $userMessage, 4000);
    if (!$response) return null;

    return parseJSONResponse($response);
}

function generateWeeklyChineseForecast(string $animal, string $weekLabel, string $weekStart): ?array {
    $animalInfo = getAnimalInfo($animal);
    $yearInfo = getCurrentYearInfo();

    $systemPrompt = <<<PROMPT
You are an expert in Chinese astrology and TCM. Generate a weekly forecast for the given zodiac animal.
Return ONLY valid JSON with no markdown formatting.
Be specific to the week — not generic monthly advice rephrased. Weekly energy moves fast; reflect that.
Include actionable TCM and elemental guidance grounded in the week's particular energy.
PROMPT;

    $userMessage = sprintf(
        "Generate a weekly forecast for the %s for the week of %s.\n\nCurrent year: %d - Year of the %s (%s)\nAnimal traits: %s\n\nReturn JSON with these exact keys:\n- overview: The dominant energy and theme for this week (3-4 sentences)\n- career: Work and financial guidance for the week (2-3 sentences)\n- relationships: Love, friendship, and social dynamics (2-3 sentences)\n- health: TCM health and body guidance specific to this week's energy (2-3 sentences)\n- element_overlays: Object with keys wood, fire, earth, metal, water — each a paragraph on how this week specifically affects people of that element who are %s signs\n- key_days: Array of 3-4 specific day callouts (e.g., \"Tuesday — favorable for negotiations\", \"Thursday — rest and restore\")\n- intention: One sentence: a focused intention or mantra for the week",
        $animal,
        $weekLabel,
        $yearInfo['year'],
        $yearInfo['animal'],
        $yearInfo['element'],
        implode(', ', $animalInfo['traits'] ?? []),
        $animal
    );

    $response = callClaudeAPI($systemPrompt, $userMessage, 4000);
    if (!$response) return null;

    return parseJSONResponse($response);
}

function generateWeeklyWesternForecast(string $sign, string $weekLabel, string $weekStart): ?array {
    $signs = getAllWesternSigns();
    $signInfo = $signs[$sign] ?? [];

    $systemPrompt = <<<PROMPT
You are an expert Western astrologer. Generate a weekly sun sign forecast.
Return ONLY valid JSON with no markdown formatting.
Make it specific to this particular week — not generic sign advice. Speak to transits, momentum, and the rhythm of the week.
Be practical and direct. Avoid vague spiritual fluff.
PROMPT;

    $userMessage = sprintf(
        "Generate a weekly Western astrology forecast for %s for the week of %s.\n\nElement: %s\nModality: %s\nRuling planet: %s\nTraits: %s\n\nReturn JSON with these exact keys:\n- overview: The week's overall energy and dominant theme for this sign (3-4 sentences)\n- love: Romantic and relational dynamics this week (2-3 sentences)\n- career: Work, ambition, and financial energy for the week (2-3 sentences)\n- wellness: Physical and emotional well-being guidance (2 sentences)\n- key_days: Array of 3-4 specific day callouts (e.g., \"Monday — lead with confidence\", \"Friday — a good day for creative work\")\n- intention: One sentence: a focused intention or mantra for the week",
        $sign,
        $weekLabel,
        $signInfo['element'] ?? '',
        $signInfo['modality'] ?? '',
        $signInfo['ruling_planet'] ?? '',
        implode(', ', $signInfo['traits'] ?? [])
    );

    $response = callClaudeAPI($systemPrompt, $userMessage, 3000);
    if (!$response) return null;

    return parseJSONResponse($response);
}

function generateStarseedReading(array $result, array $starseeds, ?array $chineseProfile = null, ?string $westernSign = null): ?array {
    $primary = $starseeds[$result['primary_lineage']];
    $secondary = $starseeds[$result['secondary_lineage']];

    $systemPrompt = <<<PROMPT
You are a scholar of cosmic metaphysics and stellar consciousness, deeply versed in the lore and spiritual framework of star seed origins.
Star seeds are souls believed to originate from advanced star systems who incarnate on Earth carrying specific missions, gifts, and soul-level memories.
Generate a deeply personal, specific reading. Return ONLY valid JSON with no markdown formatting.
Be insightful, grounded, and treat the spiritual framework with full seriousness. Avoid vague generalities — speak directly to the specifics of this person's lineage combination.
PROMPT;

    $chineseContext = '';
    if (!empty($chineseProfile['zodiac_animal'])) {
        $chineseContext = sprintf(
            "\n\nChinese Astrology: %s %s (%s element) — reference this briefly where it deepens the reading, particularly in life mission and elemental themes.",
            $chineseProfile['zodiac_element'] ?? '',
            $chineseProfile['zodiac_animal'] ?? '',
            $chineseProfile['yin_yang'] ?? ''
        );
    }
    if (!empty($westernSign)) {
        $chineseContext .= "\nWestern Sign: {$westernSign} — note resonances with their star seed nature where relevant.";
    }

    $userMessage = sprintf(
        "Generate a full star seed lineage reading.\n\nPrimary Lineage: %s\nOrigin: %s (%s light-years, %s constellation)\nCore mission: %s\nPrimary traits: %s\nSpiritual gifts: %s\nLife challenges: %s\n\nSecondary Lineage: %s\nOrigin: %s\nCore mission: %s%s\n\nReturn JSON with these exact keys:\n- overview: 3-4 sentences introducing this person's cosmic origin story and why this combination of lineages is significant\n- primary_lineage_reading: Deep exploration of their primary lineage — what they carry from this star system, how it shows up in their personality and soul patterns, what gifts they came to anchor on Earth (5-6 sentences)\n- secondary_lineage_reading: How the secondary lineage operates as a complementary force — the energy it adds, the tension or harmony it creates with the primary, and what it contributes to the overall mission (3-4 sentences)\n- mission: Their specific Earth mission based on this lineage combination — what they're here to do, build, heal, or transmit (3-4 sentences)\n- gifts_to_develop: The spiritual and practical gifts most important for them to cultivate in this lifetime, specific to their lineages (3-4 sentences)\n- core_challenges: The shadow patterns and recurring obstacles that arise from their lineage combination, and how to work with them rather than against them (3-4 sentences)\n- relationships: How their lineage combination shapes their approach to intimacy, chosen family, and soul contracts (3-4 sentences)\n- activation_practices: Specific practices, environments, or experiences that help them access their star seed gifts — drawn from their lineage traditions (3-4 sentences)\n- message: A direct, personal transmission — as if their star lineage is speaking to them through this reading. Powerful, specific, brief (3 sentences max)",
        $primary['name'],
        $primary['star_system'],
        $primary['distance_ly'],
        $primary['constellation'],
        $primary['core_mission'],
        implode(', ', array_slice($primary['traits'], 0, 5)),
        implode(', ', array_slice($primary['gifts'], 0, 4)),
        implode(', ', array_slice($primary['challenges'], 0, 3)),
        $secondary['name'],
        $secondary['star_system'],
        $secondary['core_mission'],
        $chineseContext
    );

    $response = callClaudeAPI($systemPrompt, $userMessage, 5000);
    if (!$response) return null;

    return parseJSONResponse($response);
}
