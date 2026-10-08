<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Group extends BaseModel
{
    protected string $table = 'tm_groups';

    /**
     * All active groups for the tenant with a member count.
     */
    public function getActive(int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT
                g.*,
                COUNT(gm.member_id) AS member_count,
                COALESCE(c.display_name, CONCAT(c.first_name, ' ', c.last_name)) AS created_by_name
             FROM `tm_groups` g
             LEFT JOIN `tm_group_members` gm ON gm.group_id = g.id
             LEFT JOIN `tm_members`       c  ON c.id        = g.created_by
             WHERE g.tenant_id = ? AND g.is_active = 1
             GROUP BY g.id
             ORDER BY g.name ASC",
            [$tenantId]
        );
    }

    /**
     * Whether a specific member belongs to a group.
     */
    public function isMember(int $groupId, int $memberId): bool
    {
        $row = DB::fetch(
            "SELECT group_id FROM `tm_group_members` WHERE group_id = ? AND member_id = ? LIMIT 1",
            [$groupId, $memberId]
        );

        return $row !== false;
    }

    /**
     * Add a member to a group. Silently ignores duplicate joins (INSERT IGNORE).
     */
    public function join(int $groupId, int $memberId, string $role = 'member'): void
    {
        DB::query(
            "INSERT IGNORE INTO `tm_group_members` (group_id, member_id, role, joined_at)
             VALUES (?, ?, ?, NOW())",
            [$groupId, $memberId, $role]
        );
    }

    /**
     * Remove a member from a group.
     */
    public function leave(int $groupId, int $memberId): int
    {
        return DB::delete(
            'tm_group_members',
            'group_id = ? AND member_id = ?',
            [$groupId, $memberId]
        );
    }

    /**
     * Full member list for a group, with roles and join dates.
     */
    public function getMembers(int $groupId): array
    {
        return DB::fetchAll(
            "SELECT
                m.id,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS member_name,
                m.display_name, m.first_name, m.last_name,   -- the group page reads these
                m.avatar_path,
                m.city,
                m.state,
                gm.role,
                gm.joined_at
             FROM `tm_group_members` gm
             JOIN `tm_members` m ON m.id = gm.member_id
             WHERE gm.group_id = ? AND m.is_active = 1
             ORDER BY gm.role DESC, m.first_name ASC",
            [$groupId]
        );
    }

    /**
     * All groups a member belongs to within a tenant.
     */
    public function getMemberGroups(int $memberId, int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT
                g.*,
                gm.role,
                gm.joined_at,
                (SELECT COUNT(*) FROM `tm_group_members` gm2 WHERE gm2.group_id = g.id) AS member_count
             FROM `tm_groups` g
             JOIN `tm_group_members` gm ON gm.group_id = g.id
             WHERE gm.member_id = ? AND g.tenant_id = ? AND g.is_active = 1
             ORDER BY g.name ASC",
            [$memberId, $tenantId]
        );
    }

    /**
     * Single group record with its current member count.
     */
    public function getWithMemberCount(int $id): array|false
    {
        return DB::fetch(
            "SELECT
                g.*,
                COUNT(gm.member_id) AS member_count
             FROM `tm_groups` g
             LEFT JOIN `tm_group_members` gm ON gm.group_id = g.id
             WHERE g.id = ?
             GROUP BY g.id
             LIMIT 1",
            [$id]
        );
    }
}
