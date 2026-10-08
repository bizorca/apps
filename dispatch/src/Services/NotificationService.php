<?php

declare(strict_types=1);

namespace Dispatch\Services;

use Dispatch\Core\Database;
use Dispatch\Core\Email;
use Dispatch\Models\User;
use Dispatch\Models\Notification;
use Dispatch\Models\ActionItem;

class NotificationService
{
    /**
     * Send a digest email to all users who have pending/overdue action items.
     * Call this via a cron job or manual trigger from admin.
     */
    /**
     * Send digest emails to all eligible users.
     * Each user's remind_days_before setting controls how far ahead to look.
     *
     * @param array $preferences Which preference values to include, e.g. ['daily'] or ['daily','weekly']
     */
    public static function sendDailyDigests(array $preferences = ['daily', 'weekly'], bool $dryRun = false): array
    {
        $db = Database::getInstance();

        $placeholders = implode(',', array_fill(0, count($preferences), '?'));

        // Dispatch users (a dp_profiles row) who opted in. The account is shared
        // across tools, so "every user" would mail people who never used Dispatch.
        $users = $db->fetchAll(
            'SELECT u.id, u.name, u.email, p.notification_preference, p.remind_days_before
             FROM users u JOIN dp_profiles p ON p.user_id = u.id
             WHERE p.notification_preference IN (' . $placeholders . ')
             ORDER BY u.id',
            $preferences
        );

        $results = [];

        foreach ($users as $user) {
            $window = max(1, (int) ($user['remind_days_before'] ?? 3));

            $items = $db->fetchAll(
                'SELECT ai.*, v.name as venue_name, v.submission_url, c.name as campaign_name
                 FROM dp_action_items ai
                 JOIN dp_venues v ON v.id = ai.venue_id
                 JOIN dp_campaigns c ON c.id = ai.campaign_id
                 WHERE (ai.user_id = ? OR ai.assigned_to_user_id = ?)
                   AND ai.status = \'pending\'
                   AND ai.due_date <= DATE_ADD(CURDATE(), INTERVAL ' . $window . ' DAY)
                 ORDER BY ai.due_date ASC',
                [$user['id'], $user['id']]
            );

            if (empty($items)) continue;

            $html = Email::renderTemplate('digest', [
                'user'    => $user,
                'items'   => $items,
                'appName' => DP_APP_NAME,
                // The template appends '/dashboard' and '/profile' to this.
                'appUrl'  => \Dispatch\Core\Url::absolute(''),
            ]);

            $sent = $dryRun ? true : Email::send(
                $user['email'],
                $user['name'],
                'Your Dispatch Digest — Upcoming Deadlines',
                $html
            );

            $results[] = [
                'user'  => $user['email'],
                'sent'  => $sent,
                'items' => count($items),
            ];
        }

        return $results;
    }

    /**
     * Create an in-app notification for a user.
     */
    public static function notify(int $userId, string $type, string $title, string $message, ?string $link = null): void
    {
        Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
        ]);
    }

    /**
     * Notify user when their venue submission is approved or rejected.
     */
    public static function notifyVenueDecision(int $userId, string $venueName, string $decision, ?string $adminNotes = null): void
    {
        if ($decision === 'approved') {
            self::notify(
                $userId,
                'venue_approved',
                "Venue Approved: {$venueName}",
                "Your venue submission for '{$venueName}' has been approved and added to the venue library.",
                '/venues'
            );
        } else {
            self::notify(
                $userId,
                'venue_rejected',
                "Venue Update: {$venueName}",
                "Your venue submission for '{$venueName}' was not added to the library." . ($adminNotes ? " Reason: {$adminNotes}" : ''),
                null
            );
        }
    }
}
