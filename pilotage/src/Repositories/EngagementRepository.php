<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Repositories;

use Bizorca\Pilotage\Core\Repository;

/**
 * Engagements — the unit of work everything else hangs off.
 *
 * One coach (plus optional associates) × one client organization × one
 * playbook instance × dates and a commercial arrangement.
 */
final class EngagementRepository extends Repository
{
    public const CADENCES = [
        'weekly'    => 'Weekly',
        'biweekly'  => 'Every two weeks',
        'monthly'   => 'Monthly',
        'quarterly' => 'Quarterly',
        'adhoc'     => 'As needed',
    ];

    /** Days between sessions, used for the cadence-slip check on the dashboard. */
    public const CADENCE_DAYS = [
        'weekly'    => 7,
        'biweekly'  => 14,
        'monthly'   => 31,
        'quarterly' => 92,
    ];

    protected function table(): string
    {
        return 'pl_engagements';
    }

    /** @return string[] */
    protected function writable(): array
    {
        return [
            'client_org_id', 'title', 'summary', 'status', 'coach_user_id',
            'cadence', 'starts_on', 'ends_on', 'scope_enabled',
        ];
    }

    /** @param array<string,mixed> $data */
    public function createEngagement(array $data): int
    {
        if (trim((string) ($data['title'] ?? '')) === '') {
            throw new \InvalidArgumentException('An engagement needs a title.');
        }

        if (empty($data['client_org_id'])) {
            throw new \InvalidArgumentException('An engagement needs a client organization.');
        }

        if (!empty($data['cadence']) && !isset(self::CADENCES[$data['cadence']])) {
            throw new \InvalidArgumentException('Unknown cadence.');
        }

        $id = $this->insert($data);

        if (!empty($data['coach_user_id'])) {
            $this->addMember($id, (int) $data['coach_user_id'], 'lead');
        }

        return $id;
    }

    public function addMember(int $engagementId, int $userId, string $memberRole = 'participant'): bool
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO pl_engagement_members (tenant_id, engagement_id, user_id, member_role)
             VALUES (:tid, :eid, :uid, :role)'
        );
        $stmt->execute([
            'tid'  => $this->tenantId,
            'eid'  => $engagementId,
            'uid'  => $userId,
            'role' => $memberRole,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function removeMember(int $engagementId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM pl_engagement_members
             WHERE tenant_id = :tid AND engagement_id = :eid AND user_id = :uid'
        );
        $stmt->execute(['tid' => $this->tenantId, 'eid' => $engagementId, 'uid' => $userId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,int> User ids attached to an engagement. Feeds the 'assigned' qualifier. */
    public function memberIds(int $engagementId): array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id FROM pl_engagement_members WHERE tenant_id = :tid AND engagement_id = :eid'
        );
        $stmt->execute(['tid' => $this->tenantId, 'eid' => $engagementId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * The context array Policy expects for engagement-scoped checks. Building
     * it in one place stops call sites inventing their own shape — which is
     * how the M3 bug happened.
     *
     * @param array<string,mixed> $engagement
     * @return array<string,mixed>
     */
    public function policyContext(array $engagement): array
    {
        return [
            'client_org_id'     => (int) $engagement['client_org_id'],
            'owner_user_id'     => $engagement['coach_user_id'] === null ? null : (int) $engagement['coach_user_id'],
            'assigned_user_ids' => $this->memberIds((int) $engagement['id']),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function forOrg(int $clientOrgId): array
    {
        return $this->all(['client_org_id' => $clientOrgId], 'created_at desc');
    }

    /** @return array<int,array<string,mixed>> */
    public function activeForCoach(int $coachUserId): array
    {
        return $this->all(['coach_user_id' => $coachUserId, 'status' => 'active'], 'title asc');
    }

    /** @return array<int,array<string,mixed>> Engagements a user is attached to, either side. */
    public function forMember(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT e.* FROM pl_engagements e
             JOIN pl_engagement_members m ON m.engagement_id = e.id AND m.tenant_id = e.tenant_id
             WHERE e.tenant_id = :tid AND m.user_id = :uid
             ORDER BY e.title ASC'
        );
        $stmt->execute(['tid' => $this->tenantId, 'uid' => $userId]);

        return $stmt->fetchAll();
    }

    /** The live playbook instance for an engagement, or null. */
    public function playbookInstance(int $engagementId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM pl_engagement_playbooks WHERE tenant_id = :tid AND engagement_id = :eid LIMIT 1'
        );
        $stmt->execute(['tid' => $this->tenantId, 'eid' => $engagementId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
