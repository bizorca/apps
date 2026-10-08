<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Applying a playbook to an engagement (FR-4.6).
 *
 * The whole module turns on one rule: this is a DEEP COPY, not a reference.
 *
 * A coach who rewrites step 3 of their onboarding process in March must not
 * silently rewrite what a client agreed to in January. So every phase, step,
 * and artifact is copied into the pl_engagement_* tables and the engagement
 * runs against its own frozen copy forever after. source_*_id columns are kept
 * only so the drift diff (FR-4.7) can offer changes the coach may then choose
 * to pull forward, one step at a time.
 *
 * If you ever find yourself writing a JOIN from a live engagement back to
 * pl_playbook_steps to read content, something has gone wrong.
 */
final class PlaybookInstantiator
{
    /**
     * Apply a published playbook version to an engagement.
     *
     * @return int The engagement_playbook id.
     * @throws \RuntimeException if the engagement already runs a playbook, or
     *         the version is not published.
     */
    public static function apply(int $tenantId, int $engagementId, int $versionId, ?int $appliedBy = null): int
    {
        $db = Database::conn();

        $version = self::fetchVersion($db, $tenantId, $versionId);

        if ($version === null) {
            throw new \RuntimeException('No such playbook version in this tenant.');
        }

        if ($version['state'] !== 'published') {
            throw new \RuntimeException('Only a published playbook version can be applied to an engagement.');
        }

        $existing = $db->prepare(
            'SELECT id FROM pl_engagement_playbooks WHERE tenant_id = :tid AND engagement_id = :eid LIMIT 1'
        );
        $existing->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        if ($existing->fetch() !== false) {
            throw new \RuntimeException('This engagement is already running a playbook.');
        }

        $db->beginTransaction();

        try {
            $db->prepare(
                'INSERT INTO pl_engagement_playbooks
                    (tenant_id, engagement_id, playbook_id, source_version_id, source_version_number, name, applied_by)
                 VALUES (:tid, :eid, :pid, :vid, :vnum, :name, :by)'
            )->execute([
                'tid'  => $tenantId,
                'eid'  => $engagementId,
                'pid'  => (int) $version['playbook_id'],
                'vid'  => $versionId,
                'vnum' => (int) $version['version_number'],
                'name' => (string) $version['playbook_name'],
                'by'   => $appliedBy,
            ]);

            $epId = (int) $db->lastInsertId();

            // Phases first, so steps can point at the copies.
            $phaseMap = [];

            $phases = $db->prepare(
                'SELECT * FROM pl_playbook_phases
                 WHERE tenant_id = :tid AND version_id = :vid ORDER BY position ASC, id ASC'
            );
            $phases->execute(['tid' => $tenantId, 'vid' => $versionId]);

            $insertPhase = $db->prepare(
                'INSERT INTO pl_engagement_phases
                    (tenant_id, engagement_playbook_id, source_phase_id, position, title, description)
                 VALUES (:tid, :ep, :src, :pos, :title, :desc)'
            );

            foreach ($phases->fetchAll() as $phase) {
                $insertPhase->execute([
                    'tid'   => $tenantId,
                    'ep'    => $epId,
                    'src'   => (int) $phase['id'],
                    'pos'   => (int) $phase['position'],
                    'title' => (string) $phase['title'],
                    'desc'  => $phase['description'],
                ]);
                $phaseMap[(int) $phase['id']] = (int) $db->lastInsertId();
            }

            // Steps, in playbook order.
            $steps = $db->prepare(
                'SELECT * FROM pl_playbook_steps
                 WHERE tenant_id = :tid AND version_id = :vid ORDER BY position ASC, id ASC'
            );
            $steps->execute(['tid' => $tenantId, 'vid' => $versionId]);

            $insertStep = $db->prepare(
                'INSERT INTO pl_engagement_steps
                    (tenant_id, engagement_playbook_id, engagement_phase_id, source_step_id, position,
                     title, coach_guidance, client_guidance, estimated_minutes,
                     gating, gate_config, completion_rule, is_required, status)
                 VALUES (:tid, :ep, :phase, :src, :pos, :title, :cg, :clg, :mins,
                         :gating, :gate, :rule, :req, :status)'
            );

            $insertArtifact = $db->prepare(
                'INSERT INTO pl_engagement_step_artifacts
                    (tenant_id, engagement_step_id, source_artifact_id, position, artifact_type, title, config, is_required)
                 VALUES (:tid, :step, :src, :pos, :type, :title, :config, :req)'
            );

            $artifacts = $db->prepare(
                'SELECT * FROM pl_playbook_step_artifacts
                 WHERE tenant_id = :tid AND step_id = :sid ORDER BY position ASC, id ASC'
            );

            foreach ($steps->fetchAll() as $step) {
                $sourcePhaseId = (int) $step['phase_id'];

                if (!isset($phaseMap[$sourcePhaseId])) {
                    // A step whose phase did not copy would be unreachable.
                    throw new \RuntimeException('Playbook step ' . $step['id'] . ' references a missing phase.');
                }

                $insertStep->execute([
                    'tid'    => $tenantId,
                    'ep'     => $epId,
                    'phase'  => $phaseMap[$sourcePhaseId],
                    'src'    => (int) $step['id'],
                    'pos'    => (int) $step['position'],
                    'title'  => (string) $step['title'],
                    'cg'     => $step['coach_guidance'],
                    'clg'    => $step['client_guidance'],
                    'mins'   => $step['estimated_minutes'],
                    'gating' => (string) $step['gating'],
                    'gate'   => $step['gate_config'],
                    'rule'   => (string) $step['completion_rule'],
                    'req'    => (int) $step['is_required'],
                    'status' => 'locked',
                ]);

                $newStepId = (int) $db->lastInsertId();

                $artifacts->execute(['tid' => $tenantId, 'sid' => (int) $step['id']]);

                foreach ($artifacts->fetchAll() as $artifact) {
                    $insertArtifact->execute([
                        'tid'    => $tenantId,
                        'step'   => $newStepId,
                        'src'    => (int) $artifact['id'],
                        'pos'    => (int) $artifact['position'],
                        'type'   => (string) $artifact['artifact_type'],
                        'title'  => (string) $artifact['title'],
                        'config' => $artifact['config'],
                        'req'    => (int) $artifact['is_required'],
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

        // Open whatever the gating rules say should be open on day one.
        PlaybookRunner::recomputeAvailability($tenantId, $epId);

        return $epId;
    }

    /**
     * Changes available from a newer published version (FR-4.7).
     *
     * Reports added, changed, and removed steps by comparing the live copy's
     * source_step_id set against the newest published version. Deliberately
     * read-only: pulling a change forward is a per-step decision a coach makes,
     * not something that happens to them.
     *
     * @return array{
     *   current_version:int, latest_version:?int,
     *   added:array<int,array<string,mixed>>,
     *   changed:array<int,array<string,mixed>>,
     *   removed:array<int,array<string,mixed>>
     * }
     */
    public static function drift(int $tenantId, int $engagementPlaybookId): array
    {
        $db = Database::conn();

        $ep = $db->prepare(
            'SELECT * FROM pl_engagement_playbooks WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $ep->execute(['tid' => $tenantId, 'id' => $engagementPlaybookId]);
        $instance = $ep->fetch();

        $empty = ['current_version' => 0, 'latest_version' => null, 'added' => [], 'changed' => [], 'removed' => []];

        if ($instance === false || $instance['playbook_id'] === null) {
            return $empty;
        }

        $latest = $db->prepare(
            "SELECT * FROM pl_playbook_versions
             WHERE tenant_id = :tid AND playbook_id = :pid AND state = 'published'
             ORDER BY version_number DESC LIMIT 1"
        );
        $latest->execute(['tid' => $tenantId, 'pid' => (int) $instance['playbook_id']]);
        $latestVersion = $latest->fetch();

        $current = (int) $instance['source_version_number'];

        if ($latestVersion === false || (int) $latestVersion['version_number'] <= $current) {
            return ['current_version' => $current, 'latest_version' => $current, 'added' => [], 'changed' => [], 'removed' => []];
        }

        // Live copy, keyed by the step it came from.
        $live = $db->prepare(
            'SELECT * FROM pl_engagement_steps WHERE tenant_id = :tid AND engagement_playbook_id = :ep'
        );
        $live->execute(['tid' => $tenantId, 'ep' => $engagementPlaybookId]);

        $liveBySource = [];
        foreach ($live->fetchAll() as $row) {
            if ($row['source_step_id'] !== null) {
                $liveBySource[(int) $row['source_step_id']] = $row;
            }
        }

        // Newest published steps, matched to the live copy by title. Titles are
        // the only stable handle across versions: publishing copies rows, so
        // step ids differ between every version of the same playbook.
        $newSteps = $db->prepare(
            'SELECT * FROM pl_playbook_steps WHERE tenant_id = :tid AND version_id = :vid ORDER BY position ASC'
        );
        $newSteps->execute(['tid' => $tenantId, 'vid' => (int) $latestVersion['id']]);

        $liveByTitle = [];
        foreach ($liveBySource as $row) {
            $liveByTitle[self::key((string) $row['title'])] = $row;
        }

        $added = [];
        $changed = [];
        $seen = [];

        foreach ($newSteps->fetchAll() as $step) {
            $key = self::key((string) $step['title']);
            $seen[$key] = true;

            if (!isset($liveByTitle[$key])) {
                $added[] = ['title' => $step['title'], 'source_step_id' => (int) $step['id']];
                continue;
            }

            $before = $liveByTitle[$key];
            $diffs = [];

            foreach (['coach_guidance', 'client_guidance', 'gating', 'completion_rule'] as $field) {
                if ((string) ($before[$field] ?? '') !== (string) ($step[$field] ?? '')) {
                    $diffs[] = $field;
                }
            }

            if ($diffs !== []) {
                $changed[] = [
                    'title'                => $step['title'],
                    'engagement_step_id'   => (int) $before['id'],
                    'source_step_id'       => (int) $step['id'],
                    'fields'               => $diffs,
                ];
            }
        }

        $removed = [];
        foreach ($liveByTitle as $key => $row) {
            if (!isset($seen[$key])) {
                $removed[] = ['title' => $row['title'], 'engagement_step_id' => (int) $row['id']];
            }
        }

        return [
            'current_version' => $current,
            'latest_version'  => (int) $latestVersion['version_number'],
            'added'           => $added,
            'changed'         => $changed,
            'removed'         => $removed,
        ];
    }

    /**
     * Pull one changed step's content forward. Content only — never status.
     * A step the client already completed stays completed.
     */
    public static function pullStep(int $tenantId, int $engagementStepId, int $sourceStepId): bool
    {
        $db = Database::conn();

        $source = $db->prepare('SELECT * FROM pl_playbook_steps WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $source->execute(['tid' => $tenantId, 'id' => $sourceStepId]);
        $step = $source->fetch();

        if ($step === false) {
            return false;
        }

        $stmt = $db->prepare(
            'UPDATE pl_engagement_steps
             SET title = :title, coach_guidance = :cg, client_guidance = :clg,
                 gating = :gating, gate_config = :gate, completion_rule = :rule,
                 source_step_id = :src
             WHERE tenant_id = :tid AND id = :id'
        );

        $stmt->execute([
            'title'  => (string) $step['title'],
            'cg'     => $step['coach_guidance'],
            'clg'    => $step['client_guidance'],
            'gating' => (string) $step['gating'],
            'gate'   => $step['gate_config'],
            'rule'   => (string) $step['completion_rule'],
            'src'    => $sourceStepId,
            'tid'    => $tenantId,
            'id'     => $engagementStepId,
        ]);

        return $stmt->rowCount() === 1;
    }

    private static function key(string $title): string
    {
        return mb_strtolower(trim($title));
    }

    /** @return array<string,mixed>|null */
    private static function fetchVersion(\PDO $db, int $tenantId, int $versionId): ?array
    {
        $stmt = $db->prepare(
            'SELECT v.*, p.name AS playbook_name
             FROM pl_playbook_versions v
             JOIN pl_playbooks p ON p.id = v.playbook_id AND p.tenant_id = v.tenant_id
             WHERE v.tenant_id = :tid AND v.id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $versionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
