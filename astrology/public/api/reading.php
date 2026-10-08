<?php
/**
 * GET /api/reading?type=teaser|full|western_teaser|western_full
 * Returns the user's reading for the given type.
 * Full readings require premium access.
 */
define('AS_API', true);
require dirname(__DIR__) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';

['user' => $user, 'entitlement' => $entitlement] = requireAPIAuth();

$type = $_GET['type'] ?? 'teaser';
$allowed = ['teaser', 'full', 'western_teaser', 'western_full'];
if (!in_array($type, $allowed, true)) {
    jsonError('Invalid reading type');
}

// Full readings are premium only
if (in_array($type, ['full', 'western_full'], true) && !apiHasAccess($entitlement)) {
    jsonError('Premium subscription required', 403);
}

$db = getDB();

// Get user's profile id
$profileId = $user['primary_profile_id'];
if (!$profileId) {
    $stmt = $db->prepare("SELECT id FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    $profileId = $row ? $row['id'] : null;
}

if (!$profileId) {
    jsonError('No profile found', 404);
}

$stmt = $db->prepare("SELECT * FROM as_readings WHERE user_id = ? AND profile_id = ? AND reading_type = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user['id'], $profileId, $type]);
$reading = $stmt->fetch();

if (!$reading) {
    jsonError('No reading found for this type', 404);
}

$content = json_decode($reading['content_json'], true);

jsonResponse([
    'id'           => (int) $reading['id'],
    'type'         => $reading['reading_type'],
    'content'      => $content,
    'generated_at' => $reading['created_at'],
]);
