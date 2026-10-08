<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Notifications (M11).
 *
 * Modules do not send mail. They queue a row and return. Everything downstream
 * — batching, preferences, unsubscribe, the in-app inbox — depends on that one
 * rule, because none of it is possible for a message that has already left.
 *
 * The catalogue below is the whole contract. It says, for each thing that can
 * happen: who hears about it, what the default channel is, whether the reader
 * is allowed to turn it off, and which variables a tenant may use when they
 * rewrite the copy. Adding an event means adding a line here; a queue call for
 * an event that is not in the catalogue throws, so a typo surfaces in test
 * rather than as silence in production.
 */
final class Notifications
{
    // Channels. `off` is absent from a transactional event's options on
    // purpose — see TRANSACTIONAL below.
    public const EMAIL  = 'email';   // send on its own, as it happens
    public const DIGEST = 'digest';  // hold for the next digest
    public const IN_APP = 'in_app';  // inbox only, no mail
    public const OFF    = 'off';     // do not record it at all

    public const CHANNELS = [self::EMAIL, self::DIGEST, self::IN_APP, self::OFF];

    /** Which side of the wall an event is addressed to. */
    public const FIRM   = 'firm';
    public const CLIENT = 'client';
    public const BOTH   = 'both';

    /**
     * The event catalogue.
     *
     * `default` is the channel a reader gets until they say otherwise, and the
     * defaults encode FR-11.2 directly: a direct message or an @-mention goes
     * out immediately because it is addressed to a person and waiting is rude;
     * almost everything else is digest, because the alternative is the forty
     * emails FR-11.2 exists to prevent.
     *
     * `transactional` means the reader cannot switch it off while they are a
     * participant. It is not a synonym for "important" — it means the message
     * is part of the service they are receiving rather than an update about it.
     * A document delivered for acknowledgment is transactional; the news that
     * someone else acknowledged theirs is not. Getting this list wrong in the
     * permissive direction is a compliance problem, so it is short on purpose.
     *
     * `vars` is the allowlist a tenant may use when rewriting the copy
     * (FR-11.4), checked when the template is saved.
     *
     * @var array<string,array{label:string, audience:string, default:string, transactional:bool, vars:array<int,string>}>
     */
    public const CATALOGUE = [
        // ---- addressed to a person: immediate, both sides ------------------
        'message.received' => [
            'label' => 'A new message in a thread you are in',
            'audience' => self::BOTH, 'default' => self::EMAIL, 'transactional' => false,
            'vars' => ['recipient_name', 'sender_name', 'thread_subject', 'excerpt', 'firm_name', 'org_name'],
        ],
        'message.mentioned' => [
            'label' => 'Someone @-mentions you',
            'audience' => self::BOTH, 'default' => self::EMAIL, 'transactional' => false,
            'vars' => ['recipient_name', 'sender_name', 'context_label', 'excerpt', 'firm_name', 'org_name'],
        ],

        // ---- the client is being asked to do something: transactional ------
        'task.assigned' => [
            'label' => 'A commitment is assigned to you',
            'audience' => self::BOTH, 'default' => self::EMAIL, 'transactional' => true,
            'vars' => ['recipient_name', 'task_title', 'due_on', 'assigner_name', 'firm_name', 'org_name'],
        ],
        'task.due_soon' => [
            'label' => 'A commitment of yours is due shortly',
            'audience' => self::BOTH, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'task_title', 'due_on', 'firm_name', 'org_name'],
        ],
        'task.overdue' => [
            'label' => 'A commitment of yours is past due',
            'audience' => self::BOTH, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'task_title', 'due_on', 'firm_name', 'org_name'],
        ],
        'document.delivered' => [
            'label' => 'A document is delivered to you',
            'audience' => self::CLIENT, 'default' => self::EMAIL, 'transactional' => true,
            'vars' => ['recipient_name', 'document_title', 'sender_name', 'firm_name', 'org_name'],
        ],
        'document.requested' => [
            'label' => 'Your coach asks you for a document',
            'audience' => self::CLIENT, 'default' => self::EMAIL, 'transactional' => true,
            'vars' => ['recipient_name', 'request_title', 'due_on', 'firm_name', 'org_name'],
        ],
        'worksheet.assigned' => [
            'label' => 'A worksheet or assessment is sent to you',
            'audience' => self::CLIENT, 'default' => self::EMAIL, 'transactional' => true,
            'vars' => ['recipient_name', 'worksheet_title', 'due_on', 'firm_name', 'org_name'],
        ],
        'session.scheduled' => [
            'label' => 'A session is booked, moved, or cancelled',
            'audience' => self::BOTH, 'default' => self::EMAIL, 'transactional' => true,
            'vars' => ['recipient_name', 'session_title', 'scheduled_at', 'firm_name', 'org_name'],
        ],
        'session.recap' => [
            'label' => 'The recap after a session',
            'audience' => self::CLIENT, 'default' => self::EMAIL, 'transactional' => true,
            'vars' => ['recipient_name', 'session_title', 'firm_name', 'org_name'],
        ],
        'scope.change_requested' => [
            'label' => 'A change to the scope of work needs your decision',
            'audience' => self::BOTH, 'default' => self::EMAIL, 'transactional' => true,
            'vars' => ['recipient_name', 'change_title', 'requester_name', 'firm_name', 'org_name'],
        ],

        // ---- the coach is being told what their clients did: digest --------
        'task.completed' => [
            'label' => 'A client completes a commitment',
            'audience' => self::FIRM, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'task_title', 'actor_name', 'org_name', 'firm_name'],
        ],
        'document.uploaded' => [
            'label' => 'A client uploads a file',
            'audience' => self::FIRM, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'document_title', 'actor_name', 'org_name', 'firm_name'],
        ],
        'document.acknowledged' => [
            'label' => 'A client acknowledges a deliverable',
            'audience' => self::FIRM, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'document_title', 'actor_name', 'org_name', 'firm_name'],
        ],
        'worksheet.submitted' => [
            'label' => 'A worksheet comes back',
            'audience' => self::FIRM, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'worksheet_title', 'org_name', 'firm_name'],
        ],
        'issue.raised' => [
            'label' => 'An issue is added to an engagement',
            'audience' => self::BOTH, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'issue_title', 'actor_name', 'org_name', 'firm_name'],
        ],
        'commitment.escalated' => [
            'label' => 'A commitment stalls and becomes an issue',
            'audience' => self::FIRM, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'task_title', 'org_name', 'firm_name'],
        ],
        'goal.updated' => [
            'label' => 'A goal or target changes',
            'audience' => self::BOTH, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'goal_title', 'actor_name', 'org_name', 'firm_name'],
        ],
        'metric.due' => [
            'label' => 'A scorecard number is waiting to be entered',
            'audience' => self::BOTH, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'metric_name', 'org_name', 'firm_name'],
        ],

        // ---- the digests themselves ----------------------------------------
        //
        // The digests are events in their own right, and they have to be, or
        // FR-11.5 has a hole in it. Before this, Digest::sendClientWeekly wrote
        // to every active client-side reader without consulting a preference at
        // all — so unsubscribing switched off every individual notice and the
        // weekly summary kept arriving. A digest is precisely the thing the FR
        // says people may opt out of.
        //
        // `digest` and `email` both mean "send it"; `in_app` and `off` both mean
        // "do not". The distinction does not apply to a digest, which is the
        // email, so the sender treats anything other than off/in_app as yes.
        'digest.weekly' => [
            'label' => 'Your weekly summary',
            'audience' => self::CLIENT, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'firm_name', 'org_name'],
        ],
        'digest.daily' => [
            'label' => 'Your daily summary of client activity',
            'audience' => self::FIRM, 'default' => self::DIGEST, 'transactional' => false,
            'vars' => ['recipient_name', 'firm_name'],
        ],

        // ---- the firm's own business: immediate, never batched -------------
        'intake.received' => [
            'label' => 'A new enquiry arrives through your intake form',
            'audience' => self::FIRM, 'default' => self::EMAIL, 'transactional' => true,
            'vars' => ['recipient_name', 'enquirer_name', 'enquirer_email', 'firm_name'],
        ],
    ];

    /**
     * Queue a notification.
     *
     * Deliberately does no preference lookup and sends nothing. The row is a
     * record that something happened and who it concerns; what to do about it
     * is decided when the queue is drained, using the reader's preference AT
     * THAT MOMENT. A coach who turns digests off at 4pm should not still
     * receive the ones queued at 3pm.
     *
     * Idempotency is the caller's job where it matters. `queueOnce()` is the
     * helper for the recurring cases — a nightly sweep that finds the same
     * overdue task tomorrow should not tell anyone twice.
     *
     * @param array<string,mixed> $context
     */
    public static function queue(
        int $tenantId,
        int $userId,
        string $eventType,
        string $title,
        ?string $body = null,
        ?string $link = null,
        array $context = []
    ): int {
        self::assertKnown($eventType);

        if ($title === '') {
            throw new \InvalidArgumentException('A notification needs a title.');
        }

        // A path, never an absolute URL. The host belongs to the tenant and is
        // resolved at send time — baking one in here would survive a firm
        // changing its slug and start mailing out dead links.
        if ($link !== null && $link !== '' && $link[0] !== '/') {
            throw new \InvalidArgumentException('Notification links are paths, not absolute URLs.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_notifications
                (tenant_id, user_id, event_type, title, body, link,
                 client_org_id, object_type, object_id)
             VALUES (:tid, :uid, :type, :title, :body, :link, :org, :otype, :oid)'
        )->execute([
            'tid'   => $tenantId,
            'uid'   => $userId,
            'type'  => $eventType,
            'title' => mb_substr($title, 0, 255),
            'body'  => $body === null || trim($body) === '' ? null : mb_substr($body, 0, 4000),
            'link'  => $link === null || $link === '' ? null : mb_substr($link, 0, 500),
            'org'   => isset($context['client_org_id']) ? (int) $context['client_org_id'] : null,
            'otype' => $context['object_type'] ?? null,
            'oid'   => isset($context['object_id']) ? (int) $context['object_id'] : null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Queue unless the same reader already has an undelivered notification for
     * the same object and event.
     *
     * For anything a recurring sweep can rediscover. The nightly tick finds the
     * same overdue commitment every night until it moves; the reader should
     * hear once, not nightly, and certainly not have it appear five times in
     * one digest.
     *
     * @param array<string,mixed> $context Must carry object_type and object_id.
     * @return int|null The new id, or null if one was already outstanding.
     */
    public static function queueOnce(
        int $tenantId,
        int $userId,
        string $eventType,
        string $title,
        ?string $body = null,
        ?string $link = null,
        array $context = []
    ): ?int {
        self::assertKnown($eventType);

        if (!isset($context['object_type'], $context['object_id'])) {
            throw new \InvalidArgumentException('queueOnce needs an object to be once ABOUT.');
        }

        $stmt = Database::conn()->prepare(
            'SELECT COUNT(*) AS c FROM pl_notifications
             WHERE tenant_id = :tid AND user_id = :uid AND event_type = :type
               AND object_type = :otype AND object_id = :oid
               AND emailed_at IS NULL AND delivery <> :suppressed'
        );
        $stmt->execute([
            'tid' => $tenantId, 'uid' => $userId, 'type' => $eventType,
            'otype' => (string) $context['object_type'], 'oid' => (int) $context['object_id'],
            'suppressed' => 'suppressed',
        ]);

        if ((int) $stmt->fetch()['c'] > 0) {
            return null;
        }

        return self::queue($tenantId, $userId, $eventType, $title, $body, $link, $context);
    }

    /**
     * Queue the same notification for several readers, skipping one.
     *
     * The skip is almost always the actor. Telling someone that they did the
     * thing they just did is how a product teaches people to ignore it.
     *
     * @param array<int,int> $userIds
     * @param array<string,mixed> $context
     * @return int How many were queued.
     */
    public static function queueMany(
        int $tenantId,
        array $userIds,
        string $eventType,
        string $title,
        ?string $body = null,
        ?string $link = null,
        array $context = [],
        ?int $exceptUserId = null
    ): int {
        $queued = 0;

        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId <= 0 || ($exceptUserId !== null && $userId === $exceptUserId)) {
                continue;
            }

            self::queue($tenantId, $userId, $eventType, $title, $body, $link, $context);
            $queued++;
        }

        return $queued;
    }

    // ------------------------------------------------------------- audiences

    /**
     * The firm-side people who should hear about an engagement.
     *
     * Its assigned coach, anyone added as a member, and the firm owner. The
     * owner is in the list because a solo practice is the common case and a
     * one-person firm that hears nothing about its own engagements is useless —
     * the same reasoning that corrected the §8 matrix at the end of Phase 1.
     *
     * @return array<int,int>
     */
    public static function firmRecipients(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT DISTINCT u.id
             FROM pl_users u
             LEFT JOIN pl_engagement_members m
                    ON m.user_id = u.id AND m.tenant_id = u.tenant_id AND m.engagement_id = :eid
             LEFT JOIN pl_engagements e
                    ON e.id = :eid2 AND e.tenant_id = u.tenant_id AND e.coach_user_id = u.id
             WHERE u.tenant_id = :tid
               AND u.status = 'active'
               AND u.client_org_id IS NULL
               AND (m.id IS NOT NULL OR e.id IS NOT NULL OR u.role = 'firm_owner')"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId, 'eid2' => $engagementId]);

        $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        // An engagement with no coach assigned, no members, and no owner is a
        // data problem — but the consequence of returning nothing here is that
        // the notification is queued for nobody and silently vanishes. Over-
        // telling a firm about its own client beats losing the signal.
        return $ids === [] ? self::firmStaff($tenantId) : $ids;
    }

    /**
     * The client-side people at the organization an engagement belongs to.
     *
     * @return array<int,int>
     */
    public static function clientRecipients(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT u.id
             FROM pl_users u
             JOIN pl_engagements e ON e.client_org_id = u.client_org_id AND e.tenant_id = u.tenant_id
             WHERE u.tenant_id = :tid AND e.id = :eid
               AND u.status = 'active' AND u.client_org_id IS NOT NULL"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Everyone at the firm who holds a seat. For events with no engagement. */
    /** @return array<int,int> */
    public static function firmStaff(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id FROM pl_users
             WHERE tenant_id = :tid AND status = 'active' AND client_org_id IS NULL"
        );
        $stmt->execute(['tid' => $tenantId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * The client organization behind an engagement, as queue context.
     *
     * Lives here rather than in each emitting service because four copies of
     * the same three-line lookup is how they drift apart.
     *
     * @return array<string,int>
     */
    public static function orgContext(int $tenantId, int $engagementId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT client_org_id FROM pl_engagements WHERE tenant_id = :tid AND id = :eid'
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        $row = $stmt->fetch();

        return $row === false ? [] : ['client_org_id' => (int) $row['client_org_id']];
    }

    // ----------------------------------------------------------- preferences

    /**
     * The channel this reader gets for this event.
     *
     * Order of precedence, and the reason for each step:
     *   1. A transactional event is always email. There is no stored preference
     *      that can override it, so a support request cannot turn one off by
     *      accident and neither can a bug in the preferences screen.
     *   2. The reader's stored override, if any.
     *   3. The catalogue default.
     */
    public static function channelFor(int $tenantId, int $userId, string $eventType): string
    {
        self::assertKnown($eventType);

        if (self::CATALOGUE[$eventType]['transactional']) {
            return self::EMAIL;
        }

        $stmt = Database::conn()->prepare(
            'SELECT channel FROM pl_notification_prefs
             WHERE tenant_id = :tid AND user_id = :uid AND event_type = :type'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId, 'type' => $eventType]);

        $row = $stmt->fetch();

        if ($row !== false && in_array((string) $row['channel'], self::CHANNELS, true)) {
            return (string) $row['channel'];
        }

        return self::CATALOGUE[$eventType]['default'];
    }

    /**
     * Set a preference.
     *
     * Refuses to switch off a transactional event rather than accepting the
     * write and quietly ignoring it later. A setting that appears to save and
     * then does nothing is worse than an error message.
     */
    public static function setPreference(int $tenantId, int $userId, string $eventType, string $channel): void
    {
        self::assertKnown($eventType);

        if (!in_array($channel, self::CHANNELS, true)) {
            throw new \InvalidArgumentException('Unknown channel.');
        }

        if (self::CATALOGUE[$eventType]['transactional'] && $channel !== self::EMAIL) {
            throw new \InvalidArgumentException(
                'That one cannot be turned off while you are part of an engagement — '
                . 'it is part of the service, not an update about it.'
            );
        }

        Database::conn()->prepare(
            'INSERT INTO pl_notification_prefs (tenant_id, user_id, event_type, channel)
             VALUES (:tid, :uid, :type, :chan)
             ON DUPLICATE KEY UPDATE channel = VALUES(channel)'
        )->execute(['tid' => $tenantId, 'uid' => $userId, 'type' => $eventType, 'chan' => $channel]);
    }

    /**
     * Every event this reader can see, with the channel currently in force.
     *
     * Audience-filtered: a coach has no use for a row about documents being
     * delivered to them, and a client should not be shown a switch for events
     * that only ever concern the firm.
     *
     * @return array<int,array{event_type:string, label:string, channel:string, transactional:bool}>
     */
    public static function preferencesFor(int $tenantId, int $userId, bool $firmSide): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT event_type, channel FROM pl_notification_prefs
             WHERE tenant_id = :tid AND user_id = :uid'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);

        $overrides = [];

        foreach ($stmt->fetchAll() as $row) {
            $overrides[(string) $row['event_type']] = (string) $row['channel'];
        }

        $wanted = $firmSide ? self::FIRM : self::CLIENT;
        $out = [];

        foreach (self::CATALOGUE as $type => $meta) {
            if ($meta['audience'] !== self::BOTH && $meta['audience'] !== $wanted) {
                continue;
            }

            $out[] = [
                'event_type'    => $type,
                'label'         => $meta['label'],
                'channel'       => $meta['transactional']
                    ? self::EMAIL
                    : ($overrides[$type] ?? $meta['default']),
                'transactional' => $meta['transactional'],
            ];
        }

        return $out;
    }

    /**
     * Turn off every optional event for a reader. The unsubscribe link's action.
     *
     * Transactional events are skipped, not set — see FR-11.5. Someone who
     * unsubscribes still gets the document their coach needs them to sign, and
     * the honest thing is to tell them so on the confirmation page rather than
     * let them believe everything has stopped.
     *
     * @return int How many events were switched off.
     */
    public static function unsubscribeAll(int $tenantId, int $userId): int
    {
        $count = 0;

        foreach (self::CATALOGUE as $type => $meta) {
            if ($meta['transactional']) {
                continue;
            }

            self::setPreference($tenantId, $userId, $type, self::OFF);
            $count++;
        }

        return $count;
    }

    // ---------------------------------------------------------------- inbox

    /**
     * The bell menu. In-app delivery is not conditional on the email channel —
     * a reader who has asked for no mail has asked for no mail, not to be kept
     * in the dark.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function inbox(int $tenantId, int $userId, bool $unreadOnly = false, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $sql = 'SELECT * FROM pl_notifications
                WHERE tenant_id = :tid AND user_id = :uid AND delivery <> \'suppressed\'';

        if ($unreadOnly) {
            $sql .= ' AND read_at IS NULL';
        }

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);

        return $stmt->fetchAll();
    }

    public static function unreadCount(int $tenantId, int $userId): int
    {
        $stmt = Database::conn()->prepare(
            'SELECT COUNT(*) AS c FROM pl_notifications
             WHERE tenant_id = :tid AND user_id = :uid
               AND read_at IS NULL AND delivery <> \'suppressed\''
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);

        return (int) $stmt->fetch()['c'];
    }

    public static function markRead(int $tenantId, int $userId, ?int $notificationId = null): int
    {
        $sql = 'UPDATE pl_notifications SET read_at = NOW()
                WHERE tenant_id = :tid AND user_id = :uid AND read_at IS NULL';

        $params = ['tid' => $tenantId, 'uid' => $userId];

        if ($notificationId !== null) {
            $sql .= ' AND id = :id';
            $params['id'] = $notificationId;
        }

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * One notification, scoped. Used by the click-through that marks read and
     * then forwards to the link.
     *
     * @return array<string,mixed>|null
     */
    public static function find(int $tenantId, int $userId, int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_notifications
             WHERE tenant_id = :tid AND user_id = :uid AND id = :id'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId, 'id' => $id]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    // ------------------------------------------------------------- internals

    public static function isKnown(string $eventType): bool
    {
        return isset(self::CATALOGUE[$eventType]);
    }

    private static function assertKnown(string $eventType): void
    {
        if (!isset(self::CATALOGUE[$eventType])) {
            throw new \InvalidArgumentException("Unknown notification event '{$eventType}'.");
        }
    }
}
