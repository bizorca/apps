<?php

declare(strict_types=1);

/**
 * API sign-up and sign-in for the iOS app, against the shared tools account.
 *
 * Request and response shapes are the original's: register takes first_name,
 * last_name, email, password, age and returns {token, user_id}; login takes
 * email, password and returns {token, user_id}. user_id is the profile's
 * public_id (a UUID string), as before.
 *
 * What changed underneath: people are rows in the shared `users` table, and
 * "being a Placecard member" is having a pc_profiles row.
 *   - register with a brand-new email creates the shared account and profile
 *   - register with an email that already has a tools account (from another
 *     tool) and the right password joins that account to Placecard; a wrong
 *     password, or an existing Placecard member, gets the original 409
 *   - login works for Placecard members only; a tools account that has not
 *     joined gets a 403 telling them to sign up with the same credentials
 */
class AuthController
{
    private PDO $db;
    private Auth $auth;

    public function __construct(PDO $db, Auth $auth)
    {
        $this->db   = $db;
        $this->auth = $auth;
    }

    // POST /api/auth/register
    public function register(): never
    {
        $body = PcRequest::json();

        $firstName = trim((string) ($body['first_name'] ?? ''));
        $lastName  = trim((string) ($body['last_name']  ?? ''));
        $email     = strtolower(trim((string) ($body['email'] ?? '')));
        $password  = (string) ($body['password'] ?? '');
        $age       = (int) ($body['age'] ?? 0);

        $errors = [];
        if ($firstName === '')                          $errors[] = 'first_name is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'valid email is required';
        if (strlen($password) < 8)                      $errors[] = 'password must be at least 8 characters';
        if ($age < 18)                                  $errors[] = 'you must be 18 or older';

        if ($errors) {
            Response::error('Validation failed', 422, $errors);
        }

        $stmt = $this->db->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            // An existing tools account may join Placecard by proving it owns
            // the password. Anything else is the original duplicate-email 409.
            if (Profile::exists((int) $existing['id']) || !password_verify($password, $existing['password_hash'])) {
                Response::error('An account with that email already exists', 409);
            }
            $userId = (int) $existing['id'];
        } else {
            $this->db->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)')
                     ->execute([$email, password_hash($password, PASSWORD_DEFAULT), trim("$firstName $lastName")]);
            $userId = (int) $this->db->lastInsertId();
        }

        $publicId = Profile::create($userId, $firstName, $lastName, $age);
        $token    = Auth::issueToken($userId);
        Response::success(['token' => $token, 'user_id' => $publicId], 'Account created', 201);
    }

    // POST /api/auth/login
    public function login(): never
    {
        $body     = PcRequest::json();
        $email    = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        $stmt = $this->db->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Constant-time failure regardless of whether user exists
        $hash  = $user['password_hash'] ?? '$2y$12$invalidhashpadding00000000000000000000000000000000000';
        $valid = password_verify($password, $hash) && $user;

        if (!$valid) {
            Response::error('Invalid email or password', 401);
        }

        $userId = (int) $user['id'];
        if (!Profile::exists($userId)) {
            Response::error('This email has a Bizorca Tools account that has not joined Placecard yet. Sign up with the same email and password to join.', 403);
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                     ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        }

        $token = Auth::issueToken($userId);
        Response::success(['token' => $token, 'user_id' => Profile::publicId($userId)]);
    }
}
