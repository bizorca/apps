<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Announcement extends BaseModel
{
    protected string $table = 'tm_announcements';

    /**
     * Recent announcements for a tenant, optionally scoped to a group.
     * Pinned items always float to the top.
     */
    public function getRecent(int $tenantId, ?int $groupId = null, int $limit = 5): array
    {
        $conditions = ["a.tenant_id = ?"];
        $params     = [$tenantId];

        if ($groupId !== null) {
            $conditions[] = "a.group_id = ?";
            $params[]     = $groupId;
        } else {
            // Tenant-wide: include both global (group_id IS NULL) and all group announcements
        }

        $where = implode(' AND ', $conditions);
        $params[] = $limit;

        return DB::fetchAll(
            "SELECT
                a.*,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS author_name,
                m.avatar_path AS author_avatar,
                g.name        AS group_name
             FROM `tm_announcements` a
             JOIN `tm_members` m ON m.id = a.author_id
             LEFT JOIN `tm_groups` g ON g.id = a.group_id
             WHERE {$where}
             ORDER BY a.is_pinned DESC, a.created_at DESC
             LIMIT ?",
            $params
        );
    }

    /**
     * Paginated list of all tenant announcements with author name.
     */
    public function getTenantAnnouncements(int $tenantId, int $page = 1, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $countRow = DB::fetch(
            "SELECT COUNT(*) AS cnt FROM `tm_announcements` WHERE tenant_id = ?",
            [$tenantId]
        );
        $total = $countRow ? (int) $countRow['cnt'] : 0;
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = DB::fetchAll(
            "SELECT
                a.*,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS author_name,
                m.avatar_path AS author_avatar,
                g.name        AS group_name
             FROM `tm_announcements` a
             JOIN `tm_members` m ON m.id = a.author_id
             LEFT JOIN `tm_groups` g ON g.id = a.group_id
             WHERE a.tenant_id = ?
             ORDER BY a.is_pinned DESC, a.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$tenantId]
        );

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }

    /**
     * Single announcement joined with its author's name.
     */
    public function getWithAuthor(int $id): array|false
    {
        return DB::fetch(
            "SELECT
                a.*,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS author_name,
                m.avatar_path AS author_avatar,
                g.name        AS group_name
             FROM `tm_announcements` a
             JOIN `tm_members` m ON m.id = a.author_id
             LEFT JOIN `tm_groups` g ON g.id = a.group_id
             WHERE a.id = ? AND a.tenant_id = ?
             LIMIT 1",
            [$id, $this->tenantId]
        );
    }

    /**
     * All pinned announcements for a tenant, newest first.
     */
    public function getPinned(int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT
                a.*,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS author_name,
                m.avatar_path AS author_avatar
             FROM `tm_announcements` a
             JOIN `tm_members` m ON m.id = a.author_id
             WHERE a.tenant_id = ? AND a.is_pinned = 1
             ORDER BY a.created_at DESC",
            [$tenantId]
        );
    }
}
