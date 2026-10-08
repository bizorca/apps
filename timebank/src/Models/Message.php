<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Message extends BaseModel
{
    protected string $table = 'tm_messages';

    /**
     * Send a new message, inserting both the messages row and one
     * message_recipients row per recipient. Returns the new message id.
     *
     * @param int[]  $recipientIds  One or more recipient member IDs
     */
    public function send(
        int $senderId,
        int $tenantId,
        array $recipientIds,
        string $subject,
        string $body,
        ?int $groupId = null
    ): int|string {
        $messageId = DB::insert('tm_messages', [
            'tenant_id' => $tenantId,
            'sender_id' => $senderId,
            'subject'   => $subject,
            'body'      => $body,
            'group_id'  => $groupId,
        ]);

        foreach ($recipientIds as $recipientId) {
            DB::insert('tm_message_recipients', [
                'message_id'   => $messageId,
                'recipient_id' => $recipientId,
                'is_read'      => 0,
            ]);
        }

        return $messageId;
    }

    /**
     * Paginated inbox for a member: messages they received, with sender name and read status.
     */
    public function getInbox(int $memberId, int $tenantId, int $page = 1, int $perPage = 25): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $countRow = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM `tm_message_recipients` mr
             JOIN `tm_messages` m ON m.id = mr.message_id
             WHERE mr.recipient_id = ? AND m.tenant_id = ?",
            [$memberId, $tenantId]
        );
        $total = $countRow ? (int) $countRow['cnt'] : 0;
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = DB::fetchAll(
            "SELECT
                m.*,
                mr.is_read,
                mr.read_at,
                COALESCE(s.display_name, CONCAT(s.first_name, ' ', s.last_name)) AS sender_name,
                s.avatar_path AS sender_avatar
             FROM `tm_message_recipients` mr
             JOIN `tm_messages` m  ON m.id  = mr.message_id
             JOIN `tm_members`  s  ON s.id  = m.sender_id
             WHERE mr.recipient_id = ? AND m.tenant_id = ?
             ORDER BY m.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$memberId, $tenantId]
        );

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }

    /**
     * Paginated sent-messages view for a member.
     */
    public function getSent(int $memberId, int $tenantId, int $page = 1, int $perPage = 25): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $countRow = DB::fetch(
            "SELECT COUNT(*) AS cnt FROM `tm_messages` WHERE sender_id = ? AND tenant_id = ?",
            [$memberId, $tenantId]
        );
        $total = $countRow ? (int) $countRow['cnt'] : 0;
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = DB::fetchAll(
            "SELECT
                m.*,
                (SELECT COUNT(*) FROM `tm_message_recipients` mr WHERE mr.message_id = m.id)      AS recipient_count,
                (SELECT COUNT(*) FROM `tm_message_recipients` mr WHERE mr.message_id = m.id AND mr.is_read = 1) AS read_count
             FROM `tm_messages` m
             WHERE m.sender_id = ? AND m.tenant_id = ?
             ORDER BY m.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$memberId, $tenantId]
        );

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }

    /**
     * Mark a message as read for a specific recipient.
     */
    public function markRead(int $messageId, int $memberId): int
    {
        return DB::update(
            'tm_message_recipients',
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            ['message_id' => $messageId, 'recipient_id' => $memberId]
        );
    }

    /**
     * Count of unread messages for a member in a tenant.
     */
    public function getUnreadCount(int $memberId, int $tenantId): int
    {
        $row = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM `tm_message_recipients` mr
             JOIN `tm_messages` m ON m.id = mr.message_id
             WHERE mr.recipient_id = ? AND m.tenant_id = ? AND mr.is_read = 0",
            [$memberId, $tenantId]
        );

        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Fetch a single message with its full recipient list.
     * Enforces access: the requesting member must be the sender or a recipient.
     *
     * @return array{message: array, recipients: array}|false
     */
    public function getThread(int $messageId, int $memberId): array|false
    {
        $message = DB::fetch(
            "SELECT
                m.*,
                COALESCE(s.display_name, CONCAT(s.first_name, ' ', s.last_name)) AS sender_name,
                s.avatar_path AS sender_avatar
             FROM `tm_messages` m
             JOIN `tm_members` s ON s.id = m.sender_id
             WHERE m.id = ? LIMIT 1",
            [$messageId]
        );

        if (!$message) {
            return false;
        }

        // Verify access: sender or recipient
        $isSender = (int) $message['sender_id'] === $memberId;

        $recipientCheck = DB::fetch(
            "SELECT id FROM `tm_message_recipients` WHERE message_id = ? AND recipient_id = ? LIMIT 1",
            [$messageId, $memberId]
        );

        if (!$isSender && !$recipientCheck) {
            return false;
        }

        $recipients = DB::fetchAll(
            "SELECT
                mr.recipient_id,
                mr.is_read,
                mr.read_at,
                COALESCE(mem.display_name, CONCAT(mem.first_name, ' ', mem.last_name)) AS recipient_name,
                mem.avatar_path AS recipient_avatar
             FROM `tm_message_recipients` mr
             JOIN `tm_members` mem ON mem.id = mr.recipient_id
             WHERE mr.message_id = ?",
            [$messageId]
        );

        return [
            'message'    => $message,
            'recipients' => $recipients,
        ];
    }

    /**
     * Paginated messages sent to a group thread.
     */
    public function getGroupMessages(int $groupId, int $page = 1, int $perPage = 25): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $countRow = DB::fetch(
            "SELECT COUNT(*) AS cnt FROM `tm_messages` WHERE group_id = ?",
            [$groupId]
        );
        $total = $countRow ? (int) $countRow['cnt'] : 0;
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = DB::fetchAll(
            "SELECT
                m.*,
                COALESCE(s.display_name, CONCAT(s.first_name, ' ', s.last_name)) AS sender_name,
                s.avatar_path AS sender_avatar
             FROM `tm_messages` m
             JOIN `tm_members` s ON s.id = m.sender_id
             WHERE m.group_id = ?
             ORDER BY m.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$groupId]
        );

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
        ];
    }
}
