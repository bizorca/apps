<?php

declare(strict_types=1);

namespace Dispatch\Models;

use Dispatch\Core\Database;

class Notification
{
    public static function create(array $data): int
    {
        return Database::getInstance()->insert(
            'INSERT INTO dp_notifications (user_id, type, title, message, data, link) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'],
                $data['type'],
                $data['title'],
                $data['message'],
                $data['data'] ?? null,
                $data['link'] ?? null,
            ]
        );
    }

    public static function findByUser(int $userId, int $limit = 20): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM dp_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit,
            [$userId]
        );
    }

    public static function countUnread(int $userId): int
    {
        $row = Database::getInstance()->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_notifications SET read_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }
}
