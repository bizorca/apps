<?php

declare(strict_types=1);

namespace Dispatch\Models;

use Dispatch\Core\Database;

class Document
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            'SELECT d.*, u.name as owner_name, c.name as campaign_name
             FROM dp_documents d
             JOIN users u ON u.id = d.user_id
             LEFT JOIN dp_campaigns c ON c.id = d.campaign_id
             WHERE d.id = ?',
            [$id]
        );
    }

    /** Own documents + all templates, for the library view. */
    public static function findForUser(int $userId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT d.*, u.name as owner_name, c.name as campaign_name
             FROM dp_documents d
             JOIN users u ON u.id = d.user_id
             LEFT JOIN dp_campaigns c ON c.id = d.campaign_id
             WHERE d.user_id = ? OR d.is_template = 1
             ORDER BY d.is_template ASC, d.created_at DESC',
            [$userId]
        );
    }

    /** Documents attached to a specific campaign. */
    public static function findByCampaign(int $campaignId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT d.*, u.name as owner_name
             FROM dp_documents d
             JOIN users u ON u.id = d.user_id
             WHERE d.campaign_id = ?
             ORDER BY d.created_at DESC',
            [$campaignId]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert(
            'INSERT INTO dp_documents (user_id, campaign_id, name, description, content, file_path, file_name, file_size, mime_type, is_template)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'],
                $data['campaign_id'] ?? null,
                $data['name'],
                $data['description'] ?? null,
                $data['content'] ?? null,
                $data['file_path'] ?? null,
                $data['file_name'] ?? null,
                $data['file_size'] ?? null,
                $data['mime_type'] ?? null,
                $data['is_template'] ? 1 : 0,
            ]
        );
    }

    public static function toggleTemplate(int $id): void
    {
        Database::getInstance()->execute(
            'UPDATE dp_documents SET is_template = 1 - is_template, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$id]
        );
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->execute('DELETE FROM dp_documents WHERE id = ?', [$id]);
    }
}
