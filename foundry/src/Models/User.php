<?php

namespace Bizorca\Consulting\Models;

use Bizorca\Consulting\Core\Database;

/**
 * Foundry's people are shared tools accounts (the `users` table). Foundry's
 * own consulting_users mirror of SSO users is gone.
 */
class User
{
    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetchOne('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    }

    /**
     * An account for a client who applied without one. It has an unusable
     * password: they set their own through /account/forgot.php, which is what
     * the engagement page tells the admin to send them. (The original made a
     * consulting_users row with sso_id NULL here, which its own schema forbids:
     * accepting such an applicant was a fatal error.)
     */
    public static function createForClient(string $email, string $name): int
    {
        return Database::insert('users', [
            'email'         => strtolower(trim($email)),
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'name'          => trim($name) !== '' ? trim($name) : strtok($email, '@'),
        ]);
    }
}
