<?php

namespace Bizorca\Pod\Controllers;

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Database;
use Bizorca\Pod\Services\NotificationService;

class TicketController
{
    private function requireAuth(): void
    {
        if (!Session::isLoggedIn()) {
            tl_require_login();
        }
    }

    public function index(): void
    {
        $this->requireAuth();

        $user = Session::user();

        if (Session::isStaff()) {
            $tickets = Database::fetchAll(
                'SELECT t.*, u.first_name, u.last_name, u.email
                 FROM pd_tickets t
                 JOIN pd_users u ON u.id = t.user_id
                 ORDER BY FIELD(t.status, \'open\', \'answered\', \'closed\'), t.updated_at DESC'
            );
        } else {
            $tickets = Database::fetchAll(
                'SELECT * FROM pd_tickets WHERE user_id = ? ORDER BY updated_at DESC',
                [$user['id']]
            );
        }

        render('tickets/index', compact('tickets', 'user'));
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $user = Session::user();
        render('tickets/create', compact('user'));
    }

    public function create(): void
    {
        $this->requireAuth();
        csrf_verify();

        $user    = Session::user();
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body'] ?? '');

        if (!$subject || !$body) {
            Session::flash('error', 'Subject and message are required.');
            redirect(url('tickets/new'));
        }

        $ticketId = Database::insert(
            'INSERT INTO pd_tickets (user_id, subject) VALUES (?, ?)',
            [$user['id'], $subject]
        );

        Database::insert(
            'INSERT INTO pd_ticket_messages (ticket_id, user_id, body, is_staff) VALUES (?, ?, ?, 0)',
            [$ticketId, $user['id'], $body]
        );

        NotificationService::notifyNewTicket(['id' => $ticketId, 'subject' => $subject, 'assigned_to' => null], $user);

        redirect(url("tickets/{$ticketId}"));
    }

    public function show(string $ticketId): void
    {
        $this->requireAuth();

        $user   = Session::user();
        $ticket = Database::fetchOne('SELECT * FROM pd_tickets WHERE id = ?', [$ticketId]);

        if (!$ticket || (!Session::isStaff() && $ticket['user_id'] != $user['id'])) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Ticket not found.']);
            return;
        }

        $owner = Database::fetchOne('SELECT first_name, last_name, email FROM pd_users WHERE id = ?', [$ticket['user_id']]);

        $messages = Database::fetchAll(
            'SELECT tm.*, u.first_name, u.last_name, u.is_staff
             FROM pd_ticket_messages tm
             JOIN pd_users u ON u.id = tm.user_id
             WHERE tm.ticket_id = ?
             ORDER BY tm.created_at ASC',
            [$ticketId]
        );

        $staffList = Database::fetchAll(
            'SELECT id, first_name, last_name FROM pd_users WHERE is_staff = 1 OR is_admin = 1 ORDER BY first_name'
        );

        $assignee = $ticket['assigned_to']
            ? Database::fetchOne('SELECT id, first_name, last_name FROM pd_users WHERE id = ?', [$ticket['assigned_to']])
            : null;

        render('tickets/show', compact('ticket', 'owner', 'messages', 'staffList', 'assignee', 'user'));
    }

    public function reply(string $ticketId): void
    {
        $this->requireAuth();
        csrf_verify();

        $user   = Session::user();
        $ticket = Database::fetchOne('SELECT * FROM pd_tickets WHERE id = ?', [$ticketId]);

        if (!$ticket || (!Session::isStaff() && $ticket['user_id'] != $user['id'])) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Ticket not found.']);
            return;
        }

        $body   = trim($_POST['body'] ?? '');
        $status = $_POST['status'] ?? $ticket['status'];

        if (!$body) {
            Session::flash('error', 'Reply cannot be empty.');
            redirect(url("tickets/{$ticketId}"));
        }

        $isStaff = Session::isStaff() ? 1 : 0;

        Database::insert(
            'INSERT INTO pd_ticket_messages (ticket_id, user_id, body, is_staff) VALUES (?, ?, ?, ?)',
            [$ticketId, $user['id'], $body, $isStaff]
        );

        if (Session::isStaff()) {
            $newStatus = in_array($status, ['open', 'answered', 'closed']) ? $status : $ticket['status'];
        } else {
            $newStatus = ($ticket['status'] === 'answered') ? 'open' : $ticket['status'];
        }

        Database::query(
            'UPDATE pd_tickets SET status = ?, updated_at = NOW() WHERE id = ?',
            [$newStatus, $ticketId]
        );

        // Notifications
        $ticketOwner = Database::fetchOne('SELECT * FROM pd_users WHERE id = ?', [$ticket['user_id']]);
        NotificationService::notifyTicketReply($ticket, $ticketOwner, $user, (bool)Session::isStaff());

        redirect(url("tickets/{$ticketId}") . '#bottom');
    }

    public function close(string $ticketId): void
    {
        $this->requireAuth();
        csrf_verify();

        $user   = Session::user();
        $ticket = Database::fetchOne('SELECT * FROM pd_tickets WHERE id = ?', [$ticketId]);

        if (!$ticket || (!Session::isStaff() && $ticket['user_id'] != $user['id'])) {
            http_response_code(403);
            render('error', ['code' => 403, 'message' => 'Not authorized.']);
            return;
        }

        Database::query('UPDATE pd_tickets SET status = \'closed\', updated_at = NOW() WHERE id = ?', [$ticketId]);

        redirect(url('tickets'));
    }

    public function assign(string $ticketId): void
    {
        $this->requireAuth();
        csrf_verify();

        if (!Session::isStaff()) {
            http_response_code(403);
            render('error', ['code' => 403, 'message' => 'Staff only.']);
            return;
        }

        $staffId = (int)($_POST['staff_id'] ?? 0);
        // The original stored any posted id, staff or not, on any ticket id.
        if (!Database::fetchOne('SELECT id FROM pd_tickets WHERE id = ?', [$ticketId])
            || ($staffId && !Database::fetchOne('SELECT id FROM pd_users WHERE id = ? AND (is_staff = 1 OR is_admin = 1)', [$staffId]))) {
            Session::flash('error', 'Choose a staff member.');
            redirect(url("tickets/{$ticketId}"));
        }
        Database::query(
            'UPDATE pd_tickets SET assigned_to = ? WHERE id = ?',
            [$staffId ?: null, $ticketId]
        );

        redirect(url("tickets/{$ticketId}"));
    }
}
