<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\TaskRepository;

/**
 * The accountability loop (FR-6.5).
 *
 * A commitment made in a session becomes a tracked task, becomes a nudge,
 * becomes an overdue notice, becomes the first agenda item of the next
 * session — and if it is missed twice, becomes an Issue.
 *
 * That last step is the whole argument. Most tools respond to a missed
 * commitment by making the reminder louder, which trains people to ignore
 * reminders. A commitment missed once is life. A commitment missed twice is
 * evidence that something is actually wrong — the wrong owner, the wrong
 * scope, the wrong priority, or a genuine obstacle nobody has named — and the
 * useful response is to put it on the table as a problem to solve rather than
 * to escalate the nagging.
 *
 * miss_count deliberately does not reset when a due date is pushed. Quietly
 * rescheduling something you already missed is exactly the pattern worth
 * surfacing, and a system that forgives it on reschedule can never see it.
 */
final class AccountabilityLoop
{
    /** Misses before a commitment stops being a reminder and becomes an issue. */
    public const ESCALATE_AFTER = 2;

    /** Days before a due date that a nudge goes out. */
    public const NUDGE_DAYS = [2, 0];

    /**
     * Nightly sweep. Records misses and escalates repeat offenders.
     *
     * Idempotent: a task whose miss for a given due date has already been
     * recorded is skipped, so running the tick twice in a day does not double
     * count. That is what last_missed_on is for.
     *
     * @return array{missed:int, escalated:int}
     */
    public static function sweep(int $tenantId, ?string $today = null): array
    {
        $db = Database::conn();
        $today = $today ?? date('Y-m-d');

        $stmt = $db->prepare(
            "SELECT t.*, e.client_org_id
             FROM pl_tasks t
             JOIN pl_engagements e ON e.id = t.engagement_id AND e.tenant_id = t.tenant_id
             WHERE t.tenant_id = :tid
               AND t.status IN ('open','in_progress')
               AND t.due_on IS NOT NULL
               AND t.due_on < :today
               AND (t.last_missed_on IS NULL OR t.last_missed_on < t.due_on)"
        );
        $stmt->execute(['tid' => $tenantId, 'today' => $today]);
        $overdue = $stmt->fetchAll();

        $recordMiss = $db->prepare(
            'UPDATE pl_tasks SET miss_count = miss_count + 1, last_missed_on = :due
             WHERE tenant_id = :tid AND id = :id'
        );

        $missed = 0;
        $escalated = 0;

        foreach ($overdue as $task) {
            $recordMiss->execute([
                'due' => (string) $task['due_on'],
                'tid' => $tenantId,
                'id'  => (int) $task['id'],
            ]);
            $missed++;

            $newCount = ((int) $task['miss_count']) + 1;

            if ($newCount >= self::ESCALATE_AFTER && $task['escalated_issue_id'] === null) {
                self::escalate($tenantId, $task, $newCount);
                $escalated++;
            }
        }

        return ['missed' => $missed, 'escalated' => $escalated];
    }

    /**
     * Turn a repeatedly-missed commitment into an issue.
     *
     * The issue is phrased as a question about the commitment, not an
     * accusation about the person. "Why has this not moved" is a problem to
     * solve together; "Dana missed this twice" is a thing to be defensive
     * about, and defensive people stop telling you the truth.
     *
     * @param array<string,mixed> $task
     */
    private static function escalate(int $tenantId, array $task, int $missCount): void
    {
        $db = Database::conn();

        $db->prepare(
            "INSERT INTO pl_issues (tenant_id, engagement_id, title, detail, origin, status)
             VALUES (:tid, :eid, :title, :detail, 'missed_commitment', 'open')"
        )->execute([
            'tid'    => $tenantId,
            'eid'    => (int) $task['engagement_id'],
            'title'  => mb_substr('Stuck: ' . $task['title'], 0, 255),
            'detail' => "This commitment has come and gone {$missCount} times without moving.\n\n"
                      . "Worth asking what is actually in the way — is it the right owner, "
                      . "the right size, or the right priority? Reminders have not worked, "
                      . "so something else is going on.",
        ]);

        $issueId = (int) $db->lastInsertId();

        $db->prepare('UPDATE pl_tasks SET escalated_issue_id = :iid WHERE tenant_id = :tid AND id = :id')
           ->execute(['iid' => $issueId, 'tid' => $tenantId, 'id' => (int) $task['id']]);

        Timeline::record(
            $tenantId,
            (int) $task['client_org_id'],
            'commitment.escalated',
            '"' . $task['title'] . '" has stalled and is now on the issues list',
            null,
            'issue',
            $issueId,
            false   // coach-side only: the client sees this in the session, not as a notification
        );

        // The coach hears about it in their digest. The client does not hear
        // about it at all — an escalation is a prompt for the coach to ask a
        // better question, not a demerit to serve on the person who missed it.
        Notifications::queueMany(
            $tenantId,
            Notifications::firmRecipients($tenantId, (int) $task['engagement_id']),
            'commitment.escalated',
            '"' . (string) $task['title'] . '" has stalled',
            'Missed ' . $missCount . ' times. Worth asking what is actually in the way.',
            '/issues/' . $issueId,
            [
                'client_org_id' => (int) $task['client_org_id'],
                'object_type'   => 'task',
                'object_id'     => (int) $task['id'],
            ]
        );
    }

    /**
     * Tasks that want a nudge today: due in NUDGE_DAYS, or already overdue.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function dueForNudge(int $tenantId, ?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');

        $offsets = implode(',', array_map('intval', self::NUDGE_DAYS));

        $stmt = Database::conn()->prepare(
            "SELECT t.*, u.email, u.name AS owner_name, u.client_org_id AS owner_client_org_id,
                    e.title AS engagement_title,
                    o.name AS org_name, tn.name AS firm_name, tn.slug AS firm_slug
             FROM pl_tasks t
             JOIN pl_users u ON u.id = t.owner_user_id
             JOIN pl_engagements e ON e.id = t.engagement_id
             JOIN pl_client_orgs o ON o.id = e.client_org_id
             JOIN pl_tenants tn ON tn.id = t.tenant_id
             WHERE t.tenant_id = :tid
               AND t.status IN ('open','in_progress')
               AND t.due_on IS NOT NULL
               AND (
                     DATEDIFF(t.due_on, :today) IN ({$offsets})
                     OR t.due_on < :today2
                   )
             ORDER BY t.due_on ASC"
        );
        $stmt->execute(['tid' => $tenantId, 'today' => $today, 'today2' => $today]);

        return $stmt->fetchAll();
    }

    /**
     * Roll recurring series forward, creating the next occurrence (FR-6.3).
     *
     * Each occurrence is a real task. A missed week stays missed rather than
     * being overwritten when the next one appears — otherwise a weekly metric
     * entry that has never once been done looks perpetually fresh.
     *
     * @return int Occurrences created.
     */
    public static function rollRecurring(int $tenantId, ?string $today = null): int
    {
        $db = Database::conn();
        $today = $today ?? date('Y-m-d');

        $stmt = $db->prepare(
            'SELECT * FROM pl_task_series
             WHERE tenant_id = :tid AND active = 1 AND next_due_on <= :today'
        );
        $stmt->execute(['tid' => $tenantId, 'today' => $today]);

        $tasks = new TaskRepository($tenantId);
        $created = 0;

        foreach ($stmt->fetchAll() as $series) {
            $tasks->createTask([
                'engagement_id'       => (int) $series['engagement_id'],
                'title'               => (string) $series['title'],
                'description'         => $series['description'],
                'owner_user_id'       => $series['owner_user_id'],
                'due_on'              => (string) $series['next_due_on'],
                'source'              => 'recurring',
                'recurring_parent_id' => (int) $series['id'],
            ]);

            $created++;

            $interval = match ((string) $series['frequency']) {
                'weekly'   => '+7 days',
                'biweekly' => '+14 days',
                'monthly'  => '+1 month',
                default    => '+7 days',
            };

            $db->prepare('UPDATE pl_task_series SET next_due_on = :next WHERE tenant_id = :tid AND id = :id')
               ->execute([
                   'next' => date('Y-m-d', strtotime((string) $series['next_due_on'] . ' ' . $interval)),
                   'tid'  => $tenantId,
                   'id'   => (int) $series['id'],
               ]);
        }

        return $created;
    }

    /**
     * Register the 'commitments' agenda block, paying M5's outstanding debt.
     *
     * FR-5.3 and the GROW rule both say the same thing: a session opens by
     * reviewing what was promised last time. This is that.
     */
    public static function registerAgendaProvider(): void
    {
        AgendaBuilder::registerProvider('commitments', static function (int $tenantId, array $session): array {
            // Everything since the previous completed session in this engagement.
            $stmt = Database::conn()->prepare(
                "SELECT MAX(COALESCE(ended_at, scheduled_at)) AS since
                 FROM pl_sessions
                 WHERE tenant_id = :tid AND engagement_id = :eid
                   AND status = 'complete' AND id <> :sid"
            );
            $stmt->execute([
                'tid' => $tenantId,
                'eid' => (int) $session['engagement_id'],
                'sid' => (int) $session['id'],
            ]);

            $since = $stmt->fetch()['since'] ?? null;

            $tasks = new TaskRepository($tenantId);
            $rows = $tasks->commitmentsSince((int) $session['engagement_id'], $since);

            $items = [];

            foreach ($rows as $row) {
                $mark = match ((string) $row['status']) {
                    'done'        => 'done',
                    'in_progress' => 'started',
                    default       => (int) $row['miss_count'] > 0 ? 'MISSED x' . (int) $row['miss_count'] : 'not done',
                };

                $items[] = $row['title']
                    . ' — ' . ($row['owner_name'] ?? 'unassigned')
                    . ' — ' . $mark;
            }

            return [
                'items' => $items,
                'note'  => $items === [] ? 'Nothing was promised last time.' : null,
            ];
        });
    }
}
