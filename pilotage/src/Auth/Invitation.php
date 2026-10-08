<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\SendingTrust;

/**
 * Invitations (FR-2.3): single-use tokens, 7-day expiry, role baked in.
 *
 * The role is fixed at issue time and read from the invitation row on accept,
 * never from the request. An invitee cannot promote themselves by editing a
 * form field, because the form has no say in it.
 */
final class Invitation
{
    public const LIFETIME = 7 * 24 * 3600; // 7 days

    /**
     * @return array{plaintext:string, id:int, expires_at:string, url:string}
     */
    public static function issue(
        int $tenantId,
        string $tenantSlug,
        string $email,
        string $role,
        ?int $clientOrgId = null,
        ?string $name = null,
        ?int $invitedBy = null
    ): array {
        $email = mb_strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        $firmSide = UserRepository::isFirmSide($role);

        if (!in_array($role, array_merge(UserRepository::FIRM_SIDE_ROLES, UserRepository::CLIENT_SIDE_ROLES), true)) {
            throw new \InvalidArgumentException('Unknown role: ' . $role);
        }
        if ($firmSide && $clientOrgId !== null) {
            throw new \InvalidArgumentException('A ' . $role . ' invitation cannot name a client organization.');
        }
        if (!$firmSide && $clientOrgId === null) {
            throw new \InvalidArgumentException('A ' . $role . ' invitation must name a client organization.');
        }

        /**
         * The sending throttle, at the only chokepoint that matters.
         *
         * An invitation is the sole way this product puts mail in front of
         * somebody who does not already have an account — every other message
         * needs a user, and a user needs an invitation. Checking here closes
         * the whole path from "signed up two minutes ago" to "mail in a
         * stranger's inbox", in one place, whatever the caller forgot.
         *
         * It throws an HttpException, which in this codebase is deliberately
         * NOT a RuntimeException, so it survives the `catch (\RuntimeException)`
         * blocks both call sites use for domain errors rather than being
         * silently swallowed into "that person already has an account".
         */
        SendingTrust::assertMayInvite($tenantId);

        $db = Database::conn();

        // Someone already in this tenant does not get re-invited; that would
        // mint a second identity for the same person.
        $existing = $db->prepare('SELECT id FROM pl_users WHERE tenant_id = :tid AND email = :e LIMIT 1');
        $existing->execute(['tid' => $tenantId, 'e' => $email]);
        if ($existing->fetch() !== false) {
            throw new \RuntimeException('That person already has an account in this firm.');
        }

        // Supersede any outstanding invitation to the same address.
        $db->prepare(
            'UPDATE pl_invitations SET revoked_at = NOW()
             WHERE tenant_id = :tid AND email = :e AND accepted_at IS NULL AND revoked_at IS NULL'
        )->execute(['tid' => $tenantId, 'e' => $email]);

        $token = Token::create();
        $expiresAt = date('Y-m-d H:i:s', time() + self::LIFETIME);

        $db->prepare(
            'INSERT INTO pl_invitations
                (tenant_id, client_org_id, email, name, role, selector, verifier_hash, invited_by, expires_at)
             VALUES (:tid, :org, :e, :n, :role, :sel, :hash, :by, :exp)'
        )->execute([
            'tid'  => $tenantId,
            'org'  => $clientOrgId,
            'e'    => $email,
            'n'    => $name,
            'role' => $role,
            'sel'  => $token['selector'],
            'hash' => $token['verifier_hash'],
            'by'   => $invitedBy,
            'exp'  => $expiresAt,
        ]);

        return [
            'plaintext'  => $token['plaintext'],
            'id'         => (int) $db->lastInsertId(),
            'expires_at' => $expiresAt,
            'url'        => tenant_url('/invite/' . $token['plaintext'], $tenantSlug),
        ];
    }

    /** Look up a pending invitation without consuming it, to render the accept form. */
    public static function lookup(string $plaintext, int $tenantId): ?array
    {
        $parts = Token::split($plaintext);

        if ($parts === null) {
            return null;
        }

        [$selector, $verifier] = $parts;

        $stmt = Database::conn()->prepare('SELECT * FROM pl_invitations WHERE selector = :sel LIMIT 1');
        $stmt->execute(['sel' => $selector]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }
        if ((int) $row['tenant_id'] !== $tenantId) {
            return null;
        }
        if (!Token::verify($verifier, (string) $row['verifier_hash'])) {
            return null;
        }
        if ($row['accepted_at'] !== null || $row['revoked_at'] !== null) {
            return null;
        }
        if (strtotime((string) $row['expires_at']) <= time()) {
            return null;
        }

        return $row;
    }

    /**
     * Accept an invitation: create the firm membership and bind it to the
     * Bizorca Tools account that accepted it.
     *
     * @param int|null $accountId The signed-in tools account (users.id). Required.
     * @return array{user_id:int, role:string}|null Null if the invitation is not valid.
     */
    public static function accept(string $plaintext, int $tenantId, string $name, ?int $accountId = null): ?array
    {
        $invitation = self::lookup($plaintext, $tenantId);

        if ($invitation === null) {
            return null;
        }

        $role = (string) $invitation['role'];

        // Who accepts is a Bizorca Tools account (passwords live there now).
        // The caller has already checked it is the invited address.
        if ($accountId === null || $accountId <= 0) {
            throw new \InvalidArgumentException('Accepting an invitation needs a signed-in tools account.');
        }

        $db = Database::conn();
        $users = new UserRepository($tenantId);

        $db->beginTransaction();

        try {
            // Re-check under the transaction, and claim the invitation first so
            // two simultaneous accepts cannot both create a user.
            $claim = $db->prepare(
                'UPDATE pl_invitations SET accepted_at = NOW()
                 WHERE id = :id AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()'
            );
            $claim->execute(['id' => (int) $invitation['id']]);

            if ($claim->rowCount() !== 1) {
                $db->rollBack();
                return null;
            }

            $userId = $users->create([
                'email'         => (string) $invitation['email'],
                'name'          => trim($name) !== '' ? trim($name) : (string) ($invitation['name'] ?? $invitation['email']),
                'role'          => $role,
                'client_org_id' => $invitation['client_org_id'] === null ? null : (int) $invitation['client_org_id'],
                'status'        => 'active',
            ]);

            $users->attachAccount($userId, $accountId);

            $db->prepare('UPDATE pl_invitations SET accepted_user_id = :uid WHERE id = :id')
               ->execute(['uid' => $userId, 'id' => (int) $invitation['id']]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['user_id' => $userId, 'role' => $role];
    }

    public static function revoke(int $tenantId, int $invitationId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_invitations SET revoked_at = NOW()
             WHERE id = :id AND tenant_id = :tid AND accepted_at IS NULL AND revoked_at IS NULL'
        );
        $stmt->execute(['id' => $invitationId, 'tid' => $tenantId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public static function pending(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_invitations
             WHERE tenant_id = :tid AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY created_at DESC'
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }
}
