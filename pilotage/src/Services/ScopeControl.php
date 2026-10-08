<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Scope and change control (M4B, FR-4.11 to FR-4.15).
 *
 * The lock is the whole point. Before it, scope is a working draft. After it,
 * scope only moves through a change request that someone reviewed and someone
 * can point at later.
 *
 * Scope creep rarely arrives as a demand. It arrives as a series of small
 * reasonable requests nobody wrote down, and six weeks later the engagement is
 * two-thirds over with a third of the original work done. A declined request
 * that stays visible is worth more than one that was never recorded.
 */
final class ScopeControl
{
    public static function isEnabled(int $tenantId, int $engagementId): bool
    {
        $e = self::engagement($tenantId, $engagementId);

        return $e !== null && (int) $e['scope_enabled'] === 1;
    }

    public static function isLocked(int $tenantId, int $engagementId): bool
    {
        $e = self::engagement($tenantId, $engagementId);

        return $e !== null && $e['scope_locked_at'] !== null;
    }

    public static function enable(int $tenantId, int $engagementId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_engagements SET scope_enabled = 1 WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $engagementId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Add a scope item. Refused once scope is locked — that is what locking
     * means, and the change-request path exists precisely so this refusal has
     * somewhere to send you.
     */
    public static function addItem(int $tenantId, int $engagementId, string $title, ?string $description = null): int
    {
        $title = trim($title);

        if ($title === '') {
            throw new \InvalidArgumentException('A scope item needs a title.');
        }

        if (self::isLocked($tenantId, $engagementId)) {
            throw new \RuntimeException('Scope is locked. Raise a change request instead.');
        }

        return self::insertItem($tenantId, $engagementId, $title, $description, true, null);
    }

    public static function removeItem(int $tenantId, int $itemId): bool
    {
        $item = self::item($tenantId, $itemId);

        if ($item === null) {
            return false;
        }

        if (self::isLocked($tenantId, (int) $item['engagement_id'])) {
            throw new \RuntimeException('Scope is locked. Raise a change request instead.');
        }

        $stmt = Database::conn()->prepare('DELETE FROM pl_scope_items WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['tid' => $tenantId, 'id' => $itemId]);

        return $stmt->rowCount() === 1;
    }

    /** Lock scope. Requires something to lock. */
    public static function lock(int $tenantId, int $engagementId): bool
    {
        if (self::items($tenantId, $engagementId) === []) {
            throw new \RuntimeException('There is no scope to lock yet.');
        }

        $stmt = Database::conn()->prepare(
            'UPDATE pl_engagements SET scope_locked_at = NOW()
             WHERE tenant_id = :tid AND id = :id AND scope_locked_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $engagementId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * The client accepts the locked scope. Separate from the lock: the coach
     * proposing and the client agreeing are two events, and the gap between
     * them is often where the real conversation happens.
     */
    public static function accept(int $tenantId, int $engagementId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_engagements SET client_accepted_scope_at = NOW()
             WHERE tenant_id = :tid AND id = :id
               AND scope_locked_at IS NOT NULL AND client_accepted_scope_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $engagementId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT s.*, d.title AS document_title FROM pl_scope_items s
             LEFT JOIN pl_documents d ON d.id = s.document_id
             WHERE s.tenant_id = :tid AND s.engagement_id = :eid
             ORDER BY s.position ASC, s.id ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        return $stmt->fetchAll();
    }

    /** FR-4.15: what we agreed to, against what we handed over. */
    public static function attachDeliverable(int $tenantId, int $itemId, int $documentId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_scope_items SET document_id = :did, delivered_at = NOW()
             WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute(['did' => $documentId, 'tid' => $tenantId, 'id' => $itemId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array{total:int, delivered:int, added:int} */
    public static function summary(int $tenantId, int $engagementId): array
    {
        $items = self::items($tenantId, $engagementId);

        return [
            'total'     => count($items),
            'delivered' => count(array_filter($items, static fn (array $i): bool => $i['delivered_at'] !== null)),
            'added'     => count(array_filter($items, static fn (array $i): bool => (int) $i['is_original'] === 0)),
        ];
    }

    // ---------------------------------------------------------- change requests

    public static function requestChange(
        int $tenantId,
        int $engagementId,
        string $title,
        string $description,
        ?string $justification,
        int $submittedBy
    ): int {
        $title = trim($title);
        $description = trim($description);

        if ($title === '') {
            throw new \InvalidArgumentException('A change request needs a title.');
        }

        if ($description === '') {
            throw new \InvalidArgumentException('A change request needs a description of what is changing.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_change_requests
                (tenant_id, engagement_id, title, description, justification, submitted_by)
             VALUES (:tid, :eid, :title, :desc, :just, :by)'
        )->execute([
            'tid' => $tenantId, 'eid' => $engagementId,
            'title' => mb_substr($title, 0, 255), 'desc' => $description,
            'just' => trim((string) $justification) ?: null, 'by' => $submittedBy,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Approve a request, which appends the work to scope flagged as an
     * addition. The flag is the record: an engagement that grew by five items
     * should look like one, not like it was always that size.
     */
    public static function approve(int $tenantId, int $requestId, int $reviewerId, ?string $note = null): bool
    {
        $db = Database::conn();
        $request = self::changeRequest($tenantId, $requestId);

        if ($request === null || (string) $request['status'] !== 'pending') {
            return false;
        }

        $db->beginTransaction();

        try {
            $db->prepare(
                "UPDATE pl_change_requests
                 SET status = 'approved', reviewed_by = :by, reviewed_at = NOW(), review_note = :note
                 WHERE tenant_id = :tid AND id = :id AND status = 'pending'"
            )->execute(['by' => $reviewerId, 'note' => $note, 'tid' => $tenantId, 'id' => $requestId]);

            self::insertItem(
                $tenantId,
                (int) $request['engagement_id'],
                (string) $request['title'],
                $request['description'],
                false,
                $requestId
            );

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return true;
    }

    /**
     * Decline. The row stays visible on purpose — "we asked and were told no,
     * with a reason" is the most useful thing in the file six months later.
     */
    public static function decline(int $tenantId, int $requestId, int $reviewerId, ?string $note = null): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE pl_change_requests
             SET status = 'declined', reviewed_by = :by, reviewed_at = NOW(), review_note = :note
             WHERE tenant_id = :tid AND id = :id AND status = 'pending'"
        );
        $stmt->execute(['by' => $reviewerId, 'note' => $note, 'tid' => $tenantId, 'id' => $requestId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public static function changeRequests(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT c.*, s.name AS submitter_name, r.name AS reviewer_name
             FROM pl_change_requests c
             LEFT JOIN pl_users s ON s.id = c.submitted_by
             LEFT JOIN pl_users r ON r.id = c.reviewed_by
             WHERE c.tenant_id = :tid AND c.engagement_id = :eid
             ORDER BY FIELD(c.status, "pending", "approved", "declined"), c.created_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function changeRequest(int $tenantId, int $requestId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_change_requests WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $requestId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    // ------------------------------------------------------------- internals

    private static function insertItem(
        int $tenantId,
        int $engagementId,
        string $title,
        ?string $description,
        bool $isOriginal,
        ?int $changeRequestId
    ): int {
        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_scope_items
                (tenant_id, engagement_id, position, title, description, is_original, change_request_id)
             VALUES (:tid, :eid,
                     (SELECT COALESCE(MAX(s.position), -1) + 1 FROM pl_scope_items s
                       WHERE s.tenant_id = :tid2 AND s.engagement_id = :eid2),
                     :title, :desc, :orig, :cr)'
        )->execute([
            'tid' => $tenantId, 'tid2' => $tenantId,
            'eid' => $engagementId, 'eid2' => $engagementId,
            'title' => mb_substr($title, 0, 255), 'desc' => $description,
            'orig' => $isOriginal ? 1 : 0, 'cr' => $changeRequestId,
        ]);

        return (int) $db->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    private static function item(int $tenantId, int $itemId): ?array
    {
        $stmt = Database::conn()->prepare('SELECT * FROM pl_scope_items WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $itemId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    private static function engagement(int $tenantId, int $engagementId): ?array
    {
        $stmt = Database::conn()->prepare('SELECT * FROM pl_engagements WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $engagementId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
