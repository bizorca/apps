<?php

declare(strict_types=1);

namespace Dispatch\Models;

use Dispatch\Core\Database;

class Venue
{
    /**
     * All active venues, optionally filtered by type.
     */
    public static function allActive(?string $type = null): array
    {
        if ($type) {
            return Database::getInstance()->fetchAll(
                'SELECT v.*, u.name as suggested_by_name FROM dp_venues v LEFT JOIN users u ON u.id = v.suggested_by_user_id WHERE v.is_active = 1 AND v.type = ? ORDER BY v.name ASC',
                [$type]
            );
        }
        return Database::getInstance()->fetchAll(
            'SELECT v.*, u.name as suggested_by_name FROM dp_venues v LEFT JOIN users u ON u.id = v.suggested_by_user_id WHERE v.is_active = 1 ORDER BY v.type, v.name ASC'
        );
    }

    public static function all(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT v.*, u.name as suggested_by_name FROM dp_venues v LEFT JOIN users u ON u.id = v.suggested_by_user_id ORDER BY v.is_active DESC, v.type, v.name ASC'
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            'SELECT * FROM dp_venues WHERE id = ?',
            [$id]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert(
            'INSERT INTO dp_venues (name, type, submission_url, submission_email, lead_time_days, buffer_days, submission_method, asset_requirements, notes, contact_name, contact_email, contact_notes, is_active, suggested_by_user_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'],
                $data['type'],
                $data['submission_url'] ?? null,
                $data['submission_email'] ?? null,
                (int) ($data['lead_time_days'] ?? 7),
                (int) ($data['buffer_days'] ?? 1),
                $data['submission_method'] ?? 'web',
                $data['asset_requirements'] ?? null,
                $data['notes'] ?? null,
                $data['contact_name'] ?? null,
                $data['contact_email'] ?? null,
                $data['contact_notes'] ?? null,
                (int) ($data['is_active'] ?? 1),
                $data['suggested_by_user_id'] ?? null,
                $data['created_by'] ?? null,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_venues SET name=?, type=?, submission_url=?, submission_email=?, lead_time_days=?, buffer_days=?, submission_method=?, asset_requirements=?, notes=?, contact_name=?, contact_email=?, contact_notes=?, is_active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?',
            [
                $data['name'],
                $data['type'],
                $data['submission_url'] ?? null,
                $data['submission_email'] ?? null,
                (int) ($data['lead_time_days'] ?? 7),
                (int) ($data['buffer_days'] ?? 1),
                $data['submission_method'] ?? 'web',
                $data['asset_requirements'] ?? null,
                $data['notes'] ?? null,
                $data['contact_name'] ?? null,
                $data['contact_email'] ?? null,
                $data['contact_notes'] ?? null,
                (int) ($data['is_active'] ?? 1),
                $id,
            ]
        );
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->execute('DELETE FROM dp_venues WHERE id = ?', [$id]);
    }

    public static function types(): array
    {
        return [
            'digital_social'   => 'Digital — Social Media',
            'digital_calendar' => 'Digital — Calendar / Listing',
            'print'            => 'Print — Newspaper / Magazine',
            'radio'            => 'Radio / Podcast',
            'physical'         => 'Physical — Bulletin Board / Flyer',
            'email_newsletter' => 'Email Newsletter',
            'other'            => 'Other',
        ];
    }

    public static function submissionMethods(): array
    {
        return [
            'web'       => 'Web Form',
            'email'     => 'Email',
            'phone'     => 'Phone',
            'in_person' => 'In Person',
        ];
    }

    /**
     * Get asset requirements as decoded array.
     */
    public static function getAssetRequirements(array $venue): array
    {
        if (empty($venue['asset_requirements'])) return [];
        $decoded = json_decode($venue['asset_requirements'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function count(): int
    {
        $row = Database::getInstance()->fetchOne('SELECT COUNT(*) as cnt FROM dp_venues WHERE is_active = 1');
        return (int) ($row['cnt'] ?? 0);
    }
}
