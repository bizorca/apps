<?php

namespace Bizorca\Consulting\Models;

use Bizorca\Consulting\Core\Database;

class Application
{
    /**
     * The form's dropdown choices. The view renders them and submit() accepts
     * nothing else: the original only checked "not empty", so a bot posting
     * the literal placeholder "Select..." to every field was accepted (the one
     * row in the live database is exactly that).
     */
    public const OPTIONS = [
        'revenue_range'  => ['Under $250K', '$250K–$500K', '$500K–$1M', '$1M–$3M', '$3M+'],
        'employee_count' => ['Just me', '2–5', '6–15', '16+'],
        'proc_location'  => [
            'Fully documented and actively used by the team',
            'Scattered across Google Docs, emails, and sticky notes',
            'Mostly trapped in my head',
            'We basically scramble every time',
        ],
        'timeline'       => ['Immediately (within 2 weeks)', 'Next 30–60 days', 'Next quarter', 'Just exploring options'],
        'budget_range'   => ['Under $5K', '$5K–$10K', '$10K–$18K'],
    ];

    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM fd_applications WHERE id = ?', [$id]);
    }

    public static function findByUser(int $userId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM fd_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1',
            [$userId]
        );
    }

    public static function allPending(): array
    {
        return Database::fetchAll(
            "SELECT * FROM fd_applications WHERE status = 'pending' ORDER BY created_at ASC"
        );
    }

    public static function all(): array
    {
        return Database::fetchAll(
            'SELECT * FROM fd_applications ORDER BY created_at DESC'
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('fd_applications', $data);
    }

    public static function updateStatus(int $id, string $status, int $reviewerId, ?string $adminNotes = null): void
    {
        Database::update('fd_applications', [
            'status'      => $status,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'admin_notes' => $adminNotes,
        ], 'id = ?', [$id]);
    }
}
