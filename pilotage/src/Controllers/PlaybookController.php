<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Services\PlaybookAuthor;
use Bizorca\Pilotage\Services\PlaybookInstantiator;
use Bizorca\Pilotage\Services\PlaybookRunner;
use Bizorca\Pilotage\Services\StarterPlaybooks;
use Bizorca\Pilotage\Services\Timeline;

/**
 * Playbook authoring, and running one inside an engagement.
 */
final class PlaybookController
{
    public function index(): string
    {
        [$tenant, $user] = $this->context();
        Policy::authorize($user, Policy::READ, 'playbook_template');

        $stmt = Database::conn()->prepare(
            'SELECT p.*,
                    (SELECT MAX(version_number) FROM pl_playbook_versions v
                      WHERE v.playbook_id = p.id AND v.state = "published") AS published_version,
                    (SELECT COUNT(*) FROM pl_playbook_versions v2
                      WHERE v2.playbook_id = p.id AND v2.state = "draft") AS has_draft
             FROM pl_playbooks p
             WHERE p.tenant_id = :tid AND p.status <> "archived"
             ORDER BY p.name ASC'
        );
        $stmt->execute(['tid' => (int) $tenant['id']]);

        return View::render('playbooks.index', [
            'title'     => 'Playbooks',
            'user'      => $user,
            'tenant'    => $tenant,
            'playbooks' => $stmt->fetchAll(),
        ]);
    }

    public function store(): string
    {
        [$tenant, $user] = $this->context();
        Policy::authorize($user, Policy::CREATE, 'playbook_template');
        Csrf::check($_POST);

        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '') {
            throw new HttpException(422, 'A playbook needs a name.');
        }

        $id = PlaybookAuthor::create((int) $tenant['id'], $name, $_POST['description'] ?? null, (int) $user['id']);

        redirect(url('/playbooks/' . $id));
    }

    public function installStarter(): string
    {
        [$tenant, $user] = $this->context();
        Policy::authorize($user, Policy::CREATE, 'playbook_template');
        Csrf::check($_POST);

        $id = StarterPlaybooks::installQuarterlyRhythm((int) $tenant['id'], (int) $user['id']);

        redirect(url('/playbooks/' . $id));
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        [$tenant, $user] = $this->context();
        Policy::authorize($user, Policy::READ, 'playbook_template');

        $tenantId = (int) $tenant['id'];
        $playbookId = (int) ($params['id'] ?? 0);

        $playbook = $this->playbook($tenantId, $playbookId);

        // Read-only: never call draftVersion() here. It CREATES a draft, so
        // merely viewing a published playbook would mint a new version — a GET
        // with a side effect, and one that also desynchronises the add-step
        // form (whose phase ids would come from the published version while
        // the write path targeted the fresh draft, silently dropping steps).
        // Opening a draft is an explicit action: POST /playbooks/{id}/revise.
        $version = $this->latestVersion($tenantId, $playbookId);

        if ($version === null) {
            throw new HttpException(404, 'This playbook has no versions yet.');
        }

        $canEdit = Policy::can($user, Policy::UPDATE, 'playbook_template')
            && (string) $version['state'] === 'draft';
        $canRevise = Policy::can($user, Policy::UPDATE, 'playbook_template')
            && (string) $version['state'] === 'published';

        return View::render('playbooks.show', [
            'title'    => (string) $playbook['name'],
            'user'     => $user,
            'tenant'   => $tenant,
            'playbook' => $playbook,
            'version'  => $version,
            'phases'    => $this->versionTree($tenantId, (int) $version['id']),
            'canEdit'   => $canEdit,
            'canRevise' => $canRevise,
        ]);
    }

    /**
     * Explicitly open a new draft from the newest published version, so a
     * coach can revise their process without touching live engagements.
     *
     * @param array<string,string> $params
     */
    public function revise(array $params): string
    {
        [$tenant, $user] = $this->context();
        Policy::authorize($user, Policy::UPDATE, 'playbook_template');
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $playbookId = (int) ($params['id'] ?? 0);
        $this->playbook($tenantId, $playbookId);

        PlaybookAuthor::draftVersion($tenantId, $playbookId);

        redirect(url('/playbooks/' . $playbookId));
    }

    /** @param array<string,string> $params */
    public function addPhase(array $params): string
    {
        [$tenant, $user] = $this->context();
        Policy::authorize($user, Policy::UPDATE, 'playbook_template');
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $playbookId = (int) ($params['id'] ?? 0);
        $this->playbook($tenantId, $playbookId);

        $draft = PlaybookAuthor::draftVersion($tenantId, $playbookId);
        $title = trim((string) ($_POST['title'] ?? ''));

        if ($title !== '') {
            PlaybookAuthor::addPhase($tenantId, (int) $draft['id'], $title);
        }

        redirect(url('/playbooks/' . $playbookId));
    }

    /** @param array<string,string> $params */
    public function addStep(array $params): string
    {
        [$tenant, $user] = $this->context();
        Policy::authorize($user, Policy::UPDATE, 'playbook_template');
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $playbookId = (int) ($params['id'] ?? 0);
        $this->playbook($tenantId, $playbookId);

        $draft = PlaybookAuthor::draftVersion($tenantId, $playbookId);
        $title = trim((string) ($_POST['title'] ?? ''));
        $phaseId = (int) ($_POST['phase_id'] ?? 0);

        // The phase must belong to this draft, or a coach could bolt a step
        // onto another playbook by editing a form value.
        if ($title !== '' && $this->phaseBelongsToVersion($tenantId, $phaseId, (int) $draft['id'])) {
            PlaybookAuthor::addStep($tenantId, (int) $draft['id'], $phaseId, $title, [
                'coach_guidance'  => $_POST['coach_guidance'] ?? null,
                'client_guidance' => $_POST['client_guidance'] ?? null,
                'gating'          => in_array($_POST['gating'] ?? '', ['sequential', 'parallel', 'triggered'], true)
                    ? $_POST['gating'] : 'sequential',
                'is_required'     => empty($_POST['optional']) ? 1 : 0,
            ]);
        }

        redirect(url('/playbooks/' . $playbookId));
    }

    /** @param array<string,string> $params */
    public function import(array $params): string
    {
        [$tenant, $user] = $this->context();
        Policy::authorize($user, Policy::UPDATE, 'playbook_template');
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $playbookId = (int) ($params['id'] ?? 0);
        $this->playbook($tenantId, $playbookId);

        $draft = PlaybookAuthor::draftVersion($tenantId, $playbookId);
        $markdown = (string) ($_POST['markdown'] ?? '');

        if (trim($markdown) !== '') {
            PlaybookAuthor::importMarkdown($tenantId, (int) $draft['id'], $markdown);
        }

        redirect(url('/playbooks/' . $playbookId));
    }

    /** @param array<string,string> $params */
    public function publish(array $params): string
    {
        [$tenant, $user] = $this->context();
        Policy::authorize($user, Policy::UPDATE, 'playbook_template');
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $playbookId = (int) ($params['id'] ?? 0);
        $this->playbook($tenantId, $playbookId);

        $draft = PlaybookAuthor::draftVersion($tenantId, $playbookId);

        try {
            PlaybookAuthor::publish($tenantId, (int) $draft['id'], $_POST['notes'] ?? null);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/playbooks/' . $playbookId));
    }

    // ------------------------------------------------- running an engagement

    /** @param array<string,string> $params */
    public function journey(array $params): string
    {
        [$tenant, $user] = $this->context();

        $tenantId = (int) $tenant['id'];
        $engagements = new EngagementRepository();
        $engagement = $engagements->find((int) ($params['id'] ?? 0));

        if ($engagement === null) {
            throw new HttpException(404, 'No such engagement.');
        }

        $clientSide = ($user['client_org_id'] ?? null) !== null;

        // A client-side user may only ever reach their own organization's work.
        if ($clientSide && (int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
            throw new HttpException(404, 'No such engagement.');
        }

        Policy::authorize($user, Policy::READ, 'engagement', $engagements->policyContext($engagement));

        $instance = $engagements->playbookInstance((int) $engagement['id']);

        if ($instance === null) {
            return View::render('playbooks.apply', [
                'title'      => (string) $engagement['title'],
                'user'       => $user,
                'tenant'     => $tenant,
                'engagement' => $engagement,
                'available'  => $clientSide ? [] : $this->publishedPlaybooks($tenantId),
                'canApply'   => !$clientSide && Policy::can($user, Policy::UPDATE, 'playbook_instance'),
            ]);
        }

        $epId = (int) $instance['id'];

        return View::render('playbooks.journey', [
            'title'      => (string) $engagement['title'],
            'user'       => $user,
            'tenant'     => $tenant,
            'engagement' => $engagement,
            'instance'   => $instance,
            'phases'     => PlaybookRunner::journey($tenantId, $epId, $clientSide),
            'progress'   => PlaybookRunner::progress($tenantId, $epId),
            'drift'      => $clientSide ? null : PlaybookInstantiator::drift($tenantId, $epId),
            'clientSide' => $clientSide,
            'canRun'     => !$clientSide && Policy::can($user, Policy::UPDATE, 'playbook_instance'),
        ]);
    }

    /** @param array<string,string> $params */
    public function apply(array $params): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $engagements = new EngagementRepository();
        $engagement = $engagements->find((int) ($params['id'] ?? 0));

        if ($engagement === null) {
            throw new HttpException(404, 'No such engagement.');
        }

        $this->requireFirmSide($user);
        Policy::authorize($user, Policy::UPDATE, 'playbook_instance', $engagements->policyContext($engagement));

        try {
            PlaybookInstantiator::apply(
                $tenantId,
                (int) $engagement['id'],
                (int) ($_POST['version_id'] ?? 0),
                (int) $user['id']
            );
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        Timeline::record(
            $tenantId,
            (int) $engagement['client_org_id'],
            'playbook.applied',
            'Started the process for ' . $engagement['title'],
            $user
        );

        redirect(url('/engagements/' . $engagement['id'] . '/journey'));
    }

    /** @param array<string,string> $params */
    public function stepAction(array $params): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $engagements = new EngagementRepository();
        $engagement = $engagements->find((int) ($params['id'] ?? 0));

        if ($engagement === null) {
            throw new HttpException(404, 'No such engagement.');
        }

        $this->requireFirmSide($user);
        Policy::authorize($user, Policy::UPDATE, 'playbook_instance', $engagements->policyContext($engagement));

        $stepId = (int) ($params['step'] ?? 0);

        // The step must belong to THIS engagement. Without this, a coach with
        // one engagement could drive steps in another by changing the id.
        if (!$this->stepBelongsToEngagement($tenantId, $stepId, (int) $engagement['id'])) {
            throw new HttpException(404, 'No such step.');
        }

        $action = (string) ($_POST['action'] ?? '');

        try {
            match ($action) {
                'start'    => PlaybookRunner::start($tenantId, $stepId),
                'complete' => PlaybookRunner::complete($tenantId, $stepId, (int) $user['id']),
                'skip'     => PlaybookRunner::skip($tenantId, $stepId, (int) $user['id'], (string) ($_POST['reason'] ?? '')),
                'reopen'   => PlaybookRunner::reopen($tenantId, $stepId),
                default    => throw new HttpException(422, 'Unknown action.'),
            };
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/engagements/' . $engagement['id'] . '/journey'));
    }

    // ------------------------------------------------------------- internals

    /** @return array<int,array<string,mixed>> */
    private function publishedPlaybooks(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT p.id, p.name, v.id AS version_id, v.version_number
             FROM pl_playbooks p
             JOIN pl_playbook_versions v ON v.playbook_id = p.id AND v.state = 'published'
             WHERE p.tenant_id = :tid AND p.status <> 'archived'
               AND v.version_number = (SELECT MAX(v2.version_number) FROM pl_playbook_versions v2
                                        WHERE v2.playbook_id = p.id AND v2.state = 'published')
             ORDER BY p.name ASC"
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    private function versionTree(int $tenantId, int $versionId): array
    {
        $db = Database::conn();

        $phases = $db->prepare('SELECT * FROM pl_playbook_phases WHERE tenant_id = :tid AND version_id = :vid ORDER BY position ASC, id ASC');
        $phases->execute(['tid' => $tenantId, 'vid' => $versionId]);

        $steps = $db->prepare('SELECT * FROM pl_playbook_steps WHERE tenant_id = :tid AND version_id = :vid ORDER BY position ASC, id ASC');
        $steps->execute(['tid' => $tenantId, 'vid' => $versionId]);

        $byPhase = [];
        foreach ($steps->fetchAll() as $step) {
            $byPhase[(int) $step['phase_id']][] = $step;
        }

        $out = [];
        foreach ($phases->fetchAll() as $phase) {
            $phase['steps'] = $byPhase[(int) $phase['id']] ?? [];
            $out[] = $phase;
        }

        return $out;
    }

    private function phaseBelongsToVersion(int $tenantId, int $phaseId, int $versionId): bool
    {
        $stmt = Database::conn()->prepare(
            'SELECT 1 FROM pl_playbook_phases WHERE tenant_id = :tid AND id = :id AND version_id = :vid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $phaseId, 'vid' => $versionId]);

        return $stmt->fetch() !== false;
    }

    private function stepBelongsToEngagement(int $tenantId, int $stepId, int $engagementId): bool
    {
        $stmt = Database::conn()->prepare(
            'SELECT 1 FROM pl_engagement_steps s
             JOIN pl_engagement_playbooks ep ON ep.id = s.engagement_playbook_id
             WHERE s.tenant_id = :tid AND s.id = :sid AND ep.engagement_id = :eid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => $stepId, 'eid' => $engagementId]);

        return $stmt->fetch() !== false;
    }

    /** @return array<string,mixed> */
    private function playbook(int $tenantId, int $playbookId): array
    {
        $stmt = Database::conn()->prepare('SELECT * FROM pl_playbooks WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $playbookId]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new HttpException(404, 'No such playbook.');
        }

        return $row;
    }

    /**
     * The version to display: the open draft if there is one, otherwise the
     * newest published. Read-only — creates nothing.
     *
     * @return array<string,mixed>|null
     */
    private function latestVersion(int $tenantId, int $playbookId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM pl_playbook_versions
             WHERE tenant_id = :tid AND playbook_id = :pid
             ORDER BY (state = 'draft') DESC, version_number DESC LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId, 'pid' => $playbookId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    private function latestPublished(int $tenantId, int $playbookId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM pl_playbook_versions
             WHERE tenant_id = :tid AND playbook_id = :pid AND state = 'published'
             ORDER BY version_number DESC LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId, 'pid' => $playbookId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function requireFirmSide(array $user): void
    {
        if (($user['client_org_id'] ?? null) !== null) {
            throw new HttpException(404, 'Not found.');
        }
    }

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>} */
    private function context(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'No tenant in scope.');
        }

        $user = Session::user();

        if ($user === null) {
            redirect(url('/login?redirect=' . rawurlencode(pl_request_path())));
        }

        return [$tenant, $user];
    }
}
