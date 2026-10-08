<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Member extends BaseModel
{
    protected string $table = 'tm_members';

    // -------------------------------------------------------------------------
    // Lookup
    // -------------------------------------------------------------------------

    /**
     * Find a member by email within a specific tenant. Used for login.
     */
    public function findByEmail(int $tenantId, string $email): array|false
    {
        return DB::fetch(
            "SELECT * FROM `tm_members` WHERE tenant_id = ? AND email = ? LIMIT 1",
            [$tenantId, $email]
        );
    }

    /**
     * Fetch member plus their transaction stats (given/received counts, balance).
     */
    public function findWithStats(int $id): array|false
    {
        $sql = "
            SELECT
                m.*,
                COALESCE(given.total_given, 0)       AS total_hours_given,
                COALESCE(received.total_received, 0) AS total_hours_received,
                COALESCE(given.tx_count, 0)          AS transactions_as_provider,
                COALESCE(received.rx_count, 0)       AS transactions_as_receiver
            FROM `tm_members` m
            LEFT JOIN (
                SELECT provider_id,
                       SUM(hours)   AS total_given,
                       COUNT(*)     AS tx_count
                FROM `tm_transactions`
                WHERE status = 'confirmed'
                GROUP BY provider_id
            ) given    ON given.provider_id    = m.id
            LEFT JOIN (
                SELECT receiver_id,
                       SUM(hours)   AS total_received,
                       COUNT(*)     AS rx_count
                FROM `tm_transactions`
                WHERE status = 'confirmed'
                GROUP BY receiver_id
            ) received ON received.receiver_id = m.id
            WHERE m.id = ? AND m.tenant_id = ?
            LIMIT 1
        ";

        return DB::fetch($sql, [$id, $this->tenantId]);
    }

    /**
     * Paginated member directory for a tenant. Excludes inactive members.
     * Supports filtering by role, city, or name fragment.
     *
     * @param array{role?: string, city?: string, search?: string} $filters
     */
    public function directory(int $tenantId, array $filters = [], int $page = 1, int $perPage = 24): array
    {
        $conditions = ["m.tenant_id = ?", "m.is_active = 1", "m.is_approved = 1"];
        $params     = [$tenantId];

        if (!empty($filters['role'])) {
            $conditions[] = "m.role = ?";
            $params[]     = $filters['role'];
        }

        if (!empty($filters['city'])) {
            $conditions[] = "m.city = ?";
            $params[]     = $filters['city'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(m.display_name LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR m.bio LIKE ?)";
            $like          = '%' . $filters['search'] . '%';
            $params[]      = $like;
            $params[]      = $like;
            $params[]      = $like;
            $params[]      = $like;
        }

        $where  = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $perPage);

        $countSql = "SELECT COUNT(*) AS cnt FROM `tm_members` m WHERE {$where}";
        $countRow = DB::fetch($countSql, $params);
        $total    = $countRow ? (int) $countRow['cnt'] : 0;
        $pages    = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $dataSql = "
            SELECT
                m.id, m.display_name, m.first_name, m.last_name,
                m.bio, m.avatar_path, m.city, m.state, m.balance,
                m.role, m.created_at,
                (SELECT COUNT(*) FROM `tm_offers` o
                 WHERE o.member_id = m.id AND o.is_active = 1) AS active_offer_count
            FROM `tm_members` m
            WHERE {$where}
            ORDER BY m.display_name ASC, m.first_name ASC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        return [
            'data'    => DB::fetchAll($dataSql, $params),
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }

    /**
     * Increment or decrement a member's balance atomically.
     * Pass a positive delta to credit, negative to debit.
     */
    public function updateBalance(int $id, float $delta): int
    {
        $stmt = DB::query(
            "UPDATE `tm_members` SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
            [$delta, $id, $this->tenantId]
        );

        return $stmt->rowCount();
    }

    /**
     * Set balance to an exact amount (admin override).
     */
    public function setBalance(int $id, float $amount): int
    {
        return DB::update(
            $this->table,
            ['balance' => $amount],
            ['id' => $id, 'tenant_id' => $this->tenantId]
        );
    }

    /**
     * Return paginated transaction statement for a member, optionally filtered by date range.
     */
    public function getStatement(
        int $memberId,
        int $tenantId,
        ?string $from = null,
        ?string $to = null,
        int $page = 1,
        int $perPage = 25
    ): array {
        $conditions = [
            "t.tenant_id = ?",
            "(t.provider_id = ? OR t.receiver_id = ?)",
            "t.status = 'confirmed'",
        ];
        $params = [$tenantId, $memberId, $memberId];

        if ($from !== null) {
            $conditions[] = "t.service_date >= ?";
            $params[]     = $from;
        }

        if ($to !== null) {
            $conditions[] = "t.service_date <= ?";
            $params[]     = $to;
        }

        $where  = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $perPage);

        $countSql = "SELECT COUNT(*) AS cnt FROM `tm_transactions` t WHERE {$where}";
        $countRow = DB::fetch($countSql, $params);
        $total    = $countRow ? (int) $countRow['cnt'] : 0;
        $pages    = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $dataSql = "
            SELECT
                t.*,
                CONCAT(p.first_name, ' ', p.last_name) AS provider_name,
                p.display_name                         AS provider_display,
                CONCAT(r.first_name, ' ', r.last_name) AS receiver_name,
                r.display_name                         AS receiver_display,
                CASE WHEN t.provider_id = {$memberId} THEN 'credit' ELSE 'debit' END AS direction
            FROM `tm_transactions` t
            JOIN `tm_members` p ON p.id = t.provider_id
            LEFT JOIN `tm_members` r ON r.id = t.receiver_id
            WHERE {$where}
            ORDER BY t.service_date DESC, t.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        return [
            'data'    => DB::fetchAll($dataSql, $params),
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }

    /**
     * Return all household members that share the given head_id.
     */
    public function getHouseholdMembers(int $headId): array
    {
        return DB::fetchAll(
            "SELECT * FROM `tm_members` WHERE household_head_id = ? AND tenant_id = ? ORDER BY first_name ASC",
            [$headId, $this->tenantId]
        );
    }

    /**
     * All active, approved members for a tenant (lightweight list, no stats).
     */
    public function findActive(int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT id, first_name, last_name, display_name, email, balance, role
             FROM `tm_members`
             WHERE tenant_id = ? AND is_active = 1 AND is_approved = 1
             ORDER BY display_name ASC, first_name ASC",
            [$tenantId]
        );
    }

    /**
     * Full-text style name/bio search across members in a tenant.
     */
    public function searchMembers(int $tenantId, string $query): array
    {
        $like = '%' . $query . '%';

        return DB::fetchAll(
            "SELECT id, first_name, last_name, display_name, bio, avatar_path, city, state
             FROM `tm_members`
             WHERE tenant_id = ?
               AND is_active = 1
               AND is_approved = 1
               AND (
                   first_name   LIKE ? OR
                   last_name    LIKE ? OR
                   display_name LIKE ? OR
                   bio          LIKE ? OR
                   email        LIKE ?
               )
             ORDER BY display_name ASC, first_name ASC
             LIMIT 50",
            [$tenantId, $like, $like, $like, $like, $like]
        );
    }

    /**
     * Decode the privacy_settings JSON for a member row.
     *
     * @return array{show_email: bool, show_phone: bool, show_address: bool}
     */
    public function getPrivacySettings(array $member): array
    {
        $defaults = [
            'show_email'   => false,
            'show_phone'   => false,
            'show_address' => false,
        ];

        if (empty($member['privacy_settings'])) {
            return $defaults;
        }

        $decoded = json_decode($member['privacy_settings'], true);
        return is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
    }

    /**
     * Decode the email_preferences JSON for a member row.
     *
     * @return array{weekly_digest: bool, new_message: bool, transaction_recorded: bool}
     */
    public function getEmailPreferences(array $member): array
    {
        $defaults = [
            'weekly_digest'        => true,
            'new_message'          => true,
            'transaction_recorded' => true,
        ];

        if (empty($member['email_preferences'])) {
            return $defaults;
        }

        $decoded = json_decode($member['email_preferences'], true);
        return is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
    }
}
