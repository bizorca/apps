<?php

namespace Bizorca\Consulting\Controllers;

use Bizorca\Consulting\Auth\CSRF;
use Bizorca\Consulting\Models\ChangeRequest;
use Bizorca\Consulting\Models\Engagement;

class ChangeRequestController
{
    public function index(string $id): void
    {
        require_auth();
        $user       = current_user();
        $engagement = $this->loadEngagement((int) $id, $user);
        $changes    = ChangeRequest::forEngagement($engagement['id']);

        render('engagements/changes', compact('user', 'engagement', 'changes'));
    }

    public function submit(string $id): void
    {
        require_auth();
        CSRF::verify();
        $user       = current_user();
        $engagement = $this->loadEngagement((int) $id, $user);

        // Only clients (not admin) submit change requests, and only on active engagements
        if ($user['is_admin'] || $engagement['status'] !== 'active') {
            redirect("/engagements/{$id}");
        }

        // Scope must be locked before a change request makes sense
        if (!$engagement['scope_locked_at']) {
            redirect("/engagements/{$id}");
        }

        $title         = trim($_POST['title'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $justification = trim($_POST['justification'] ?? '');

        if (empty($title) || empty($description)) {
            redirect("/engagements/{$id}/changes");
        }

        ChangeRequest::create([
            'engagement_id' => $engagement['id'],
            'submitted_by'  => $user['id'],
            'title'         => $title,
            'description'   => $description,
            'justification' => $justification,
        ]);

        redirect("/engagements/{$id}/changes");
    }

    private function loadEngagement(int $id, array $user): array
    {
        $engagement = Engagement::find($id);

        if (!$engagement) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Engagement not found.']);
            exit;
        }

        if (!$user['is_admin'] && $engagement['client_id'] != $user['id']) {
            http_response_code(403);
            render('error', ['code' => 403, 'message' => 'Access denied.']);
            exit;
        }

        return $engagement;
    }
}
