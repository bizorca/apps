<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Repositories;

use Bizorca\Pilotage\Core\Repository;

/**
 * Tasks — commitments, in both directions (FR-6.2).
 *
 * The subtask rule (FR-6.4) is enforced here: exactly one level of nesting. A
 * subtask cannot itself have children. Deeper trees turn a coaching product
 * into a worse Asana, and the spec calls that wall deliberate.
 */
final class TaskRepository extends Repository
{
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'];

    public const EVIDENCE = [
        'none'   => 'Just mark it done',
        'note'   => 'A note explaining what happened',
        'file'   => 'A file',
        'metric' => 'A number',
    ];

    protected function table(): string
    {
        return 'pl_tasks';
    }

    /** @return string[] */
    protected function writable(): array
    {
        return [
            'engagement_id', 'parent_id', 'title', 'description', 'definition_of_done',
            'owner_user_id', 'assigned_by', 'due_on', 'priority', 'status',
            'source', 'source_session_id', 'source_step_id', 'recurring_parent_id',
            'evidence_required', 'client_visible',
        ];
    }

    /** @param array<string,mixed> $data */
    public function createTask(array $data): int
    {
        $problems = self::problems($data);

        if ($problems !== []) {
            throw new \InvalidArgumentException(implode(' ', $problems));
        }

        // One level of nesting. If the named parent is itself a subtask,
        // attach to ITS parent rather than refusing — the user's intent is
        // obvious and an error here would be pedantry.
        if (!empty($data['parent_id'])) {
            $parent = $this->find((int) $data['parent_id']);

            if ($parent === null) {
                throw new \InvalidArgumentException('That parent task does not exist.');
            }

            if ($parent['parent_id'] !== null) {
                $data['parent_id'] = (int) $parent['parent_id'];
            }

            // A subtask always belongs to its parent's engagement.
            $data['engagement_id'] = (int) $parent['engagement_id'];
        }

        return $this->insert($data);
    }

    /**
     * @param array<string,mixed> $data
     * @return string[]
     */
    public static function problems(array $data): array
    {
        $problems = [];

        if (trim((string) ($data['title'] ?? '')) === '') {
            $problems[] = 'A task needs a title.';
        }

        if (empty($data['engagement_id']) && empty($data['parent_id'])) {
            $problems[] = 'A task must belong to an engagement.';
        }

        if (!empty($data['priority']) && !isset(self::PRIORITIES[$data['priority']])) {
            $problems[] = 'Unknown priority.';
        }

        if (!empty($data['evidence_required']) && !isset(self::EVIDENCE[$data['evidence_required']])) {
            $problems[] = 'Unknown evidence requirement.';
        }

        if (!empty($data['due_on'])) {
            $due = (string) $data['due_on'];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) || strtotime($due) === false) {
                $problems[] = 'Due date should look like YYYY-MM-DD.';
            }
        }

        return $problems;
    }

    /**
     * Mark done, honouring the evidence requirement (FR-6.6).
     *
     * @throws \InvalidArgumentException when required evidence is missing.
     */
    public function complete(int $taskId, int $userId, ?string $evidenceNote = null): bool
    {
        $task = $this->find($taskId);

        if ($task === null) {
            return false;
        }

        if ((string) $task['status'] === 'done') {
            return true;
        }

        $required = (string) $task['evidence_required'];

        if ($required !== 'none' && trim((string) $evidenceNote) === '') {
            throw new \InvalidArgumentException(match ($required) {
                'note'   => 'This one needs a note saying what happened.',
                'file'   => 'This one needs a file before it can be closed.',
                'metric' => 'This one needs a number before it can be closed.',
                default  => 'This one needs evidence before it can be closed.',
            });
        }

        $stmt = $this->db->prepare(
            'UPDATE ' . $this->table() . "
             SET status = 'done', completed_at = NOW(), completed_by = :uid, evidence_note = :note
             WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([
            'uid'  => $userId,
            'note' => $evidenceNote,
            'id'   => $taskId,
            'tid'  => $this->tenantId,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function reopen(int $taskId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ' . $this->table() . "
             SET status = 'open', completed_at = NULL, completed_by = NULL
             WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute(['id' => $taskId, 'tid' => $this->tenantId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public function forEngagement(int $engagementId, bool $clientVisibleOnly = false): array
    {
        $sql = 'SELECT * FROM ' . $this->table() . '
                WHERE tenant_id = :tid AND engagement_id = :eid';

        if ($clientVisibleOnly) {
            $sql .= ' AND client_visible = 1';
        }

        $sql .= " ORDER BY FIELD(status,'open','in_progress','done','cancelled'),
                           due_on IS NULL, due_on ASC, id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tid' => $this->tenantId, 'eid' => $engagementId]);

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Everything owed BY this person. */
    public function forOwner(int $userId, bool $openOnly = true): array
    {
        $sql = 'SELECT t.*, e.title AS engagement_title, o.name AS org_name
                FROM ' . $this->table() . ' t
                JOIN pl_engagements e ON e.id = t.engagement_id
                JOIN pl_client_orgs o ON o.id = e.client_org_id
                WHERE t.tenant_id = :tid AND t.owner_user_id = :uid';

        if ($openOnly) {
            $sql .= " AND t.status IN ('open','in_progress')";
        }

        $sql .= ' ORDER BY t.due_on IS NULL, t.due_on ASC, t.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tid' => $this->tenantId, 'uid' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Everything overdue across the whole book — the number a coach actually
     * opens the product to see (FR-12.1).
     *
     * @return array<int,array<string,mixed>>
     */
    public function overdue(?int $coachUserId = null): array
    {
        $sql = "SELECT t.*, e.title AS engagement_title, o.name AS org_name, u.name AS owner_name
                FROM " . $this->table() . " t
                JOIN pl_engagements e ON e.id = t.engagement_id
                JOIN pl_client_orgs o ON o.id = e.client_org_id
                LEFT JOIN pl_users u ON u.id = t.owner_user_id
                WHERE t.tenant_id = :tid
                  AND t.status IN ('open','in_progress')
                  AND t.due_on IS NOT NULL AND t.due_on < CURDATE()";

        $params = ['tid' => $this->tenantId];

        if ($coachUserId !== null) {
            $sql .= ' AND e.coach_user_id = :coach';
            $params['coach'] = $coachUserId;
        }

        $sql .= ' ORDER BY t.due_on ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Commitments due or overdue since a given moment — the "what we promised"
     * agenda block (FR-5.3).
     *
     * @return array<int,array<string,mixed>>
     */
    public function commitmentsSince(int $engagementId, ?string $since): array
    {
        $sql = "SELECT t.*, u.name AS owner_name
                FROM " . $this->table() . " t
                LEFT JOIN pl_users u ON u.id = t.owner_user_id
                WHERE t.tenant_id = :tid AND t.engagement_id = :eid
                  AND t.status <> 'cancelled'
                  AND (t.due_on IS NULL OR t.due_on <= CURDATE())";

        $params = ['tid' => $this->tenantId, 'eid' => $engagementId];

        if ($since !== null) {
            $sql .= ' AND (t.completed_at IS NULL OR t.completed_at >= :since)';
            $params['since'] = $since;
        }

        $sql .= ' ORDER BY t.status ASC, t.due_on ASC LIMIT 25';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function subtasks(int $parentId): array
    {
        return $this->all(['parent_id' => $parentId], 'id asc');
    }

    /**
     * Completion rate, which feeds the engagement health score (FR-12.2).
     *
     * Counts only tasks that have come due — a task due next month is not
     * evidence of anything yet, and including it would flatter every new
     * engagement.
     *
     * @return array{due:int, done:int, rate:?int}
     */
    public function completionRate(int $engagementId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN due_on IS NOT NULL AND due_on <= CURDATE() THEN 1 ELSE 0 END) AS due_count,
                SUM(CASE WHEN due_on IS NOT NULL AND due_on <= CURDATE() AND status = 'done' THEN 1 ELSE 0 END) AS done_count
             FROM " . $this->table() . "
             WHERE tenant_id = :tid AND engagement_id = :eid AND status <> 'cancelled'"
        );
        $stmt->execute(['tid' => $this->tenantId, 'eid' => $engagementId]);
        $row = $stmt->fetch();

        $due = (int) ($row['due_count'] ?? 0);
        $done = (int) ($row['done_count'] ?? 0);

        return [
            'due'  => $due,
            'done' => $done,
            'rate' => $due === 0 ? null : (int) round($done / $due * 100),
        ];
    }
}
