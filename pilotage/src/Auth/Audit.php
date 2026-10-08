<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

use Bizorca\Pilotage\Core\Database;

/**
 * Append-only audit log (FR-14.1).
 *
 * There is deliberately no update() or delete() here. The only way to remove
 * audit rows is the retention purge (FR-14.2), which is a separate scheduled
 * job with its own warning period — not something a request can reach.
 *
 * actor_label preserves who did a thing even after the user row is gone, so
 * the log survives a deletion it was meant to record.
 */
final class Audit
{
    // Authentication
    public const LOGIN_SUCCESS      = 'login.success';
    public const LOGIN_FAILURE      = 'login.failure';
    public const LOGIN_RATE_LIMITED = 'login.rate_limited';
    public const LOGOUT             = 'logout';
    public const MAGIC_LINK_SENT    = 'magic_link.sent';
    public const MAGIC_LINK_USED    = 'magic_link.used';
    public const PASSWORD_CHANGED   = 'password.changed';
    public const TWO_FACTOR_ENABLED = 'two_factor.enabled';
    public const TWO_FACTOR_FAILED  = 'two_factor.failed';

    // Membership
    public const INVITATION_SENT     = 'invitation.sent';
    public const INVITATION_ACCEPTED = 'invitation.accepted';
    public const INVITATION_REVOKED  = 'invitation.revoked';
    public const USER_DISABLED       = 'user.disabled';
    public const ROLE_CHANGED        = 'role.changed';

    // Support access
    public const IMPERSONATION_START = 'impersonation.start';
    public const IMPERSONATION_END   = 'impersonation.end';

    /** @param array<string,mixed>|null $meta */
    public static function record(
        string $action,
        ?int $tenantId = null,
        ?array $actor = null,
        ?string $objectType = null,
        ?int $objectId = null,
        ?array $meta = null,
        ?string $ip = null,
        ?int $impersonatorId = null
    ): void {
        $stmt = Database::conn()->prepare(
            'INSERT INTO pl_audit_log
                (tenant_id, actor_user_id, actor_label, impersonator_id, action, object_type, object_id, ip, meta)
             VALUES (:tid, :uid, :label, :imp, :action, :otype, :oid, :ip, :meta)'
        );

        $label = null;
        if ($actor !== null) {
            $label = trim((string) ($actor['name'] ?? '') . ' <' . (string) ($actor['email'] ?? '') . '>');
        }

        $stmt->execute([
            'tid'    => $tenantId,
            'uid'    => $actor === null ? null : (((int) ($actor['id'] ?? 0)) ?: null),
            'label'  => $label,
            'imp'    => $impersonatorId,
            'action' => $action,
            'otype'  => $objectType,
            'oid'    => $objectId,
            'ip'     => $ip === null ? null : RateLimiter::packIp($ip),
            'meta'   => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Read the log for one tenant. Firm owners may read their own tenant's log
     * and nothing else (SPEC.md §8).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forTenant(int $tenantId, int $limit = 200, ?string $action = null): array
    {
        $limit = max(1, min($limit, 1000));

        $sql = 'SELECT * FROM pl_audit_log WHERE tenant_id = :tid';
        $params = ['tid' => $tenantId];

        if ($action !== null) {
            $sql .= ' AND action = :action';
            $params['action'] = $action;
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
