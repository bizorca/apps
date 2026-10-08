<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

use Bizorca\Pilotage\Core\Database;

/**
 * Platform-admin support access (FR-2.6).
 *
 * Four non-negotiables, all enforced here rather than by convention:
 *   1. A non-empty reason string is required.
 *   2. Hard 60-minute cap, independent of session lifetime.
 *   3. Written to the audit log at start and end.
 *   4. The session carries impersonator_id so the UI can show a persistent
 *      banner — an admin must never forget whose account they are inside.
 */
final class Impersonation
{
    public const MAX_DURATION = 3600; // 60 minutes, hard cap

    public const MIN_REASON_LENGTH = 10;

    /**
     * Begin impersonating a tenant user.
     *
     * @return int The impersonation record id.
     * @throws \InvalidArgumentException when the reason is missing or perfunctory.
     */
    public static function begin(int $adminId, int $tenantId, int $userId, string $reason, ?string $ip = null): int
    {
        $reason = trim($reason);

        // "test" and "debugging" are not reasons. The log is read by people
        // asking why someone was in a client's account.
        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new \InvalidArgumentException(
                'An impersonation reason of at least ' . self::MIN_REASON_LENGTH . ' characters is required.'
            );
        }

        $db = Database::conn();

        $stmt = $db->prepare(
            'INSERT INTO pl_impersonations (admin_id, tenant_id, user_id, reason, expires_at)
             VALUES (:aid, :tid, :uid, :reason, DATE_ADD(NOW(), INTERVAL :secs SECOND))'
        );
        $stmt->execute([
            'aid'    => $adminId,
            'tid'    => $tenantId,
            'uid'    => $userId,
            'reason' => mb_substr($reason, 0, 500),
            'secs'   => self::MAX_DURATION,
        ]);

        $id = (int) $db->lastInsertId();

        Audit::record(
            Audit::IMPERSONATION_START,
            $tenantId,
            null,
            'user',
            $userId,
            ['admin_id' => $adminId, 'reason' => $reason, 'impersonation_id' => $id],
            $ip,
            $adminId
        );

        return $id;
    }

    /**
     * Is there a live impersonation behind this session?
     *
     * Called on every request that carries an impersonator_id, so the 60-minute
     * cap is enforced continuously rather than only at login.
     */
    public static function isActive(string $sessionId): bool
    {
        $stmt = Database::conn()->prepare(
            'SELECT i.id
             FROM pl_impersonations i
             JOIN pl_user_sessions s
               ON s.impersonator_id = i.admin_id
              AND s.user_id = i.user_id
              AND s.tenant_id = i.tenant_id
             WHERE s.id = :sid
               AND i.ended_at IS NULL
               AND i.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['sid' => $sessionId]);

        return $stmt->fetch() !== false;
    }

    public static function end(int $impersonationId, ?string $ip = null): void
    {
        $db = Database::conn();

        $stmt = $db->prepare('SELECT * FROM pl_impersonations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $impersonationId]);
        $row = $stmt->fetch();

        if ($row === false || $row['ended_at'] !== null) {
            return;
        }

        $db->prepare('UPDATE pl_impersonations SET ended_at = NOW() WHERE id = :id')
           ->execute(['id' => $impersonationId]);

        // Kill the sessions it was driving, so ending access actually ends it.
        $db->prepare(
            'UPDATE pl_user_sessions SET revoked_at = NOW()
             WHERE tenant_id = :tid AND user_id = :uid AND impersonator_id = :aid AND revoked_at IS NULL'
        )->execute([
            'tid' => (int) $row['tenant_id'],
            'uid' => (int) $row['user_id'],
            'aid' => (int) $row['admin_id'],
        ]);

        Audit::record(
            Audit::IMPERSONATION_END,
            (int) $row['tenant_id'],
            null,
            'user',
            (int) $row['user_id'],
            ['admin_id' => (int) $row['admin_id'], 'impersonation_id' => $impersonationId],
            $ip,
            (int) $row['admin_id']
        );
    }

    /** Expire anything past its cap. For the cron tick. */
    public static function expireStale(): int
    {
        $db = Database::conn();

        $stmt = $db->query(
            'SELECT id FROM pl_impersonations WHERE ended_at IS NULL AND expires_at <= NOW()'
        );

        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            self::end((int) $row['id']);
            $count++;
        }

        return $count;
    }
}
