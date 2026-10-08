<?php

declare(strict_types=1);

namespace Dispatch\Models;

use Dispatch\Core\Database;

class ActionItem
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            'SELECT ai.*, v.name as venue_name, v.type as venue_type, v.submission_url, v.submission_email, v.submission_method, v.asset_requirements, v.contact_name, v.contact_email, v.contact_notes, c.name as campaign_name, u.name as assigned_to_name FROM dp_action_items ai JOIN dp_venues v ON v.id = ai.venue_id JOIN dp_campaigns c ON c.id = ai.campaign_id LEFT JOIN users u ON u.id = ai.assigned_to_user_id WHERE ai.id = ?',
            [$id]
        );
    }

    public static function findByCampaign(int $campaignId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT ai.*, v.name as venue_name, v.type as venue_type, v.submission_url, v.submission_email, v.submission_method, v.asset_requirements, v.contact_name, v.contact_email, v.contact_notes, u.name as assigned_to_name FROM dp_action_items ai JOIN dp_venues v ON v.id = ai.venue_id LEFT JOIN users u ON u.id = ai.assigned_to_user_id WHERE ai.campaign_id = ? ORDER BY ai.due_date ASC',
            [$campaignId]
        );
    }

    public static function findByUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT ai.*, v.name as venue_name, v.type as venue_type, v.submission_url, c.name as campaign_name FROM dp_action_items ai JOIN dp_venues v ON v.id = ai.venue_id JOIN dp_campaigns c ON c.id = ai.campaign_id WHERE ai.user_id = ? ORDER BY ai.due_date ASC',
            [$userId]
        );
    }

    /**
     * Get action items due today or overdue, for a user (owner or assigned).
     */
    public static function findDueTodayByUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT ai.*, v.name as venue_name, v.type as venue_type, v.submission_url, v.submission_email, v.submission_method, v.asset_requirements, c.name as campaign_name FROM dp_action_items ai JOIN dp_venues v ON v.id = ai.venue_id JOIN dp_campaigns c ON c.id = ai.campaign_id WHERE (ai.user_id = ? OR ai.assigned_to_user_id = ?) AND ai.status = \'pending\' AND ai.due_date <= CURDATE() ORDER BY ai.due_date ASC',
            [$userId, $userId]
        );
    }

    /**
     * Get upcoming action items (next N days) for a user (owner or assigned).
     */
    public static function findUpcomingByUser(int $userId, int $days = 7): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT ai.*, v.name as venue_name, v.type as venue_type, v.submission_url, v.asset_requirements, c.name as campaign_name FROM dp_action_items ai JOIN dp_venues v ON v.id = ai.venue_id JOIN dp_campaigns c ON c.id = ai.campaign_id WHERE (ai.user_id = ? OR ai.assigned_to_user_id = ?) AND ai.status = \'pending\' AND ai.due_date > CURDATE() AND ai.due_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int) $days . ' DAY) ORDER BY ai.due_date ASC',
            [$userId, $userId]
        );
    }

    /**
     * Items marked submitted where follow-up date has arrived.
     */
    public static function findFollowUpsDueByUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT ai.*, v.name as venue_name, v.type as venue_type, c.name as campaign_name FROM dp_action_items ai JOIN dp_venues v ON v.id = ai.venue_id JOIN dp_campaigns c ON c.id = ai.campaign_id WHERE (ai.user_id = ? OR ai.assigned_to_user_id = ?) AND ai.status = \'submitted\' AND ai.follow_up_date <= CURDATE() ORDER BY ai.follow_up_date ASC',
            [$userId, $userId]
        );
    }

    /**
     * Items assigned to this user by someone else, still needing action.
     */
    public static function findAssignedToUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT ai.*, v.name as venue_name, v.type as venue_type, v.submission_url, c.name as campaign_name FROM dp_action_items ai JOIN dp_venues v ON v.id = ai.venue_id JOIN dp_campaigns c ON c.id = ai.campaign_id WHERE ai.assigned_to_user_id = ? AND ai.user_id != ? AND ai.status IN (\'pending\', \'submitted\', \'no_response\') ORDER BY ai.due_date ASC',
            [$userId, $userId]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert(
            'INSERT INTO dp_action_items (campaign_id, venue_id, user_id, due_date, event_date, status) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['campaign_id'],
                $data['venue_id'],
                $data['user_id'],
                $data['due_date'],
                $data['event_date'],
                $data['status'] ?? 'pending',
            ]
        );
    }

    public static function markComplete(int $id): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET status = "complete", completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$id]
        );
    }

    public static function markSubmitted(int $id): void
    {
        $followUpDate = date('Y-m-d', strtotime('+7 days'));
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET status = "submitted", submitted_at = CURRENT_TIMESTAMP, follow_up_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$followUpDate, $id]
        );
    }

    public static function markConfirmed(int $id): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET status = "confirmed", confirmed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$id]
        );
    }

    public static function markRejected(int $id): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET status = "rejected", updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$id]
        );
    }

    public static function markNoResponse(int $id): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET status = "no_response", updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$id]
        );
    }

    public static function markSkipped(int $id, ?string $notes = null): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET status = "skipped", notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$notes, $id]
        );
    }

    public static function markPending(int $id): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET status = "pending", completed_at = NULL, submitted_at = NULL, confirmed_at = NULL, follow_up_date = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$id]
        );
    }

    public static function assign(int $id, ?int $userId): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET assigned_to_user_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$userId ?: null, $id]
        );
    }

    /** Assign all pending/submitted action items on a campaign to a user (or clear assignment). */
    public static function assignAllByCampaign(int $campaignId, ?int $userId): void
    {
        Database::getInstance()->execute(
            "UPDATE dp_action_items SET assigned_to_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE campaign_id = ? AND status IN ('pending', 'submitted', 'no_response')",
            [$userId ?: null, $campaignId]
        );
    }

    public static function updateCosts(int $id, ?float $estimated, ?float $actual): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET estimated_cost = ?, actual_cost = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$estimated, $actual, $id]
        );
    }

    /**
     * Set (or clear) the target publication date and update due_date accordingly.
     * Pass null to clear — due_date reverts to the recalculated value from event_date.
     */
    public static function setPublicationDate(int $id, ?string $publicationDate, string $recalculatedDueDate): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_action_items SET target_publication_date = ?, due_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$publicationDate ?: null, $recalculatedDueDate, $id]
        );
    }

    public static function deleteByCampaign(int $campaignId): void
    {
        Database::getInstance()->execute('DELETE FROM dp_action_items WHERE campaign_id = ?', [$campaignId]);
    }

    /**
     * Delete only non-final items — preserve complete, skipped, confirmed, rejected.
     */
    public static function deletePendingByCampaign(int $campaignId): void
    {
        Database::getInstance()->execute(
            'DELETE FROM dp_action_items WHERE campaign_id = ? AND status IN (\'pending\', \'overdue\', \'submitted\', \'no_response\')',
            [$campaignId]
        );
    }

    /**
     * Get venue IDs for items that are in a terminal/preserved state.
     */
    public static function getCompletedVenueIds(int $campaignId): array
    {
        $rows = Database::getInstance()->fetchAll(
            'SELECT venue_id FROM dp_action_items WHERE campaign_id = ? AND status IN (\'complete\', \'skipped\', \'confirmed\', \'rejected\')',
            [$campaignId]
        );
        return array_map('intval', array_column($rows, 'venue_id'));
    }

    public static function countPendingByUser(int $userId): int
    {
        $row = Database::getInstance()->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_action_items WHERE (user_id = ? OR assigned_to_user_id = ?) AND status IN ("pending", "overdue", "submitted", "no_response")',
            [$userId, $userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function countOverdueByUser(int $userId): int
    {
        $row = Database::getInstance()->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_action_items ai WHERE (ai.user_id = ? OR ai.assigned_to_user_id = ?) AND ai.status = \'pending\' AND ai.due_date < CURDATE()',
            [$userId, $userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
