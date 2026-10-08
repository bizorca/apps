<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * The organization timeline (FR-3.4).
 *
 * "What has happened with this client" in one reverse-chronological feed —
 * the thing a coach reads in the ninety seconds before a call.
 *
 * Written at event time rather than assembled at read time. A union across a
 * dozen tables gets slower with every module shipped; appending one row does
 * not. It also means the feed keeps its history when a session or task is
 * later deleted, which is the behaviour you want from a record of what
 * happened.
 *
 * client_visible is the wall. Coach-side entries (private notes taken, an
 * internal status change) never render in the client portal.
 */
final class Timeline
{
    public const ORG_CREATED      = 'org.created';
    public const ORG_UPDATED      = 'org.updated';
    public const ORG_CONVERTED    = 'org.converted';
    public const ORG_ARCHIVED     = 'org.archived';
    public const CONTACT_ADDED    = 'contact.added';
    public const CONTACT_UPDATED  = 'contact.updated';
    public const CONTACT_REMOVED  = 'contact.removed';
    public const ACCESS_GRANTED   = 'access.granted';
    public const ACCESS_REVOKED   = 'access.revoked';
    public const INVITE_SENT      = 'invite.sent';
    public const INVITE_ACCEPTED  = 'invite.accepted';

    /** @param array<string,mixed>|null $meta */
    public static function record(
        int $tenantId,
        int $clientOrgId,
        string $eventType,
        string $summary,
        ?array $actor = null,
        ?string $objectType = null,
        ?int $objectId = null,
        bool $clientVisible = true,
        ?array $meta = null
    ): void {
        $label = null;

        if ($actor !== null) {
            $label = trim((string) ($actor['name'] ?? '')) ?: (string) ($actor['email'] ?? '');
        }

        Database::conn()->prepare(
            'INSERT INTO pl_org_events
                (tenant_id, client_org_id, actor_user_id, actor_label, event_type,
                 summary, object_type, object_id, client_visible, meta)
             VALUES (:tid, :org, :uid, :label, :type, :summary, :otype, :oid, :vis, :meta)'
        )->execute([
            'tid'     => $tenantId,
            'org'     => $clientOrgId,
            'uid'     => $actor === null ? null : (((int) ($actor['id'] ?? 0)) ?: null),
            'label'   => $label,
            'type'    => $eventType,
            'summary' => mb_substr($summary, 0, 500),
            'otype'   => $objectType,
            'oid'     => $objectId,
            'vis'     => $clientVisible ? 1 : 0,
            'meta'    => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param bool $clientVisibleOnly Pass true when rendering to a client-side
     *        user. Defaulting to false would make every new call site a
     *        potential leak, so callers must ask for the unfiltered view.
     * @return array<int,array<string,mixed>>
     */
    public static function forOrg(
        int $tenantId,
        int $clientOrgId,
        bool $clientVisibleOnly,
        int $limit = 100,
        int $offset = 0
    ): array {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        $sql = 'SELECT * FROM pl_org_events
                WHERE tenant_id = :tid AND client_org_id = :org';

        if ($clientVisibleOnly) {
            $sql .= ' AND client_visible = 1';
        }

        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'org' => $clientOrgId]);

        return $stmt->fetchAll();
    }

    /**
     * Recent activity across every organization in the tenant, for the coach
     * dashboard.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function recentForTenant(int $tenantId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $stmt = Database::conn()->prepare(
            'SELECT e.*, o.name AS org_name
             FROM pl_org_events e
             JOIN pl_client_orgs o ON o.id = e.client_org_id
             WHERE e.tenant_id = :tid
             ORDER BY e.occurred_at DESC, e.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }
}
