<?php

declare(strict_types=1);

class UserController
{
    private PDO  $db;
    private Auth $auth;

    // users/me as the original returned it, from the profile plus the shared
    // account's email. show_last_name is new in the response (the original
    // saved it but never returned it, so the web privacy toggle always
    // rendered as off); extra keys are ignored by the iOS decoder.
    private const ME_SQL =
        'SELECT p.public_id AS id, p.first_name, p.last_name, u.email, p.age, p.bio, p.interests,
                p.dining_preferences, p.show_exact_age, p.show_last_name, p.is_profile_complete,
                p.member_since
           FROM pc_profiles p JOIN users u ON u.id = p.user_id
          WHERE p.user_id = ?';

    public function __construct(PDO $db, Auth $auth)
    {
        $this->db   = $db;
        $this->auth = $auth;
    }

    // GET /api/users/me
    public function me(): never
    {
        $userId = $this->auth->requireAuth();

        $stmt = $this->db->prepare(self::ME_SQL);
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::notFound('User not found');
        }

        Response::success($this->formatUser($user));
    }

    // PUT /api/users/me
    public function update(): never
    {
        $userId = $this->auth->requireAuth();
        $body   = PcRequest::json();

        $allowed = ['bio', 'interests', 'dining_preferences', 'show_exact_age', 'show_last_name'];
        $sets    = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $value = $body[$field];
            if (in_array($field, ['interests', 'dining_preferences'], true)) {
                $value = json_encode(is_array($value) ? array_values($value) : []);
            } elseif (in_array($field, ['show_exact_age', 'show_last_name'], true)) {
                // The original bound PHP false as '' into a TINYINT, which
                // strict-mode MySQL rejects: turning a privacy toggle OFF was a
                // "Database error". Store 0/1.
                $value = $value ? 1 : 0;
            } else {
                $value = $value === null ? null : (string) $value;
            }
            $sets[]   = "$field = ?";
            $params[] = $value;
        }

        // Mark profile complete if bio + interests are present
        if (isset($body['bio'], $body['interests']) && is_array($body['interests'])
            && strlen(trim((string) $body['bio'])) >= 10 && count($body['interests']) >= 1) {
            $sets[] = 'is_profile_complete = 1';
        }

        if (empty($sets)) {
            Response::error('No valid fields to update', 422);
        }

        $params[] = $userId;
        $this->db->prepare('UPDATE pc_profiles SET ' . implode(', ', $sets) . ' WHERE user_id = ?')->execute($params);

        $stmt = $this->db->prepare(self::ME_SQL);
        $stmt->execute([$userId]);
        Response::success($this->formatUser($stmt->fetch()), 'Profile updated');
    }

    // GET /api/users/me/events
    public function myEvents(): never
    {
        $userId = $this->auth->requireAuth();

        // restaurant_id and host_user_id are added: the iOS APIEvent decoder
        // requires both, so the original list could not be decoded by the app.
        $stmt = $this->db->prepare('
            SELECT
                e.id, e.title, e.city_id, e.event_date, e.max_attendees, e.notes,
                r.id   AS restaurant_id,
                r.name AS restaurant_name,
                r.neighborhood AS restaurant_neighborhood,
                hp.public_id  AS host_user_id,
                hp.first_name AS host_first_name,
                (e.host_user_id = :uid) AS is_host,
                (SELECT COUNT(*) FROM pc_rsvps WHERE event_id = e.id) AS attendee_count
            FROM pc_events e
            JOIN pc_restaurants r ON r.id = e.restaurant_id
            JOIN pc_profiles hp ON hp.user_id = e.host_user_id
            WHERE e.is_active = 1
              AND (e.host_user_id = :uid2 OR EXISTS (
                    SELECT 1 FROM pc_rsvps WHERE event_id = e.id AND user_id = :uid3
              ))
            ORDER BY e.event_date ASC, e.id ASC
        ');
        $stmt->execute([':uid' => $userId, ':uid2' => $userId, ':uid3' => $userId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['attendee_count']  = (int) $row['attendee_count'];
            $row['max_attendees']   = (int) $row['max_attendees'];
            $row['is_host']         = (bool) $row['is_host'];
            $row['spots_remaining'] = max(0, $row['max_attendees'] - $row['attendee_count']);
            $row['is_full']         = $row['spots_remaining'] === 0;
        }
        unset($row);

        Response::success($rows);
    }

    private function formatUser(array $user): array
    {
        $user['interests']           = json_decode($user['interests'] ?? '[]', true) ?? [];
        $user['dining_preferences']  = json_decode($user['dining_preferences'] ?? '[]', true) ?? [];
        $user['age']                 = $user['age'] === null ? null : (int) $user['age'];
        $user['show_exact_age']      = (bool) $user['show_exact_age'];
        $user['show_last_name']      = (bool) $user['show_last_name'];
        $user['is_profile_complete'] = (bool) $user['is_profile_complete'];
        // Never expose last_name in the public-facing response
        unset($user['last_name']);
        return $user;
    }
}
