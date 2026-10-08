<?php

declare(strict_types=1);

class EventController
{
    private PDO  $db;
    private Auth $auth;

    public function __construct(PDO $db, Auth $auth)
    {
        $this->db   = $db;
        $this->auth = $auth;
    }

    // GET /api/events?city_id=chiang-mai
    public function index(): never
    {
        $cityId = $_GET['city_id'] ?? null;
        if (!is_string($cityId)) {
            $cityId = null;
        }

        $sql = '
            SELECT
                e.id, e.title, e.city_id, e.event_date, e.max_attendees, e.notes,
                r.id   AS restaurant_id,
                r.name AS restaurant_name,
                r.neighborhood AS restaurant_neighborhood,
                r.price_range,
                hp.public_id  AS host_user_id,
                hp.first_name AS host_first_name,
                (SELECT COUNT(*) FROM pc_rsvps WHERE event_id = e.id) AS attendee_count
            FROM pc_events e
            JOIN pc_restaurants r ON r.id = e.restaurant_id
            JOIN pc_profiles hp ON hp.user_id = e.host_user_id
            WHERE e.is_active = 1
              AND e.event_date > NOW()
        ';

        $params = [];
        if ($cityId) {
            $sql .= ' AND e.city_id = ?';
            $params[] = $cityId;
        }

        $sql .= ' ORDER BY e.event_date ASC, e.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['attendee_count'] = (int) $row['attendee_count'];
            $row['max_attendees']  = (int) $row['max_attendees'];
            $row['spots_remaining'] = max(0, $row['max_attendees'] - $row['attendee_count']);
            $row['is_full'] = $row['spots_remaining'] === 0;
        }
        unset($row);

        Response::success($rows);
    }

    // GET /api/events/{id}
    public function show(string $id): never
    {
        $event = $this->fetchEvent($id);
        if (!$event) {
            Response::notFound('Event not found');
        }
        Response::success($event);
    }

    // POST /api/events
    public function create(): never
    {
        $userId = $this->auth->requireAuth();
        $this->requireProfileComplete($userId);

        $body = PcRequest::json();

        $restaurantId = (string) ($body['restaurant_id'] ?? '');
        $cityId       = (string) ($body['city_id']       ?? '');
        $eventDate    = (string) ($body['event_date']     ?? '');
        $maxAttendees = (int) ($body['max_attendees'] ?? 6);
        $notes        = trim((string) ($body['notes'] ?? ''));
        $title        = trim((string) ($body['title'] ?? ''));

        $errors = [];
        if (!$restaurantId) $errors[] = 'restaurant_id is required';
        if (!$cityId)       $errors[] = 'city_id is required';
        if (!$eventDate)    $errors[] = 'event_date is required (ISO 8601)';
        if ($maxAttendees < 2 || $maxAttendees > 20) $errors[] = 'max_attendees must be between 2 and 20';

        if ($errors) {
            Response::error('Validation failed', 422, $errors);
        }

        // Validate date is in the future
        $ts = strtotime($eventDate);
        if (!$ts || $ts <= time()) {
            Response::error('event_date must be in the future', 422);
        }

        // Confirm restaurant exists and is in the right city
        $stmt = $this->db->prepare('SELECT id FROM pc_restaurants WHERE id = ? AND city_id = ? AND is_active = 1');
        $stmt->execute([$restaurantId, $cityId]);
        if (!$stmt->fetch()) {
            Response::error('Restaurant not found in that city', 404);
        }

        $id = Profile::uuid();
        if ($title === '') {
            $stmt = $this->db->prepare('SELECT name FROM pc_restaurants WHERE id = ?');
            $stmt->execute([$restaurantId]);
            $rName = $stmt->fetchColumn();
            $title = "Dinner at $rName";
        }

        $this->db->prepare(
            'INSERT INTO pc_events (id, title, restaurant_id, city_id, host_user_id, event_date, max_attendees, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, $title, $restaurantId, $cityId, $userId, date('Y-m-d H:i:s', $ts), $maxAttendees, $notes]);

        // Host automatically RSVPs
        $this->db->prepare(
            'INSERT INTO pc_rsvps (id, event_id, user_id) VALUES (?, ?, ?)'
        )->execute([Profile::uuid(), $id, $userId]);

        $event = $this->fetchEvent($id);
        Response::success($event, 'Event created', 201);
    }

    // DELETE /api/events/{id}
    public function delete(string $id): never
    {
        $userId = $this->auth->requireAuth();

        $stmt = $this->db->prepare('SELECT host_user_id FROM pc_events WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::notFound('Event not found');
        }
        if ((int) $row['host_user_id'] !== $userId) {
            Response::forbidden('Only the host can cancel this event');
        }

        $this->db->prepare('UPDATE pc_events SET is_active = 0 WHERE id = ?')->execute([$id]);
        Response::success(null, 'Event cancelled');
    }

    // POST /api/events/{id}/rsvp
    public function rsvp(string $id): never
    {
        $userId = $this->auth->requireAuth();
        $this->requireProfileComplete($userId);

        $event = $this->fetchEvent($id);
        if (!$event) {
            Response::notFound('Event not found');
        }
        if ($event['is_full']) {
            Response::error('This dinner is full', 409);
        }

        // Idempotent — already RSVP'd is fine
        $stmt = $this->db->prepare('SELECT id FROM pc_rsvps WHERE event_id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if ($stmt->fetch()) {
            Response::success($this->fetchEvent($id), 'Already RSVP\'d');
        }

        $this->db->prepare(
            'INSERT INTO pc_rsvps (id, event_id, user_id) VALUES (?, ?, ?)'
        )->execute([Profile::uuid(), $id, $userId]);

        Response::success($this->fetchEvent($id), 'RSVP confirmed');
    }

    // DELETE /api/events/{id}/rsvp
    public function cancelRsvp(string $id): never
    {
        $userId = $this->auth->requireAuth();

        // Hosts can't un-RSVP their own event
        $stmt = $this->db->prepare('SELECT host_user_id FROM pc_events WHERE id = ?');
        $stmt->execute([$id]);
        $event = $stmt->fetch();
        if ($event && (int) $event['host_user_id'] === $userId) {
            Response::error('Hosts cannot cancel their own RSVP. Cancel the event instead.', 422);
        }

        $this->db->prepare(
            'DELETE FROM pc_rsvps WHERE event_id = ? AND user_id = ?'
        )->execute([$id, $userId]);

        Response::success(null, 'RSVP cancelled');
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function fetchEvent(string $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                e.id, e.title, e.city_id, e.event_date, e.max_attendees, e.notes, hp.public_id AS host_user_id,
                r.id   AS restaurant_id,
                r.name AS restaurant_name,
                r.neighborhood AS restaurant_neighborhood,
                r.price_range,
                hp.first_name AS host_first_name,
                (SELECT COUNT(*) FROM pc_rsvps WHERE event_id = e.id) AS attendee_count
            FROM pc_events e
            JOIN pc_restaurants r ON r.id = e.restaurant_id
            JOIN pc_profiles hp ON hp.user_id = e.host_user_id
            WHERE e.id = ? AND e.is_active = 1
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['attendee_count']  = (int) $row['attendee_count'];
        $row['max_attendees']   = (int) $row['max_attendees'];
        $row['spots_remaining'] = max(0, $row['max_attendees'] - $row['attendee_count']);
        $row['is_full']         = $row['spots_remaining'] === 0;

        return $row;
    }

    private function requireProfileComplete(int $userId): void
    {
        $stmt = $this->db->prepare('SELECT is_profile_complete FROM pc_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !$row['is_profile_complete']) {
            Response::error('Complete your profile before creating or RSVPing to events', 403);
        }
    }
}
