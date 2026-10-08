<?php

namespace Bizorca\Consulting\Models;

use Bizorca\Consulting\Core\Database;

class Engagement
{
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT e.*, ' . fd_name_cols() . ', u.email
             FROM fd_engagements e
             JOIN users u ON u.id = e.client_id
             WHERE e.id = ?',
            [$id]
        );
    }

    public static function forClient(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM fd_engagements WHERE client_id = ? AND status != 'cancelled' ORDER BY created_at DESC",
            [$userId]
        );
    }

    public static function all(): array
    {
        return Database::fetchAll(
            'SELECT e.*, ' . fd_name_cols() . ', u.email
             FROM fd_engagements e
             JOIN users u ON u.id = e.client_id
             ORDER BY e.created_at DESC'
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('fd_engagements', $data);
    }

    public static function scopeItems(int $engagementId): array
    {
        return Database::fetchAll(
            'SELECT * FROM fd_scope_items WHERE engagement_id = ? ORDER BY position ASC',
            [$engagementId]
        );
    }

    public static function onboardingSteps(int $engagementId): array
    {
        return Database::fetchAll(
            'SELECT * FROM fd_onboarding_steps WHERE engagement_id = ? ORDER BY position ASC',
            [$engagementId]
        );
    }

    public static function createDefaultOnboardingSteps(int $engagementId): void
    {
        $steps = [
            ['step_key' => 'agreement_signed',        'label' => 'Sign the engagement agreement',    'position' => 1],
            ['step_key' => 'questionnaire_complete',   'label' => 'Complete the discovery questionnaire', 'position' => 2],
            ['step_key' => 'kickoff_scheduled',        'label' => 'Schedule the kickoff call',        'position' => 3],
            ['step_key' => 'scope_accepted',           'label' => 'Review and accept the scope document', 'position' => 4],
        ];
        foreach ($steps as $step) {
            Database::insert('fd_onboarding_steps', array_merge(['engagement_id' => $engagementId], $step));
        }
    }

    public static function pendingChangeRequests(int $engagementId): array
    {
        return Database::fetchAll(
            "SELECT cr.*, " . fd_name_cols() . "
             FROM fd_change_requests cr
             JOIN users u ON u.id = cr.submitted_by
             WHERE cr.engagement_id = ? AND cr.status = 'pending'
             ORDER BY cr.created_at ASC",
            [$engagementId]
        );
    }
}
