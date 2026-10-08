<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Offer extends BaseModel
{
    protected string $table = 'tm_offers';

    /**
     * Most recent active offers or requests for a tenant.
     *
     * @param string $type 'offer' or 'request'
     */
    public function getRecent(int $tenantId, string $type, int $limit = 10): array
    {
        return DB::fetchAll(
            "SELECT
                o.*,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS member_name,
                m.avatar_path AS member_avatar,
                c.name        AS category_name,
                c.icon        AS category_icon
             FROM `tm_offers` o
             JOIN `tm_members` m    ON m.id = o.member_id
             LEFT JOIN `tm_categories` c ON c.id = o.category_id
             WHERE o.tenant_id = ? AND o.type = ? AND o.is_active = 1
               AND m.is_active = 1 AND m.is_approved = 1
             ORDER BY o.created_at DESC
             LIMIT ?",
            [$tenantId, $type, $limit]
        );
    }

    /**
     * Search offers/requests by title and description (LIKE-based).
     * Optionally filter by type and/or category.
     */
    public function search(
        int $tenantId,
        string $query,
        ?string $type = null,
        ?int $categoryId = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        $conditions = ["o.tenant_id = ?", "o.is_active = 1", "m.is_active = 1", "m.is_approved = 1"];
        $params     = [$tenantId];

        $like         = '%' . $query . '%';
        $conditions[] = "(o.title LIKE ? OR o.description LIKE ?)";
        $params[]     = $like;
        $params[]     = $like;

        if ($type !== null) {
            $conditions[] = "o.type = ?";
            $params[]     = $type;
        }

        if ($categoryId !== null) {
            $conditions[] = "o.category_id = ?";
            $params[]     = $categoryId;
        }

        $where  = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $perPage);

        $countRow = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM `tm_offers` o
             JOIN `tm_members` m ON m.id = o.member_id
             WHERE {$where}",
            $params
        );
        $total = $countRow ? (int) $countRow['cnt'] : 0;
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = DB::fetchAll(
            "SELECT
                o.*,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS member_name,
                m.avatar_path AS member_avatar,
                c.name        AS category_name,
                c.icon        AS category_icon
             FROM `tm_offers` o
             JOIN `tm_members` m ON m.id = o.member_id
             LEFT JOIN `tm_categories` c ON c.id = o.category_id
             WHERE {$where}
             ORDER BY o.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }

    /**
     * All offers and requests belonging to a specific member (any status).
     */
    public function getByMember(int $memberId): array
    {
        return DB::fetchAll(
            "SELECT
                o.*,
                c.name AS category_name,
                c.icon AS category_icon
             FROM `tm_offers` o
             LEFT JOIN `tm_categories` c ON c.id = o.category_id
             WHERE o.member_id = ? AND o.tenant_id = ?
             ORDER BY o.is_active DESC, o.updated_at DESC",
            [$memberId, $this->tenantId]
        );
    }

    /**
     * Single offer joined with its member's public info and category.
     */
    public function getWithMember(int $id): array|false
    {
        return DB::fetch(
            "SELECT
                o.*,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS member_name,
                m.avatar_path AS member_avatar,
                m.bio         AS member_bio,
                m.city        AS member_city,
                m.state       AS member_state,
                c.name        AS category_name,
                c.icon        AS category_icon
             FROM `tm_offers` o
             JOIN `tm_members` m ON m.id = o.member_id
             LEFT JOIN `tm_categories` c ON c.id = o.category_id
             WHERE o.id = ? AND o.tenant_id = ?
             LIMIT 1",
            [$id, $this->tenantId]
        );
    }

    /**
     * Paginated full browse of all active offers or requests with member name + category.
     */
    public function browseAll(
        int $tenantId,
        string $type,
        ?int $categoryId = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        $conditions = [
            "o.tenant_id = ?",
            "o.is_active = 1",
            "o.type = ?",
            "m.is_active = 1",
            "m.is_approved = 1",
        ];
        $params = [$tenantId, $type];

        if ($categoryId !== null) {
            $conditions[] = "o.category_id = ?";
            $params[]     = $categoryId;
        }

        $where  = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $perPage);

        $countRow = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM `tm_offers` o
             JOIN `tm_members` m ON m.id = o.member_id
             WHERE {$where}",
            $params
        );
        $total = $countRow ? (int) $countRow['cnt'] : 0;
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = DB::fetchAll(
            "SELECT
                o.*,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS member_name,
                m.avatar_path AS member_avatar,
                m.city        AS member_city,
                m.state       AS member_state,
                c.name        AS category_name,
                c.icon        AS category_icon
             FROM `tm_offers` o
             JOIN `tm_members` m ON m.id = o.member_id
             LEFT JOIN `tm_categories` c ON c.id = o.category_id
             WHERE {$where}
             ORDER BY o.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }

    public function deactivate(int $id): int
    {
        return DB::update(
            $this->table,
            ['is_active' => 0],
            ['id' => $id, 'tenant_id' => $this->tenantId]
        );
    }

    public function activate(int $id): int
    {
        return DB::update(
            $this->table,
            ['is_active' => 1],
            ['id' => $id, 'tenant_id' => $this->tenantId]
        );
    }
}
