<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Comments on anything (FR-6.7, FR-8.2).
 *
 * Conversation happens where the work is, not in a separate inbox. That is the
 * CoachAccountable pattern and it is the right one — a question about a task
 * belongs on the task, where it is still legible in six months.
 *
 * client_visible is the wall: a coach can leave an internal note on a task
 * without it reaching the client.
 */
final class Comments
{
    /** Object types that may be commented on. An allowlist, so a typo denies. */
    public const COMMENTABLE = [
        'task', 'document', 'worksheet', 'metric', 'goal', 'session', 'step', 'issue',
    ];

    public static function add(
        int $tenantId,
        string $objectType,
        int $objectId,
        string $body,
        ?array $author,
        bool $clientVisible = true
    ): int {
        if (!in_array($objectType, self::COMMENTABLE, true)) {
            throw new \InvalidArgumentException('Cannot comment on "' . $objectType . '".');
        }

        $body = trim($body);

        if ($body === '') {
            throw new \InvalidArgumentException('An empty comment is not a comment.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_comments (tenant_id, object_type, object_id, author_id, author_label, body, client_visible)
             VALUES (:tid, :type, :oid, :aid, :label, :body, :vis)'
        )->execute([
            'tid'   => $tenantId,
            'type'  => $objectType,
            'oid'   => $objectId,
            'aid'   => $author === null ? null : (((int) ($author['id'] ?? 0)) ?: null),
            'label' => $author === null ? null : (string) ($author['name'] ?? ''),
            'body'  => $body,
            'vis'   => $clientVisible ? 1 : 0,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * @param bool $clientVisibleOnly Callers must say which side they render
     *        for. Defaulting would make every new call site a potential leak.
     * @return array<int,array<string,mixed>>
     */
    public static function forObject(int $tenantId, string $objectType, int $objectId, bool $clientVisibleOnly): array
    {
        $sql = 'SELECT * FROM pl_comments
                WHERE tenant_id = :tid AND object_type = :type AND object_id = :oid AND deleted_at IS NULL';

        if ($clientVisibleOnly) {
            $sql .= ' AND client_visible = 1';
        }

        $sql .= ' ORDER BY created_at ASC, id ASC';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'type' => $objectType, 'oid' => $objectId]);

        return $stmt->fetchAll();
    }

    /** Soft delete — the audit trail outlives the comment. */
    public static function remove(int $tenantId, int $commentId, int $byUserId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_comments SET deleted_at = NOW()
             WHERE tenant_id = :tid AND id = :id AND author_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $commentId, 'uid' => $byUserId]);

        return $stmt->rowCount() === 1;
    }

    public static function count(int $tenantId, string $objectType, int $objectId, bool $clientVisibleOnly): int
    {
        return count(self::forObject($tenantId, $objectType, $objectId, $clientVisibleOnly));
    }
}
