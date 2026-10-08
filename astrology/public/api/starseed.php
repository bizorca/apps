<?php
/**
 * GET /api/starseed
 * Returns the user's most recent star seed result and reading.
 */
define('AS_API', true);
require dirname(__DIR__) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';

['user' => $user] = requireAPIAuth();

$db = getDB();

$stmt = $db->prepare("SELECT * FROM as_starseed_results WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user['id']]);
$result = $stmt->fetch();

if (!$result) {
    jsonError('No star seed result found', 404);
}

jsonResponse([
    'id'                => (int) $result['id'],
    'user_id'           => (int) $result['user_id'],
    'primary_lineage'   => $result['primary_lineage'],
    'secondary_lineage' => $result['secondary_lineage'],
    'scores_json'       => json_decode($result['scores_json'], true),
    'reading_json'      => $result['reading_json'] ? json_decode($result['reading_json'], true) : null,
    'created_at'        => $result['created_at'],
]);
