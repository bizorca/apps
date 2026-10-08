<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Cohorts — several client organizations running one playbook together.
 *
 * ---------------------------------------------------------------------------
 * A COHORT IS A COORDINATING LAYER, NOT A CONTAINER.
 *
 * Every member keeps its own engagement, and every commitment, document,
 * metric, goal, issue and message stays scoped to that engagement exactly as
 * before. Cohort membership grants access to three things and nothing else:
 *
 *   1. the shared sessions — they were all in the room
 *   2. material the coach deliberately published to the cohort
 *   3. the roster, and only if the coach turned it on
 *
 * That is the entire surface. Everything else about a member organization is
 * as invisible to its cohort peers as it was before the cohort existed, because
 * nothing about membership touches the queries that return those records.
 *
 * The reason to build it this way rather than as a container: a container would
 * mean every existing scoped query needing a new "…or shared with my cohort"
 * branch, and one missed branch is a cross-client leak. Here there are no
 * branches to miss.
 *
 * WHAT A COACH MUST STILL BE CAREFUL ABOUT, because software cannot fix it:
 * shared notes on a cohort session are read by every member. That is correct —
 * they were all there — but it means "Alpha is struggling with cash" typed into
 * a cohort session's shared notes is published to Alpha's peers. The screens
 * say so, loudly, next to the box. Private notes remain per-coach as always.
 * ---------------------------------------------------------------------------
 */
final class Cohorts
{
    /** @param array<string,mixed> $data */
    public static function create(int $tenantId, array $data, ?int $userId = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('A cohort needs a name.');
        }

        $cadence = (string) ($data['cadence'] ?? 'monthly');

        if (!in_array($cadence, ['weekly', 'biweekly', 'monthly', 'quarterly', 'adhoc'], true)) {
            throw new \InvalidArgumentException('Unknown cadence.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_cohorts
                (tenant_id, name, description, playbook_version_id, starts_on, ends_on,
                 cadence, roster_visible, created_by)
             VALUES (:tid, :name, :desc, :version, :starts, :ends, :cadence, :roster, :by)'
        )->execute([
            'tid'     => $tenantId,
            'name'    => mb_substr($name, 0, 255),
            'desc'    => trim((string) ($data['description'] ?? '')) ?: null,
            'version' => ($data['playbook_version_id'] ?? null) ?: null,
            'starts'  => ($data['starts_on'] ?? null) ?: null,
            'ends'    => ($data['ends_on'] ?? null) ?: null,
            'cadence' => $cadence,
            // Off unless asked for. See the note in the migration: publishing a
            // roster nobody agreed to publish is a confidentiality incident,
            // and it must never be the result of doing nothing.
            'roster'  => !empty($data['roster_visible']) ? 1 : 0,
            'by'      => $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $tenantId, int $cohortId, array $data): bool
    {
        $fields = [];
        $params = ['tid' => $tenantId, 'id' => $cohortId];

        foreach ([
            'name' => 'name', 'description' => 'desc', 'starts_on' => 'starts',
            'ends_on' => 'ends', 'cadence' => 'cadence', 'status' => 'status',
        ] as $column => $placeholder) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $fields[] = "`{$column}` = :{$placeholder}";
            $params[$placeholder] = $data[$column] === '' ? null : $data[$column];
        }

        if (array_key_exists('roster_visible', $data)) {
            $fields[] = '`roster_visible` = :roster';
            $params['roster'] = empty($data['roster_visible']) ? 0 : 1;
        }

        if ($fields === []) {
            return false;
        }

        $stmt = Database::conn()->prepare(
            'UPDATE pl_cohorts SET ' . implode(', ', $fields) . ' WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute($params);

        return $stmt->rowCount() === 1;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $tenantId, int $cohortId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT c.*, v.version_number, p.name AS playbook_name
             FROM pl_cohorts c
             LEFT JOIN pl_playbook_versions v ON v.id = c.playbook_version_id AND v.tenant_id = c.tenant_id
             LEFT JOIN pl_playbooks p ON p.id = v.playbook_id AND p.tenant_id = c.tenant_id
             WHERE c.tenant_id = :tid AND c.id = :id'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $cohortId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(int $tenantId, bool $activeOnly = false): array
    {
        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM pl_cohort_members m
                         WHERE m.cohort_id = c.id AND m.left_at IS NULL) AS member_count,
                       (SELECT COUNT(*) FROM pl_sessions s
                         WHERE s.cohort_id = c.id AND s.status = 'complete') AS sessions_held
                FROM pl_cohorts c
                WHERE c.tenant_id = :tid";

        if ($activeOnly) {
            $sql .= " AND c.status IN ('draft','active')";
        }

        $sql .= ' ORDER BY c.starts_on DESC, c.id DESC';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------ membership

    /**
     * Add an engagement to a cohort.
     *
     * By engagement rather than by organization, deliberately. A client can be
     * in a cohort for one piece of work and not another, and the engagement is
     * already the unit everything else is scoped to — so nothing new has to
     * learn about cohorts.
     */
    public static function addMember(int $tenantId, int $cohortId, int $engagementId): bool
    {
        $db = Database::conn();

        // Both ends must belong to this firm. Checking rather than trusting,
        // because this is the one method that creates a link between two client
        // organizations and it is worth being paranoid about.
        $check = $db->prepare(
            'SELECT
                (SELECT COUNT(*) FROM pl_cohorts WHERE tenant_id = :tid AND id = :cid) AS cohort_ok,
                (SELECT COUNT(*) FROM pl_engagements WHERE tenant_id = :tid2 AND id = :eid) AS engagement_ok'
        );
        $check->execute(['tid' => $tenantId, 'cid' => $cohortId, 'tid2' => $tenantId, 'eid' => $engagementId]);
        $ok = $check->fetch();

        if ((int) $ok['cohort_ok'] !== 1 || (int) $ok['engagement_ok'] !== 1) {
            return false;
        }

        $stmt = $db->prepare(
            'INSERT INTO pl_cohort_members (tenant_id, cohort_id, engagement_id)
             VALUES (:tid, :cid, :eid)
             ON DUPLICATE KEY UPDATE left_at = NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $cohortId, 'eid' => $engagementId]);

        return true;
    }

    /**
     * Remove an engagement from a cohort.
     *
     * Marked, not deleted. "Who was in the room in March" has to stay
     * answerable after someone leaves — a cohort session's notes were shared
     * with whoever was a member at the time, and pretending otherwise later
     * would misrepresent what happened.
     */
    public static function removeMember(int $tenantId, int $cohortId, int $engagementId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_cohort_members SET left_at = NOW()
             WHERE tenant_id = :tid AND cohort_id = :cid AND engagement_id = :eid AND left_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $cohortId, 'eid' => $engagementId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * The members, with where each has got to.
     *
     * This is the coach's view and it is the point of a cohort: seeing at a
     * glance that four of six are through phase two and two have not started.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function members(int $tenantId, int $cohortId, bool $includeLeft = false): array
    {
        $sql = 'SELECT m.*, e.title AS engagement_title, e.status AS engagement_status,
                       o.id AS client_org_id, o.name AS org_name
                FROM pl_cohort_members m
                JOIN pl_engagements e ON e.id = m.engagement_id AND e.tenant_id = m.tenant_id
                JOIN pl_client_orgs o ON o.id = e.client_org_id AND o.tenant_id = m.tenant_id
                WHERE m.tenant_id = :tid AND m.cohort_id = :cid';

        if (!$includeLeft) {
            $sql .= ' AND m.left_at IS NULL';
        }

        $sql .= ' ORDER BY o.name ASC';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'cid' => $cohortId]);

        $rows = $stmt->fetchAll();
        $engagements = new \Bizorca\Pilotage\Repositories\EngagementRepository($tenantId);

        foreach ($rows as $i => $row) {
            $instance = $engagements->playbookInstance((int) $row['engagement_id']);

            $rows[$i]['progress'] = $instance === null
                ? null
                : PlaybookRunner::progress($tenantId, (int) $instance['id']);
        }

        return $rows;
    }

    /**
     * Which cohorts an engagement belongs to. The client-side entry point.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forEngagement(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT c.*
             FROM pl_cohorts c
             JOIN pl_cohort_members m ON m.cohort_id = c.id AND m.tenant_id = c.tenant_id
             WHERE c.tenant_id = :tid AND m.engagement_id = :eid AND m.left_at IS NULL
               AND c.status IN ('active','complete')
             ORDER BY c.starts_on DESC"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        return $stmt->fetchAll();
    }

    /**
     * May this person see this cohort at all?
     *
     * Firm-side: yes, it is their firm's cohort. Client-side: only if one of
     * their organization's engagements is a current member. A client who left
     * the cohort loses the shared surface, which is the right way round —
     * material published after they left is not theirs.
     *
     * @param array<string,mixed> $user
     */
    public static function visibleTo(int $tenantId, int $cohortId, array $user): bool
    {
        if (($user['client_org_id'] ?? null) === null) {
            return self::find($tenantId, $cohortId) !== null;
        }

        $stmt = Database::conn()->prepare(
            'SELECT COUNT(*) AS c
             FROM pl_cohort_members m
             JOIN pl_engagements e ON e.id = m.engagement_id AND e.tenant_id = m.tenant_id
             WHERE m.tenant_id = :tid AND m.cohort_id = :cid
               AND m.left_at IS NULL AND e.client_org_id = :org'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $cohortId, 'org' => (int) $user['client_org_id']]);

        return (int) $stmt->fetch()['c'] > 0;
    }

    /**
     * The roster, as this person is allowed to see it.
     *
     * A client-side viewer gets organization names and nothing else — no
     * engagement titles, no progress, no contact details. The names are the
     * point of a peer group; anything more is somebody else's business.
     *
     * Returns an empty list when the coach has not turned the roster on, which
     * is the default.
     *
     * @param array<string,mixed> $user
     * @return array<int,string>
     */
    public static function rosterFor(int $tenantId, int $cohortId, array $user): array
    {
        $cohort = self::find($tenantId, $cohortId);

        if ($cohort === null || !self::visibleTo($tenantId, $cohortId, $user)) {
            return [];
        }

        $firmSide = ($user['client_org_id'] ?? null) === null;

        if (!$firmSide && (int) $cohort['roster_visible'] !== 1) {
            return [];
        }

        $names = [];

        foreach (self::members($tenantId, $cohortId) as $member) {
            $names[] = (string) $member['org_name'];
        }

        return $names;
    }

    // -------------------------------------------------------------- sessions

    /**
     * Schedule a session for the whole cohort.
     *
     * One meeting, not one per member. Attendance is recorded per person, so
     * "who actually came" survives, but the session, its agenda and its shared
     * notes are a single record because a single thing happened.
     */
    public static function scheduleSession(
        int $tenantId,
        int $cohortId,
        string $title,
        ?string $scheduledAt,
        ?int $createdBy = null,
        ?string $location = null,
        int $durationMinutes = 90
    ): int {
        $title = trim($title);

        if ($title === '') {
            throw new \InvalidArgumentException('A session needs a title.');
        }

        if (self::find($tenantId, $cohortId) === null) {
            throw new \RuntimeException('No such cohort.');
        }

        $db = Database::conn();

        $db->prepare(
            "INSERT INTO pl_sessions
                (tenant_id, engagement_id, cohort_id, title, session_type, scheduled_at,
                 duration_minutes, location, created_by)
             VALUES (:tid, NULL, :cid, :title, 'working', :at, :mins, :loc, :by)"
        )->execute([
            'tid' => $tenantId, 'cid' => $cohortId,
            'title' => mb_substr($title, 0, 255),
            'at' => $scheduledAt, 'mins' => max(15, $durationMinutes),
            'loc' => $location, 'by' => $createdBy,
        ]);

        $sessionId = (int) $db->lastInsertId();

        // Everyone on the client side of every member engagement hears about
        // it. This is transactional under the M11 catalogue — a meeting in
        // someone's calendar is the work, not news about it.
        foreach (self::members($tenantId, $cohortId) as $member) {
            Notifications::queueMany(
                $tenantId,
                Notifications::clientRecipients($tenantId, (int) $member['engagement_id']),
                'session.scheduled',
                $title . ($scheduledAt === null ? '' : ' — ' . date('j F, H:i', strtotime($scheduledAt)) . ' UTC'),
                $location === null || $location === '' ? null : 'Where: ' . $location,
                '/cohorts/' . $cohortId,
                ['client_org_id' => (int) $member['client_org_id'], 'object_type' => 'session', 'object_id' => $sessionId],
                $createdBy
            );
        }

        return $sessionId;
    }

    /** @return array<int,array<string,mixed>> */
    public static function sessions(int $tenantId, int $cohortId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM pl_session_attendees a
                      WHERE a.session_id = s.id AND a.tenant_id = s.tenant_id AND a.attended = 1) AS attended_count
             FROM pl_sessions s
             WHERE s.tenant_id = :tid AND s.cohort_id = :cid
             ORDER BY s.scheduled_at DESC, s.id DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $cohortId]);

        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------- materials

    /**
     * Publish a document to the whole cohort.
     *
     * Library documents only, and that restriction is the safety property. A
     * deliverable belongs to one engagement and a client file belongs to one
     * client; either of those becoming cohort material would publish one
     * member's document to its peers. Only something already in the firm's own
     * library — written by the coach, for reuse — can be shared this way.
     */
    public static function publishMaterial(
        int $tenantId,
        int $cohortId,
        int $documentId,
        ?string $note = null,
        ?int $userId = null
    ): bool {
        $db = Database::conn();

        $stmt = $db->prepare(
            "SELECT context, engagement_id FROM pl_documents
             WHERE tenant_id = :tid AND id = :id"
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $documentId]);
        $document = $stmt->fetch();

        if ($document === false) {
            return false;
        }

        if ((string) $document['context'] !== 'library') {
            throw new \InvalidArgumentException(
                'Only a library document can go to a cohort. A deliverable or a client file '
                . 'belongs to one client, and publishing it here would hand it to their peers.'
            );
        }

        $db->prepare(
            'INSERT INTO pl_cohort_materials (tenant_id, cohort_id, document_id, note, published_by)
             VALUES (:tid, :cid, :did, :note, :by)
             ON DUPLICATE KEY UPDATE note = VALUES(note), published_at = NOW()'
        )->execute([
            'tid' => $tenantId, 'cid' => $cohortId, 'did' => $documentId,
            'note' => $note === null ? null : mb_substr($note, 0, 500), 'by' => $userId,
        ]);

        return true;
    }

    public static function withdrawMaterial(int $tenantId, int $cohortId, int $documentId): bool
    {
        $stmt = Database::conn()->prepare(
            'DELETE FROM pl_cohort_materials
             WHERE tenant_id = :tid AND cohort_id = :cid AND document_id = :did'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $cohortId, 'did' => $documentId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public static function materials(int $tenantId, int $cohortId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT m.*, d.title, d.current_version_id, u.name AS published_by_name
             FROM pl_cohort_materials m
             JOIN pl_documents d ON d.id = m.document_id AND d.tenant_id = m.tenant_id
             LEFT JOIN pl_users u ON u.id = m.published_by AND u.tenant_id = m.tenant_id
             WHERE m.tenant_id = :tid AND m.cohort_id = :cid
             ORDER BY m.published_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $cohortId]);

        return $stmt->fetchAll();
    }

    /**
     * May this person open this document because of a cohort?
     *
     * The one place cohort membership widens document access, so it is a single
     * explicit method rather than a clause bolted onto the document reader.
     *
     * @param array<string,mixed> $user
     */
    public static function grantsDocumentAccess(int $tenantId, int $documentId, array $user): bool
    {
        if (($user['client_org_id'] ?? null) === null) {
            return false;   // firm-side access is decided elsewhere, on its own terms
        }

        $stmt = Database::conn()->prepare(
            'SELECT COUNT(*) AS c
             FROM pl_cohort_materials mat
             JOIN pl_cohort_members mem ON mem.cohort_id = mat.cohort_id AND mem.tenant_id = mat.tenant_id
             JOIN pl_engagements e ON e.id = mem.engagement_id AND e.tenant_id = mem.tenant_id
             WHERE mat.tenant_id = :tid AND mat.document_id = :did
               AND mem.left_at IS NULL AND e.client_org_id = :org'
        );
        $stmt->execute(['tid' => $tenantId, 'did' => $documentId, 'org' => (int) $user['client_org_id']]);

        return (int) $stmt->fetch()['c'] > 0;
    }

    // ---------------------------------------------------------- announcements

    /**
     * Tell the cohort something.
     *
     * Goes to every client-side person in every current member engagement, as a
     * notification rather than an email of its own — M11 decides whether it
     * becomes mail, per reader.
     *
     * @return int How many people were told.
     */
    public static function announce(
        int $tenantId,
        int $cohortId,
        string $subject,
        string $body,
        ?int $userId = null
    ): int {
        $subject = trim($subject);

        if ($subject === '') {
            throw new \InvalidArgumentException('An announcement needs a subject.');
        }

        $cohort = self::find($tenantId, $cohortId);

        if ($cohort === null) {
            throw new \RuntimeException('No such cohort.');
        }

        $db = Database::conn();
        $told = 0;

        foreach (self::members($tenantId, $cohortId) as $member) {
            $told += Notifications::queueMany(
                $tenantId,
                Notifications::clientRecipients($tenantId, (int) $member['engagement_id']),
                'issue.raised',
                $subject,
                mb_substr(trim($body), 0, 500),
                '/cohorts/' . $cohortId,
                ['client_org_id' => (int) $member['client_org_id'], 'object_type' => 'cohort', 'object_id' => $cohortId],
                $userId
            );
        }

        $db->prepare(
            "INSERT INTO pl_announcements (tenant_id, subject, body, segment, cohort_id,
                                            created_by, sent_at, recipient_count)
             VALUES (:tid, :subject, :body, 'cohort', :cid, :by, NOW(), :n)"
        )->execute([
            'tid' => $tenantId, 'subject' => mb_substr($subject, 0, 255), 'body' => $body,
            'cid' => $cohortId, 'by' => $userId, 'n' => $told,
        ]);

        return $told;
    }

    /** @return array<int,array<string,mixed>> */
    public static function announcements(int $tenantId, int $cohortId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT a.*, u.name AS created_by_name
             FROM pl_announcements a
             LEFT JOIN pl_users u ON u.id = a.created_by AND u.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tid AND a.cohort_id = :cid
             ORDER BY a.sent_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $cohortId]);

        return $stmt->fetchAll();
    }
}
