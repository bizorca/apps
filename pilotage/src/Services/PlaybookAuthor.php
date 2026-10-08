<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Authoring playbooks: drafts, publishing, duplication, and Markdown import.
 *
 * Versioning model: a playbook always has at most one draft version, which is
 * the only thing editable. Publishing freezes it. Editing a published playbook
 * opens a NEW draft seeded from the last published version, so a coach can
 * revise their process without touching any engagement already running it.
 *
 * FR-4.8 calls migration friction the biggest adoption barrier, and it is
 * right: coaches keep their process in a Google Doc. importMarkdown() exists
 * so getting it into Pilotage is a paste, not an afternoon of clicking.
 */
final class PlaybookAuthor
{
    public static function create(int $tenantId, string $name, ?string $description = null, ?int $userId = null): int
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException('A playbook needs a name.');
        }

        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_playbooks (tenant_id, name, description, created_by) VALUES (:tid, :n, :d, :by)'
        )->execute(['tid' => $tenantId, 'n' => $name, 'd' => $description, 'by' => $userId]);

        $playbookId = (int) $db->lastInsertId();

        self::newDraftVersion($tenantId, $playbookId, 1);

        return $playbookId;
    }

    /** The editable draft for a playbook, creating one if the last version is published. */
    public static function draftVersion(int $tenantId, int $playbookId): array
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            "SELECT * FROM pl_playbook_versions
             WHERE tenant_id = :tid AND playbook_id = :pid AND state = 'draft'
             ORDER BY version_number DESC LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId, 'pid' => $playbookId]);
        $draft = $stmt->fetch();

        if ($draft !== false) {
            return $draft;
        }

        // No draft: open one seeded from the newest published version, so
        // revising an established playbook starts from what it already says.
        $latest = $db->prepare(
            'SELECT * FROM pl_playbook_versions
             WHERE tenant_id = :tid AND playbook_id = :pid
             ORDER BY version_number DESC LIMIT 1'
        );
        $latest->execute(['tid' => $tenantId, 'pid' => $playbookId]);
        $previous = $latest->fetch();

        $next = $previous === false ? 1 : ((int) $previous['version_number'] + 1);
        $newId = self::newDraftVersion($tenantId, $playbookId, $next);

        if ($previous !== false) {
            self::copyVersionContent($tenantId, (int) $previous['id'], $newId);
        }

        $stmt->execute(['tid' => $tenantId, 'pid' => $playbookId]);

        return $stmt->fetch();
    }

    /** Freeze the draft. Engagements may only be started from published versions. */
    public static function publish(int $tenantId, int $versionId, ?string $notes = null): bool
    {
        $db = Database::conn();

        $steps = $db->prepare('SELECT COUNT(*) AS c FROM pl_playbook_steps WHERE tenant_id = :tid AND version_id = :vid');
        $steps->execute(['tid' => $tenantId, 'vid' => $versionId]);

        if ((int) $steps->fetch()['c'] === 0) {
            throw new \RuntimeException('A playbook needs at least one step before it can be published.');
        }

        $stmt = $db->prepare(
            "UPDATE pl_playbook_versions
             SET state = 'published', published_at = NOW(), notes = :notes
             WHERE tenant_id = :tid AND id = :id AND state = 'draft'"
        );
        $stmt->execute(['notes' => $notes, 'tid' => $tenantId, 'id' => $versionId]);

        if ($stmt->rowCount() !== 1) {
            return false;
        }

        $db->prepare(
            "UPDATE pl_playbooks p
             JOIN pl_playbook_versions v ON v.playbook_id = p.id
             SET p.status = 'published'
             WHERE v.id = :id AND p.tenant_id = :tid"
        )->execute(['id' => $versionId, 'tid' => $tenantId]);

        return true;
    }

    public static function addPhase(int $tenantId, int $versionId, string $title, ?string $description = null, ?int $position = null): int
    {
        $db = Database::conn();

        $position = $position ?? self::nextPosition($db, 'pl_playbook_phases', 'version_id', $versionId, $tenantId);

        $db->prepare(
            'INSERT INTO pl_playbook_phases (tenant_id, version_id, position, title, description)
             VALUES (:tid, :vid, :pos, :title, :desc)'
        )->execute(['tid' => $tenantId, 'vid' => $versionId, 'pos' => $position, 'title' => $title, 'desc' => $description]);

        return (int) $db->lastInsertId();
    }

    /** @param array<string,mixed> $attrs */
    public static function addStep(int $tenantId, int $versionId, int $phaseId, string $title, array $attrs = []): int
    {
        $db = Database::conn();

        $position = $attrs['position'] ?? self::nextPosition($db, 'pl_playbook_steps', 'version_id', $versionId, $tenantId);

        $db->prepare(
            'INSERT INTO pl_playbook_steps
                (tenant_id, version_id, phase_id, position, title, coach_guidance, client_guidance,
                 estimated_minutes, gating, gate_config, completion_rule, is_required)
             VALUES (:tid, :vid, :pid, :pos, :title, :cg, :clg, :mins, :gating, :gate, :rule, :req)'
        )->execute([
            'tid'    => $tenantId,
            'vid'    => $versionId,
            'pid'    => $phaseId,
            'pos'    => $position,
            'title'  => $title,
            'cg'     => $attrs['coach_guidance'] ?? null,
            'clg'    => $attrs['client_guidance'] ?? null,
            'mins'   => $attrs['estimated_minutes'] ?? null,
            'gating' => $attrs['gating'] ?? 'sequential',
            'gate'   => isset($attrs['gate_config']) ? json_encode($attrs['gate_config']) : null,
            'rule'   => $attrs['completion_rule'] ?? 'coach_marks',
            'req'    => isset($attrs['is_required']) ? (int) $attrs['is_required'] : 1,
        ]);

        return (int) $db->lastInsertId();
    }

    /** @param array<string,mixed> $config */
    public static function addArtifact(int $tenantId, int $stepId, string $type, string $title, array $config = [], bool $required = true): int
    {
        $db = Database::conn();

        $position = self::nextPosition($db, 'pl_playbook_step_artifacts', 'step_id', $stepId, $tenantId);

        $db->prepare(
            'INSERT INTO pl_playbook_step_artifacts
                (tenant_id, step_id, position, artifact_type, title, config, is_required)
             VALUES (:tid, :sid, :pos, :type, :title, :config, :req)'
        )->execute([
            'tid'    => $tenantId,
            'sid'    => $stepId,
            'pos'    => $position,
            'type'   => $type,
            'title'  => $title,
            'config' => $config === [] ? null : json_encode($config),
            'req'    => $required ? 1 : 0,
        ]);

        return (int) $db->lastInsertId();
    }

    /** Duplicate-and-modify (FR-4.8). Copies the newest version into a fresh draft. */
    public static function duplicate(int $tenantId, int $playbookId, string $newName, ?int $userId = null): int
    {
        $db = Database::conn();

        $source = $db->prepare(
            'SELECT * FROM pl_playbook_versions
             WHERE tenant_id = :tid AND playbook_id = :pid
             ORDER BY (state = "published") DESC, version_number DESC LIMIT 1'
        );
        $source->execute(['tid' => $tenantId, 'pid' => $playbookId]);
        $version = $source->fetch();

        if ($version === false) {
            throw new \RuntimeException('Nothing to duplicate.');
        }

        $newPlaybookId = self::create($tenantId, $newName, null, $userId);
        $draft = self::draftVersion($tenantId, $newPlaybookId);

        self::copyVersionContent($tenantId, (int) $version['id'], (int) $draft['id']);

        return $newPlaybookId;
    }

    /**
     * Import a process from Markdown (FR-4.8).
     *
     * The format is whatever a coach's Google Doc already looks like:
     *
     *   # Phase title
     *   ## Step title
     *   Prose here becomes coach guidance.
     *   > Blockquotes become client-facing guidance.
     *   - [ ] Checkbox lines become task artifacts.
     *
     * Deliberately forgiving. A step before any heading gets an "Untitled
     * phase" rather than an error, because the point is to get somebody's
     * existing document in with as little friction as possible.
     *
     * @return array{phases:int, steps:int, artifacts:int}
     */
    public static function importMarkdown(int $tenantId, int $versionId, string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];

        $phaseId = null;
        $stepId = null;
        $coachBuffer = [];
        $clientBuffer = [];

        $counts = ['phases' => 0, 'steps' => 0, 'artifacts' => 0];

        $flush = static function () use (&$stepId, &$coachBuffer, &$clientBuffer, $tenantId): void {
            if ($stepId === null) {
                return;
            }

            $coach = trim(implode("\n", $coachBuffer));
            $client = trim(implode("\n", $clientBuffer));

            if ($coach !== '' || $client !== '') {
                Database::conn()->prepare(
                    'UPDATE pl_playbook_steps SET coach_guidance = :cg, client_guidance = :clg
                     WHERE tenant_id = :tid AND id = :id'
                )->execute([
                    'cg'  => $coach === '' ? null : $coach,
                    'clg' => $client === '' ? null : $client,
                    'tid' => $tenantId,
                    'id'  => $stepId,
                ]);
            }

            $coachBuffer = [];
            $clientBuffer = [];
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^#\s+(.+)$/', $trimmed, $m) === 1) {
                $flush();
                $stepId = null;
                $phaseId = self::addPhase($tenantId, $versionId, trim($m[1]));
                $counts['phases']++;
                continue;
            }

            if (preg_match('/^##\s+(.+)$/', $trimmed, $m) === 1) {
                $flush();

                if ($phaseId === null) {
                    $phaseId = self::addPhase($tenantId, $versionId, 'Untitled phase');
                    $counts['phases']++;
                }

                $stepId = self::addStep($tenantId, $versionId, $phaseId, trim($m[1]));
                $counts['steps']++;
                continue;
            }

            if ($stepId !== null && preg_match('/^[-*]\s+\[[ xX]\]\s+(.+)$/', $trimmed, $m) === 1) {
                self::addArtifact($tenantId, $stepId, 'task', trim($m[1]));
                $counts['artifacts']++;
                continue;
            }

            if ($stepId !== null && preg_match('/^>\s?(.*)$/', $trimmed, $m) === 1) {
                $clientBuffer[] = $m[1];
                continue;
            }

            if ($stepId !== null && $trimmed !== '') {
                $coachBuffer[] = $trimmed;
            }
        }

        $flush();

        return $counts;
    }

    // ------------------------------------------------------------- internals

    private static function newDraftVersion(int $tenantId, int $playbookId, int $number): int
    {
        $db = Database::conn();

        $db->prepare(
            "INSERT INTO pl_playbook_versions (tenant_id, playbook_id, version_number, state)
             VALUES (:tid, :pid, :num, 'draft')"
        )->execute(['tid' => $tenantId, 'pid' => $playbookId, 'num' => $number]);

        return (int) $db->lastInsertId();
    }

    /** Deep-copy one version's phases, steps, and artifacts into another. */
    private static function copyVersionContent(int $tenantId, int $fromVersionId, int $toVersionId): void
    {
        $db = Database::conn();

        $phases = $db->prepare('SELECT * FROM pl_playbook_phases WHERE tenant_id = :tid AND version_id = :vid ORDER BY position ASC, id ASC');
        $phases->execute(['tid' => $tenantId, 'vid' => $fromVersionId]);

        $phaseMap = [];

        foreach ($phases->fetchAll() as $phase) {
            $phaseMap[(int) $phase['id']] = self::addPhase(
                $tenantId,
                $toVersionId,
                (string) $phase['title'],
                $phase['description'],
                (int) $phase['position']
            );
        }

        $steps = $db->prepare('SELECT * FROM pl_playbook_steps WHERE tenant_id = :tid AND version_id = :vid ORDER BY position ASC, id ASC');
        $steps->execute(['tid' => $tenantId, 'vid' => $fromVersionId]);

        $artifacts = $db->prepare('SELECT * FROM pl_playbook_step_artifacts WHERE tenant_id = :tid AND step_id = :sid ORDER BY position ASC, id ASC');

        foreach ($steps->fetchAll() as $step) {
            if (!isset($phaseMap[(int) $step['phase_id']])) {
                continue;
            }

            $gate = $step['gate_config'];

            $newStepId = self::addStep($tenantId, $toVersionId, $phaseMap[(int) $step['phase_id']], (string) $step['title'], [
                'position'          => (int) $step['position'],
                'coach_guidance'    => $step['coach_guidance'],
                'client_guidance'   => $step['client_guidance'],
                'estimated_minutes' => $step['estimated_minutes'],
                'gating'            => (string) $step['gating'],
                'gate_config'       => is_string($gate) ? json_decode($gate, true) : $gate,
                'completion_rule'   => (string) $step['completion_rule'],
                'is_required'       => (int) $step['is_required'],
            ]);

            $artifacts->execute(['tid' => $tenantId, 'sid' => (int) $step['id']]);

            foreach ($artifacts->fetchAll() as $artifact) {
                $config = $artifact['config'];

                self::addArtifact(
                    $tenantId,
                    $newStepId,
                    (string) $artifact['artifact_type'],
                    (string) $artifact['title'],
                    is_string($config) ? (json_decode($config, true) ?? []) : ($config ?? []),
                    (int) $artifact['is_required'] === 1
                );
            }
        }
    }

    private static function nextPosition(\PDO $db, string $table, string $column, int $parentId, int $tenantId): int
    {
        // Table and column are hardcoded call-site constants, never user input.
        $stmt = $db->prepare(
            'SELECT COALESCE(MAX(position), -1) + 1 AS p FROM ' . $table . '
             WHERE tenant_id = :tid AND ' . $column . ' = :parent'
        );
        $stmt->execute(['tid' => $tenantId, 'parent' => $parentId]);

        return (int) $stmt->fetch()['p'];
    }
}
