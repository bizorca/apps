<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Tags, across every object type that can carry one.
 *
 * The point of a tag is that it CROSSES things. "Bank financing" is a task, a
 * couple of documents and an issue, and the useful question — "what is going
 * on with bank financing across all my clients?" — only has an answer if the
 * tag is one row referenced from several places rather than a string repeated
 * in several columns.
 *
 * Slugging is what keeps that true. "Cash flow", "cashflow" and "Cash Flow"
 * all normalise to `cash-flow` and land on the same tag, so a firm does not
 * quietly accumulate three spellings of one idea that no search can reconcile.
 */
final class Tags
{
    /** Object types that can carry a tag. An allowlist, so a typo denies. */
    public const TAGGABLE = ['task', 'document', 'issue', 'client_org', 'engagement'];

    public const MAX_LENGTH = 60;

    /**
     * Normalise a tag name for matching.
     *
     * Deliberately aggressive: case, punctuation and repeated spaces all
     * collapse. Two people typing the same idea differently must land on the
     * same tag, because a tag nobody can find twice is worse than no tag.
     */
    public static function slug(string $name): string
    {
        $slug = mb_strtolower(trim($name));
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return mb_substr($slug, 0, self::MAX_LENGTH);
    }

    /**
     * Find or create a tag. Returns its id.
     *
     * @throws \InvalidArgumentException when the name reduces to nothing.
     */
    public static function ensure(int $tenantId, string $name, ?int $userId = null): int
    {
        $name = trim($name);
        $slug = self::slug($name);

        if ($slug === '') {
            throw new \InvalidArgumentException('That is not a usable tag.');
        }

        $db = Database::conn();

        $stmt = $db->prepare('SELECT id FROM pl_tags WHERE tenant_id = :tid AND slug = :slug LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'slug' => $slug]);
        $row = $stmt->fetch();

        if ($row !== false) {
            return (int) $row['id'];
        }

        $db->prepare(
            'INSERT INTO pl_tags (tenant_id, name, slug, created_by) VALUES (:tid, :name, :slug, :by)'
        )->execute([
            'tid'  => $tenantId,
            'name' => mb_substr($name, 0, self::MAX_LENGTH),
            'slug' => $slug,
            'by'   => $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Attach a tag to an object. Idempotent.
     */
    public static function attach(int $tenantId, string $objectType, int $objectId, string $name, ?int $userId = null): int
    {
        self::assertTaggable($objectType);

        $tagId = self::ensure($tenantId, $name, $userId);

        Database::conn()->prepare(
            'INSERT IGNORE INTO pl_taggings (tenant_id, tag_id, object_type, object_id, tagged_by)
             VALUES (:tid, :tag, :type, :oid, :by)'
        )->execute([
            'tid' => $tenantId, 'tag' => $tagId, 'type' => $objectType,
            'oid' => $objectId, 'by' => $userId,
        ]);

        return $tagId;
    }

    public static function detach(int $tenantId, string $objectType, int $objectId, int $tagId): bool
    {
        $stmt = Database::conn()->prepare(
            'DELETE FROM pl_taggings
             WHERE tenant_id = :tid AND tag_id = :tag AND object_type = :type AND object_id = :oid'
        );
        $stmt->execute(['tid' => $tenantId, 'tag' => $tagId, 'type' => $objectType, 'oid' => $objectId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Replace an object's tags wholesale, from a comma-separated string.
     * This is what a form submits.
     */
    public static function sync(int $tenantId, string $objectType, int $objectId, string $commaSeparated, ?int $userId = null): void
    {
        self::assertTaggable($objectType);

        $wanted = [];

        foreach (explode(',', $commaSeparated) as $piece) {
            $slug = self::slug($piece);

            if ($slug !== '') {
                $wanted[$slug] = trim($piece);
            }
        }

        $db = Database::conn();
        $db->beginTransaction();

        try {
            $db->prepare(
                'DELETE FROM pl_taggings WHERE tenant_id = :tid AND object_type = :type AND object_id = :oid'
            )->execute(['tid' => $tenantId, 'type' => $objectType, 'oid' => $objectId]);

            foreach ($wanted as $name) {
                self::attach($tenantId, $objectType, $objectId, $name, $userId);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function forObject(int $tenantId, string $objectType, int $objectId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT t.* FROM pl_tags t
             JOIN pl_taggings g ON g.tag_id = t.id
             WHERE g.tenant_id = :tid AND g.object_type = :type AND g.object_id = :oid
             ORDER BY t.name ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'type' => $objectType, 'oid' => $objectId]);

        return $stmt->fetchAll();
    }

    /** Comma-separated, for an edit field. */
    public static function stringFor(int $tenantId, string $objectType, int $objectId): string
    {
        return implode(', ', array_column(self::forObject($tenantId, $objectType, $objectId), 'name'));
    }

    /**
     * Every tag in the firm, with how much carries it. Feeds the picker and
     * the tag index — the enumeration the string column could never do.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT t.*, COUNT(g.id) AS use_count
             FROM pl_tags t
             LEFT JOIN pl_taggings g ON g.tag_id = t.id
             WHERE t.tenant_id = :tid
             GROUP BY t.id
             ORDER BY use_count DESC, t.name ASC'
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function findBySlug(int $tenantId, string $slug): ?array
    {
        $stmt = Database::conn()->prepare('SELECT * FROM pl_tags WHERE tenant_id = :tid AND slug = :slug LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'slug' => self::slug($slug)]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Everything carrying a tag, across every object type and every client.
     *
     * This is the question the whole feature exists to answer, so it resolves
     * titles per type rather than returning bare ids — a list of
     * ('task', 47) helps nobody.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function objectsFor(int $tenantId, int $tagId): array
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT object_type, object_id FROM pl_taggings
             WHERE tenant_id = :tid AND tag_id = :tag
             ORDER BY object_type ASC, object_id DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'tag' => $tagId]);

        $rows = $stmt->fetchAll();
        $out = [];

        // One query per type rather than per row.
        $byType = [];
        foreach ($rows as $row) {
            $byType[(string) $row['object_type']][] = (int) $row['object_id'];
        }

        foreach ($byType as $type => $ids) {
            foreach (self::resolve($tenantId, $type, $ids) as $resolved) {
                $out[] = $resolved;
            }
        }

        return $out;
    }

    /**
     * Rename a tag. The slug moves with it, so a fixed typo really is fixed
     * everywhere rather than leaving the old spelling orphaned.
     *
     * @throws \RuntimeException if the new name collides with an existing tag.
     */
    public static function rename(int $tenantId, int $tagId, string $newName): bool
    {
        $slug = self::slug($newName);

        if ($slug === '') {
            throw new \InvalidArgumentException('That is not a usable tag.');
        }

        $existing = self::findBySlug($tenantId, $slug);

        if ($existing !== null && (int) $existing['id'] !== $tagId) {
            throw new \RuntimeException('There is already a tag called that. Merge them instead.');
        }

        $stmt = Database::conn()->prepare(
            'UPDATE pl_tags SET name = :name, slug = :slug WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute([
            'name' => mb_substr(trim($newName), 0, self::MAX_LENGTH),
            'slug' => $slug, 'tid' => $tenantId, 'id' => $tagId,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Fold one tag into another. Everything tagged with the source ends up
     * tagged with the target, and the source is removed.
     *
     * @return int Objects moved.
     */
    public static function merge(int $tenantId, int $fromTagId, int $intoTagId): int
    {
        if ($fromTagId === $intoTagId) {
            return 0;
        }

        $db = Database::conn();
        $db->beginTransaction();

        try {
            // INSERT IGNORE handles objects already carrying both tags.
            $moved = $db->prepare(
                'INSERT IGNORE INTO pl_taggings (tenant_id, tag_id, object_type, object_id, tagged_by)
                 SELECT tenant_id, :into, object_type, object_id, tagged_by
                 FROM pl_taggings WHERE tenant_id = :tid AND tag_id = :from'
            );
            $moved->execute(['into' => $intoTagId, 'tid' => $tenantId, 'from' => $fromTagId]);
            $count = $moved->rowCount();

            $db->prepare('DELETE FROM pl_tags WHERE tenant_id = :tid AND id = :id')
               ->execute(['tid' => $tenantId, 'id' => $fromTagId]);

            $db->commit();

            return $count;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function delete(int $tenantId, int $tagId): bool
    {
        $stmt = Database::conn()->prepare('DELETE FROM pl_tags WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['tid' => $tenantId, 'id' => $tagId]);

        return $stmt->rowCount() === 1;
    }


    // ------------------------------------------------------------- internals

    /**
     * @param array<int,int> $ids
     * @return array<int,array<string,mixed>>
     */
    private static function resolve(int $tenantId, string $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // Placeholders are generated from a count, never from input.
        $in = implode(',', array_fill(0, count($ids), '?'));

        // Each type knows where its title lives and which engagement it sits
        // under, so a result can say "task · Alpha Manufacturing" rather than
        // leaving the reader to guess.
        $sql = match ($type) {
            'task' => "SELECT t.id, t.title, 'task' AS object_type, o.name AS org_name, t.engagement_id, t.status
                       FROM pl_tasks t
                       JOIN pl_engagements e ON e.id = t.engagement_id
                       JOIN pl_client_orgs o ON o.id = e.client_org_id
                       WHERE t.tenant_id = ? AND t.id IN ({$in})",
            'document' => "SELECT d.id, d.title, 'document' AS object_type, o.name AS org_name, d.engagement_id, d.status
                           FROM pl_documents d
                           LEFT JOIN pl_client_orgs o ON o.id = d.client_org_id
                           WHERE d.tenant_id = ? AND d.id IN ({$in})",
            'issue' => "SELECT i.id, i.title, 'issue' AS object_type, o.name AS org_name, i.engagement_id, i.status
                        FROM pl_issues i
                        JOIN pl_engagements e ON e.id = i.engagement_id
                        JOIN pl_client_orgs o ON o.id = e.client_org_id
                        WHERE i.tenant_id = ? AND i.id IN ({$in})",
            'client_org' => "SELECT c.id, c.name AS title, 'client_org' AS object_type, c.name AS org_name,
                                    NULL AS engagement_id, c.status
                             FROM pl_client_orgs c WHERE c.tenant_id = ? AND c.id IN ({$in})",
            'engagement' => "SELECT e.id, e.title, 'engagement' AS object_type, o.name AS org_name,
                                    e.id AS engagement_id, e.status
                             FROM pl_engagements e
                             JOIN pl_client_orgs o ON o.id = e.client_org_id
                             WHERE e.tenant_id = ? AND e.id IN ({$in})",
            default => null,
        };

        if ($sql === null) {
            return [];
        }

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $ids));

        return $stmt->fetchAll();
    }

    private static function assertTaggable(string $objectType): void
    {
        if (!in_array($objectType, self::TAGGABLE, true)) {
            throw new \InvalidArgumentException('Cannot tag a "' . $objectType . '".');
        }
    }
}
