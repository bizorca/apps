<?php

namespace Bizorca\Consulting\Models;

use Bizorca\Consulting\Core\Database;

class ChangeRequest
{
    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM fd_change_requests WHERE id = ?', [$id]);
    }

    public static function forEngagement(int $engagementId): array
    {
        return Database::fetchAll(
            'SELECT cr.*, ' . fd_name_cols() . '
             FROM fd_change_requests cr
             JOIN users u ON u.id = cr.submitted_by
             WHERE cr.engagement_id = ?
             ORDER BY cr.created_at DESC',
            [$engagementId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('fd_change_requests', $data);
    }

    public static function approve(int $id, int $reviewerId, ?string $note = null): void
    {
        Database::update('fd_change_requests', [
            'status'      => 'approved',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => $note,
        ], 'id = ?', [$id]);
    }

    public static function decline(int $id, int $reviewerId, ?string $note = null): void
    {
        Database::update('fd_change_requests', [
            'status'      => 'declined',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => $note,
        ], 'id = ?', [$id]);
    }
}
