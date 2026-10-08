<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Donation extends BaseModel
{
    protected string $table = 'tm_donations';

    /**
     * Paginated donation list for a tenant, optionally filtered by status.
     * Includes member name.
     *
     * @param string|null $status 'pending', 'paid', or 'forgiven'
     */
    public function getForTenant(int $tenantId, ?string $status = null, int $page = 1, int $perPage = 25): array
    {
        $conditions = ["d.tenant_id = ?"];
        $params     = [$tenantId];

        if ($status !== null) {
            $conditions[] = "d.status = ?";
            $params[]     = $status;
        }

        $where  = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $perPage);

        $countRow = DB::fetch(
            "SELECT COUNT(*) AS cnt FROM `tm_donations` d WHERE {$where}",
            $params
        );
        $total = $countRow ? (int) $countRow['cnt'] : 0;
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = DB::fetchAll(
            "SELECT
                d.*,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS member_name,
                m.email AS member_email
             FROM `tm_donations` d
             JOIN `tm_members` m ON m.id = d.member_id
             WHERE {$where}
             ORDER BY d.requested_at DESC
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
     * All donation records for a single member, newest first.
     */
    public function getForMember(int $memberId): array
    {
        return DB::fetchAll(
            "SELECT * FROM `tm_donations` WHERE member_id = ? ORDER BY requested_at DESC",
            [$memberId]
        );
    }

    /**
     * Mark a donation as paid, recording the payment reference and method.
     */
    public function markPaid(int $id, string $paymentReference, string $method): int
    {
        return DB::update(
            $this->table,
            [
                'status'            => 'paid',
                'payment_reference' => $paymentReference,
                'payment_method'    => $method,
                'paid_at'           => date('Y-m-d H:i:s'),
            ],
            ['id' => $id, 'tenant_id' => $this->tenantId]
        );
    }

    /**
     * Forgive a donation — marks it as forgiven with no payment required.
     */
    public function forgive(int $id): int
    {
        return DB::update(
            $this->table,
            [
                'status'         => 'forgiven',
                'payment_method' => 'forgiven',
                'paid_at'        => date('Y-m-d H:i:s'),
            ],
            // Scoped to this community: the original updated by id alone, so an
            // admin of one timebank could settle another timebank's donations.
            ['id' => $id, 'tenant_id' => $this->tenantId]
        );
    }

    /**
     * Create pending donation requests for all active approved members
     * who do not already have a paid or forgiven donation for the current year.
     *
     * Pass $amountHours to request payment in time credits instead of USD.
     *
     * @return int Number of donation records created
     */
    public function requestAll(int $tenantId, ?float $amountHours = null): int
    {
        $currentYear = date('Y');

        // Active members who haven't already settled this year
        $members = DB::fetchAll(
            "SELECT m.id
             FROM `tm_members` m
             WHERE m.tenant_id = ? AND m.is_active = 1 AND m.is_approved = 1
               AND m.id NOT IN (
                   SELECT d.member_id
                   FROM `tm_donations` d
                   WHERE d.tenant_id = ?
                     AND d.status IN ('paid', 'forgiven')
                     AND YEAR(d.requested_at) = ?
               )",
            [$tenantId, $tenantId, $currentYear]
        );

        $count = 0;

        foreach ($members as $member) {
            DB::insert('tm_donations', [
                'tenant_id'      => $tenantId,
                'member_id'      => $member['id'],
                'amount_usd'     => $amountHours === null ? null : null,
                'hours'          => $amountHours,
                'payment_method' => $amountHours !== null ? 'manual' : 'manual',
                'status'         => 'pending',
                'requested_at'   => date('Y-m-d H:i:s'),
            ]);

            $count++;
        }

        return $count;
    }
}
