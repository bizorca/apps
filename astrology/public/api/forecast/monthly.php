<?php
/**
 * GET /api/forecast/monthly?month=YYYY-MM
 * Returns the monthly forecast for the user's zodiac animal.
 * Requires premium access.
 */
define('AS_API', true);
require dirname(__DIR__, 2) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';

['user' => $user, 'entitlement' => $entitlement] = requireAPIAuth();

if (!apiHasAccess($entitlement)) {
    jsonError('Premium subscription required', 403);
}

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthDate = $month . '-01';

$db = getDB();

// Get user's animal
$profileId = $user['primary_profile_id'];
if ($profileId) {
    $stmt = $db->prepare("SELECT zodiac_animal FROM as_profiles WHERE id = ? AND user_id = ?");
    $stmt->execute([$profileId, $user['id']]);
} else {
    $stmt = $db->prepare("SELECT zodiac_animal FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user['id']]);
}
$profile = $stmt->fetch();

if (!$profile) {
    jsonError('No profile found', 404);
}

$stmt = $db->prepare("SELECT * FROM as_forecasts WHERE forecast_month = ? AND zodiac_animal = ?");
$stmt->execute([$monthDate, $profile['zodiac_animal']]);
$forecast = $stmt->fetch();

if (!$forecast) {
    jsonError('No forecast available for ' . date('F Y', strtotime($monthDate)), 404);
}

jsonResponse([
    'id'             => (int) $forecast['id'],
    'forecast_month' => $forecast['forecast_month'],
    'zodiac_animal'  => $forecast['zodiac_animal'],
    'content_json'   => json_decode($forecast['content_json'], true),
]);
