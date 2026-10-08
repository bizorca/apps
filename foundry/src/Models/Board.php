<?php

namespace Bizorca\Consulting\Models;

use Bizorca\Consulting\Core\Database;

class Board
{
    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM fd_boards WHERE id = ?', [$id]);
    }

    public static function forEngagement(int $engagementId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM fd_boards WHERE engagement_id = ? ORDER BY created_at ASC LIMIT 1',
            [$engagementId]
        );
    }

    public static function create(int $engagementId, string $name, string $description = ''): int
    {
        $boardId = Database::insert('fd_boards', [
            'engagement_id' => $engagementId,
            'name'          => $name,
            'description'   => $description,
        ]);

        // Create default columns
        $columns = [
            ['name' => 'Discovery',      'color' => 'violet',  'position' => 1],
            ['name' => 'In Progress',    'color' => 'blue',    'position' => 2],
            ['name' => 'Your Review',    'color' => 'amber',   'position' => 3],
            ['name' => 'Client Review',  'color' => 'orange',  'position' => 4],
            ['name' => 'Complete',       'color' => 'emerald', 'position' => 5],
        ];

        foreach ($columns as $col) {
            Database::insert('fd_columns', array_merge(['board_id' => $boardId], $col));
        }

        return $boardId;
    }

    public static function columns(int $boardId): array
    {
        return Database::fetchAll(
            'SELECT * FROM fd_columns WHERE board_id = ? ORDER BY position ASC',
            [$boardId]
        );
    }

    public static function cards(int $boardId): array
    {
        return Database::fetchAll(
            'SELECT c.*, col.name AS column_name
             FROM fd_cards c
             JOIN fd_columns col ON col.id = c.column_id
             WHERE c.board_id = ? AND c.closed_at IS NULL
             ORDER BY c.column_id ASC, c.position ASC',
            [$boardId]
        );
    }
}
