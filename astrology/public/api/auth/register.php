<?php
/**
 * POST /astrology/api/auth/register.php
 *
 * Create a shared tools.bizorca.com account from the Meridian iOS app. Same
 * rules as /account/register.php (10+ character password); bearer token, no
 * cookie.
 *
 * Body (JSON): { "name": "...", "email": "...", "password": "..." }
 * Response:    { "token": "...", "user": {...} }
 */
define('AS_API', true);
require dirname(__DIR__, 2) . '/_bootstrap.php';
require AS_ROOT . '/includes/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$body     = json_decode((string) file_get_contents('php://input'), true) ?: [];
$name     = trim((string) ($body['name'] ?? ''));
$email    = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

if ($name === '' || $email === '' || $password === '') {
    jsonError('Name, email, and password are required');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Please enter a valid email address');
}
if (strlen($password) < 10) {
    jsonError('Password must be at least 10 characters');
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jsonError('An account with that email already exists', 409);
}

$db->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)")
   ->execute([mb_substr($name, 0, 100), $email, password_hash($password, PASSWORD_DEFAULT)]);
$userId = (int) $db->lastInsertId();

$token = issueAPIToken($userId);

jsonResponse([
    'token' => $token,
    'user'  => [
        'id'                 => $userId,
        'name'               => $name,
        'email'              => $email,
        'plan'               => getEffectivePlan($userId),
        'primary_profile_id' => null,
    ],
]);
