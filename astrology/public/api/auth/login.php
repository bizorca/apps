<?php
/**
 * POST /astrology/api/auth/login.php
 *
 * Email + password login for the Meridian iOS app, against the shared
 * tools.bizorca.com account. Issues a bearer token; sets no cookie.
 *
 * Body (JSON): { "email": "...", "password": "..." }
 * Response:    { "token": "...", "user": {...} }
 */
define('AS_API', true);
require dirname(__DIR__, 2) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$body     = json_decode((string) file_get_contents('php://input'), true) ?: [];
$email    = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');   // not trimmed: the web never trimmed it either

if ($email === '' || $password === '') {
    jsonError('Email and password are required');
}

$stmt = getDB()->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

// One message either way: the API should not reveal which addresses exist.
if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonError('Invalid email or password. No password yet? Set one at ' . APP_URL . '/account/forgot.php', 401);
}

$token = issueAPIToken((int) $user['id']);

$m = getDB()->prepare("SELECT primary_profile_id FROM as_members WHERE user_id = ?");
$m->execute([$user['id']]);
$ppid = $m->fetchColumn();

jsonResponse([
    'token' => $token,
    'user'  => [
        'id'                 => (int) $user['id'],
        'name'               => $user['name'],
        'email'              => $user['email'],
        'plan'               => getEffectivePlan((int) $user['id']),
        'primary_profile_id' => $ppid ? (int) $ppid : null,
    ],
]);
