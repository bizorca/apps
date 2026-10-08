<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Endorsement extends BaseModel
{
    protected string $table = 'tm_endorsements';

    /**
     * All endorsements received by a member, with endorser names and linked transaction info.
     */
    public function getForMember(int $memberId, int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT
                e.*,
                COALESCE(fm.display_name, CONCAT(fm.first_name, ' ', fm.last_name)) AS from_member_name,
                fm.avatar_path AS from_member_avatar,
                t.description  AS transaction_description,
                t.service_date AS transaction_date
             FROM `tm_endorsements` e
             JOIN `tm_members` fm ON fm.id = e.from_member_id
             LEFT JOIN `tm_transactions` t ON t.id = e.transaction_id
             WHERE e.to_member_id = ? AND e.tenant_id = ?
             ORDER BY e.created_at DESC",
            [$memberId, $tenantId]
        );
    }

    /**
     * Average rating for a member within a tenant.
     * Returns 0.0 if the member has no endorsements.
     */
    public function getAverageRating(int $memberId, int $tenantId): float
    {
        $row = DB::fetch(
            "SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt
             FROM `tm_endorsements`
             WHERE to_member_id = ? AND tenant_id = ?",
            [$memberId, $tenantId]
        );

        if (!$row || (int) $row['cnt'] === 0) {
            return 0.0;
        }

        return round((float) $row['avg_rating'], 2);
    }

    /**
     * Check whether from_member has already endorsed to_member for a given transaction.
     * Prevents duplicate endorsements on the same exchange.
     */
    public function alreadyEndorsed(int $fromId, int $toId, int $transactionId): bool
    {
        $row = DB::fetch(
            "SELECT id FROM `tm_endorsements`
             WHERE from_member_id = ? AND to_member_id = ? AND transaction_id = ?
             LIMIT 1",
            [$fromId, $toId, $transactionId]
        );

        return $row !== false;
    }
}
