<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Transaction extends BaseModel
{
    protected string $table = 'tm_transactions';

    /**
     * Record a transaction and move the hours between balances, atomically.
     *
     * The provider earns the hours (credit); the receiver, if any, pays them
     * (debit). For one_to_many / many_to_one, pass 'participants' as
     * [['member_id' => int, 'role' => 'provider'|'receiver', 'hours' => float]];
     * those rows go into tm_transaction_participants and the main row carries
     * the exchange total, as in the original.
     *
     * The original ran the insert and the two balance updates as separate
     * statements, so a failure in between left balances disagreeing with the
     * ledger. Callers validate who the parties are (see TransactionController).
     *
     * @param array $data Must include: provider_id, hours, service_date, recorded_by.
     *                    Optional: receiver_id, group_id, type, prep_hours, description, offer_id, participants[]
     */
    public function record(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $participants = $data['participants'] ?? [];
            unset($data['participants']);

            $data['tenant_id']  = $this->tenantId;
            $data['type']       = $data['type']       ?? 'one_to_one';
            $data['prep_hours'] = self::decimal($data['prep_hours'] ?? 0);
            $data['hours']      = self::decimal($data['hours']);
            $data['status']     = 'confirmed';

            $transactionId = (int) DB::insert($this->table, $data);

            foreach ($participants as $participant) {
                DB::insert('tm_transaction_participants', [
                    'transaction_id' => $transactionId,
                    'member_id'      => (int) $participant['member_id'],
                    'role'           => $participant['role'],
                    'hours'          => self::decimal($participant['hours']),
                ]);
            }

            if ($participants) {
                // Class / workshop: each participant row moves its own hours
                // (the provider's row carries the total).
                $this->applyParticipants($participants, 1);
            } else {
                $this->updateBalances($data);
            }

            return $transactionId;
        });
    }

    /**
     * Credit a member from the community fund: welcome credits on joining.
     *
     * The original recorded welcome credits with provider_id = 0 ("community
     * fund") and the new member as receiver. provider_id is a foreign key to
     * members, so the insert failed and every registration in a community with
     * welcome credits ended in a fatal error; had it succeeded, the member, as
     * receiver, would have been DEBITED. Here the member is the credited party,
     * there is no receiver (the same shape the admin "record hours" grant
     * uses), and the community fund balance goes down by the same amount.
     */
    public function creditFromCommunityFund(int $memberId, float $hours, string $description, int $recordedBy): int
    {
        return DB::transaction(function () use ($memberId, $hours, $description, $recordedBy): int {
            $id = $this->record([
                'provider_id'  => $memberId,
                'receiver_id'  => null,
                'hours'        => $hours,
                'service_date' => date('Y-m-d'),
                'description'  => $description,
                'recorded_by'  => $recordedBy,
                'type'         => 'one_to_one',
            ]);
            DB::query(
                'UPDATE `tm_tenants` SET community_fund_balance = community_fund_balance - CAST(? AS DECIMAL(8,2)) WHERE id = ?',
                [self::decimal($hours), $this->tenantId]
            );
            return $id;
        });
    }

    /**
     * Apply ($sign = 1) or reverse ($sign = -1) participant-level balance
     * changes: providers earn, receivers pay.
     */
    private function applyParticipants(array $participants, int $sign): void
    {
        foreach ($participants as $p) {
            $earns = ($p['role'] === 'provider') === ($sign === 1);
            DB::query(
                'UPDATE `tm_members` SET balance = balance ' . ($earns ? '+' : '-') . ' CAST(? AS DECIMAL(8,2)) WHERE id = ? AND tenant_id = ?',
                [self::decimal($p['hours']), (int) $p['member_id'], $this->tenantId]
            );
        }
    }

    /** Hours as an exact two-place decimal string, for DECIMAL columns. */
    public static function decimal(float|int|string $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    /**
     * Credit the provider and debit the receiver (if any) by the hours.
     * Amounts go in as CAST(... AS DECIMAL) so the arithmetic stays exact.
     */
    public function updateBalances(array $transaction): void
    {
        $hours = self::decimal($transaction['hours']);

        DB::query(
            'UPDATE `tm_members` SET balance = balance + CAST(? AS DECIMAL(8,2)) WHERE id = ? AND tenant_id = ?',
            [$hours, (int) $transaction['provider_id'], $this->tenantId]
        );

        if (!empty($transaction['receiver_id'])) {
            DB::query(
                'UPDATE `tm_members` SET balance = balance - CAST(? AS DECIMAL(8,2)) WHERE id = ? AND tenant_id = ?',
                [$hours, (int) $transaction['receiver_id'], $this->tenantId]
            );
        }
    }

    /**
     * Single transaction with provider and receiver names joined.
     */
    public function getWithNames(int $id): array|false
    {
        return DB::fetch(
            "SELECT
                t.*,
                COALESCE(p.display_name, CONCAT(p.first_name, ' ', p.last_name)) AS provider_name,
                p.avatar_path AS provider_avatar,
                COALESCE(r.display_name, CONCAT(r.first_name, ' ', r.last_name)) AS receiver_name,
                r.avatar_path AS receiver_avatar,
                rec.first_name AS recorded_by_first,
                rec.last_name  AS recorded_by_last
             FROM `tm_transactions` t
             JOIN `tm_members` p   ON p.id = t.provider_id
             LEFT JOIN `tm_members` r   ON r.id = t.receiver_id
             LEFT JOIN `tm_members` rec ON rec.id = t.recorded_by
             WHERE t.id = ? AND t.tenant_id = ?
             LIMIT 1",
            [$id, $this->tenantId]
        );
    }

    /**
     * Paginated transaction history for a single member (both sides of the exchange).
     */
    public function getMemberTransactions(
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

        $countRow = DB::fetch("SELECT COUNT(*) AS cnt FROM `tm_transactions` t WHERE {$where}", $params);
        $total    = $countRow ? (int) $countRow['cnt'] : 0;
        $pages    = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = DB::fetchAll(
            "SELECT
                t.*,
                COALESCE(p.display_name, CONCAT(p.first_name, ' ', p.last_name)) AS provider_name,
                COALESCE(r.display_name, CONCAT(r.first_name, ' ', r.last_name)) AS receiver_name,
                CASE WHEN t.provider_id = {$memberId} THEN 'credit' ELSE 'debit' END AS direction
             FROM `tm_transactions` t
             JOIN  `tm_members` p ON p.id = t.provider_id
             LEFT JOIN `tm_members` r ON r.id = t.receiver_id
             WHERE {$where}
             ORDER BY t.service_date DESC, t.created_at DESC
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
     * Aggregate stats for the entire tenant: total hours circulated, active
     * member count, and transaction count.
     */
    public function getTenantSummary(int $tenantId): array
    {
        $stats = DB::fetch(
            "SELECT
                COUNT(*)        AS transaction_count,
                COALESCE(SUM(hours), 0) AS total_hours
             FROM `tm_transactions`
             WHERE tenant_id = ? AND status = 'confirmed'",
            [$tenantId]
        );

        $activeMembers = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM `tm_members`
             WHERE tenant_id = ? AND is_active = 1 AND is_approved = 1",
            [$tenantId]
        );

        return [
            'transaction_count' => (int) ($stats['transaction_count'] ?? 0),
            'total_hours'       => (float) ($stats['total_hours'] ?? 0),
            'active_members'    => (int) ($activeMembers['cnt'] ?? 0),
        ];
    }

    /**
     * Most recent confirmed transactions with provider and receiver names.
     */
    public function getRecentActivity(int $tenantId, int $limit = 10): array
    {
        return DB::fetchAll(
            "SELECT
                t.*,
                COALESCE(p.display_name, CONCAT(p.first_name, ' ', p.last_name)) AS provider_name,
                COALESCE(r.display_name, CONCAT(r.first_name, ' ', r.last_name)) AS receiver_name
             FROM `tm_transactions` t
             JOIN `tm_members` p ON p.id = t.provider_id
             LEFT JOIN `tm_members` r ON r.id = t.receiver_id
             WHERE t.tenant_id = ? AND t.status = 'confirmed'
             ORDER BY t.created_at DESC
             LIMIT ?",
            [$tenantId, $limit]
        );
    }

    /**
     * Total hours exchanged per category (via the offer join).
     * Categories with no offer-linked transactions are excluded.
     */
    public function getCategoryBreakdown(int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT
                c.id,
                c.name              AS category_name,
                c.icon              AS category_icon,
                COUNT(t.id)         AS transaction_count,
                COALESCE(SUM(t.hours), 0) AS total_hours
             FROM `tm_categories` c
             JOIN `tm_offers` o ON o.category_id = c.id
             JOIN `tm_transactions` t ON t.offer_id = o.id AND t.status = 'confirmed'
             WHERE c.tenant_id = ?
             GROUP BY c.id, c.name, c.icon
             ORDER BY total_hours DESC",
            [$tenantId]
        );
    }

    /**
     * Per-member totals (hours given, hours received, net) for reporting.
     */
    public function getMemberHoursSummary(int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT
                m.id,
                COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS member_name,
                m.first_name, m.last_name, m.display_name,   -- the report view shows these
                m.email,
                m.balance,
                COALESCE(given.total_given, 0)       AS hours_given,
                COALESCE(received.total_received, 0) AS hours_received,
                COALESCE(given.total_given, 0) - COALESCE(received.total_received, 0) AS net_hours
             FROM `tm_members` m
             LEFT JOIN (
                 SELECT provider_id, SUM(hours) AS total_given
                 FROM `tm_transactions`
                 WHERE tenant_id = ? AND status = 'confirmed'
                 GROUP BY provider_id
             ) given    ON given.provider_id    = m.id
             LEFT JOIN (
                 SELECT receiver_id, SUM(hours) AS total_received
                 FROM `tm_transactions`
                 WHERE tenant_id = ? AND status = 'confirmed'
                 GROUP BY receiver_id
             ) received ON received.receiver_id = m.id
             WHERE m.tenant_id = ? AND m.is_active = 1
             ORDER BY hours_given DESC",
            [$tenantId, $tenantId, $tenantId]
        );
    }

    /**
     * Cancel a transaction and reverse its balance changes, atomically.
     * Only the original recorder or a community admin may cancel.
     */
    public function deleteAndRefund(int $id, int $requesterId, bool $requesterIsAdmin = false): bool
    {
        return DB::transaction(function () use ($id, $requesterId, $requesterIsAdmin): bool {
            $transaction = DB::fetch(
                'SELECT * FROM `tm_transactions` WHERE id = ? AND tenant_id = ? LIMIT 1 FOR UPDATE',
                [$id, $this->tenantId]
            );
            if (!$transaction || $transaction['status'] === 'cancelled') {
                return false;
            }
            if ((int) $transaction['recorded_by'] !== $requesterId && !$requesterIsAdmin) {
                return false;
            }

            $hours = self::decimal($transaction['hours']);

            $participants = DB::fetchAll(
                'SELECT member_id, role, hours FROM `tm_transaction_participants` WHERE transaction_id = ?',
                [$id]
            );
            if ($participants) {
                $this->applyParticipants($participants, -1);
                DB::update($this->table, ['status' => 'cancelled'], ['id' => $id, 'tenant_id' => $this->tenantId]);
                return true;
            }

            DB::query(
                'UPDATE `tm_members` SET balance = balance - CAST(? AS DECIMAL(8,2)) WHERE id = ? AND tenant_id = ?',
                [$hours, (int) $transaction['provider_id'], $this->tenantId]
            );
            if (!empty($transaction['receiver_id'])) {
                DB::query(
                    'UPDATE `tm_members` SET balance = balance + CAST(? AS DECIMAL(8,2)) WHERE id = ? AND tenant_id = ?',
                    [$hours, (int) $transaction['receiver_id'], $this->tenantId]
                );
            }

            DB::update($this->table, ['status' => 'cancelled'], ['id' => $id, 'tenant_id' => $this->tenantId]);
            return true;
        });
    }
}
