<?php

declare(strict_types=1);

namespace Dispatch\Models;

use Dispatch\Core\Database;

/**
 * A Dispatch user: the shared tools account (users) joined to its Dispatch
 * profile (dp_profiles: role and digest settings).
 *
 * Only accounts with a profile are Dispatch users. That keeps the admin user
 * list, the assignment dropdown and the digest to people who have actually
 * used Dispatch, not every account on tools.bizorca.com.
 *
 * Name, email and password belong to the shared account and are edited at
 * /account/settings.php; nothing here writes them.
 */
class User
{
    private const SELECT = 'SELECT u.id, u.name, u.email, u.created_at, u.is_admin AS site_admin,
                                   p.role, p.notification_preference, p.remind_days_before, p.created_at AS joined_at
                              FROM users u JOIN dp_profiles p ON p.user_id = u.id';

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(self::SELECT . ' WHERE u.id = ?', [$id]);
    }

    /** Give a shared account its Dispatch profile (first visit). */
    public static function ensureProfile(int $id): array
    {
        Database::getInstance()->execute('INSERT IGNORE INTO dp_profiles (user_id) VALUES (?)', [$id]);
        return self::findById($id);
    }

    /** Digest settings. Name lives on the shared account. */
    public static function update(int $id, array $data): void
    {
        $allowed = ['notification_preference', 'remind_days_before'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($sets)) return;
        $params[] = $id;
        Database::getInstance()->execute(
            'UPDATE dp_profiles SET ' . implode(', ', $sets) . ' WHERE user_id = ?',
            $params
        );
    }

    public static function updateRole(int $id, string $role): void
    {
        Database::getInstance()->execute('UPDATE dp_profiles SET role = ? WHERE user_id = ?', [$role, $id]);
    }

    public static function all(): array
    {
        return Database::getInstance()->fetchAll(self::SELECT . ' ORDER BY p.created_at DESC, u.id DESC');
    }

    /**
     * People an action item can be assigned to. The original limited this to
     * verified accounts; a tools account with a Dispatch profile has signed in,
     * which is the same bar.
     */
    public static function allVerified(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT u.id, u.name, u.email FROM users u JOIN dp_profiles p ON p.user_id = u.id ORDER BY u.name ASC, u.id ASC'
        );
    }

    public static function count(): int
    {
        $row = Database::getInstance()->fetchOne('SELECT COUNT(*) AS cnt FROM dp_profiles');
        return (int) ($row['cnt'] ?? 0);
    }

    public static function recent(int $limit = 5): array
    {
        return Database::getInstance()->fetchAll(self::SELECT . ' ORDER BY p.created_at DESC, u.id DESC LIMIT ' . max(1, $limit));
    }
}
