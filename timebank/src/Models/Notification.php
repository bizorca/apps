<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Notification extends BaseModel
{
    protected string $table = 'tm_notifications';

    /**
     * Insert a notification for a member; returns its id.
     *
     * Named notify(), not create(): the original overrode BaseModel::create(array)
     * with an incompatible signature, a fatal error the moment this class loaded
     * (every dashboard, transaction and message).
     */
    public function notify(
        int $tenantId,
        int $memberId,
        string $type,
        string $title,
        string $body,
        ?string $url = null
    ): int|string {
        return DB::insert('tm_notifications', [
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'type'      => $type,
            'title'     => $title,
            'body'      => $body,
            'url'       => $url,
            'is_read'   => 0,
        ]);
    }

    /**
     * Most recent unread notifications for a member.
     */
    public function getUnread(int $memberId, int $tenantId, int $limit = 10): array
    {
        return DB::fetchAll(
            "SELECT * FROM `tm_notifications`
             WHERE member_id = ? AND tenant_id = ? AND is_read = 0
             ORDER BY created_at DESC
             LIMIT ?",
            [$memberId, $tenantId, $limit]
        );
    }

    /**
     * Paginated full notification history for a member.
     */
    public function getAll(int $memberId, int $tenantId, int $page = 1, int $perPage = 25): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $countRow = DB::fetch(
            "SELECT COUNT(*) AS cnt FROM `tm_notifications` WHERE member_id = ? AND tenant_id = ?",
            [$memberId, $tenantId]
        );
        $total = $countRow ? (int) $countRow['cnt'] : 0;
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = DB::fetchAll(
            "SELECT * FROM `tm_notifications`
             WHERE member_id = ? AND tenant_id = ?
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$memberId, $tenantId]
        );

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }

    /**
     * Mark a single notification as read. Verifies ownership by member_id.
     */
    public function markRead(int $id, int $memberId): int
    {
        return DB::update(
            $this->table,
            ['is_read' => 1],
            ['id' => $id, 'member_id' => $memberId]
        );
    }

    /**
     * Mark every unread notification as read for a member in a tenant.
     */
    public function markAllRead(int $memberId, int $tenantId): int
    {
        $stmt = DB::query(
            "UPDATE `tm_notifications` SET is_read = 1
             WHERE member_id = ? AND tenant_id = ? AND is_read = 0",
            [$memberId, $tenantId]
        );

        return $stmt->rowCount();
    }

    /**
     * Count of unread notifications for a member in a tenant.
     */
    public function getUnreadCount(int $memberId, int $tenantId): int
    {
        $row = DB::fetch(
            "SELECT COUNT(*) AS cnt FROM `tm_notifications`
             WHERE member_id = ? AND tenant_id = ? AND is_read = 0",
            [$memberId, $tenantId]
        );

        return $row ? (int) $row['cnt'] : 0;
    }
}
