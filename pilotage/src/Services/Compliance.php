<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Auth\Audit;
use Bizorca\Pilotage\Core\Database;

/**
 * Retention, erasure, archival, and the coaching agreement (M14).
 *
 * ---------------------------------------------------------------------------
 * THE RULE: destruction is announced before it happens and recorded after.
 *
 * Every path in this class that destroys something warns first, waits out a
 * notice period, and leaves a receipt in the audit log. A compliance feature
 * that silently deletes client records is not a compliance feature; it is the
 * incident that the compliance feature was supposed to prevent.
 *
 * Concretely, and none of these is negotiable:
 *
 *   - Retention defaults to KEEP FOREVER. A default that deletes would destroy
 *     a firm's records because nobody visited a settings page.
 *   - Nothing is purged that has not sat on the notice table for the full
 *     notice period with a warning already sent.
 *   - The owner can stop the clock at any point, and the fact that they did is
 *     itself recorded — that is what answers "why is this still here" later.
 *   - Erasure of a person defaults to PSEUDONYMISATION, not deletion. See below.
 * ---------------------------------------------------------------------------
 */
final class Compliance
{
    /** Days between warning a firm and destroying anything (FR-14.2). */
    public const NOTICE_DAYS = 30;

    /** What a pseudonymised participant is called afterwards. */
    public const ANONYMOUS_LABEL = 'A former participant';

    // ------------------------------------------------------------- retention

    /**
     * Schedule destruction for engagements that have been closed longer than
     * the firm's retention policy.
     *
     * Schedules only. Nothing is destroyed here — this creates the notice, and
     * `purge()` acts on it thirty days later. Splitting the two is the entire
     * safety property: a firm that misconfigures retention has a month and an
     * email in which to notice.
     *
     * @return array<int,array<string,mixed>> The notices created, to be emailed.
     */
    public static function scheduleRetention(int $tenantId, ?array $tenant = null): array
    {
        $tenant = $tenant ?? self::tenant($tenantId);
        $months = (int) ($tenant['retention_months'] ?? 0);

        if ($months <= 0) {
            return [];   // keep forever, which is the default and the safe one
        }

        $db = Database::conn();

        $stmt = $db->prepare(
            "SELECT e.id, e.title, e.completed_at, o.name AS org_name
             FROM pl_engagements e
             JOIN pl_client_orgs o ON o.id = e.client_org_id AND o.tenant_id = e.tenant_id
             LEFT JOIN pl_retention_notices n
                    ON n.engagement_id = e.id AND n.tenant_id = e.tenant_id
             WHERE e.tenant_id = :tid
               AND e.status = 'complete'
               AND e.completed_at IS NOT NULL
               AND e.completed_at < NOW() - INTERVAL :months MONTH
               AND n.id IS NULL"
        );
        $stmt->execute(['tid' => $tenantId, 'months' => $months]);

        $insert = $db->prepare(
            'INSERT INTO pl_retention_notices (tenant_id, engagement_id, closed_at, purge_after)
             VALUES (:tid, :eid, :closed, NOW() + INTERVAL :days DAY)'
        );

        $created = [];

        foreach ($stmt->fetchAll() as $row) {
            try {
                $insert->execute([
                    'tid' => $tenantId,
                    'eid' => (int) $row['id'],
                    'closed' => (string) $row['completed_at'],
                    'days' => self::NOTICE_DAYS,
                ]);
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') {
                    continue;   // already scheduled; the sweep ran twice
                }

                throw $e;
            }

            $created[] = $row + ['notice_id' => (int) $db->lastInsertId()];
        }

        return $created;
    }

    /**
     * Tell the firm owner what is about to be destroyed.
     *
     * Queued through M11 like everything else, but as an ordinary notification
     * rather than a transactional one would be a mistake — this is the one
     * message where "I turned that off" must not mean "and so my records went".
     * It goes out as its own immediate email regardless of preferences, because
     * it is the notice the whole feature rests on.
     *
     * @param array<int,array<string,mixed>> $notices
     */
    public static function announce(int $tenantId, array $notices, array $tenant): int
    {
        if ($notices === []) {
            return 0;
        }

        $db = Database::conn();
        $sent = 0;

        $owners = $db->prepare(
            "SELECT id, name, email FROM pl_users
             WHERE tenant_id = :tid AND role = 'firm_owner' AND status = 'active'"
        );
        $owners->execute(['tid' => $tenantId]);

        $lines = [];

        foreach ($notices as $notice) {
            $lines[] = $notice['org_name'] . ' — ' . $notice['title'];
        }

        foreach ($owners->fetchAll() as $owner) {
            $body = MailTemplate::action(
                'Hello ' . $owner['name'] . ',',
                [
                    'Your retention policy is set to ' . (int) $tenant['retention_months']
                        . ' months, and these closed engagements have now passed it:',
                    implode("\n", $lines),
                    'In ' . self::NOTICE_DAYS . ' days they will be permanently deleted — sessions, notes, '
                        . 'commitments, documents, all of it. This cannot be undone afterwards.',
                    'If any of them should be kept, open the retention page and say so. Nothing happens '
                        . 'until then, and stopping the clock takes one click.',
                ],
                'Review what is scheduled',
                tenant_url('/firm/retention', (string) $tenant['slug']),
                ['You are getting this because you own this firm on Pilotage. This particular notice '
                    . 'cannot be switched off — it is the warning before records are destroyed.'],
                \Bizorca\Pilotage\Core\Mailer::PLATFORM_NAME
            );

            \Bizorca\Pilotage\Core\Mailer::send(
                (string) $owner['email'],
                (string) $owner['name'],
                count($notices) . ' engagement' . (count($notices) === 1 ? '' : 's')
                    . ' scheduled for deletion in ' . self::NOTICE_DAYS . ' days',
                $body['text'],
                $body['html'],
                \Bizorca\Pilotage\Core\Mailer::PLATFORM_NAME
            );

            $sent++;
        }

        $ids = array_map(static fn (array $n): int => (int) $n['notice_id'], $notices);

        $db->exec(
            'UPDATE pl_retention_notices SET notified_at = NOW() WHERE id IN ('
            . implode(',', array_map('intval', $ids)) . ')'
        );

        return $sent;
    }

    /**
     * Actually destroy what is due.
     *
     * Three conditions, all required, none of them optional:
     *   - the notice period has passed
     *   - the owner was actually told (notified_at is set)
     *   - nobody cancelled it
     *
     * The second one matters most. Without it, a mail outage on the day the
     * notice was scheduled would produce a silent deletion thirty days later —
     * destruction with no warning, which is the exact thing this is built to
     * prevent.
     *
     * @return array<int,int> Engagement ids destroyed.
     */
    public static function purge(int $tenantId): array
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT n.id, n.engagement_id, e.title, e.client_org_id
             FROM pl_retention_notices n
             JOIN pl_engagements e ON e.id = n.engagement_id AND e.tenant_id = n.tenant_id
             WHERE n.tenant_id = :tid
               AND n.purge_after <= NOW()
               AND n.notified_at IS NOT NULL
               AND n.purged_at IS NULL
               AND n.cancelled_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId]);

        $purged = [];

        foreach ($stmt->fetchAll() as $row) {
            $engagementId = (int) $row['engagement_id'];

            // The receipt goes in BEFORE the deletion. The audit log has no FK
            // to the engagement and survives it, which is the point — after
            // this, the audit entry is the only trace that the engagement ever
            // existed, and writing it afterwards risks not writing it at all.
            Audit::record(
                'retention.purged',
                $tenantId,
                null,
                'engagement',
                $engagementId,
                ['title' => (string) $row['title'], 'notice_id' => (int) $row['id']],
                null
            );

            $db->prepare('DELETE FROM pl_engagements WHERE tenant_id = :tid AND id = :id')
               ->execute(['tid' => $tenantId, 'id' => $engagementId]);

            $db->prepare('UPDATE pl_retention_notices SET purged_at = NOW() WHERE id = :id')
               ->execute(['id' => (int) $row['id']]);

            $purged[] = $engagementId;
        }

        return $purged;
    }

    /**
     * Stop the clock on a scheduled destruction.
     *
     * The reason is required. "Why is this record still here" is a question an
     * auditor asks, and "someone clicked cancel" is not an answer.
     */
    public static function cancelNotice(int $tenantId, int $noticeId, int $userId, string $reason): bool
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Say why this one is being kept — that is the record.');
        }

        $stmt = Database::conn()->prepare(
            'UPDATE pl_retention_notices
             SET cancelled_at = NOW(), cancelled_by = :uid, cancel_reason = :reason
             WHERE tenant_id = :tid AND id = :id AND purged_at IS NULL AND cancelled_at IS NULL'
        );
        $stmt->execute([
            'uid' => $userId, 'reason' => mb_substr($reason, 0, 255),
            'tid' => $tenantId, 'id' => $noticeId,
        ]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public static function notices(int $tenantId, bool $pendingOnly = true): array
    {
        $sql = 'SELECT n.*, e.title, o.name AS org_name, u.name AS cancelled_by_name
                FROM pl_retention_notices n
                LEFT JOIN pl_engagements e ON e.id = n.engagement_id AND e.tenant_id = n.tenant_id
                LEFT JOIN pl_client_orgs o ON o.id = e.client_org_id AND o.tenant_id = n.tenant_id
                LEFT JOIN pl_users u ON u.id = n.cancelled_by AND u.tenant_id = n.tenant_id
                WHERE n.tenant_id = :tid';

        if ($pendingOnly) {
            $sql .= ' AND n.purged_at IS NULL AND n.cancelled_at IS NULL';
        }

        $sql .= ' ORDER BY n.purge_after ASC';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function setRetention(int $tenantId, int $months, int $userId): void
    {
        if ($months < 0 || $months > 240) {
            throw new \InvalidArgumentException('Retention is between 0 months (keep forever) and 20 years.');
        }

        Database::conn()->prepare(
            'UPDATE pl_tenants SET retention_months = :m, retention_confirmed_at = NOW() WHERE id = :id'
        )->execute(['m' => $months, 'id' => $tenantId]);

        Audit::record('retention.policy_changed', $tenantId, ['id' => $userId], 'tenant', $tenantId,
            ['months' => $months], null);
    }

    // --------------------------------------------------------------- erasure

    /**
     * What stands in the way of erasing this person (FR-14.3).
     *
     * The conflict is the whole point of the feature. A client-side participant
     * asks to be forgotten, and the commitments they made, the sessions they
     * attended and the documents they acknowledged ARE the engagement record —
     * which their coach may be professionally or legally obliged to keep. The
     * two obligations are real and they genuinely collide.
     *
     * So this enumerates the collision rather than resolving it silently in
     * either direction. A human decides, and the decision is recorded.
     *
     * @return array<int,array{kind:string, count:int, note:string}>
     */
    public static function erasureConflicts(int $tenantId, int $userId): array
    {
        $db = Database::conn();
        $out = [];

        $checks = [
            ['kind' => 'commitments', 'sql' =>
                'SELECT COUNT(*) AS c FROM pl_tasks WHERE tenant_id = :tid AND owner_user_id = :uid',
                'note' => 'commitments they owned. Deleting these rewrites what was agreed and when.'],
            ['kind' => 'sessions', 'sql' =>
                'SELECT COUNT(*) AS c FROM pl_session_attendees WHERE tenant_id = :tid AND user_id = :uid',
                'note' => 'sessions they attended. Attendance is part of the engagement record.'],
            ['kind' => 'acknowledgments', 'sql' =>
                'SELECT COUNT(*) AS c FROM pl_document_deliveries WHERE tenant_id = :tid AND acknowledged_by = :uid',
                'note' => 'documents they acknowledged receiving. These are what an advisor is paid on.'],
            ['kind' => 'messages', 'sql' =>
                'SELECT COUNT(*) AS c FROM pl_messages WHERE tenant_id = :tid AND author_id = :uid',
                'note' => 'messages they wrote. Removing these leaves half a conversation, which is '
                        . 'often less useful to everyone than the whole one.'],
            ['kind' => 'worksheets', 'sql' =>
                'SELECT COUNT(*) AS c FROM pl_worksheet_responses WHERE tenant_id = :tid AND respondent_user_id = :uid',
                'note' => 'named worksheet responses. Anonymous ones are already unlinked and cannot be found.'],
        ];

        foreach ($checks as $check) {
            $stmt = $db->prepare($check['sql']);
            $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);
            $count = (int) $stmt->fetch()['c'];

            if ($count > 0) {
                $out[] = ['kind' => $check['kind'], 'count' => $count, 'note' => $check['note']];
            }
        }

        return $out;
    }

    /**
     * Open an erasure request, capturing the conflicts as they stand now.
     *
     * The conflicts are stored rather than recomputed at decision time on
     * purpose: recomputing later answers a different question, and the record
     * has to show what the decision was actually made on.
     */
    public static function requestErasure(int $tenantId, int $subjectId, ?int $requestedBy, ?string $reason): int
    {
        $db = Database::conn();

        $stmt = $db->prepare('SELECT name, email FROM pl_users WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['tid' => $tenantId, 'id' => $subjectId]);
        $subject = $stmt->fetch();

        if ($subject === false) {
            throw new \RuntimeException('No such person in this firm.');
        }

        $conflicts = self::erasureConflicts($tenantId, $subjectId);

        $db->prepare(
            'INSERT INTO pl_erasure_requests
                (tenant_id, subject_user_id, subject_label, subject_email, requested_by, reason, conflicts)
             VALUES (:tid, :sid, :label, :email, :by, :reason, :conflicts)'
        )->execute([
            'tid' => $tenantId,
            'sid' => $subjectId,
            'label' => mb_substr((string) $subject['name'], 0, 255),
            'email' => (string) $subject['email'],
            'by' => $requestedBy,
            'reason' => $reason === null ? null : mb_substr($reason, 0, 500),
            'conflicts' => json_encode($conflicts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Pseudonymise: erase the person, keep the shape of the record.
     *
     * This is the answer to the conflict, and it is the right one far more often
     * than either extreme. The identifying details go — name, email, phone, the
     * ability to sign in — and the structure of what happened stays, attributed
     * to "a former participant". "Dana Owner completed 14 commitments" becomes
     * "a former participant completed 14 commitments": the person is genuinely
     * gone, and the engagement record is not rewritten into a fiction.
     *
     * The user row survives with its identity emptied rather than being deleted,
     * because deleting it would cascade through commitments and attendance and
     * take the record with it — which is exactly what we are trying not to do.
     */
    public static function pseudonymise(int $tenantId, int $requestId, int $decidedBy, string $decision): bool
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            "SELECT * FROM pl_erasure_requests
             WHERE tenant_id = :tid AND id = :id AND status = 'open'"
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $requestId]);
        $request = $stmt->fetch();

        if ($request === false || $request['subject_user_id'] === null) {
            return false;
        }

        $userId = (int) $request['subject_user_id'];

        // A placeholder email, because the column is unique and NOT NULL and a
        // real address must not survive. Scoped to the tenant so two firms
        // erasing someone cannot collide.
        $placeholder = 'erased+' . $tenantId . '-' . $userId . '@invalid.pilotage';

        $db->prepare(
            "UPDATE pl_users
             SET name = :label, email = :email, password_hash = NULL, status = 'disabled'
             WHERE tenant_id = :tid AND id = :id"
        )->execute([
            'label' => self::ANONYMOUS_LABEL,
            'email' => $placeholder,
            'tid' => $tenantId,
            'id' => $userId,
        ]);

        // Denormalised author labels carry the name too. Missing these would
        // leave the erased name printed on every message they ever wrote.
        foreach ([
            ['pl_messages', 'author_label', 'author_id'],
            ['pl_org_events', 'actor_label', 'actor_user_id'],
        ] as [$table, $labelColumn, $idColumn]) {
            $db->prepare(
                "UPDATE {$table} SET {$labelColumn} = :label
                 WHERE tenant_id = :tid AND {$idColumn} = :id"
            )->execute(['label' => self::ANONYMOUS_LABEL, 'tid' => $tenantId, 'id' => $userId]);
        }

        $db->prepare(
            "UPDATE pl_session_attendees SET display_name = :label
             WHERE tenant_id = :tid AND user_id = :id"
        )->execute(['label' => self::ANONYMOUS_LABEL, 'tid' => $tenantId, 'id' => $userId]);

        $db->prepare(
            "UPDATE pl_erasure_requests
             SET status = 'pseudonymised', decision = :decision, decided_by = :by,
                 decided_at = NOW(), subject_email = NULL
             WHERE tenant_id = :tid AND id = :id"
        )->execute([
            'decision' => mb_substr($decision, 0, 1000),
            'by' => $decidedBy, 'tid' => $tenantId, 'id' => $requestId,
        ]);

        \Bizorca\Pilotage\Auth\Session::revokeAllForUser($tenantId, $userId);

        Audit::record('erasure.pseudonymised', $tenantId, ['id' => $decidedBy], 'user', $userId,
            ['request_id' => $requestId], null);

        return true;
    }

    /**
     * Refuse a request, with a reason.
     *
     * A refusal is a legitimate outcome — a coach may be obliged to keep a
     * record — but it has to be an explained one. An unexplained refusal is
     * indistinguishable from ignoring the request, both to the person who made
     * it and to a regulator reading this table later.
     */
    public static function refuseErasure(int $tenantId, int $requestId, int $decidedBy, string $reason): bool
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException(
                'A refusal needs a reason. An unexplained one is indistinguishable from ignoring the request.'
            );
        }

        $stmt = Database::conn()->prepare(
            "UPDATE pl_erasure_requests
             SET status = 'refused', decision = :reason, decided_by = :by, decided_at = NOW()
             WHERE tenant_id = :tid AND id = :id AND status = 'open'"
        );
        $stmt->execute([
            'reason' => mb_substr($reason, 0, 1000), 'by' => $decidedBy,
            'tid' => $tenantId, 'id' => $requestId,
        ]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public static function erasureRequests(int $tenantId, bool $openOnly = false): array
    {
        $sql = 'SELECT r.*, u.name AS decided_by_name
                FROM pl_erasure_requests r
                LEFT JOIN pl_users u ON u.id = r.decided_by AND u.tenant_id = r.tenant_id
                WHERE r.tenant_id = :tid';

        if ($openOnly) {
            $sql .= " AND r.status = 'open'";
        }

        $sql .= ' ORDER BY r.created_at DESC';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId]);

        $rows = $stmt->fetchAll();

        foreach ($rows as $i => $row) {
            $rows[$i]['conflicts'] = $row['conflicts'] === null
                ? []
                : (json_decode((string) $row['conflicts'], true) ?: []);
        }

        return $rows;
    }

    // ------------------------------------------------------------- agreement

    /**
     * Does this engagement have a coaching agreement on file (FR-14.5)?
     *
     * Ungated but visibly flagged, per the FR. Blocking work until one is
     * uploaded would lock out every coach who signed on paper — which is most
     * of them — so the waiver exists and is itself recorded.
     *
     * @param array<string,mixed> $engagement
     * @return array{present:bool, waived:bool, flag:?string}
     */
    public static function agreementStatus(array $engagement): array
    {
        if ($engagement['agreement_document_id'] !== null) {
            return ['present' => true, 'waived' => false, 'flag' => null];
        }

        if ($engagement['agreement_waived_at'] !== null) {
            return [
                'present' => false, 'waived' => true,
                'flag' => 'Signed elsewhere' . (empty($engagement['agreement_waived_note'])
                    ? '.' : ': ' . (string) $engagement['agreement_waived_note']),
            ];
        }

        return [
            'present' => false, 'waived' => false,
            'flag' => 'No coaching agreement on file. Attach the signed one, or note that it was signed elsewhere.',
        ];
    }

    public static function setAgreement(int $tenantId, int $engagementId, int $documentId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_engagements e
             JOIN pl_documents d ON d.id = :did AND d.tenant_id = e.tenant_id AND d.engagement_id = e.id
             SET e.agreement_document_id = :did2, e.agreement_waived_at = NULL, e.agreement_waived_note = NULL
             WHERE e.tenant_id = :tid AND e.id = :eid'
        );
        $stmt->execute(['did' => $documentId, 'did2' => $documentId, 'tid' => $tenantId, 'eid' => $engagementId]);

        return $stmt->rowCount() === 1;
    }

    public static function waiveAgreement(int $tenantId, int $engagementId, string $note): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_engagements
             SET agreement_waived_at = NOW(), agreement_waived_note = :note
             WHERE tenant_id = :tid AND id = :eid AND agreement_document_id IS NULL'
        );
        $stmt->execute([
            'note' => mb_substr(trim($note), 0, 255) ?: 'Signed outside Pilotage.',
            'tid' => $tenantId, 'eid' => $engagementId,
        ]);

        return $stmt->rowCount() === 1;
    }

    // ------------------------------------------------------------- archival

    /**
     * Close an engagement (FR-14.4).
     *
     * Read-only, still searchable, and no longer counting against active
     * limits. Note what this is NOT: it is not deletion, and it does not start
     * the retention clock ticking on its own — retention is a separate,
     * opt-in policy with its own notice period.
     */
    public static function archive(int $tenantId, int $engagementId, int $userId): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE pl_engagements
             SET status = 'complete', completed_at = COALESCE(completed_at, NOW())
             WHERE tenant_id = :tid AND id = :eid AND status <> 'complete'"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        if ($stmt->rowCount() !== 1) {
            return false;
        }

        Audit::record('engagement.archived', $tenantId, ['id' => $userId], 'engagement', $engagementId, null, null);

        return true;
    }

    public static function reopen(int $tenantId, int $engagementId, int $userId): bool
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            "UPDATE pl_engagements
             SET status = 'active', completed_at = NULL
             WHERE tenant_id = :tid AND id = :eid AND status = 'complete'"
        );
        $stmt->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        if ($stmt->rowCount() !== 1) {
            return false;
        }

        // Reopening stops any pending destruction. An engagement someone has
        // just picked back up is not one to delete in a fortnight, and making
        // them find the retention page to say so is how accidents happen.
        $db->prepare(
            "UPDATE pl_retention_notices
             SET cancelled_at = NOW(), cancelled_by = :uid, cancel_reason = 'The engagement was reopened.'
             WHERE tenant_id = :tid AND engagement_id = :eid AND purged_at IS NULL AND cancelled_at IS NULL"
        )->execute(['uid' => $userId, 'tid' => $tenantId, 'eid' => $engagementId]);

        Audit::record('engagement.reopened', $tenantId, ['id' => $userId], 'engagement', $engagementId, null, null);

        return true;
    }

    /** @return array<string,mixed>|null */
    private static function tenant(int $tenantId): ?array
    {
        $stmt = Database::conn()->prepare('SELECT * FROM pl_tenants WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
