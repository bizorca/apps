<?php

namespace Bizorca\Pod\Controllers;

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Database;

class EventController
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

        $events = Database::fetchAll(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM pd_event_rsvps WHERE event_id = e.id) AS rsvp_count
             FROM pd_events e
             WHERE e.is_published = 1
             ORDER BY e.starts_at ASC'
        );

        $user = Session::user();

        $myRsvps = array_column(
            Database::fetchAll(
                'SELECT event_id FROM pd_event_rsvps WHERE user_id = ?',
                [$user['id']]
            ),
            'event_id'
        );

        $now = new \DateTime();
        foreach ($events as &$e) {
            $e['is_past'] = new \DateTime($e['starts_at']) < $now;
        }
        unset($e);

        render('events/index', compact('events', 'myRsvps', 'user'));
    }

    public function show(string $eventId): void
    {
        $this->requireAuth();

        $event = Database::fetchOne(
            'SELECT * FROM pd_events WHERE id = ? AND is_published = 1',
            [$eventId]
        );

        if (!$event) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Event not found.']);
            return;
        }

        $user = Session::user();

        $rsvped = (bool)Database::fetchOne(
            'SELECT id FROM pd_event_rsvps WHERE event_id = ? AND user_id = ?',
            [$eventId, $user['id']]
        );

        $rsvpCount = (int)Database::fetchOne(
            'SELECT COUNT(*) AS n FROM pd_event_rsvps WHERE event_id = ?',
            [$eventId]
        )['n'];

        $isPast = new \DateTime($event['starts_at']) < new \DateTime();

        render('events/show', compact('event', 'rsvped', 'rsvpCount', 'isPast', 'user'));
    }

    public function rsvp(string $eventId): void
    {
        $this->requireAuth();
        csrf_verify();

        $event = Database::fetchOne('SELECT * FROM pd_events WHERE id = ? AND is_published = 1', [$eventId]);

        if (!$event) {
            http_response_code(404);
            render('error', ['code' => 404, 'message' => 'Event not found.']);
            return;
        }

        $user = Session::user();

        Database::query(
            'INSERT IGNORE INTO pd_event_rsvps (event_id, user_id) VALUES (?, ?)',
            [$eventId, $user['id']]
        );

        redirect(url("events/{$eventId}"));
    }

    public function cancelRsvp(string $eventId): void
    {
        $this->requireAuth();
        csrf_verify();

        $user = Session::user();

        Database::query(
            'DELETE FROM pd_event_rsvps WHERE event_id = ? AND user_id = ?',
            [$eventId, $user['id']]
        );

        redirect(url("events/{$eventId}"));
    }
}
