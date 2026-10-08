<?php

namespace Bizorca\Consulting\Controllers;

use Bizorca\Consulting\Auth\CSRF;
use Bizorca\Consulting\Core\Database;
use Bizorca\Consulting\Models\Application;
use Bizorca\Consulting\Models\Board;
use Bizorca\Consulting\Models\Card;
use Bizorca\Consulting\Models\ChangeRequest;
use Bizorca\Consulting\Models\Engagement;
use Bizorca\Consulting\Models\User;

class AdminController
{
    public function dashboard(): void
    {
        require_admin();
        $user = current_user();

        $pendingApps = Application::allPending();
        $engagements = Engagement::all();

        $pendingChanges = Database::fetchAll(
            "SELECT cr.*, e.title AS engagement_title, " . fd_name_cols() . "
             FROM fd_change_requests cr
             JOIN fd_engagements e ON e.id = cr.engagement_id
             JOIN users u ON u.id = cr.submitted_by
             WHERE cr.status = 'pending'
             ORDER BY cr.created_at ASC"
        );

        render('admin/dashboard', compact('user', 'pendingApps', 'engagements', 'pendingChanges'));
    }

    public function applications(): void
    {
        require_admin();
        $user         = current_user();
        $applications = Application::all();
        render('admin/applications', compact('user', 'applications'));
    }

    public function applicationDetail(string $id): void
    {
        require_admin();
        $user        = current_user();
        $application = Application::find((int) $id);
        if (!$application) { $this->notFound(); }
        render('admin/application-detail', compact('user', 'application'));
    }

    public function acceptApplication(string $id): void
    {
        require_admin();
        CSRF::verify();
        $user        = current_user();
        $application = Application::find((int) $id);
        if (!$application) { $this->notFound(); }

        $adminNotes = trim($_POST['admin_notes'] ?? '');
        Application::updateStatus((int) $id, 'accepted', $user['id'], $adminNotes ?: null);

        // The client is a shared tools account. If the applicant never made
        // one, make it now with an unusable password; they set their own
        // through Forgot your password (the engagement page says so).
        $clientUser = User::findByEmail($application['email']);
        $clientId   = $clientUser
            ? (int) $clientUser['id']
            : User::createForClient($application['email'], $application['first_name'] . ' ' . $application['last_name']);

        // Create the engagement
        $engagementId = Engagement::create([
            'application_id' => $application['id'],
            'client_id'      => $clientId,
            'title'          => $application['company_name'] . ' — Systems Engagement',
            'status'         => 'onboarding',
        ]);

        // Seed default onboarding steps
        Engagement::createDefaultOnboardingSteps($engagementId);

        redirect('/admin/engagements/' . $engagementId);
    }

    public function declineApplication(string $id): void
    {
        require_admin();
        CSRF::verify();
        $user        = current_user();
        $application = Application::find((int) $id);
        if (!$application) { $this->notFound(); }

        $adminNotes = trim($_POST['admin_notes'] ?? '');
        Application::updateStatus((int) $id, 'declined', $user['id'], $adminNotes ?: null);

        redirect('/admin/applications');
    }

    public function engagements(): void
    {
        require_admin();
        $user        = current_user();
        $engagements = Engagement::all();
        render('admin/engagements', compact('user', 'engagements'));
    }

    public function engagementDetail(string $id): void
    {
        require_admin();
        $user       = current_user();
        $engagement = Engagement::find((int) $id);
        if (!$engagement) { $this->notFound(); }

        $board   = Board::forEngagement($engagement['id']);
        $columns = $board ? Board::columns($board['id'])  : [];
        $cards   = $board ? Board::cards($board['id'])    : [];
        $scopes  = Engagement::scopeItems($engagement['id']);
        $changes = ChangeRequest::forEngagement($engagement['id']);
        $steps   = Engagement::onboardingSteps($engagement['id']);

        $cardsByColumn = [];
        foreach ($cards as $card) {
            $cardsByColumn[$card['column_id']][] = $card;
        }

        $scopeStats = $board ? Card::scopeAddedCount($board['id']) : ['total' => 0, 'original' => 0, 'added' => 0];

        render('admin/engagement-detail', compact(
            'user', 'engagement', 'board', 'columns', 'cardsByColumn',
            'scopes', 'changes', 'steps', 'scopeStats'
        ));
    }

    public function createBoard(string $id): void
    {
        require_admin();
        CSRF::verify();
        $engagement = Engagement::find((int) $id);
        if (!$engagement) { $this->notFound(); }

        $name = trim($_POST['name'] ?? ($engagement['title'] . ' — Project Board'));
        Board::create($engagement['id'], $name);

        redirect('/admin/engagements/' . $id);
    }

    public function saveScope(string $id): void
    {
        require_admin();
        CSRF::verify();
        $engagement = Engagement::find((int) $id);
        if (!$engagement) { $this->notFound(); }

        // Delete existing scope items (pre-lock)
        if (!$engagement['scope_locked_at']) {
            Database::execute(
                'DELETE FROM fd_scope_items WHERE engagement_id = ? AND is_original = 1',
                [$engagement['id']]
            );
        }

        $titles       = $_POST['scope_title']       ?? [];
        $descriptions = $_POST['scope_description'] ?? [];

        foreach ($titles as $i => $title) {
            $title = trim($title);
            if (empty($title)) continue;
            Database::insert('fd_scope_items', [
                'engagement_id' => $engagement['id'],
                'title'         => $title,
                'description'   => trim($descriptions[$i] ?? ''),
                'position'      => $i + 1,
                'is_original'   => 1,
            ]);
        }

        redirect('/admin/engagements/' . $id);
    }

    public function lockScope(string $id): void
    {
        require_admin();
        CSRF::verify();
        $engagement = Engagement::find((int) $id);
        if (!$engagement) { $this->notFound(); }

        if (!$engagement['scope_locked_at']) {
            Database::update('fd_engagements', [
                'scope_locked_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$engagement['id']]);
        }

        redirect('/admin/engagements/' . $id);
    }

    public function createCard(): void
    {
        require_admin();
        CSRF::verify();
        $user = current_user();

        $columnId  = (int) ($_POST['column_id'] ?? 0);
        $boardId   = (int) ($_POST['board_id']  ?? 0);
        $title     = trim($_POST['title']        ?? '');
        $desc      = trim($_POST['description']  ?? '');
        $scopeItem = (int) ($_POST['scope_item_id'] ?? 0);
        $dueAt     = trim($_POST['due_at'] ?? '') ?: null;

        // The column must belong to the board (and the scope item to its
        // engagement): the original took both ids as posted.
        $board  = $boardId ? Board::find($boardId) : null;
        $column = $columnId ? Database::fetchOne('SELECT * FROM fd_columns WHERE id = ?', [$columnId]) : null;
        if (!$board || !$column || (int) $column['board_id'] !== $boardId || empty($title)) {
            redirect($board ? '/admin/engagements/' . $board['engagement_id'] : '/admin');
        }
        if ($scopeItem && !Database::fetchOne('SELECT 1 AS x FROM fd_scope_items WHERE id = ? AND engagement_id = ?', [$scopeItem, $board['engagement_id']])) {
            $scopeItem = 0;
        }

        // Get next position in column
        $maxPos = Database::fetchOne(
            'SELECT MAX(position) AS pos FROM fd_cards WHERE column_id = ?',
            [$columnId]
        );
        $position = ($maxPos['pos'] ?? 0) + 1;

        $cardId = Card::create([
            'board_id'          => $boardId,
            'column_id'         => $columnId,
            'scope_item_id'     => $scopeItem ?: null,
            'title'             => $title,
            'description'       => $desc,
            'position'          => $position,
            'is_original_scope' => 1,
            'created_by'        => $user['id'],
            'due_at'            => $dueAt,
        ]);

        // Add checklist steps if provided
        $stepTitles = array_filter(array_map('trim', $_POST['steps'] ?? []));
        foreach ($stepTitles as $i => $stepTitle) {
            Database::insert('fd_card_steps', [
                'card_id'  => $cardId,
                'title'    => $stepTitle,
                'position' => $i + 1,
            ]);
        }

        redirect('/admin/engagements/' . $board['engagement_id']);
    }

    public function updateCard(string $id): void
    {
        require_admin();
        CSRF::verify();

        $card = Card::find((int) $id);
        if (!$card) { $this->notFound(); }

        Database::update('fd_cards', [
            'title'       => trim($_POST['title'] ?? $card['title']),
            'description' => trim($_POST['description'] ?? ''),
            'due_at'      => trim($_POST['due_at'] ?? '') ?: null,
        ], 'id = ?', [$card['id']]);

        $board = Board::find($card['board_id']);
        redirect('/admin/engagements/' . $board['engagement_id']);
    }

    public function moveCard(string $id): void
    {
        require_admin();
        CSRF::verify();

        $card     = Card::find((int) $id);
        if (!$card) { $this->notFound(); }

        // Only to a column on the card's own board.
        $columnId = (int) ($_POST['column_id'] ?? 0);
        if ($columnId && Database::fetchOne('SELECT 1 AS x FROM fd_columns WHERE id = ? AND board_id = ?', [$columnId, $card['board_id']])) {
            Database::update('fd_cards', ['column_id' => $columnId], 'id = ?', [$card['id']]);
        }

        $board = Board::find($card['board_id']);
        redirect('/admin/engagements/' . $board['engagement_id']);
    }

    public function approveChange(string $id): void
    {
        require_admin();
        CSRF::verify();
        $user = current_user();
        $cr   = ChangeRequest::find((int) $id);
        if (!$cr) { $this->notFound(); }

        $note = trim($_POST['review_note'] ?? '');
        ChangeRequest::approve((int) $id, $user['id'], $note ?: null);

        // Create a scope item for the approved change
        Database::insert('fd_scope_items', [
            'engagement_id'     => $cr['engagement_id'],
            'title'             => $cr['title'],
            'description'       => $cr['description'],
            'position'          => 999,
            'is_original'       => 0,
            'change_request_id' => $cr['id'],
        ]);

        redirect('/admin/engagements/' . $cr['engagement_id']);
    }

    public function declineChange(string $id): void
    {
        require_admin();
        CSRF::verify();
        $user = current_user();
        $cr   = ChangeRequest::find((int) $id);
        if (!$cr) { $this->notFound(); }

        $note = trim($_POST['review_note'] ?? '');
        ChangeRequest::decline((int) $id, $user['id'], $note ?: null);

        redirect('/admin/engagements/' . $cr['engagement_id']);
    }

    private function notFound(): never
    {
        http_response_code(404);
        render('error', ['code' => 404, 'message' => 'Not found.']);
        exit;
    }
}
