<?php

namespace Bizorca\Consulting\Controllers;

use Bizorca\Consulting\Auth\CSRF;
use Bizorca\Consulting\Core\Database;
use Bizorca\Consulting\Models\Board;
use Bizorca\Consulting\Models\Card;
use Bizorca\Consulting\Models\ChangeRequest;
use Bizorca\Consulting\Models\Engagement;

class EngagementController
{
    public function show(string $id): void
    {
        require_auth();
        $user       = current_user();
        $engagement = $this->loadEngagement((int) $id, $user);

        $board   = Board::forEngagement($engagement['id']);
        $columns = $board ? Board::columns($board['id']) : [];
        $cards   = $board ? Board::cards($board['id'])   : [];
        $scopes  = Engagement::scopeItems($engagement['id']);
        $changes = ChangeRequest::forEngagement($engagement['id']);

        // Group cards by column
        $cardsByColumn = [];
        foreach ($cards as $card) {
            $cardsByColumn[$card['column_id']][] = $card;
        }

        // Scope creep indicator
        $scopeStats = $board ? Card::scopeAddedCount($board['id']) : ['total' => 0, 'original' => 0, 'added' => 0];

        render('engagements/show', compact(
            'user', 'engagement', 'board', 'columns', 'cardsByColumn',
            'scopes', 'changes', 'scopeStats'
        ));
    }

    public function scope(string $id): void
    {
        require_auth();
        $user       = current_user();
        $engagement = $this->loadEngagement((int) $id, $user);
        $scopes     = Engagement::scopeItems($engagement['id']);

        render('engagements/scope', compact('user', 'engagement', 'scopes'));
    }

    public function acceptScope(string $id): void
    {
        require_auth();
        CSRF::verify();
        $user       = current_user();
        $engagement = $this->loadEngagement((int) $id, $user);

        if (!$engagement['scope_locked_at']) {
            redirect("/engagements/{$id}/scope");
        }

        if (!$engagement['client_accepted_scope_at']) {
            Database::update('fd_engagements', [
                'client_accepted_scope_at' => date('Y-m-d H:i:s'),
                'status'                   => 'active',
            ], 'id = ?', [$engagement['id']]);

            // Mark scope_accepted onboarding step complete
            Database::update('fd_onboarding_steps', [
                'completed_at' => date('Y-m-d H:i:s'),
                'completed_by' => $user['id'],
            ], 'engagement_id = ? AND step_key = ?', [$engagement['id'], 'scope_accepted']);
        }

        redirect("/engagements/{$id}");
    }

    private function loadEngagement(int $id, array $user): array
    {
        $engagement = Engagement::find($id);

        if (!$engagement) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Engagement not found.']);
            exit;
        }

        // Clients can only see their own engagements; admins see all
        if (!$user['is_admin'] && $engagement['client_id'] != $user['id']) {
            http_response_code(403);
            render('error', ['code' => 403, 'message' => 'Access denied.']);
            exit;
        }

        return $engagement;
    }
}
