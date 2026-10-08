<?php

declare(strict_types=1);

namespace Dispatch\Models;

use Dispatch\Core\Database;

class VenueSubmission
{
    public static function create(array $data): int
    {
        return Database::getInstance()->insert(
            'INSERT INTO dp_venue_submissions (user_id, venue_name, submission_url, submission_email, venue_type, perceived_lead_time, asset_requirements, justification) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'],
                $data['venue_name'],
                $data['submission_url'] ?? null,
                $data['submission_email'] ?? null,
                $data['venue_type'] ?? null,
                $data['perceived_lead_time'] ?? null,
                $data['asset_requirements'] ?? null,
                $data['justification'] ?? null,
            ]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            'SELECT vs.*, u.name as submitter_name, u.email as submitter_email FROM dp_venue_submissions vs JOIN users u ON u.id = vs.user_id WHERE vs.id = ?',
            [$id]
        );
    }

    public static function allPending(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT vs.*, u.name as submitter_name FROM dp_venue_submissions vs JOIN users u ON u.id = vs.user_id WHERE vs.status = "pending" ORDER BY vs.created_at ASC'
        );
    }

    public static function allWithStatus(string $status): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT vs.*, u.name as submitter_name FROM dp_venue_submissions vs JOIN users u ON u.id = vs.user_id WHERE vs.status = ? ORDER BY vs.created_at DESC',
            [$status]
        );
    }

    public static function all(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT vs.*, u.name as submitter_name FROM dp_venue_submissions vs JOIN users u ON u.id = vs.user_id ORDER BY vs.created_at DESC'
        );
    }

    public static function updateStatus(int $id, string $status, int $reviewerId, ?string $adminNotes = null): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_venue_submissions SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$status, $adminNotes, $reviewerId, $id]
        );
    }

    public static function countByStatus(string $status): int
    {
        $row = Database::getInstance()->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_venue_submissions WHERE status = ?',
            [$status]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
