<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Auth\RateLimiter;
use Bizorca\Pilotage\Auth\Token;
use Bizorca\Pilotage\Core\Database;

/**
 * Documents: versions, the delivery lifecycle, requests, and share links.
 *
 * The rule that shapes every read method here: a client-side caller sees the
 * CURRENT version of a DELIVERED document and nothing else. Prior versions are
 * the coach's working history — a deliverable revised four times before it went
 * out should show the client one document, not four drafts.
 */
final class DocumentService
{
    public const SHARE_LINK_DEFAULT_DAYS = 14;

    /**
     * Create a document and its first version.
     *
     * @param array<string,mixed> $meta
     * @param array{tmp_name:string,name:string,size:int,error:int} $upload
     */
    public static function create(int $tenantId, array $meta, array $upload, ?int $userId = null): int
    {
        $context = (string) ($meta['context'] ?? '');

        if (!in_array($context, ['library', 'deliverable', 'client_file'], true)) {
            throw new \InvalidArgumentException('Unknown document context.');
        }

        $engagementId = $meta['engagement_id'] ?? null;

        if ($context === 'library' && $engagementId !== null) {
            throw new \InvalidArgumentException('A library template does not belong to an engagement.');
        }
        if ($context !== 'library' && $engagementId === null) {
            throw new \InvalidArgumentException('That document must belong to an engagement.');
        }

        $title = trim((string) ($meta['title'] ?? ''));

        // Store the bytes first: if the upload is rejected we want no row.
        $stored = Storage::put($tenantId, $upload);

        if ($title === '') {
            $title = $stored['original_name'];
        }

        $db = Database::conn();
        $db->beginTransaction();

        try {
            $db->prepare(
                'INSERT INTO pl_documents
                    (tenant_id, context, engagement_id, client_org_id, title, description, folder, status, created_by)
                 VALUES (:tid, :ctx, :eid, :org, :title, :desc, :folder, :status, :by)'
            )->execute([
                'tid'    => $tenantId,
                'ctx'    => $context,
                'eid'    => $engagementId,
                'org'    => $meta['client_org_id'] ?? null,
                'title'  => mb_substr($title, 0, 255),
                'desc'   => $meta['description'] ?? null,
                'folder' => $meta['folder'] ?? null,
                // A client's own upload is not a draft awaiting review; it is
                // simply theirs, and already in their hands.
                'status' => $context === 'client_file' ? 'delivered' : 'draft',
                'by'     => $userId,
            ]);

            $documentId = (int) $db->lastInsertId();
            $versionId = self::insertVersion($db, $tenantId, $documentId, 1, $stored, $userId);

            $db->prepare('UPDATE pl_documents SET current_version_id = :vid WHERE id = :id AND tenant_id = :tid')
               ->execute(['vid' => $versionId, 'id' => $documentId, 'tid' => $tenantId]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Storage::delete($tenantId, $stored['storage_key']);
            throw $e;
        }

        // A file arriving from the client side is news for the firm. A firm
        // uploading its own deliverable is not news to itself, and a library
        // template belongs to no engagement at all.
        if ($context === 'client_file' && $engagementId !== null) {
            Notifications::queueMany(
                $tenantId,
                Notifications::firmRecipients($tenantId, (int) $engagementId),
                'document.uploaded',
                'New file: "' . $title . '"',
                null,
                '/documents/' . $documentId,
                ['object_type' => 'document', 'object_id' => $documentId]
                    + Notifications::orgContext($tenantId, (int) $engagementId),
                $userId
            );
        }

        return $documentId;
    }

    /**
     * Add a new version (FR-7.3). The document identity is stable; this is a
     * new revision of it, not a new document.
     *
     * @param array{tmp_name:string,name:string,size:int,error:int} $upload
     */
    public static function addVersion(int $tenantId, int $documentId, array $upload, ?int $userId = null): int
    {
        $document = self::find($tenantId, $documentId);

        if ($document === null) {
            throw new \RuntimeException('No such document.');
        }

        $stored = Storage::put($tenantId, $upload);

        $db = Database::conn();
        $db->beginTransaction();

        try {
            $next = $db->prepare(
                'SELECT COALESCE(MAX(version_number), 0) + 1 AS n
                 FROM pl_document_versions WHERE tenant_id = :tid AND document_id = :did'
            );
            $next->execute(['tid' => $tenantId, 'did' => $documentId]);
            $versionNumber = (int) $next->fetch()['n'];

            $versionId = self::insertVersion($db, $tenantId, $documentId, $versionNumber, $stored, $userId);

            // A revision of something already delivered goes back to draft:
            // it has not been delivered until the coach says so again.
            $status = in_array((string) $document['status'], ['delivered', 'acknowledged'], true)
                ? 'draft'
                : (string) $document['status'];

            $db->prepare(
                'UPDATE pl_documents SET current_version_id = :vid, status = :status
                 WHERE id = :id AND tenant_id = :tid'
            )->execute(['vid' => $versionId, 'status' => $status, 'id' => $documentId, 'tid' => $tenantId]);

            $db->commit();

            return $versionId;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Storage::delete($tenantId, $stored['storage_key']);
            throw $e;
        }
    }

    /** Deliver the current version to the client (FR-7.2). */
    public static function deliver(int $tenantId, int $documentId, int $userId, ?string $note = null): bool
    {
        $document = self::find($tenantId, $documentId);

        if ($document === null || $document['current_version_id'] === null) {
            return false;
        }

        if ((string) $document['context'] !== 'deliverable') {
            throw new \RuntimeException('Only a deliverable is delivered.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_document_deliveries (tenant_id, document_id, version_id, delivered_by, note)
             VALUES (:tid, :did, :vid, :by, :note)'
        )->execute([
            'tid'  => $tenantId,
            'did'  => $documentId,
            'vid'  => (int) $document['current_version_id'],
            'by'   => $userId,
            'note' => $note,
        ]);

        $db->prepare("UPDATE pl_documents SET status = 'delivered' WHERE id = :id AND tenant_id = :tid")
           ->execute(['id' => $documentId, 'tid' => $tenantId]);

        // Transactional: a deliverable that needs acknowledging is the work
        // itself, not news about it, so this reaches the client whatever their
        // preferences say (FR-11.5).
        if ($document['engagement_id'] !== null) {
            Notifications::queueMany(
                $tenantId,
                Notifications::clientRecipients($tenantId, (int) $document['engagement_id']),
                'document.delivered',
                'Your advisor delivered "' . (string) $document['title'] . '"',
                $note,
                '/documents/' . $documentId,
                ['object_type' => 'document', 'object_id' => $documentId]
                    + Notifications::orgContext($tenantId, (int) $document['engagement_id']),
                $userId
            );
        }

        return true;
    }

    /**
     * Client acknowledges receipt. Timestamped and IP-logged, because advisors
     * get paid on delivery and periodically have to prove it happened.
     */
    public static function acknowledge(int $tenantId, int $documentId, int $userId, ?string $ip = null): bool
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            'UPDATE pl_document_deliveries
             SET acknowledged_at = NOW(), acknowledged_by = :uid, acknowledged_ip = :ip
             WHERE tenant_id = :tid AND document_id = :did AND acknowledged_at IS NULL
             ORDER BY delivered_at DESC LIMIT 1'
        );
        $stmt->execute([
            'uid' => $userId,
            'ip'  => $ip === null ? null : RateLimiter::packIp($ip),
            'tid' => $tenantId,
            'did' => $documentId,
        ]);

        if ($stmt->rowCount() !== 1) {
            return false;
        }

        $db->prepare("UPDATE pl_documents SET status = 'acknowledged' WHERE id = :id AND tenant_id = :tid")
           ->execute(['id' => $documentId, 'tid' => $tenantId]);

        $document = self::find($tenantId, $documentId);

        if ($document !== null && $document['engagement_id'] !== null) {
            Notifications::queueMany(
                $tenantId,
                Notifications::firmRecipients($tenantId, (int) $document['engagement_id']),
                'document.acknowledged',
                'Acknowledged: "' . (string) $document['title'] . '"',
                null,
                '/documents/' . $documentId,
                ['object_type' => 'document', 'object_id' => $documentId]
                    + Notifications::orgContext($tenantId, (int) $document['engagement_id']),
                $userId
            );
        }

        return true;
    }


    /** @return array<string,mixed>|null */
    public static function find(int $tenantId, int $documentId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_documents WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $documentId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function version(int $tenantId, int $versionId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_document_versions WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $versionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Versions of a document.
     *
     * @param bool $clientSide True returns ONLY the current version. Prior
     *        versions are the coach's working history and are never a client's
     *        business — that is the whole point of versioning here.
     * @return array<int,array<string,mixed>>
     */
    public static function versions(int $tenantId, int $documentId, bool $clientSide): array
    {
        if ($clientSide) {
            $document = self::find($tenantId, $documentId);

            if ($document === null || $document['current_version_id'] === null) {
                return [];
            }

            $current = self::version($tenantId, (int) $document['current_version_id']);

            return $current === null ? [] : [$current];
        }

        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_document_versions
             WHERE tenant_id = :tid AND document_id = :did
             ORDER BY version_number DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'did' => $documentId]);

        return $stmt->fetchAll();
    }

    /**
     * Documents on an engagement.
     *
     * @param bool $clientSide Hides drafts and anything in review — a client
     *        should not see a deliverable being worked on.
     * @return array<int,array<string,mixed>>
     */
    public static function forEngagement(int $tenantId, int $engagementId, bool $clientSide, ?string $context = null): array
    {
        $sql = 'SELECT d.*, v.original_name, v.byte_size, v.mime_type, v.version_number
                FROM pl_documents d
                LEFT JOIN pl_document_versions v ON v.id = d.current_version_id
                WHERE d.tenant_id = :tid AND d.engagement_id = :eid';

        $params = ['tid' => $tenantId, 'eid' => $engagementId];

        if ($context !== null) {
            $sql .= ' AND d.context = :ctx';
            $params['ctx'] = $context;
        }

        if ($clientSide) {
            $sql .= " AND d.status IN ('delivered','acknowledged')";
        }

        $sql .= ' ORDER BY d.folder IS NULL, d.folder ASC, d.created_at DESC';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public static function library(int $tenantId, string $search = ''): array
    {
        $search = trim($search);

        if ($search === '') {
            $stmt = Database::conn()->prepare(
                "SELECT d.*, v.original_name, v.byte_size
                 FROM pl_documents d
                 LEFT JOIN pl_document_versions v ON v.id = d.current_version_id
                 WHERE d.tenant_id = :tid AND d.context = 'library'
                 ORDER BY d.title ASC"
            );
            $stmt->execute(['tid' => $tenantId]);

            return $stmt->fetchAll();
        }

        // Distinct placeholders per occurrence: emulated prepares are off, so
        // a named parameter cannot be reused.
        $stmt = Database::conn()->prepare(
            "SELECT d.*, v.original_name, v.byte_size
             FROM pl_documents d
             LEFT JOIN pl_document_versions v ON v.id = d.current_version_id
             WHERE d.tenant_id = :tid AND d.context = 'library'
               -- Tags come from the real model rather than a LIKE against a
               -- comma-separated string (dropped in 021). That substring match
               -- was one of the three failings that motivated replacing it: a
               -- search for bank also matched bankruptcy and embankment.
               AND (d.title LIKE :q1 OR d.description LIKE :q2 OR v.original_name LIKE :q4
                    OR EXISTS (
                       SELECT 1 FROM pl_taggings tg
                       JOIN pl_tags tag ON tag.id = tg.tag_id AND tag.tenant_id = tg.tenant_id
                       WHERE tg.tenant_id = d.tenant_id AND tg.object_type = 'document'
                         AND tg.object_id = d.id AND tag.name LIKE :q3
                    ))
             ORDER BY d.title ASC LIMIT 100"
        );

        $like = '%' . $search . '%';
        $stmt->execute(['tid' => $tenantId, 'q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]);

        return $stmt->fetchAll();
    }

    public static function logAccess(int $tenantId, int $documentId, ?int $versionId, ?int $userId, string $via, ?string $ip): void
    {
        Database::conn()->prepare(
            'INSERT INTO pl_document_access_log (tenant_id, document_id, version_id, user_id, via, ip)
             VALUES (:tid, :did, :vid, :uid, :via, :ip)'
        )->execute([
            'tid' => $tenantId,
            'did' => $documentId,
            'vid' => $versionId,
            'uid' => $userId,
            'via' => $via,
            'ip'  => $ip === null ? null : RateLimiter::packIp($ip),
        ]);
    }

    // ------------------------------------------------------------- requests

    /**
     * @param array<int,array{label:string,hint?:string,required?:bool}> $items
     */
    public static function createRequest(
        int $tenantId,
        int $engagementId,
        string $title,
        array $items,
        ?string $dueOn = null,
        ?int $userId = null,
        ?string $note = null
    ): int {
        $title = trim($title);

        if ($title === '') {
            throw new \InvalidArgumentException('A request needs a title.');
        }

        $items = array_values(array_filter($items, static fn (array $i): bool => trim((string) ($i['label'] ?? '')) !== ''));

        if ($items === []) {
            throw new \InvalidArgumentException('A request needs at least one item.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_document_requests (tenant_id, engagement_id, title, note, due_on, created_by)
             VALUES (:tid, :eid, :title, :note, :due, :by)'
        )->execute([
            'tid' => $tenantId, 'eid' => $engagementId, 'title' => $title,
            'note' => $note, 'due' => $dueOn, 'by' => $userId,
        ]);

        $requestId = (int) $db->lastInsertId();

        $insert = $db->prepare(
            'INSERT INTO pl_document_request_items (tenant_id, request_id, position, label, hint, required)
             VALUES (:tid, :rid, :pos, :label, :hint, :req)'
        );

        foreach ($items as $i => $item) {
            $insert->execute([
                'tid'   => $tenantId,
                'rid'   => $requestId,
                'pos'   => $i,
                'label' => mb_substr(trim((string) $item['label']), 0, 255),
                'hint'  => $item['hint'] ?? null,
                'req'   => ($item['required'] ?? true) ? 1 : 0,
            ]);
        }

        // Transactional: being asked for a document IS the work.
        Notifications::queueMany(
            $tenantId,
            Notifications::clientRecipients($tenantId, $engagementId),
            'document.requested',
            'Your advisor needs ' . count($items) . ' thing' . (count($items) === 1 ? '' : 's') . ': ' . $title,
            $note,
            '/engagements/' . $engagementId . '/documents',
            ['object_type' => 'document_request', 'object_id' => $requestId]
                + Notifications::orgContext($tenantId, $engagementId),
            $userId
        );

        return $requestId;
    }

    /**
     * Fulfil one item of a request by attaching an uploaded document.
     * Completes the request once every REQUIRED item is in.
     */
    public static function fulfilRequestItem(int $tenantId, int $itemId, int $documentId, int $userId): bool
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            'UPDATE pl_document_request_items
             SET document_id = :did, fulfilled_at = NOW(), fulfilled_by = :uid
             WHERE tenant_id = :tid AND id = :id AND document_id IS NULL'
        );
        $stmt->execute(['did' => $documentId, 'uid' => $userId, 'tid' => $tenantId, 'id' => $itemId]);

        if ($stmt->rowCount() !== 1) {
            return false;
        }

        $req = $db->prepare('SELECT request_id FROM pl_document_request_items WHERE tenant_id = :tid AND id = :id');
        $req->execute(['tid' => $tenantId, 'id' => $itemId]);
        $requestId = (int) $req->fetch()['request_id'];

        $outstanding = $db->prepare(
            'SELECT COUNT(*) AS c FROM pl_document_request_items
             WHERE tenant_id = :tid AND request_id = :rid AND required = 1 AND document_id IS NULL'
        );
        $outstanding->execute(['tid' => $tenantId, 'rid' => $requestId]);

        if ((int) $outstanding->fetch()['c'] === 0) {
            $db->prepare(
                "UPDATE pl_document_requests SET status = 'complete', completed_at = NOW()
                 WHERE tenant_id = :tid AND id = :id AND status = 'open'"
            )->execute(['tid' => $tenantId, 'id' => $requestId]);
        }

        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public static function requests(int $tenantId, int $engagementId, bool $openOnly = false): array
    {
        $sql = 'SELECT * FROM pl_document_requests WHERE tenant_id = :tid AND engagement_id = :eid';

        if ($openOnly) {
            $sql .= " AND status = 'open'";
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        $requests = $stmt->fetchAll();

        $items = Database::conn()->prepare(
            'SELECT * FROM pl_document_request_items
             WHERE tenant_id = :tid AND request_id = :rid ORDER BY position ASC'
        );

        foreach ($requests as $i => $request) {
            $items->execute(['tid' => $tenantId, 'rid' => (int) $request['id']]);
            $rows = $items->fetchAll();

            $done = count(array_filter($rows, static fn (array $r): bool => $r['document_id'] !== null));

            $requests[$i]['items'] = $rows;
            $requests[$i]['done_count'] = $done;
            $requests[$i]['total_count'] = count($rows);
        }

        return $requests;
    }

    // ---------------------------------------------------------- share links

    /**
     * @return array{plaintext:string, id:int, expires_at:string}
     */
    public static function createShareLink(
        int $tenantId,
        int $documentId,
        ?int $userId = null,
        ?string $label = null,
        int $days = self::SHARE_LINK_DEFAULT_DAYS,
        ?int $maxViews = null
    ): array {
        $document = self::find($tenantId, $documentId);

        if ($document === null) {
            throw new \RuntimeException('No such document.');
        }

        $days = max(1, min($days, 90));   // hard ceiling; a link that never dies is not a share
        $token = Token::create();
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_share_links
                (tenant_id, document_id, version_id, selector, verifier_hash, label, created_by, expires_at, max_views)
             VALUES (:tid, :did, :vid, :sel, :hash, :label, :by, :exp, :max)'
        )->execute([
            'tid'   => $tenantId,
            'did'   => $documentId,
            'vid'   => $document['current_version_id'],
            'sel'   => $token['selector'],
            'hash'  => $token['verifier_hash'],
            'label' => $label,
            'by'    => $userId,
            'exp'   => $expiresAt,
            'max'   => $maxViews,
        ]);

        return [
            'plaintext'  => $token['plaintext'],
            'id'         => (int) $db->lastInsertId(),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Resolve a share link. Null for anything invalid — expired, revoked,
     * over its view cap, tampered, or unknown. One generic outcome; telling a
     * stranger which of those it was is telling them something.
     *
     * @return array{link:array<string,mixed>, document:array<string,mixed>, version:array<string,mixed>}|null
     */
    public static function resolveShareLink(string $plaintext, ?string $ip = null, ?string $userAgent = null): ?array
    {
        $parts = Token::split($plaintext);

        if ($parts === null) {
            return null;
        }

        [$selector, $verifier] = $parts;

        $db = Database::conn();

        $stmt = $db->prepare('SELECT * FROM pl_share_links WHERE selector = :sel LIMIT 1');
        $stmt->execute(['sel' => $selector]);
        $link = $stmt->fetch();

        if ($link === false || !Token::verify($verifier, (string) $link['verifier_hash'])) {
            return null;
        }

        if ($link['revoked_at'] !== null || strtotime((string) $link['expires_at']) <= time()) {
            return null;
        }

        if ($link['max_views'] !== null && (int) $link['view_count'] >= (int) $link['max_views']) {
            return null;
        }

        $tenantId = (int) $link['tenant_id'];
        $document = self::find($tenantId, (int) $link['document_id']);

        if ($document === null) {
            return null;
        }

        $versionId = $link['version_id'] !== null
            ? (int) $link['version_id']
            : ($document['current_version_id'] === null ? null : (int) $document['current_version_id']);

        $version = $versionId === null ? null : self::version($tenantId, $versionId);

        if ($version === null) {
            return null;
        }

        $db->prepare('UPDATE pl_share_links SET view_count = view_count + 1 WHERE id = :id')
           ->execute(['id' => (int) $link['id']]);

        $db->prepare(
            'INSERT INTO pl_share_link_views (tenant_id, link_id, ip, user_agent)
             VALUES (:tid, :lid, :ip, :ua)'
        )->execute([
            'tid' => $tenantId,
            'lid' => (int) $link['id'],
            'ip'  => $ip === null ? null : RateLimiter::packIp($ip),
            'ua'  => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ]);

        self::logAccess($tenantId, (int) $document['id'], $versionId, null, 'share_link', $ip);

        return ['link' => $link, 'document' => $document, 'version' => $version];
    }

    public static function revokeShareLink(int $tenantId, int $linkId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_share_links SET revoked_at = NOW()
             WHERE tenant_id = :tid AND id = :id AND revoked_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $linkId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public static function shareLinks(int $tenantId, int $documentId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_share_links WHERE tenant_id = :tid AND document_id = :did ORDER BY created_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'did' => $documentId]);

        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------- internals

    /** @param array<string,mixed> $stored */
    private static function insertVersion(\PDO $db, int $tenantId, int $documentId, int $number, array $stored, ?int $userId): int
    {
        $db->prepare(
            'INSERT INTO pl_document_versions
                (tenant_id, document_id, version_number, original_name, storage_key, mime_type, byte_size, sha256, uploaded_by)
             VALUES (:tid, :did, :num, :name, :key, :mime, :size, :hash, :by)'
        )->execute([
            'tid'  => $tenantId,
            'did'  => $documentId,
            'num'  => $number,
            'name' => $stored['original_name'],
            'key'  => $stored['storage_key'],
            'mime' => $stored['mime_type'],
            'size' => $stored['byte_size'],
            'hash' => $stored['sha256'],
            'by'   => $userId,
        ]);

        return (int) $db->lastInsertId();
    }
}
