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
use Bizorca\Pilotage\Services\Worksheets;

/**
 * Worksheets: the builder, the filling-in, and the results (M10).
 *
 * The resume key for an anonymous response lives in the session and nowhere
 * else. That is the cost of the respondent link genuinely not existing —
 * there is no server-side way to find your own half-finished anonymous
 * worksheet, because finding it would mean knowing it was yours.
 */
final class WorksheetController
{
    private const RESUME_SESSION_KEY = '_worksheet_resume';

    // ---------------------------------------------------------------- library

    public function index(): string
    {
        [$tenant, $user] = $this->firmContext();
        Policy::authorize($user, Policy::READ, 'worksheet_template');

        return View::render('worksheets.index', [
            'title'      => 'Worksheets',
            'user'       => $user,
            'tenant'     => $tenant,
            'worksheets' => Worksheets::all((int) $tenant['id']),
        ]);
    }

    public function store(): string
    {
        [$tenant, $user] = $this->firmContext();
        Policy::authorize($user, Policy::CREATE, 'worksheet_template');
        Csrf::check($_POST);

        try {
            $id = Worksheets::create((int) $tenant['id'], [
                'title'        => $_POST['title'] ?? '',
                'description'  => $_POST['description'] ?? null,
                'kind'         => $_POST['kind'] ?? 'form',
                'is_anonymous' => !empty($_POST['is_anonymous']),
                'min_responses' => $_POST['min_responses'] ?? Worksheets::DEFAULT_MIN_RESPONSES,
            ], (int) $user['id']);
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/worksheets/' . $id));
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        [$tenant, $user] = $this->firmContext();
        Policy::authorize($user, Policy::READ, 'worksheet_template');

        $tenantId = (int) $tenant['id'];
        $worksheet = Worksheets::find($tenantId, (int) ($params['id'] ?? 0));

        if ($worksheet === null) {
            throw new HttpException(404, 'No such worksheet.');
        }

        return View::render('worksheets.build', [
            'title'     => (string) $worksheet['title'],
            'user'      => $user,
            'tenant'    => $tenant,
            'worksheet' => $worksheet,
            'fields'    => Worksheets::fields($tenantId, (int) $worksheet['id']),
            'bands'     => Worksheets::bands($tenantId, (int) $worksheet['id']),
            'canEdit'   => Policy::can($user, Policy::UPDATE, 'worksheet_template')
                && (string) $worksheet['status'] === 'draft',
        ]);
    }

    /** @param array<string,string> $params */
    public function build(array $params): string
    {
        [$tenant, $user] = $this->firmContext();
        Policy::authorize($user, Policy::UPDATE, 'worksheet_template');
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $id = (int) ($params['id'] ?? 0);

        if (Worksheets::find($tenantId, $id) === null) {
            throw new HttpException(404, 'No such worksheet.');
        }

        try {
            match ((string) ($_POST['action'] ?? '')) {
                'add_field' => Worksheets::addField($tenantId, $id, $_POST),
                'add_band'  => Worksheets::addBand($tenantId, $id, $_POST),
                'publish'   => Worksheets::publish($tenantId, $id),
                default     => throw new HttpException(422, 'Unknown action.'),
            };
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/worksheets/' . $id));
    }

    // ------------------------------------------------------------- assigning

    /** @param array<string,string> $params */
    public function assign(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        $this->requireFirmSide($user);
        Csrf::check($_POST);
        Policy::authorize($user, Policy::CREATE, 'worksheet_template');

        try {
            Worksheets::assign(
                (int) $tenant['id'],
                (int) ($_POST['worksheet_id'] ?? 0),
                (int) $engagement['id'],
                ((int) ($_POST['assigned_user_id'] ?? 0)) ?: null,
                $_POST['label'] ?? null,
                trim((string) ($_POST['due_on'] ?? '')) ?: null,
                (int) $user['id']
            );
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/engagements/' . $engagement['id'] . '/worksheets'));
    }

    /** @param array<string,string> $params */
    public function forEngagement(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);

        $tenantId = (int) $tenant['id'];
        $clientSide = !$this->isFirmSide($user);

        return View::render('worksheets.engagement', [
            'title'       => 'Worksheets',
            'user'        => $user,
            'tenant'      => $tenant,
            'engagement'  => $engagement,
            'assignments' => Worksheets::assignmentsFor($tenantId, (int) $engagement['id']),
            'available'   => $clientSide ? [] : Worksheets::all($tenantId),
            'people'      => $clientSide ? [] : $this->clientPeople($tenantId, $engagement),
            'clientSide'  => $clientSide,
        ]);
    }

    // ------------------------------------------------------------- answering

    /** @param array<string,string> $params */
    public function fill(array $params): string
    {
        [$tenant, $user] = $this->context();

        $tenantId = (int) $tenant['id'];
        $assignmentId = (int) ($params['id'] ?? 0);
        $assignment = Worksheets::assignment($tenantId, $assignmentId);

        if ($assignment === null) {
            throw new HttpException(404, 'No such worksheet.');
        }

        $this->assertMayAnswer($tenantId, $user, $assignment);

        $resumeKey = $_SESSION[self::RESUME_SESSION_KEY][$assignmentId] ?? null;
        $response = Worksheets::startOrResume($tenantId, $assignmentId, (int) $user['id'], is_string($resumeKey) ? $resumeKey : null);

        // Hold the key for an anonymous response — there is no other way back
        // to it, since nothing records that it is theirs.
        $_SESSION[self::RESUME_SESSION_KEY][$assignmentId] = (string) $response['resume_key'];

        return View::render('worksheets.fill', [
            'title'      => (string) $assignment['title'],
            'user'       => $user,
            'tenant'     => $tenant,
            'assignment' => $assignment,
            'fields'     => Worksheets::fields($tenantId, (int) $assignment['worksheet_id']),
            'response'   => $response,
            'answers'    => Worksheets::answersByField($tenantId, (int) $response['id']),
            'missing'    => [],
        ]);
    }

    /** @param array<string,string> $params */
    public function save(array $params): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $assignmentId = (int) ($params['id'] ?? 0);
        $assignment = Worksheets::assignment($tenantId, $assignmentId);

        if ($assignment === null) {
            throw new HttpException(404, 'No such worksheet.');
        }

        $this->assertMayAnswer($tenantId, $user, $assignment);

        $resumeKey = $_SESSION[self::RESUME_SESSION_KEY][$assignmentId] ?? null;
        $response = Worksheets::startOrResume($tenantId, $assignmentId, (int) $user['id'], is_string($resumeKey) ? $resumeKey : null);

        if ((string) $response['status'] === 'submitted') {
            throw new HttpException(422, 'That worksheet has already been sent back.');
        }

        foreach (($_POST['f'] ?? []) as $fieldId => $value) {
            Worksheets::saveAnswer($tenantId, (int) $response['id'], (int) $fieldId, $value);
        }

        if (($_POST['action'] ?? '') !== 'submit') {
            redirect(url('/worksheets/fill/' . $assignmentId));
        }

        $result = Worksheets::submit($tenantId, (int) $response['id']);

        if ($result['missing'] !== []) {
            return View::render('worksheets.fill', [
                'title'      => (string) $assignment['title'],
                'user'       => $user,
                'tenant'     => $tenant,
                'assignment' => $assignment,
                'fields'     => Worksheets::fields($tenantId, (int) $assignment['worksheet_id']),
                'response'   => Worksheets::response($tenantId, (int) $response['id']),
                'answers'    => Worksheets::answersByField($tenantId, (int) $response['id']),
                'missing'    => $result['missing'],
            ]);
        }

        // The key has done its job. Dropping it means even this browser can no
        // longer point at an anonymous response.
        unset($_SESSION[self::RESUME_SESSION_KEY][$assignmentId]);

        redirect(url('/worksheets/done/' . $assignmentId));
    }

    /** @param array<string,string> $params */
    public function done(array $params): string
    {
        [$tenant, $user] = $this->context();

        $assignment = Worksheets::assignment((int) $tenant['id'], (int) ($params['id'] ?? 0));

        if ($assignment === null) {
            throw new HttpException(404, 'Not found.');
        }

        return View::render('worksheets.done', [
            'title'      => 'Thank you',
            'user'       => $user,
            'tenant'     => $tenant,
            'assignment' => $assignment,
        ]);
    }

    // --------------------------------------------------------------- results

    /** @param array<string,string> $params */
    public function results(array $params): string
    {
        [$tenant, $user] = $this->firmContext();

        $tenantId = (int) $tenant['id'];
        $assignmentId = (int) ($params['id'] ?? 0);
        $assignment = Worksheets::assignment($tenantId, $assignmentId);

        if ($assignment === null) {
            throw new HttpException(404, 'No such worksheet.');
        }

        Policy::authorize($user, Policy::READ, 'worksheet_response');

        return View::render('worksheets.results', [
            'title'      => (string) $assignment['title'],
            'user'       => $user,
            'tenant'     => $tenant,
            'assignment' => $assignment,
            'results'    => Worksheets::results($tenantId, $assignmentId),
            'fields'     => Worksheets::answerableFields($tenantId, (int) $assignment['worksheet_id']),
            'trend'      => Worksheets::overTime($tenantId, (int) $assignment['worksheet_id'], (int) $assignment['engagement_id']),
        ]);
    }

    /** @param array<string,string> $params */
    public function export(array $params): string
    {
        [$tenant, $user] = $this->firmContext();
        Policy::authorize($user, Policy::READ, 'worksheet_response');

        $tenantId = (int) $tenant['id'];
        $assignmentId = (int) ($params['id'] ?? 0);
        $assignment = Worksheets::assignment($tenantId, $assignmentId);

        if ($assignment === null) {
            throw new HttpException(404, 'Not found.');
        }

        $csv = Worksheets::exportCsv($tenantId, $assignmentId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="worksheet-' . $assignmentId . '.csv"');
        header('Cache-Control: private, no-store');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo $csv;
        exit;
    }

    // ------------------------------------------------------------- internals

    /**
     * May this person answer this worksheet?
     *
     * Firm-side users can, for testing their own material. Client-side users
     * must belong to the engagement's organization, and — for a named
     * worksheet assigned to one person — must be that person.
     *
     * @param array<string,mixed> $assignment
     */
    private function assertMayAnswer(int $tenantId, array $user, array $assignment): void
    {
        $engagements = new EngagementRepository($tenantId);
        $engagement = $engagements->find((int) $assignment['engagement_id']);

        if ($engagement === null) {
            throw new HttpException(404, 'Not found.');
        }

        if (!$this->isFirmSide($user)) {
            if ((int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
                throw new HttpException(404, 'Not found.');
            }
        }

        $assignedTo = $assignment['assigned_user_id'];

        if ($assignedTo !== null && (int) $assignedTo !== (int) $user['id'] && !$this->isFirmSide($user)) {
            throw new HttpException(404, 'Not found.');
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function clientPeople(int $tenantId, array $engagement): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id, name FROM pl_users
             WHERE tenant_id = :tid AND status = 'active' AND client_org_id = :org
             ORDER BY name ASC"
        );
        $stmt->execute(['tid' => $tenantId, 'org' => (int) $engagement['client_org_id']]);

        return $stmt->fetchAll();
    }

    private function isFirmSide(array $user): bool
    {
        return ($user['client_org_id'] ?? null) === null;
    }

    private function requireFirmSide(array $user): void
    {
        if (!$this->isFirmSide($user)) {
            throw new HttpException(404, 'Not found.');
        }
    }

    /** @return array{0:array,1:array} */
    private function firmContext(): array
    {
        [$tenant, $user] = $this->context();
        $this->requireFirmSide($user);

        return [$tenant, $user];
    }

    /** @param array<string,string> $params @return array{0:array,1:array,2:array} */
    private function engagementContext(array $params): array
    {
        [$tenant, $user] = $this->context();

        $engagements = new EngagementRepository();
        $engagement = $engagements->find((int) ($params['id'] ?? 0));

        if ($engagement === null) {
            throw new HttpException(404, 'No such engagement.');
        }

        if (!$this->isFirmSide($user) && (int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
            throw new HttpException(404, 'No such engagement.');
        }

        Policy::authorize($user, Policy::READ, 'engagement', $engagements->policyContext($engagement));

        return [$tenant, $user, $engagement];
    }

    /** @return array{0:array,1:array} */
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
