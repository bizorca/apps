<?php

namespace Bizorca\Consulting\Models;

use Bizorca\Consulting\Core\Database;

class Card
{
    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM fd_cards WHERE id = ?', [$id]);
    }

    public static function steps(int $cardId): array
    {
        return Database::fetchAll(
            'SELECT * FROM fd_card_steps WHERE card_id = ? ORDER BY position ASC',
            [$cardId]
        );
    }

    public static function comments(int $cardId): array
    {
        return Database::fetchAll(
            'SELECT cc.*, ' . fd_name_cols() . '
             FROM fd_card_comments cc
             JOIN users u ON u.id = cc.user_id
             WHERE cc.card_id = ?
             ORDER BY cc.created_at ASC',
            [$cardId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('fd_cards', $data);
    }

    public static function addComment(int $cardId, int $userId, string $body): int
    {
        return Database::insert('fd_card_comments', [
            'card_id' => $cardId,
            'user_id' => $userId,
            'body'    => $body,
        ]);
    }

    public static function toggleStep(int $stepId, int $cardId, int $userId): void
    {
        $step = Database::fetchOne('SELECT * FROM fd_card_steps WHERE id = ? AND card_id = ?', [$stepId, $cardId]);
        if (!$step) return;

        if ($step['completed']) {
            Database::update('fd_card_steps', [
                'completed'    => 0,
                'completed_at' => null,
                'completed_by' => null,
            ], 'id = ?', [$stepId]);
        } else {
            Database::update('fd_card_steps', [
                'completed'    => 1,
                'completed_at' => date('Y-m-d H:i:s'),
                'completed_by' => $userId,
            ], 'id = ?', [$stepId]);
        }
    }

    public static function reorder(int $boardId, int $columnId, array $cardIds): void
    {
        Database::beginTransaction();
        try {
            foreach ($cardIds as $position => $cardId) {
                Database::update('fd_cards', [
                    'column_id' => $columnId,
                    'position'  => $position + 1,
                ], 'id = ? AND board_id = ?', [(int) $cardId, $boardId]);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function scopeAddedCount(int $boardId): array
    {
        $row = Database::fetchOne(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_original_scope = 1 THEN 1 ELSE 0 END) AS original,
                SUM(CASE WHEN is_original_scope = 0 THEN 1 ELSE 0 END) AS added
             FROM fd_cards
             WHERE board_id = ? AND closed_at IS NULL',
            [$boardId]
        );
        return $row ?? ['total' => 0, 'original' => 0, 'added' => 0];
    }
}
