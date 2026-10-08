<?php
/**
 * POST /api/forecast/generate
 *
 * Checks whether this week's Chinese + Western forecasts exist for the
 * authenticated user's zodiac animal and Western sign. Generates any that
 * are missing. Premium only. Safe to call repeatedly — skips existing rows.
 *
 * Response: { "week_start": "...", "generated": ["chinese", "western"] }
 */
define('AS_API', true);
require dirname(__DIR__, 2) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';
require AS_ROOT . '/includes/astrology.php';
require AS_ROOT . '/includes/western-astrology.php';
require AS_ROOT . '/includes/claude-api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

['user' => $user, 'entitlement' => $entitlement] = requireAPIAuth();

if (!apiHasAccess($entitlement)) {
    jsonError('Premium required', 403);
}

$db = getDB();

$stmt = $db->prepare("SELECT zodiac_animal, western_sign FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    jsonError('No profile found', 404);
}

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$weekLabel = date('M j', strtotime($weekStart)) . ' – ' . date('M j, Y', strtotime($weekEnd));
$generated = [];

// Chinese
$stmt = $db->prepare("SELECT id FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'chinese' AND zodiac_key = ?");
$stmt->execute([$weekStart, $profile['zodiac_animal']]);
if (!$stmt->fetch()) {
    $content = generateWeeklyChineseForecast($profile['zodiac_animal'], $weekLabel, $weekStart);
    if ($content) {
        $db->prepare("INSERT INTO as_weekly_forecasts (week_start, forecast_type, zodiac_key, content_json) VALUES (?, 'chinese', ?, ?)")
           ->execute([$weekStart, $profile['zodiac_animal'], json_encode($content)]);
        $generated[] = 'chinese';
    }
}

// Western
if (!empty($profile['western_sign'])) {
    $stmt = $db->prepare("SELECT id FROM as_weekly_forecasts WHERE week_start = ? AND forecast_type = 'western' AND zodiac_key = ?");
    $stmt->execute([$weekStart, $profile['western_sign']]);
    if (!$stmt->fetch()) {
        $content = generateWeeklyWesternForecast($profile['western_sign'], $weekLabel, $weekStart);
        if ($content) {
            $db->prepare("INSERT INTO as_weekly_forecasts (week_start, forecast_type, zodiac_key, content_json) VALUES (?, 'western', ?, ?)")
               ->execute([$weekStart, $profile['western_sign'], json_encode($content)]);
            $generated[] = 'western';
        }
    }
}

jsonResponse([
    'week_start' => $weekStart,
    'generated'  => $generated,
]);
