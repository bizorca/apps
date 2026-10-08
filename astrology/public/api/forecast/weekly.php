<?php
/**
 * GET /api/forecast/weekly?week=YYYY-MM-DD
 * Returns Chinese + Western weekly forecasts for the user.
 * Requires premium access.
 */
define('AS_API', true);
require dirname(__DIR__, 2) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';
require AS_ROOT . '/includes/western-astrology.php';

['user' => $user, 'entitlement' => $entitlement] = requireAPIAuth();

if (!apiHasAccess($entitlement)) {
    jsonError('Premium subscription required', 403);
}

$requestedDate = $_GET['week'] ?? null;
if ($requestedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
    $weekStart = date('Y-m-d', strtotime('monday', strtotime($requestedDate)));
} else {
    $weekStart = date('Y-m-d', strtotime('monday this week'));
}

$db = getDB();

// Load profile for animal + western sign
$profileId = $user['primary_profile_id'];
if ($profileId) {
    $stmt = $db->prepare("SELECT zodiac_animal, western_sign, birth_month, birth_day FROM as_profiles WHERE id = ? AND user_id = ?");
    $stmt->execute([$profileId, $user['id']]);
} else {
    $stmt = $db->prepare("SELECT zodiac_animal, western_sign, birth_month, birth_day FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user['id']]);
}
$profile = $stmt->fetch();

if (!$profile) {
    jsonError('No profile found', 404);
}

// Resolve western sign if not stored yet
$westernSign = $profile['western_sign'];
if (!$westernSign && $profile['birth_month'] && $profile['birth_day']) {
    $westernSign = getWesternSign((int)$profile['birth_month'], (int)$profile['birth_day']);
}

// Fetch Chinese forecast
$stmt = $db->prepare("SELECT * FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'chinese' AND zodiac_key = ?");
$stmt->execute([$weekStart, $profile['zodiac_animal']]);
$chinese = $stmt->fetch();

// Fetch Western forecast
$western = null;
if ($westernSign) {
    $stmt = $db->prepare("SELECT * FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'western' AND zodiac_key = ?");
    $stmt->execute([$weekStart, $westernSign]);
    $western = $stmt->fetch();
}

if (!$chinese && !$western) {
    jsonError('No weekly forecast available for ' . $weekStart, 404);
}

$formatForecast = function(?array $row): ?array {
    if (!$row) return null;
    return [
        'id'           => (int) $row['id'],
        'week_start'   => $row['week_start'],
        'forecast_type'=> $row['forecast_type'],
        'zodiac_key'   => $row['zodiac_key'],
        'content_json' => json_decode($row['content_json'], true),
    ];
};

jsonResponse([
    'week_start' => $weekStart,
    'chinese'    => $formatForecast($chinese),
    'western'    => $formatForecast($western),
]);
