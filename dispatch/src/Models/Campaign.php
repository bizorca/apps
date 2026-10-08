<?php

declare(strict_types=1);

namespace Dispatch\Models;

use Dispatch\Core\Database;

class Campaign
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            'SELECT * FROM dp_campaigns WHERE id = ?',
            [$id]
        );
    }

    public static function findByUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM dp_campaigns WHERE user_id = ? ORDER BY event_date ASC',
            [$userId]
        );
    }

    public static function findActiveByUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM dp_campaigns WHERE user_id = ? AND status = "active" ORDER BY event_date ASC',
            [$userId]
        );
    }

    public static function findUpcomingByUser(int $userId, int $days = 30): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM dp_campaigns WHERE user_id = ? AND status = \'active\' AND event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int) $days . ' DAY) ORDER BY event_date ASC',
            [$userId]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert(
            'INSERT INTO dp_campaigns (user_id, name, description, event_date, event_time, location, recurrence_type, recurrence_day_pattern, recurrence_interval, recurrence_end_date, base_assets, asset_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['event_date'],
                $data['event_time'] ?? null,
                $data['location'] ?? null,
                $data['recurrence_type'] ?? null,
                $data['recurrence_day_pattern'] ?? null,
                $data['recurrence_interval'] ?? null,
                $data['recurrence_end_date'] ?? null,
                $data['base_assets'] ?? null,
                $data['asset_link'] ?? null,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_campaigns SET name=?, description=?, event_date=?, event_time=?, location=?, recurrence_type=?, recurrence_day_pattern=?, recurrence_interval=?, recurrence_end_date=?, base_assets=?, asset_link=?, post_event_notes=?, post_event_rating=?, updated_at=CURRENT_TIMESTAMP WHERE id=?',
            [
                $data['name'],
                $data['description'] ?? null,
                $data['event_date'],
                $data['event_time'] ?? null,
                $data['location'] ?? null,
                $data['recurrence_type'] ?? null,
                $data['recurrence_day_pattern'] ?? null,
                $data['recurrence_interval'] ?? null,
                $data['recurrence_end_date'] ?? null,
                $data['base_assets'] ?? null,
                $data['asset_link'] ?? null,
                $data['post_event_notes'] ?? null,
                isset($data['post_event_rating']) ? max(1, min(5, (int) $data['post_event_rating'])) : null,
                $id,
            ]
        );
    }

    /**
     * Clone a campaign to a new event date. Returns the new campaign ID.
     */
    public static function cloneFrom(int $sourceId, int $userId, string $newEventDate): int
    {
        $source = self::findById($sourceId);
        if (!$source) return 0;

        return self::create([
            'user_id'     => $userId,
            'name'        => $source['name'],
            'description' => $source['description'],
            'event_date'  => $newEventDate,
            'event_time'  => $source['event_time'],
            'location'    => $source['location'],
            'base_assets' => $source['base_assets'],
            'asset_link'  => $source['asset_link'] ?? null,
        ]);
    }

    /**
     * Get campaigns for a specific month (for calendar view).
     */
    public static function findForCalendar(int $userId, string $start, string $end): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT id, name, event_date, status FROM dp_campaigns WHERE user_id = ? AND event_date BETWEEN ? AND ? ORDER BY event_date ASC',
            [$userId, $start, $end]
        );
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_campaigns SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$status, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->execute('DELETE FROM dp_campaigns WHERE id = ?', [$id]);
    }

    /**
     * Get venues associated with this campaign.
     */
    public static function getVenues(int $campaignId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT v.*, cv.is_active as cv_active FROM dp_campaign_venues cv JOIN dp_venues v ON v.id = cv.venue_id WHERE cv.campaign_id = ? ORDER BY v.type, v.name',
            [$campaignId]
        );
    }

    /**
     * Get venue IDs associated with this campaign.
     */
    public static function getVenueIds(int $campaignId): array
    {
        $rows = Database::getInstance()->fetchAll(
            'SELECT venue_id FROM dp_campaign_venues WHERE campaign_id = ?',
            [$campaignId]
        );
        return array_column($rows, 'venue_id');
    }

    /**
     * Sync venues for a campaign (replace all associations).
     */
    public static function syncVenues(int $campaignId, array $venueIds): void
    {
        $db = Database::getInstance();
        $db->execute('DELETE FROM dp_campaign_venues WHERE campaign_id = ?', [$campaignId]);
        foreach ($venueIds as $venueId) {
            $db->execute(
                'INSERT IGNORE INTO dp_campaign_venues (campaign_id, venue_id) VALUES (?, ?)',
                [$campaignId, (int) $venueId]
            );
        }
    }

    public static function count(): int
    {
        $row = Database::getInstance()->fetchOne('SELECT COUNT(*) as cnt FROM dp_campaigns WHERE status = "active"');
        return (int) ($row['cnt'] ?? 0);
    }

    public static function countByUser(int $userId): int
    {
        $row = Database::getInstance()->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_campaigns WHERE user_id = ? AND status = "active"',
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
