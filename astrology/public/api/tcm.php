<?php
/**
 * GET /api/tcm
 *
 * Returns the TCM (Traditional Chinese Medicine) profile for the authenticated
 * user's zodiac element, plus the current season and any seasonal guidance
 * from their saved full reading.
 */
define('AS_API', true);
require dirname(__DIR__) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';
require AS_ROOT . '/includes/astrology.php';

['user' => $user] = requireAPIAuth();

$db = getDB();

$stmt = $db->prepare("SELECT id, zodiac_element FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    jsonError('No profile found', 404);
}

$tcm = getElementTCM($profile['zodiac_element']);

// Current calendar season
$month = (int) date('n');
if ($month >= 3 && $month <= 5)       $currentSeason = 'spring';
elseif ($month >= 6 && $month <= 8)   $currentSeason = 'summer';
elseif ($month >= 9 && $month <= 11)  $currentSeason = 'autumn';
else                                   $currentSeason = 'winter';

// Pull seasonal guidance for the current season from the user's full reading
$seasonalGuidance = null;
$stmt = $db->prepare("SELECT content_json FROM as_readings WHERE user_id = ? AND reading_type = 'full' ORDER BY id DESC LIMIT 1");
$stmt->execute([$user['id']]);
$readingRow = $stmt->fetch();
if ($readingRow) {
    $content = json_decode($readingRow['content_json'], true);
    $seasonalGuidance = $content['seasonal_guidance'][$currentSeason] ?? null;
}

jsonResponse([
    'element'           => $profile['zodiac_element'],
    'season'            => $tcm['season']            ?? '',
    'organs'            => $tcm['organs']            ?? [],
    'emotion_positive'  => $tcm['emotion_positive']  ?? '',
    'emotion_negative'  => $tcm['emotion_negative']  ?? '',
    'foods'             => $tcm['foods']             ?? [],
    'health_focus'      => $tcm['health_focus']      ?? '',
    'current_season'    => $currentSeason,
    'seasonal_guidance' => $seasonalGuidance,
]);
