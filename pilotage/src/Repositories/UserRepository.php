<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Repositories;

use Bizorca\Pilotage\Core\Repository;

/**
 * Users, tenant-scoped like everything else.
 *
 * Two columns are deliberately NOT writable through the generic insert/update
 * path. account_id (which Bizorca Tools account this membership answers to)
 * goes through attachAccount(); password_hash is no longer written at all,
 * because passwords live on the shared account since the port. Neither can be
 * set by a caller passing the wrong key in an array built from request input.
 */
final class UserRepository extends Repository
{
    public const FIRM_SIDE_ROLES   = ['firm_owner', 'coach', 'associate'];
    public const CLIENT_SIDE_ROLES = ['client_owner', 'client_member', 'sponsor'];

    protected function table(): string
    {
        return 'pl_users';
    }

    /** @return string[] */
    protected function writable(): array
    {
        return ['client_org_id', 'email', 'name', 'role', 'status'];
    }

    public static function isFirmSide(string $role): bool
    {
        return in_array($role, self::FIRM_SIDE_ROLES, true);
    }

    /** Email lookup within the tenant. Emails are stored and matched lowercase. */
    /**
     * This firm's membership for a Bizorca Tools account, or null. The one
     * lookup sign-in uses: who you are comes from the shared account, which
     * firm you are in comes from the route, and this joins the two.
     */
    public function findByAccountId(int $accountId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE tenant_id = :tid AND account_id = :aid LIMIT 1'
        );
        $stmt->execute(['tid' => $this->tenantId, 'aid' => $accountId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Bind a membership to a tools account. Deliberately NOT in writable():
     * which account a pl_users row answers to is an authentication fact, so it
     * has exactly one write path, and nothing built from request input can
     * reach it — the same reason password_hash had setPassword().
     */
    public function attachAccount(int $userId, int $accountId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ' . $this->table() . ' SET account_id = :aid
             WHERE id = :id AND tenant_id = :tid'
        );
        $stmt->execute(['aid' => $accountId, 'id' => $userId, 'tid' => $this->tenantId]);

        return $stmt->rowCount() === 1;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->firstWhere(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * Create a user. Enforces the side-of-the-wall rule in application code as
     * well as at the schema level — the CHECK constraint is the backstop, but a
     * clear exception beats a driver error.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $role = (string) ($data['role'] ?? '');
        $orgId = $data['client_org_id'] ?? null;

        if (!in_array($role, array_merge(self::FIRM_SIDE_ROLES, self::CLIENT_SIDE_ROLES), true)) {
            throw new \InvalidArgumentException('Unknown role: ' . $role);
        }

        if (self::isFirmSide($role) && $orgId !== null) {
            throw new \InvalidArgumentException('A ' . $role . ' cannot belong to a client organization.');
        }
        if (!self::isFirmSide($role) && $orgId === null) {
            throw new \InvalidArgumentException('A ' . $role . ' must belong to a client organization.');
        }

        $data['email'] = mb_strtolower(trim((string) ($data['email'] ?? '')));

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        return $this->insert($data);
    }

    /** @return array<int,array<string,mixed>> */
    public function firmSide(): array
    {
        return $this->all(['client_org_id' => null], 'name asc');
    }

    /** @return array<int,array<string,mixed>> */
    public function forClientOrg(int $clientOrgId): array
    {
        return $this->all(['client_org_id' => $clientOrgId], 'name asc');
    }

    /**
     * Firm owners must keep 2FA on (FR-2.2). Used to decide whether to force
     * an enrolment step after login.
     */
    public function requiresTwoFactor(array $user): bool
    {
        return ($user['role'] ?? '') === 'firm_owner';
    }
}
