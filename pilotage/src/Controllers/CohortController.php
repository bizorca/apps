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
use Bizorca\Pilotage\Services\Cohorts;
use Bizorca\Pilotage\Services\DocumentService;

/**
 * Cohorts: group coaching across several client organizations.
 *
 * Both sides reach this controller, and they see materially different things.
 * A coach sees the roster, everyone's progress, and the controls. A member sees
 * the shared sessions, the published material, and — only if the coach turned
 * it on — who else is in the group. Nothing else about a peer is reachable from
 * here, and `Cohorts` is where that is enforced rather than in the views.
 */
final class CohortController
{
    public function index(): string
    {
        [$tenant, $user] = $this->firmContext();

        return View::render('cohorts.index', [
            'title'   => 'Cohorts',
            'user'    => $user,
            'tenant'  => $tenant,
            'cohorts' => Cohorts::all((int) $tenant['id']),
            'error'   => $_GET['error'] ?? null,
        ]);
    }

    public function store(): string
    {
        [$tenant, $user] = $this->firmContext();
        Csrf::check($_POST);
        Policy::authorize($user, Policy::CREATE, 'engagement');

        try {
            $id = Cohorts::create((int) $tenant['id'], [
                'name'           => $_POST['name'] ?? '',
                'description'    => $_POST['description'] ?? null,
                'cadence'        => $_POST['cadence'] ?? 'monthly',
                'starts_on'      => trim((string) ($_POST['starts_on'] ?? '')) ?: null,
                'roster_visible' => !empty($_POST['roster_visible']),
            ], (int) $user['id']);
        } catch (\InvalidArgumentException $e) {
            redirect(url('/cohorts?error=' . rawurlencode($e->getMessage())));
        }

        redirect(url('/cohorts/' . $id));
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'No tenant in scope.');
        }

        $user = Session::user();

        if ($user === null) {
            redirect(url('/login?redirect=' . rawurlencode(pl_request_path())));
        }

        $tenantId = (int) $tenant['id'];
        $cohortId = (int) ($params['id'] ?? 0);
        $cohort = Cohorts::find($tenantId, $cohortId);

        // A 404 rather than a 403 for a non-member: whether a particular cohort
        // exists is itself something a client should not be able to probe.
        if ($cohort === null || !Cohorts::visibleTo($tenantId, $cohortId, $user)) {
            throw new HttpException(404, 'Not found.');
        }

        $firmSide = ($user['client_org_id'] ?? null) === null;

        return View::render('cohorts.show', [
            'title'    => (string) $cohort['name'],
            'user'     => $user,
            'tenant'   => $tenant,
            'cohort'   => $cohort,
            'firmSide' => $firmSide,
            // Progress across every member is the coach's view of the thing.
            // A member gets names only, and only if the roster is published.
            'members'  => $firmSide ? Cohorts::members($tenantId, $cohortId) : [],
            'roster'   => Cohorts::rosterFor($tenantId, $cohortId, $user),
            'sessions' => Cohorts::sessions($tenantId, $cohortId),
            'materials' => Cohorts::materials($tenantId, $cohortId),
            'announcements' => Cohorts::announcements($tenantId, $cohortId),
            'available' => $firmSide ? $this->joinableEngagements($tenantId, $cohortId) : [],
            'library'   => $firmSide ? DocumentService::library($tenantId) : [],
            'error'     => $_GET['error'] ?? null,
        ]);
    }

    /** @param array<string,string> $params */
    public function act(array $params): string
    {
        [$tenant, $user] = $this->firmContext();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $cohortId = (int) ($params['id'] ?? 0);

        if (Cohorts::find($tenantId, $cohortId) === null) {
            throw new HttpException(404, 'No such cohort.');
        }

        try {
            match ((string) ($_POST['action'] ?? '')) {
                'add_member' => Cohorts::addMember($tenantId, $cohortId, (int) ($_POST['engagement_id'] ?? 0)),
                'remove_member' => Cohorts::removeMember($tenantId, $cohortId, (int) ($_POST['engagement_id'] ?? 0)),
                'schedule' => Cohorts::scheduleSession(
                    $tenantId, $cohortId,
                    (string) ($_POST['title'] ?? ''),
                    trim((string) ($_POST['scheduled_at'] ?? '')) ?: null,
                    (int) $user['id'],
                    trim((string) ($_POST['location'] ?? '')) ?: null,
                    (int) ($_POST['duration_minutes'] ?? 90)
                ),
                'publish' => Cohorts::publishMaterial(
                    $tenantId, $cohortId, (int) ($_POST['document_id'] ?? 0),
                    trim((string) ($_POST['note'] ?? '')) ?: null, (int) $user['id']
                ),
                'withdraw' => Cohorts::withdrawMaterial($tenantId, $cohortId, (int) ($_POST['document_id'] ?? 0)),
                'announce' => Cohorts::announce(
                    $tenantId, $cohortId,
                    (string) ($_POST['subject'] ?? ''),
                    (string) ($_POST['body'] ?? ''),
                    (int) $user['id']
                ),
                'settings' => Cohorts::update($tenantId, $cohortId, [
                    'name'           => $_POST['name'] ?? '',
                    'description'    => $_POST['description'] ?? null,
                    'cadence'        => $_POST['cadence'] ?? 'monthly',
                    'status'         => $_POST['status'] ?? 'active',
                    'roster_visible' => !empty($_POST['roster_visible']),
                ]),
                default => throw new HttpException(422, 'Unknown action.'),
            };
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            redirect(url('/cohorts/' . $cohortId . '?error=' . rawurlencode($e->getMessage())));
        }

        redirect(url('/cohorts/' . $cohortId));
    }

    // ------------------------------------------------------------- internals

    /**
     * Active engagements not already in this cohort.
     *
     * @return array<int,array<string,mixed>>
     */
    private function joinableEngagements(int $tenantId, int $cohortId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT e.id, e.title, o.name AS org_name
             FROM pl_engagements e
             JOIN pl_client_orgs o ON o.id = e.client_org_id AND o.tenant_id = e.tenant_id
             WHERE e.tenant_id = :tid AND e.status = 'active'
               AND NOT EXISTS (
                 SELECT 1 FROM pl_cohort_members m
                 WHERE m.cohort_id = :cid AND m.engagement_id = e.id AND m.left_at IS NULL
               )
             ORDER BY o.name ASC"
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $cohortId]);

        return $stmt->fetchAll();
    }

    /** @return array{0:array,1:array} */
    private function firmContext(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'No tenant in scope.');
        }

        $user = Session::user();

        if ($user === null) {
            redirect(url('/login?redirect=' . rawurlencode(pl_request_path())));
        }

        if (($user['client_org_id'] ?? null) !== null) {
            throw new HttpException(404, 'Not found.');
        }

        return [$tenant, $user];
    }
}
