<?php

namespace Bizorca\Pod\Services;

use Bizorca\Pod\Core\Database;

/**
 * In-app notifications, each also emailed. $path is an app path
 * ('/forum/post/5'): stored as is, made absolute only for the email.
 */
class NotificationService
{
    public static function notify(int $userId, string $type, string $message, string $path): void
    {
        Database::query(
            'INSERT INTO pd_notifications (user_id, type, message, url) VALUES (?, ?, ?, ?)',
            [$userId, $type, $message, $path]
        );

        $recipient = Database::fetchOne(
            'SELECT email FROM pd_users WHERE id = ? AND is_active = 1',
            [$userId]
        );

        if (!$recipient) {
            return;
        }

        $text = $message . ($path ? "\n\n" . absolute_url($path) : '');

        tl_mail($recipient['email'], $message, $text);
    }

    public static function notifyForumReply(array $post, array $replier): void
    {
        if ((int)$post['user_id'] === (int)$replier['id']) {
            return;
        }

        $message = "{$replier['first_name']} {$replier['last_name']} replied to your post \"{$post['title']}\"";

        self::notify((int)$post['user_id'], 'forum_reply', $message, "/forum/post/{$post['id']}");
    }

    public static function notifyTicketReply(array $ticket, array $ticketOwner, array $replier, bool $isStaff): void
    {
        $ticketPath = "/tickets/{$ticket['id']}";

        if ($isStaff) {
            // Notify ticket owner if replier is not the owner
            if ((int)$replier['id'] !== (int)$ticketOwner['id']) {
                $message = "Staff replied to your ticket: \"{$ticket['subject']}\"";
                self::notify((int)$ticketOwner['id'], 'ticket_reply', $message, $ticketPath);
            }
        } else {
            $message = "{$ticketOwner['first_name']} replied to ticket: \"{$ticket['subject']}\"";
            self::notifyStaff($ticket, $replier, $message, $ticketPath);
        }
    }

    /**
     * A new ticket. The original told nobody: staff only heard about a ticket
     * once its owner replied to it.
     */
    public static function notifyNewTicket(array $ticket, array $owner): void
    {
        $message = "{$owner['first_name']} opened a ticket: \"{$ticket['subject']}\"";
        self::notifyStaff($ticket, $owner, $message, "/tickets/{$ticket['id']}");
    }

    /** The assigned staff member, or every staff member and admin. */
    private static function notifyStaff(array $ticket, array $actor, string $message, string $path): void
    {
        if (!empty($ticket['assigned_to'])) {
            $assigned = Database::fetchOne('SELECT id FROM pd_users WHERE id = ?', [$ticket['assigned_to']]);
            if ($assigned && (int)$assigned['id'] !== (int)$actor['id']) {
                self::notify((int)$assigned['id'], 'ticket_reply', $message, $path);
            }
            return;
        }

        $staffMembers = Database::fetchAll(
            'SELECT id FROM pd_users WHERE (is_staff = 1 OR is_admin = 1) AND is_active = 1'
        );
        foreach ($staffMembers as $staff) {
            if ((int)$staff['id'] !== (int)$actor['id']) {
                self::notify((int)$staff['id'], 'ticket_reply', $message, $path);
            }
        }
    }

    public static function notifyMentions(string $body, array $author, string $path): void
    {
        if (!preg_match_all('/@([A-Za-z]+)/', $body, $matches)) {
            return;
        }

        $firstNames = array_unique($matches[1]);

        foreach ($firstNames as $firstName) {
            $users = Database::fetchAll(
                'SELECT id FROM pd_users WHERE first_name = ? AND is_active = 1',
                [$firstName]
            );
            foreach ($users as $u) {
                if ((int)$u['id'] === (int)$author['id']) {
                    continue;
                }
                $message = "{$author['first_name']} {$author['last_name']} mentioned you";
                self::notify((int)$u['id'], 'mention', $message, $path);
            }
        }
    }
}
