<?php

declare(strict_types=1);

namespace Dispatch\Models;

use Dispatch\Core\Database;

class FlyerLocation
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            'SELECT fl.*, c.name as campaign_name FROM dp_flyer_locations fl LEFT JOIN dp_campaigns c ON c.id = fl.campaign_id WHERE fl.id = ?',
            [$id]
        );
    }

    public static function findByUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT fl.*, c.name as campaign_name FROM dp_flyer_locations fl LEFT JOIN dp_campaigns c ON c.id = fl.campaign_id WHERE fl.user_id = ? ORDER BY fl.posted_at DESC, fl.created_at DESC',
            [$userId]
        );
    }

    public static function findActiveByUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT fl.*, c.name as campaign_name FROM dp_flyer_locations fl LEFT JOIN dp_campaigns c ON c.id = fl.campaign_id WHERE fl.user_id = ? AND fl.status = "active" ORDER BY fl.posted_at DESC',
            [$userId]
        );
    }

    public static function findByCampaign(int $campaignId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM dp_flyer_locations WHERE campaign_id = ? ORDER BY posted_at DESC',
            [$campaignId]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert(
            'INSERT INTO dp_flyer_locations (user_id, campaign_id, location_name, address, posted_at, quantity, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'],
                $data['campaign_id'] ?? null,
                $data['location_name'],
                $data['address'] ?? null,
                $data['posted_at'] ?? null,
                (int) ($data['quantity'] ?? 1),
                $data['notes'] ?? null,
                $data['status'] ?? 'active',
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_flyer_locations SET location_name=?, address=?, posted_at=?, removed_at=?, quantity=?, notes=?, status=?, campaign_id=?, updated_at=CURRENT_TIMESTAMP WHERE id=?',
            [
                $data['location_name'],
                $data['address'] ?? null,
                $data['posted_at'] ?? null,
                $data['removed_at'] ?? null,
                (int) ($data['quantity'] ?? 1),
                $data['notes'] ?? null,
                $data['status'] ?? 'active',
                $data['campaign_id'] ?? null,
                $id,
            ]
        );
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->execute('DELETE FROM dp_flyer_locations WHERE id = ?', [$id]);
    }

    public static function countActiveByUser(int $userId): int
    {
        $row = Database::getInstance()->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_flyer_locations WHERE user_id = ? AND status = "active"',
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
