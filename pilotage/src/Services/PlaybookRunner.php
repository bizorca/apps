<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Running a live playbook: gating (FR-4.4), completion (FR-4.5), progress.
 *
 * Availability is recomputed rather than incrementally patched. Recomputing a
 * few dozen steps is cheap, and it means a step can never get stuck in a state
 * that no longer matches the rules — which is exactly the bug incremental
 * state machines produce six months in.
 */
final class PlaybookRunner
{
    public const LOCKED      = 'locked';
    public const AVAILABLE   = 'available';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETE    = 'complete';
    public const SKIPPED     = 'skipped';

    /** Terminal states — recompute never moves a step out of these. */
    private const SETTLED = [self::COMPLETE, self::SKIPPED];

    /**
     * Recompute which steps are open.
     *
     * sequential — open once every earlier REQUIRED step is settled. Optional
     *              steps never block; a coach who skips the optional
     *              benchmarking step should not stall the engagement.
     * parallel   — open as soon as its phase has been reached.
     * triggered  — open when its gate condition is met.
     *
     * @return int Number of steps whose status changed.
     */
    public static function recomputeAvailability(int $tenantId, int $engagementPlaybookId, ?int $now = null): int
    {
        $now = $now ?? time();
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT s.*, p.position AS phase_position
             FROM pl_engagement_steps s
             JOIN pl_engagement_phases p ON p.id = s.engagement_phase_id
             WHERE s.tenant_id = :tid AND s.engagement_playbook_id = :ep
             ORDER BY p.position ASC, s.position ASC, s.id ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'ep' => $engagementPlaybookId]);
        $steps = $stmt->fetchAll();

        if ($steps === []) {
            return 0;
        }

        $appliedAt = self::appliedAt($db, $tenantId, $engagementPlaybookId);

        $update = $db->prepare(
            'UPDATE pl_engagement_steps SET status = :status WHERE tenant_id = :tid AND id = :id'
        );

        $changed = 0;
        $priorRequiredAllSettled = true;

        foreach ($steps as $step) {
            $current = (string) $step['status'];

            if (in_array($current, self::SETTLED, true)) {
                if ((int) $step['is_required'] === 1) {
                    // A settled required step does not block what follows.
                    // (Skipped counts as settled — FR-4.5 allows deviation.)
                    $priorRequiredAllSettled = $priorRequiredAllSettled && true;
                }
                continue;
            }

            $shouldOpen = match ((string) $step['gating']) {
                'parallel'  => true,
                'triggered' => self::triggerMet($step, $appliedAt, $now),
                default     => $priorRequiredAllSettled,
            };

            $target = $shouldOpen ? self::AVAILABLE : self::LOCKED;

            // Never demote work already underway.
            if ($current === self::IN_PROGRESS) {
                $target = self::IN_PROGRESS;
            }

            if ($target !== $current) {
                $update->execute(['status' => $target, 'tid' => $tenantId, 'id' => (int) $step['id']]);
                $changed++;
            }

            if ((int) $step['is_required'] === 1) {
                $priorRequiredAllSettled = false;
            }
        }

        return $changed;
    }

    /**
     * Mark a step complete.
     *
     * Honours the completion rule (FR-4.5): a step set to artifacts_complete
     * cannot be closed while a required artifact is outstanding, whoever asks.
     *
     * @throws \RuntimeException when the rule is not satisfied.
     */
    public static function complete(int $tenantId, int $engagementStepId, int $userId): bool
    {
        $db = Database::conn();
        $step = self::step($db, $tenantId, $engagementStepId);

        if ($step === null) {
            return false;
        }

        if ((string) $step['status'] === self::COMPLETE) {
            return true;
        }

        if ((string) $step['status'] === self::LOCKED) {
            throw new \RuntimeException('That step is not open yet.');
        }

        if ((string) $step['completion_rule'] === 'artifacts_complete' && self::outstandingArtifacts($db, $tenantId, $engagementStepId) > 0) {
            throw new \RuntimeException('This step closes when its required items are done.');
        }

        $db->prepare(
            'UPDATE pl_engagement_steps
             SET status = :s, completed_at = NOW(), completed_by = :uid
             WHERE tenant_id = :tid AND id = :id'
        )->execute(['s' => self::COMPLETE, 'uid' => $userId, 'tid' => $tenantId, 'id' => $engagementStepId]);

        self::recomputeAvailability($tenantId, (int) $step['engagement_playbook_id']);

        return true;
    }

    /**
     * Skip a step. FR-4.5: coaches deviate from their own process constantly,
     * and the system should record it rather than prevent it — but never
     * silently. The reason is mandatory in code and in the schema.
     *
     * @throws \InvalidArgumentException when no reason is given.
     */
    public static function skip(int $tenantId, int $engagementStepId, int $userId, string $reason): bool
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Skipping a step requires a reason.');
        }

        $db = Database::conn();
        $step = self::step($db, $tenantId, $engagementStepId);

        if ($step === null || (string) $step['status'] === self::COMPLETE) {
            return false;
        }

        $db->prepare(
            'UPDATE pl_engagement_steps
             SET status = :s, skipped_reason = :reason, completed_at = NOW(), completed_by = :uid
             WHERE tenant_id = :tid AND id = :id'
        )->execute([
            's'      => self::SKIPPED,
            'reason' => mb_substr($reason, 0, 500),
            'uid'    => $userId,
            'tid'    => $tenantId,
            'id'     => $engagementStepId,
        ]);

        self::recomputeAvailability($tenantId, (int) $step['engagement_playbook_id']);

        return true;
    }

    public static function start(int $tenantId, int $engagementStepId): bool
    {
        $stmt = Database::conn()->prepare(
            'UPDATE pl_engagement_steps
             SET status = :s, started_at = COALESCE(started_at, NOW())
             WHERE tenant_id = :tid AND id = :id AND status = :avail'
        );
        $stmt->execute([
            's'     => self::IN_PROGRESS,
            'avail' => self::AVAILABLE,
            'tid'   => $tenantId,
            'id'    => $engagementStepId,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Reopen a settled step. Clears the skip reason so a reopened step does not
     * carry a stale explanation.
     */
    public static function reopen(int $tenantId, int $engagementStepId): bool
    {
        $db = Database::conn();
        $step = self::step($db, $tenantId, $engagementStepId);

        if ($step === null) {
            return false;
        }

        $db->prepare(
            'UPDATE pl_engagement_steps
             SET status = :s, completed_at = NULL, completed_by = NULL, skipped_reason = NULL
             WHERE tenant_id = :tid AND id = :id'
        )->execute(['s' => self::AVAILABLE, 'tid' => $tenantId, 'id' => $engagementStepId]);

        self::recomputeAvailability($tenantId, (int) $step['engagement_playbook_id']);

        return true;
    }

    /**
     * Progress, for both dashboards.
     *
     * Skipped counts as settled but not as done: a coach who skipped four
     * steps should see honest progress, not a flattering number.
     *
     * @return array{total:int, complete:int, skipped:int, settled:int, percent:int, next:?array<string,mixed>}
     */
    public static function progress(int $tenantId, int $engagementPlaybookId): array
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT status, COUNT(*) AS c FROM pl_engagement_steps
             WHERE tenant_id = :tid AND engagement_playbook_id = :ep GROUP BY status'
        );
        $stmt->execute(['tid' => $tenantId, 'ep' => $engagementPlaybookId]);

        $counts = ['locked' => 0, 'available' => 0, 'in_progress' => 0, 'complete' => 0, 'skipped' => 0];

        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }

        $total = array_sum($counts);
        $complete = $counts['complete'];
        $settled = $complete + $counts['skipped'];

        $next = $db->prepare(
            "SELECT s.* FROM pl_engagement_steps s
             JOIN pl_engagement_phases p ON p.id = s.engagement_phase_id
             WHERE s.tenant_id = :tid AND s.engagement_playbook_id = :ep
               AND s.status IN ('available','in_progress')
             ORDER BY p.position ASC, s.position ASC LIMIT 1"
        );
        $next->execute(['tid' => $tenantId, 'ep' => $engagementPlaybookId]);
        $nextStep = $next->fetch();

        return [
            'total'    => $total,
            'complete' => $complete,
            'skipped'  => $counts['skipped'],
            'settled'  => $settled,
            'percent'  => $total === 0 ? 0 : (int) round($complete / $total * 100),
            'next'     => $nextStep === false ? null : $nextStep,
        ];
    }

    /**
     * The journey, grouped by phase.
     *
     * @param bool $clientSide Strips coach_guidance. Callers must say which
     *        side they are rendering for — defaulting would make every new
     *        call site a potential leak.
     * @return array<int,array<string,mixed>>
     */
    public static function journey(int $tenantId, int $engagementPlaybookId, bool $clientSide): array
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT s.*, p.title AS phase_title, p.position AS phase_position, p.id AS phase_id
             FROM pl_engagement_steps s
             JOIN pl_engagement_phases p ON p.id = s.engagement_phase_id
             WHERE s.tenant_id = :tid AND s.engagement_playbook_id = :ep
             ORDER BY p.position ASC, s.position ASC, s.id ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'ep' => $engagementPlaybookId]);

        $phases = [];

        foreach ($stmt->fetchAll() as $row) {
            if ($clientSide) {
                // FR-4.10: the client never sees the coach's how-to-run-it text.
                unset($row['coach_guidance']);
            }

            $phaseId = (int) $row['phase_id'];

            if (!isset($phases[$phaseId])) {
                $phases[$phaseId] = [
                    'id'       => $phaseId,
                    'title'    => $row['phase_title'],
                    'position' => (int) $row['phase_position'],
                    'steps'    => [],
                ];
            }

            $phases[$phaseId]['steps'][] = $row;
        }

        return array_values($phases);
    }

    // ------------------------------------------------------------- internals

    /** @param array<string,mixed> $step */
    private static function triggerMet(array $step, ?int $appliedAt, int $now): bool
    {
        $config = $step['gate_config'];

        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        if (!is_array($config)) {
            // A triggered step with no usable config would never open. Treat it
            // as available rather than stranding the engagement.
            return true;
        }

        return match ((string) ($config['type'] ?? '')) {
            'date'   => $appliedAt !== null
                        && $now >= $appliedAt + (((int) ($config['offset_days'] ?? 0)) * 86400),
            'manual' => false,   // the coach opens it by hand
            // session and metric triggers land with M5 and M9. Until then they
            // stay closed rather than opening on a condition nobody checks.
            default  => false,
        };
    }

    private static function appliedAt(\PDO $db, int $tenantId, int $engagementPlaybookId): ?int
    {
        $stmt = $db->prepare(
            'SELECT applied_at FROM pl_engagement_playbooks WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $engagementPlaybookId]);
        $row = $stmt->fetch();

        return $row === false ? null : strtotime((string) $row['applied_at']);
    }

    private static function outstandingArtifacts(\PDO $db, int $tenantId, int $stepId): int
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS c FROM pl_engagement_step_artifacts
             WHERE tenant_id = :tid AND engagement_step_id = :sid AND is_required = 1 AND completed_at IS NULL'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => $stepId]);

        return (int) $stmt->fetch()['c'];
    }

    /** @return array<string,mixed>|null */
    private static function step(\PDO $db, int $tenantId, int $stepId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM pl_engagement_steps WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $stepId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
