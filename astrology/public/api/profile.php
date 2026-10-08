<?php
/**
 * GET  /api/profile  — fetch primary profile
 * POST /api/profile  — create or update primary profile
 */
define('AS_API', true);
require dirname(__DIR__) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';
require AS_ROOT . '/includes/astrology.php';
require AS_ROOT . '/includes/western-astrology.php';

['user' => $user] = requireAPIAuth();
$db = getDB();

// ── POST: create / update ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body       = json_decode(file_get_contents('php://input'), true);
    $birthYear  = isset($body['birth_year'])  ? (int)$body['birth_year']  : null;
    $birthMonth = isset($body['birth_month']) ? (int)$body['birth_month'] : null;
    $birthDay   = isset($body['birth_day'])   ? (int)$body['birth_day']   : null;
    $birthHour  = isset($body['birth_hour'])  ? (int)$body['birth_hour']  : null;

    if (!$birthYear || $birthYear < 1920 || $birthYear > (int)date('Y')) {
        jsonError('A valid birth year is required.');
    }

    // Calculate Chinese zodiac
    // getHeavenlyStem/getEarthlyBranch return arrays — format as "Name (Chinese)"
    // to match what calculateProfile() and the web app store in the DB.
    $animal      = getZodiacAnimal($birthYear);
    $element     = getElement($birthYear);
    $yinYang     = getYinYang($birthYear);
    $stemArr     = getHeavenlyStem($birthYear);
    $branchArr   = getEarthlyBranch($birthYear);
    $stem        = $stemArr['name']   . ' (' . $stemArr['chinese']   . ')';
    $branch      = $branchArr['name'] . ' (' . $branchArr['chinese'] . ')';

    // Calculate Western sign if we have enough data
    $westernSign    = null;
    $westernElement = null;
    if ($birthMonth && $birthDay) {
        $westernSign    = getWesternSign($birthMonth, $birthDay);
        $westernElement = getWesternElement($westernSign);
    }

    // Calculate Four Pillars
    $fourPillars = calculateFourPillars($birthYear, $birthMonth, $birthDay, $birthHour);

    // Find existing profile
    $profileId = $user['primary_profile_id'];
    if ($profileId) {
        $stmt = $db->prepare("SELECT id FROM as_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$profileId, $user['id']]);
        $existing = $stmt->fetch();
    } else {
        $stmt = $db->prepare("SELECT id FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $existing = $stmt->fetch();
    }

    if ($existing) {
        // Update
        $db->prepare("
            UPDATE as_profiles SET
                birth_year = ?, birth_month = ?, birth_day = ?, birth_hour = ?,
                zodiac_animal = ?, zodiac_element = ?, yin_yang = ?,
                heavenly_stem = ?, earthly_branch = ?,
                western_sign = ?, western_element = ?,
                four_pillars_json = ?
            WHERE id = ?
        ")->execute([
            $birthYear, $birthMonth ?: null, $birthDay ?: null, $birthHour ?: null,
            $animal, $element, $yinYang, $stem, $branch,
            $westernSign, $westernElement,
            json_encode($fourPillars),
            $existing['id'],
        ]);
        $profileId = $existing['id'];
    } else {
        // Create
        $db->prepare("
            INSERT INTO as_profiles
                (user_id, birth_year, birth_month, birth_day, birth_hour,
                 zodiac_animal, zodiac_element, yin_yang, heavenly_stem, earthly_branch,
                 western_sign, western_element, four_pillars_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $user['id'],
            $birthYear, $birthMonth ?: null, $birthDay ?: null, $birthHour ?: null,
            $animal, $element, $yinYang, $stem, $branch,
            $westernSign, $westernElement,
            json_encode($fourPillars),
        ]);
        $profileId = (int)$db->lastInsertId();

        // Set as primary profile
        setPrimaryProfile((int) $user['id'], $profileId);
    }

    jsonResponse([
        'id'             => (int)$profileId,
        'user_id'        => (int)$user['id'],
        'birth_year'     => $birthYear,
        'birth_month'    => $birthMonth ?: null,
        'birth_day'      => $birthDay   ?: null,
        'birth_hour'     => $birthHour  ?: null,
        'zodiac_animal'  => $animal,
        'zodiac_element' => $element,
        'yin_yang'       => $yinYang,
        'heavenly_stem'  => $stem,
        'earthly_branch' => $branch,
        'western_sign'   => $westernSign,
        'western_element'=> $westernElement,
        'four_pillars'   => $fourPillars,
    ]);
}

// ── GET: fetch ───────────────────────────────────────────────────────────────
$profileId = $user['primary_profile_id'];
if ($profileId) {
    $stmt = $db->prepare("SELECT * FROM as_profiles WHERE id = ? AND user_id = ?");
    $stmt->execute([$profileId, $user['id']]);
} else {
    $stmt = $db->prepare("SELECT * FROM as_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user['id']]);
}
$profile = $stmt->fetch();

if (!$profile) {
    jsonError('No profile found', 404);
}

// Backfill western sign if needed
if (!$profile['western_sign'] && $profile['birth_month'] && $profile['birth_day']) {
    $westernSign    = getWesternSign((int)$profile['birth_month'], (int)$profile['birth_day']);
    $westernElement = getWesternElement($westernSign);
    $db->prepare("UPDATE as_profiles SET western_sign = ?, western_element = ? WHERE id = ?")
       ->execute([$westernSign, $westernElement, $profile['id']]);
    $profile['western_sign']    = $westernSign;
    $profile['western_element'] = $westernElement;
}

// Backfill four pillars if needed
$fourPillars = !empty($profile['four_pillars_json']) ? json_decode($profile['four_pillars_json'], true) : null;
if (!$fourPillars) {
    $fourPillars = calculateFourPillars(
        (int)$profile['birth_year'],
        $profile['birth_month'] !== null ? (int)$profile['birth_month'] : null,
        $profile['birth_day']   !== null ? (int)$profile['birth_day']   : null,
        $profile['birth_hour']  !== null ? (int)$profile['birth_hour']  : null
    );
    $db->prepare("UPDATE as_profiles SET four_pillars_json = ? WHERE id = ?")
       ->execute([json_encode($fourPillars), $profile['id']]);
}

jsonResponse([
    'id'             => (int)$profile['id'],
    'user_id'        => (int)$profile['user_id'],
    'birth_year'     => (int)$profile['birth_year'],
    'birth_month'    => $profile['birth_month']    ? (int)$profile['birth_month']    : null,
    'birth_day'      => $profile['birth_day']      ? (int)$profile['birth_day']      : null,
    'birth_hour'     => $profile['birth_hour']     ? (int)$profile['birth_hour']     : null,
    'zodiac_animal'  => $profile['zodiac_animal'],
    'zodiac_element' => $profile['zodiac_element'],
    'yin_yang'       => $profile['yin_yang'],
    'heavenly_stem'  => $profile['heavenly_stem'],
    'earthly_branch' => $profile['earthly_branch'],
    'western_sign'   => $profile['western_sign'],
    'western_element'=> $profile['western_element'],
    'four_pillars'   => $fourPillars,
]);
