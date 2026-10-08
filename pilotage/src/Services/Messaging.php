<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Auth\Token;
use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Database;

/**
 * Threads, mentions, and reply-by-email (M8).
 *
 * Asynchronous by design. A coach with forty clients cannot honour the
 * expectation of immediacy a chat box creates, so this is email-shaped:
 * considered replies, notified, never a typing indicator.
 *
 * The client_visible wall runs through everything here, same as sessions and
 * comments — a coach can think out loud on a client-facing thread without the
 * client reading it.
 */
final class Messaging
{
    public const REPLY_TOKEN_DAYS = 90;

    public static function createThread(
        int $tenantId,
        int $engagementId,
        string $subject,
        string $body,
        array $author,
        bool $clientVisible = true
    ): int {
        $subject = trim($subject);

        if ($subject === '') {
            throw new \InvalidArgumentException('A thread needs a subject.');
        }

        if (trim($body) === '') {
            throw new \InvalidArgumentException('A thread needs a first message.');
        }

        $db = Database::conn();
        $db->beginTransaction();

        try {
            $db->prepare(
                'INSERT INTO pl_threads (tenant_id, engagement_id, subject, created_by)
                 VALUES (:tid, :eid, :subject, :by)'
            )->execute([
                'tid' => $tenantId, 'eid' => $engagementId,
                'subject' => mb_substr($subject, 0, 255), 'by' => (int) ($author['id'] ?? 0) ?: null,
            ]);

            $threadId = (int) $db->lastInsertId();
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        self::addParticipant($tenantId, $threadId, (int) $author['id']);
        self::post($tenantId, $threadId, $body, $author, $clientVisible);

        return $threadId;
    }

    /** Post to a thread. Bumps the denormalized counters and records mentions. */
    public static function post(
        int $tenantId,
        int $threadId,
        string $body,
        ?array $author,
        bool $clientVisible = true,
        string $via = 'web'
    ): int {
        $body = trim($body);

        if ($body === '') {
            throw new \InvalidArgumentException('An empty message is not a message.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_messages (tenant_id, thread_id, author_id, author_label, body, client_visible, via)
             VALUES (:tid, :thread, :aid, :label, :body, :vis, :via)'
        )->execute([
            'tid'    => $tenantId,
            'thread' => $threadId,
            'aid'    => $author === null ? null : (((int) ($author['id'] ?? 0)) ?: null),
            'label'  => $author === null ? null : (string) ($author['name'] ?? ''),
            'body'   => $body,
            'vis'    => $clientVisible ? 1 : 0,
            'via'    => $via,
        ]);

        $messageId = (int) $db->lastInsertId();

        $db->prepare(
            'UPDATE pl_threads SET last_message_at = NOW(), message_count = message_count + 1
             WHERE tenant_id = :tid AND id = :id'
        )->execute(['tid' => $tenantId, 'id' => $threadId]);

        if ($author !== null && !empty($author['id'])) {
            self::addParticipant($tenantId, $threadId, (int) $author['id']);
        }

        // Mentions are only recorded for people who can already see the message.
        $mentioned = self::recordMentions($tenantId, 'message', $messageId, $body, $author, $threadId, $clientVisible);

        self::notifyThread($tenantId, $threadId, $messageId, $body, $author, $clientVisible, $mentioned);

        return $messageId;
    }

    /**
     * Tell the thread (M11).
     *
     * Two rules worth stating, because both are easy to get subtly wrong:
     *
     * A mention beats a message. Someone named in a post gets ONE notification,
     * the mention, not a mention and a "new message in a thread you are in".
     * Two emails about the same sentence is exactly the noise M11 exists to
     * remove.
     *
     * The client-visible flag is honoured here as well as in the reader. An
     * internal note that mentions a coach must not queue anything for the
     * client side — a notification title is a leak in a smaller font.
     *
     * @param array<string,mixed>|null $author
     * @param array<int,int> $mentioned
     */
    private static function notifyThread(
        int $tenantId,
        int $threadId,
        int $messageId,
        string $body,
        ?array $author,
        bool $clientVisible,
        array $mentioned
    ): void {
        $thread = self::thread($tenantId, $threadId);

        if ($thread === null) {
            return;
        }

        $engagementId = (int) $thread['engagement_id'];

        $stmt = Database::conn()->prepare(
            "SELECT p.user_id, u.client_org_id
             FROM pl_thread_participants p
             JOIN pl_users u ON u.id = p.user_id AND u.tenant_id = p.tenant_id
             WHERE p.tenant_id = :tid AND p.thread_id = :thread AND u.status = 'active'"
        );
        $stmt->execute(['tid' => $tenantId, 'thread' => $threadId]);

        $authorId = $author === null ? null : (((int) ($author['id'] ?? 0)) ?: null);
        $authorName = trim((string) ($author['name'] ?? '')) ?: 'Someone';
        $subject = trim((string) ($thread['subject'] ?? '')) ?: 'your conversation';
        $excerpt = mb_substr(preg_replace('/\s+/u', ' ', $body) ?? $body, 0, 140);
        // The thread's own page. The original built
        // /engagements/{id}/messages/{thread}, which no route matches, so every
        // message notification (email and in-app) opened a 404.
        $link = '/threads/' . $threadId;

        $context = [
            'object_type' => 'message',
            'object_id'   => $messageId,
        ];

        // The org, for digest grouping. A thread belongs to an engagement,
        // which belongs to exactly one client organization.
        $orgStmt = Database::conn()->prepare(
            'SELECT client_org_id FROM pl_engagements WHERE tenant_id = :tid AND id = :eid'
        );
        $orgStmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);
        $orgRow = $orgStmt->fetch();

        if ($orgRow !== false) {
            $context['client_org_id'] = (int) $orgRow['client_org_id'];
        }

        $mentionedSet = array_flip(array_map('intval', $mentioned));

        foreach ($stmt->fetchAll() as $row) {
            $userId = (int) $row['user_id'];

            if ($authorId !== null && $userId === $authorId) {
                continue;   // nobody needs telling what they just wrote
            }

            $isClientSide = $row['client_org_id'] !== null;

            if (!$clientVisible && $isClientSide) {
                continue;   // an internal note is internal, including its title
            }

            if (isset($mentionedSet[$userId])) {
                Notifications::queue(
                    $tenantId, $userId, 'message.mentioned',
                    $authorName . ' mentioned you in "' . $subject . '"',
                    $excerpt, $link, $context
                );
                continue;
            }

            Notifications::queue(
                $tenantId, $userId, 'message.received',
                $authorName . ' replied in "' . $subject . '"',
                $excerpt, $link, $context
            );
        }
    }

    /**
     * @param bool $clientVisibleOnly Callers must say which side they render
     *        for; defaulting would make every new call site a potential leak.
     * @return array<int,array<string,mixed>>
     */
    public static function messages(int $tenantId, int $threadId, bool $clientVisibleOnly): array
    {
        $sql = 'SELECT * FROM pl_messages
                WHERE tenant_id = :tid AND thread_id = :thread AND deleted_at IS NULL';

        if ($clientVisibleOnly) {
            $sql .= ' AND client_visible = 1';
        }

        $sql .= ' ORDER BY created_at ASC, id ASC';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'thread' => $threadId]);

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public static function threads(int $tenantId, int $engagementId, bool $clientVisibleOnly): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM pl_threads
             WHERE tenant_id = :tid AND engagement_id = :eid AND status = 'open'
             ORDER BY last_message_at DESC, id DESC"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        $threads = $stmt->fetchAll();

        if (!$clientVisibleOnly) {
            return $threads;
        }

        // A thread whose every message is internal must not appear at all —
        // its subject alone would leak that a conversation is happening.
        return array_values(array_filter($threads, static function (array $t) use ($tenantId): bool {
            return self::messages($tenantId, (int) $t['id'], true) !== [];
        }));
    }

    /** @return array<string,mixed>|null */
    public static function thread(int $tenantId, int $threadId): ?array
    {
        $stmt = Database::conn()->prepare('SELECT * FROM pl_threads WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $threadId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function addParticipant(int $tenantId, int $threadId, int $userId): void
    {
        Database::conn()->prepare(
            'INSERT IGNORE INTO pl_thread_participants (tenant_id, thread_id, user_id)
             VALUES (:tid, :thread, :uid)'
        )->execute(['tid' => $tenantId, 'thread' => $threadId, 'uid' => $userId]);
    }

    public static function markRead(int $tenantId, int $threadId, int $userId): void
    {
        self::addParticipant($tenantId, $threadId, $userId);

        // Record the highest message id seen, not the clock. See the note on
        // last_read_message_id in 008_messaging.sql.
        Database::conn()->prepare(
            'UPDATE pl_thread_participants p
             SET p.last_read_message_id = (
                    SELECT COALESCE(MAX(m.id), 0) FROM pl_messages m
                    WHERE m.thread_id = :thread2 AND m.tenant_id = :tid2
                 ),
                 p.last_read_at = NOW()
             WHERE p.tenant_id = :tid AND p.thread_id = :thread AND p.user_id = :uid'
        )->execute([
            'tid' => $tenantId, 'tid2' => $tenantId,
            'thread' => $threadId, 'thread2' => $threadId,
            'uid' => $userId,
        ]);
    }

    /** Unread count for a user, respecting the client-visible wall. */
    public static function unreadCount(int $tenantId, int $userId, bool $clientVisibleOnly): int
    {
        $sql = 'SELECT COUNT(*) AS c
                FROM pl_messages m
                JOIN pl_thread_participants p
                  ON p.thread_id = m.thread_id AND p.user_id = :uid AND p.tenant_id = m.tenant_id
                WHERE m.tenant_id = :tid
                  AND m.deleted_at IS NULL
                  AND (m.author_id IS NULL OR m.author_id <> :uid2)
                  AND (p.last_read_message_id IS NULL OR m.id > p.last_read_message_id)';

        if ($clientVisibleOnly) {
            $sql .= ' AND m.client_visible = 1';
        }

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId, 'uid2' => $userId]);

        return (int) $stmt->fetch()['c'];
    }

    /** Soft delete (FR-8.6). Nothing is destroyed while an engagement is live. */
    public static function deleteMessage(int $tenantId, int $messageId, int $byUserId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_messages SET deleted_at = NOW()
             WHERE tenant_id = :tid AND id = :id AND author_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $messageId, 'uid' => $byUserId]);

        return $stmt->rowCount() === 1;
    }

    // ------------------------------------------------------------- mentions

    /**
     * Record @-mentions found in a body (FR-8.4).
     *
     * Only people who can ALREADY see the object are mentionable. Mentioning
     * someone into a message they cannot read would either leak it to them or
     * notify them about something they then cannot open — both bad.
     *
     * @return int[] Mentioned user ids.
     */
    public static function recordMentions(
        int $tenantId,
        string $objectType,
        int $objectId,
        string $body,
        ?array $author,
        ?int $threadId = null,
        bool $clientVisible = true,
        ?string $contextLabel = null
    ): array {
        if (!preg_match_all('/@([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}|[a-z0-9_.-]{2,40})/i', $body, $m)) {
            return [];
        }

        $handles = array_unique($m[1]);

        if ($handles === []) {
            return [];
        }

        $db = Database::conn();
        $mentioned = [];

        $insert = $db->prepare(
            'INSERT INTO pl_mentions (tenant_id, object_type, object_id, mentioned_user_id, by_user_id, context_label)
             VALUES (:tid, :type, :oid, :uid, :by, :label)'
        );

        foreach ($handles as $handle) {
            $user = self::resolveHandle($tenantId, (string) $handle);

            if ($user === null) {
                continue;
            }

            // An internal message can only mention firm-side people.
            if (!$clientVisible && $user['client_org_id'] !== null) {
                continue;
            }

            $insert->execute([
                'tid'   => $tenantId,
                'type'  => $objectType,
                'oid'   => $objectId,
                'uid'   => (int) $user['id'],
                'by'    => $author === null ? null : (((int) ($author['id'] ?? 0)) ?: null),
                'label' => $contextLabel,
            ]);

            $mentioned[] = (int) $user['id'];

            if ($threadId !== null) {
                self::addParticipant($tenantId, $threadId, (int) $user['id']);
            }
        }

        return $mentioned;
    }

    /** @return array<int,array<string,mixed>> */
    public static function unreadMentions(int $tenantId, int $userId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_mentions
             WHERE tenant_id = :tid AND mentioned_user_id = :uid AND read_at IS NULL
             ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);

        return $stmt->fetchAll();
    }

    public static function markMentionsRead(int $tenantId, int $userId): int
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_mentions SET read_at = NOW()
             WHERE tenant_id = :tid AND mentioned_user_id = :uid AND read_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);

        return $stmt->rowCount();
    }

    // -------------------------------------------------------- reply by email

    /**
     * A reply-to address that identifies the thread AND the user.
     *
     * The token is the authority, not the From header — From is trivially
     * forged, and treating it as identity would let anyone post as anyone by
     * guessing an address.
     */
    public static function replyAddress(int $tenantId, string $objectType, int $objectId, int $userId): string
    {
        $token = Token::create();

        Database::conn()->prepare(
            'INSERT INTO pl_reply_tokens (tenant_id, selector, verifier_hash, object_type, object_id, user_id, expires_at)
             VALUES (:tid, :sel, :hash, :type, :oid, :uid, DATE_ADD(NOW(), INTERVAL :days DAY))'
        )->execute([
            'tid'  => $tenantId,
            'sel'  => $token['selector'],
            'hash' => $token['verifier_hash'],
            'type' => $objectType,
            'oid'  => $objectId,
            'uid'  => $userId,
            'days' => self::REPLY_TOKEN_DAYS,
        ]);

        $domain = (string) Config::get('domains.base');

        return 'reply+' . str_replace('.', '~', $token['plaintext']) . '@' . $domain;
    }

    /**
     * Handle an inbound reply.
     *
     * @return int|null The created message id, or null if the token is not valid.
     */
    public static function acceptInboundReply(string $replyAddress, string $body): ?int
    {
        if (!preg_match('/reply\+([^@]+)@/i', $replyAddress, $m)) {
            return null;
        }

        $plaintext = str_replace('~', '.', $m[1]);
        $parts = Token::split($plaintext);

        if ($parts === null) {
            return null;
        }

        [$selector, $verifier] = $parts;

        $db = Database::conn();

        $stmt = $db->prepare('SELECT * FROM pl_reply_tokens WHERE selector = :sel LIMIT 1');
        $stmt->execute(['sel' => $selector]);
        $token = $stmt->fetch();

        if ($token === false || !Token::verify($verifier, (string) $token['verifier_hash'])) {
            return null;
        }

        if ($token['revoked_at'] !== null || strtotime((string) $token['expires_at']) <= time()) {
            return null;
        }

        $body = self::stripQuotedReply($body);

        if (trim($body) === '') {
            return null;
        }

        $tenantId = (int) $token['tenant_id'];

        $user = $db->prepare('SELECT id, name, client_org_id FROM pl_users WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $user->execute(['tid' => $tenantId, 'id' => (int) $token['user_id']]);
        $author = $user->fetch();

        if ($author === false) {
            return null;
        }

        // An emailed reply is always client-visible. A coach cannot safely make
        // an internal note by email — there is no checkbox in an inbox, and
        // guessing wrong would put a private thought in front of the client.
        if ((string) $token['object_type'] === 'thread') {
            return self::post($tenantId, (int) $token['object_id'], $body, $author, true, 'email');
        }

        return Comments::add($tenantId, (string) $token['object_type'], (int) $token['object_id'], $body, $author, true);
    }

    /**
     * Strip the quoted history most mail clients append. Crude on purpose:
     * over-trimming loses a line, under-trimming pastes an entire thread into
     * the record, and the second is much worse to read six months later.
     */
    public static function stripQuotedReply(string $body): string
    {
        $body = str_replace("\r\n", "\n", $body);
        $lines = explode("\n", $body);
        $kept = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '>')) {
                break;
            }
            if (preg_match('/^On .+ wrote:$/i', $trimmed)) {
                break;
            }
            if (preg_match('/^-{2,}\s*Original Message\s*-{2,}$/i', $trimmed)) {
                break;
            }
            if ($trimmed === '--' || preg_match('/^_{5,}$/', $trimmed)) {
                break;
            }

            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }

    // ----------------------------------------------------------- internals

    /** @return array<string,mixed>|null */
    private static function resolveHandle(int $tenantId, string $handle): ?array
    {
        $db = Database::conn();

        if (str_contains($handle, '@')) {
            $stmt = $db->prepare(
                "SELECT id, name, client_org_id FROM pl_users
                 WHERE tenant_id = :tid AND email = :h AND status = 'active' LIMIT 1"
            );
            $stmt->execute(['tid' => $tenantId, 'h' => mb_strtolower($handle)]);
        } else {
            // Match the local part of the email, which is what people type.
            $stmt = $db->prepare(
                "SELECT id, name, client_org_id FROM pl_users
                 WHERE tenant_id = :tid AND SUBSTRING_INDEX(email, '@', 1) = :h AND status = 'active' LIMIT 1"
            );
            $stmt->execute(['tid' => $tenantId, 'h' => mb_strtolower($handle)]);
        }

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
