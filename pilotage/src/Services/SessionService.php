<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Sessions: scheduling, running, notes, recaps, cadence.
 *
 * The two rules this class exists to enforce:
 *
 *   1. Private notes are never returned by anything a client-side caller can
 *      reach. They live in their own table and only sessionForCoach() touches
 *      it (FR-5.4).
 *   2. A recap is drafted, never auto-sent (FR-5.6). A recap that leaves
 *      before the coach has read it will eventually carry something that
 *      should have stayed in the room.
 */
final class SessionService
{
    /** Create a session, copying the template's agenda into it. */
    public static function schedule(
        int $tenantId,
        int $engagementId,
        string $title,
        ?string $scheduledAt = null,
        ?int $templateId = null,
        ?int $createdBy = null,
        ?string $location = null
    ): int {
        $title = trim($title);

        if ($title === '') {
            throw new \InvalidArgumentException('A session needs a title.');
        }

        $db = Database::conn();

        $template = null;

        if ($templateId !== null) {
            $stmt = $db->prepare('SELECT * FROM pl_session_templates WHERE tenant_id = :tid AND id = :id LIMIT 1');
            $stmt->execute(['tid' => $tenantId, 'id' => $templateId]);
            $template = $stmt->fetch();

            if ($template === false) {
                throw new \RuntimeException('No such session template.');
            }
        }

        $db->beginTransaction();

        try {
            $db->prepare(
                'INSERT INTO pl_sessions
                    (tenant_id, engagement_id, template_id, title, session_type,
                     scheduled_at, duration_minutes, location, created_by)
                 VALUES (:tid, :eid, :tpl, :title, :type, :at, :mins, :loc, :by)'
            )->execute([
                'tid'   => $tenantId,
                'eid'   => $engagementId,
                'tpl'   => $templateId,
                'title' => $title,
                'type'  => $template === false || $template === null ? 'working' : (string) $template['session_type'],
                'at'    => $scheduledAt,
                'mins'  => $template === null ? 60 : (int) ($template['time_box_minutes'] ?? 60),
                'loc'   => $location,
                'by'    => $createdBy,
            ]);

            $sessionId = (int) $db->lastInsertId();

            // Copy the agenda. Editing a template later must not rewrite a
            // meeting that has already happened — same reasoning as playbooks.
            if ($templateId !== null) {
                $items = $db->prepare(
                    'SELECT * FROM pl_session_template_items
                     WHERE tenant_id = :tid AND template_id = :tpl ORDER BY position ASC, id ASC'
                );
                $items->execute(['tid' => $tenantId, 'tpl' => $templateId]);

                $insert = $db->prepare(
                    'INSERT INTO pl_session_agenda_items
                        (tenant_id, session_id, position, title, minutes, auto_block, prompt)
                     VALUES (:tid, :sid, :pos, :title, :mins, :block, :prompt)'
                );

                foreach ($items->fetchAll() as $item) {
                    $insert->execute([
                        'tid'    => $tenantId,
                        'sid'    => $sessionId,
                        'pos'    => (int) $item['position'],
                        'title'  => (string) $item['title'],
                        'mins'   => $item['minutes'],
                        'block'  => $item['auto_block'],
                        'prompt' => $item['prompt'],
                    ]);
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // AFTER the commit, deliberately. Queueing inside the transaction
        // would mean a rolled-back session had still told everyone it existed.
        //
        // Transactional: a meeting in someone's calendar is not an update
        // about the work, it is the work. Both sides hear about it.
        Notifications::queueMany(
            $tenantId,
            array_merge(
                Notifications::firmRecipients($tenantId, $engagementId),
                Notifications::clientRecipients($tenantId, $engagementId)
            ),
            'session.scheduled',
            $title . ' — ' . date('j F, H:i', strtotime($scheduledAt)) . ' UTC',
            $location === null || $location === '' ? null : 'Where: ' . $location,
            '/sessions/' . $sessionId,
            ['object_type' => 'session', 'object_id' => $sessionId]
                + Notifications::orgContext($tenantId, $engagementId),
            $createdBy
        );

        return $sessionId;
    }

    /**
     * The full session for the COACH: agenda with auto blocks filled, shared
     * notes, and their own private notes.
     *
     * @return array<string,mixed>|null
     */
    public static function forCoach(int $tenantId, int $sessionId, int $userId): ?array
    {
        $session = self::find($tenantId, $sessionId);

        if ($session === null) {
            return null;
        }

        $session['agenda'] = self::agenda($tenantId, $session);
        $session['shared_notes'] = self::sharedNotes($tenantId, $sessionId);
        $session['private_notes'] = self::privateNotes($tenantId, $sessionId, $userId);
        $session['attendees'] = self::attendees($tenantId, $sessionId);
        $session['recap'] = self::recap($tenantId, $sessionId);

        return $session;
    }

    /**
     * The session as a CLIENT sees it.
     *
     * Never touches pl_session_notes_private, and never returns the agenda's
     * coach-facing prompts. A recap is only visible once it has been sent.
     *
     * @return array<string,mixed>|null
     */
    public static function forClient(int $tenantId, int $sessionId): ?array
    {
        $session = self::find($tenantId, $sessionId);

        if ($session === null) {
            return null;
        }

        $agenda = self::agenda($tenantId, $session);

        foreach ($agenda as $i => $item) {
            unset($agenda[$i]['prompt']);   // coach-facing "what to do here"
        }

        $recap = self::recap($tenantId, $sessionId);

        $session['agenda'] = $agenda;
        $session['shared_notes'] = self::sharedNotes($tenantId, $sessionId);
        $session['attendees'] = self::attendees($tenantId, $sessionId);
        $session['recap'] = ($recap !== null && $recap['status'] === 'sent') ? $recap : null;

        return $session;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $tenantId, int $sessionId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT s.*, e.client_org_id, e.title AS engagement_title, e.coach_user_id
             FROM pl_sessions s
             JOIN pl_engagements e ON e.id = s.engagement_id AND e.tenant_id = s.tenant_id
             WHERE s.tenant_id = :tid AND s.id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $sessionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function forEngagement(int $tenantId, int $engagementId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_sessions
             WHERE tenant_id = :tid AND engagement_id = :eid
             ORDER BY COALESCE(scheduled_at, created_at) DESC LIMIT ' . $limit
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $session @return array<int,array<string,mixed>> */
    public static function agenda(int $tenantId, array $session): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_session_agenda_items
             WHERE tenant_id = :tid AND session_id = :sid ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => (int) $session['id']]);

        $items = $stmt->fetchAll();

        foreach ($items as $i => $item) {
            if ($item['auto_block'] !== null) {
                $items[$i]['block'] = AgendaBuilder::build((string) $item['auto_block'], $tenantId, $session);
            }
        }

        return $items;
    }

    public static function start(int $tenantId, int $sessionId): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE pl_sessions
             SET status = 'in_progress', started_at = COALESCE(started_at, NOW())
             WHERE tenant_id = :tid AND id = :id AND status = 'scheduled'"
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $sessionId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Close the session and draft a recap (FR-5.6).
     *
     * Draft only. Sending is a separate, deliberate act.
     */
    public static function close(int $tenantId, int $sessionId): ?string
    {
        $db = Database::conn();
        $session = self::find($tenantId, $sessionId);

        if ($session === null) {
            return null;
        }

        $db->prepare(
            "UPDATE pl_sessions SET status = 'complete', ended_at = NOW()
             WHERE tenant_id = :tid AND id = :id"
        )->execute(['tid' => $tenantId, 'id' => $sessionId]);

        $recap = self::draftRecapBody($tenantId, $session);

        $db->prepare(
            "INSERT INTO pl_session_recaps (tenant_id, session_id, body, status)
             VALUES (:tid, :sid, :body, 'draft')
             ON DUPLICATE KEY UPDATE body = VALUES(body)"
        )->execute(['tid' => $tenantId, 'sid' => $sessionId, 'body' => $recap]);

        return $recap;
    }

    /**
     * Build the client-facing recap from shared notes only.
     *
     * @param array<string,mixed> $session
     */
    public static function draftRecapBody(int $tenantId, array $session): string
    {
        $shared = self::sharedNotes($tenantId, (int) $session['id']);

        $lines = [];
        $lines[] = $session['title'];

        if (!empty($session['scheduled_at'])) {
            $lines[] = date('j F Y', strtotime((string) $session['scheduled_at']));
        }

        $lines[] = '';

        if ($shared !== null && trim((string) $shared['body']) !== '') {
            $lines[] = trim((string) $shared['body']);
        } else {
            $lines[] = '(No shared notes were recorded.)';
        }

        $agenda = self::agenda($tenantId, $session);
        $covered = [];

        foreach ($agenda as $item) {
            if ($item['covered_at'] !== null) {
                $covered[] = '- ' . $item['title'];
            }
        }

        if ($covered !== []) {
            $lines[] = '';
            $lines[] = 'What we covered:';
            $lines = array_merge($lines, $covered);
        }

        return implode("\n", $lines);
    }

    public static function saveRecap(int $tenantId, int $sessionId, string $body): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE pl_session_recaps SET body = :body
             WHERE tenant_id = :tid AND session_id = :sid AND status = 'draft'"
        );
        $stmt->execute(['body' => $body, 'tid' => $tenantId, 'sid' => $sessionId]);

        return $stmt->rowCount() === 1;
    }

    /** Marks the recap sent. Actual delivery is the caller's job. */
    public static function markRecapSent(int $tenantId, int $sessionId, int $userId): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE pl_session_recaps
             SET status = 'sent', sent_at = NOW(), sent_by = :uid
             WHERE tenant_id = :tid AND session_id = :sid AND status = 'draft'"
        );
        $stmt->execute(['uid' => $userId, 'tid' => $tenantId, 'sid' => $sessionId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<string,mixed>|null */
    public static function recap(int $tenantId, int $sessionId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_session_recaps WHERE tenant_id = :tid AND session_id = :sid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => $sessionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    // ------------------------------------------------------------------ notes

    public static function saveSharedNotes(int $tenantId, int $sessionId, string $body, ?int $authorId): void
    {
        Database::conn()->prepare(
            'INSERT INTO pl_session_notes_shared (tenant_id, session_id, body, author_id)
             VALUES (:tid, :sid, :body, :uid)
             ON DUPLICATE KEY UPDATE body = VALUES(body), author_id = VALUES(author_id)'
        )->execute(['tid' => $tenantId, 'sid' => $sessionId, 'body' => $body, 'uid' => $authorId]);
    }

    /** @return array<string,mixed>|null */
    public static function sharedNotes(int $tenantId, int $sessionId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_session_notes_shared WHERE tenant_id = :tid AND session_id = :sid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => $sessionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function savePrivateNotes(int $tenantId, int $sessionId, string $body, int $authorId): void
    {
        Database::conn()->prepare(
            'INSERT INTO pl_session_notes_private (tenant_id, session_id, body, author_id)
             VALUES (:tid, :sid, :body, :uid)
             ON DUPLICATE KEY UPDATE body = VALUES(body)'
        )->execute(['tid' => $tenantId, 'sid' => $sessionId, 'body' => $body, 'uid' => $authorId]);
    }

    /**
     * A coach's own private notes. Note the author filter: private notes are
     * private from other coaches too, not merely from the client.
     *
     * @return array<string,mixed>|null
     */
    public static function privateNotes(int $tenantId, int $sessionId, int $authorId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_session_notes_private
             WHERE tenant_id = :tid AND session_id = :sid AND author_id = :uid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => $sessionId, 'uid' => $authorId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    // -------------------------------------------------------------- attendees

    public static function addAttendee(int $tenantId, int $sessionId, ?int $userId, string $displayName, ?int $contactId = null): int
    {
        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_session_attendees (tenant_id, session_id, user_id, contact_id, display_name)
             VALUES (:tid, :sid, :uid, :cid, :name)
             ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)'
        )->execute([
            'tid'  => $tenantId,
            'sid'  => $sessionId,
            'uid'  => $userId,
            'cid'  => $contactId,
            'name' => $displayName,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function markAttendance(int $tenantId, int $attendeeId, bool $attended): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_session_attendees SET attended = :a WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute(['a' => $attended ? 1 : 0, 'tid' => $tenantId, 'id' => $attendeeId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public static function attendees(int $tenantId, int $sessionId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_session_attendees WHERE tenant_id = :tid AND session_id = :sid ORDER BY display_name ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => $sessionId]);

        return $stmt->fetchAll();
    }

    public static function isAttendee(int $tenantId, int $sessionId, int $userId): bool
    {
        $stmt = Database::conn()->prepare(
            'SELECT 1 FROM pl_session_attendees
             WHERE tenant_id = :tid AND session_id = :sid AND user_id = :uid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => $sessionId, 'uid' => $userId]);

        return $stmt->fetch() !== false;
    }

    // ---------------------------------------------------------------- cadence

    /**
     * Cadence slip (FR-5.8): engagements with no future session booked and a
     * last session older than their declared rhythm allows.
     *
     * This is a risk signal for the coach dashboard, not a nag for the client.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function cadenceSlipped(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT e.id, e.title, e.cadence, e.client_org_id, o.name AS org_name,
                    -- When it ACTUALLY happened, not when it was pencilled in.
                    -- A session moved twice and finally held last Tuesday is
                    -- last met on Tuesday; its scheduled_at may say anything.
                    (SELECT MAX(COALESCE(s.ended_at, s.scheduled_at)) FROM pl_sessions s
                      WHERE s.engagement_id = e.id AND s.status = 'complete') AS last_session,
                    (SELECT COUNT(*) FROM pl_sessions s2
                      WHERE s2.engagement_id = e.id AND s2.status = 'scheduled'
                        AND s2.scheduled_at > NOW()) AS upcoming
             FROM pl_engagements e
             JOIN pl_client_orgs o ON o.id = e.client_org_id
             WHERE e.tenant_id = :tid AND e.status = 'active' AND e.cadence <> 'adhoc'"
        );
        $stmt->execute(['tid' => $tenantId]);

        $slipped = [];
        $now = time();

        foreach ($stmt->fetchAll() as $row) {
            if ((int) $row['upcoming'] > 0) {
                continue;   // something is booked; not slipping
            }

            $allowed = \Bizorca\Pilotage\Repositories\EngagementRepository::CADENCE_DAYS[(string) $row['cadence']] ?? null;

            if ($allowed === null) {
                continue;
            }

            $last = $row['last_session'] === null ? null : strtotime((string) $row['last_session']);

            // Never met, or overdue by the cadence. Both are worth surfacing.
            if ($last === null || ($now - $last) > ($allowed * 86400)) {
                $row['days_since'] = $last === null ? null : (int) floor(($now - $last) / 86400);
                $slipped[] = $row;
            }
        }

        return $slipped;
    }
}
