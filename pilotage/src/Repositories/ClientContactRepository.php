<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Repositories;

use Bizorca\Pilotage\Core\Repository;

/**
 * People at a client organization (FR-3.2).
 *
 * A contact is not a user. The coach needs a silent co-owner, a controller, or
 * a bookkeeper on file whether or not any of them will ever log in — FR-3.2
 * requires an access level of "no portal access", which a users table cannot
 * express. Granting access issues an invitation; accepting it creates the user
 * and links it back here.
 *
 * The contact outlives the login. Disabling someone's access does not erase
 * the fact that they are the CFO.
 */
final class ClientContactRepository extends Repository
{
    public const ACCESS_LEVELS = [
        'none'   => 'No portal access',
        'member' => 'Team member',
        'owner'  => 'Account owner',
    ];

    /** Maps a contact's access level to the user role it becomes on acceptance. */
    public const ACCESS_TO_ROLE = [
        'member' => 'client_member',
        'owner'  => 'client_owner',
    ];

    protected function table(): string
    {
        return 'pl_client_contacts';
    }

    /** @return string[] */
    protected function writable(): array
    {
        return ['client_org_id', 'user_id', 'name', 'email', 'title', 'phone', 'portal_access', 'is_primary', 'notes'];
    }

    /** @return array<int,array<string,mixed>> */
    public function forOrg(int $clientOrgId): array
    {
        return $this->all(['client_org_id' => $clientOrgId], 'is_primary desc');
    }

    public function findByEmailInOrg(int $clientOrgId, string $email): ?array
    {
        return $this->firstWhere([
            'client_org_id' => $clientOrgId,
            'email'         => mb_strtolower(trim($email)),
        ]);
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->firstWhere(['user_id' => $userId]);
    }

    /** @param array<string,mixed> $data */
    public function createContact(array $data): int
    {
        $data = self::normalize($data);
        $problems = self::problems($data);

        if ($problems !== []) {
            throw new \InvalidArgumentException(implode(' ', $problems));
        }

        $id = $this->insert($data);

        if (!empty($data['is_primary'])) {
            $this->makePrimary((int) $data['client_org_id'], $id);
        }

        return $id;
    }

    /**
     * Exactly one primary contact per organization. Enforced here rather than
     * with a partial unique index, which MySQL does not have.
     */
    public function makePrimary(int $clientOrgId, int $contactId): void
    {
        $this->db->prepare(
            'UPDATE ' . $this->table() . '
             SET is_primary = CASE WHEN id = :id THEN 1 ELSE 0 END
             WHERE tenant_id = :tid AND client_org_id = :org'
        )->execute(['id' => $contactId, 'tid' => $this->tenantId, 'org' => $clientOrgId]);
    }

    /** Link a roster entry to the login created when its invitation was accepted. */
    public function linkUser(int $contactId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ' . $this->table() . '
             SET user_id = :uid WHERE id = :id AND tenant_id = :tid AND user_id IS NULL'
        );
        $stmt->execute(['uid' => $userId, 'id' => $contactId, 'tid' => $this->tenantId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * How many portal seats this organization has used, against its cap
     * (FR-3.3). Counts granted access rather than accepted logins, so pending
     * invitations cannot be used to overshoot the cap.
     */
    public function seatsUsed(int $clientOrgId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM ' . $this->table() . "
             WHERE tenant_id = :tid AND client_org_id = :org AND portal_access <> 'none'"
        );
        $stmt->execute(['tid' => $this->tenantId, 'org' => $clientOrgId]);

        return (int) $stmt->fetch()['c'];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $data[$key] = $value === '' ? null : $value;
            }
        }

        if (!empty($data['email'])) {
            $data['email'] = mb_strtolower((string) $data['email']);
        }

        $data['is_primary'] = !empty($data['is_primary']) ? 1 : 0;

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     * @return string[]
     */
    public static function problems(array $data): array
    {
        $problems = [];

        if (trim((string) ($data['name'] ?? '')) === '') {
            $problems[] = 'A contact name is required.';
        }

        if (empty($data['client_org_id'])) {
            $problems[] = 'A contact must belong to a client organization.';
        }

        $access = (string) ($data['portal_access'] ?? 'none');

        if (!isset(self::ACCESS_LEVELS[$access])) {
            $problems[] = 'Unknown access level.';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $problems[] = 'That email address does not look right.';
        }

        // The schema enforces this too; catching it here gives a usable message.
        if ($access !== 'none' && empty($data['email'])) {
            $problems[] = 'Portal access needs an email address to send the invitation to.';
        }

        return $problems;
    }
}
