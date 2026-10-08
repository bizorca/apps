<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Audit;
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\ClientIp;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Compliance;

/**
 * Administration and compliance screens (M14).
 *
 * Firm-owner only, all of it. The audit log is a record of what everyone in the
 * firm did, retention decides when client records are destroyed, and erasure
 * decides whether a person is removed from them — none of that belongs to a
 * coach holding a seat, let alone to a client.
 */
final class AdminController
{
    /** The audit log (FR-14.1). */
    public function audit(): string
    {
        [$tenant, $user] = $this->ownerContext();
        Policy::authorize($user, Policy::READ, 'audit_log');

        $action = trim((string) ($_GET['action'] ?? ''));

        return View::render('admin.audit', [
            'title'   => 'Audit log',
            'user'    => $user,
            'tenant'  => $tenant,
            'entries' => Audit::forTenant((int) $tenant['id'], 300, $action !== '' ? $action : null),
            'action'  => $action,
            'actions' => $this->actionsSeen((int) $tenant['id']),
        ]);
    }

    /** Retention policy and what is scheduled for destruction (FR-14.2). */
    public function retention(): string
    {
        [$tenant, $user] = $this->ownerContext();
        Policy::authorize($user, Policy::UPDATE, 'tenant_settings');

        return View::render('admin.retention', [
            'title'    => 'Retention',
            'user'     => $user,
            'tenant'   => $tenant,
            'notices'  => Compliance::notices((int) $tenant['id'], false),
            'noticeDays' => Compliance::NOTICE_DAYS,
            'saved'    => isset($_GET['saved']),
            'error'    => $_GET['error'] ?? null,
        ]);
    }

    public function saveRetention(): string
    {
        [$tenant, $user] = $this->ownerContext();
        Policy::authorize($user, Policy::UPDATE, 'tenant_settings');
        Csrf::check($_POST);

        try {
            Compliance::setRetention((int) $tenant['id'], (int) ($_POST['retention_months'] ?? 0), (int) $user['id']);
        } catch (\InvalidArgumentException $e) {
            redirect(url('/firm/retention?error=' . rawurlencode($e->getMessage())));
        }

        redirect(url('/firm/retention?saved=1'));
    }

    /** Stop the clock on one scheduled destruction. */
    public function keepEngagement(): string
    {
        [$tenant, $user] = $this->ownerContext();
        Policy::authorize($user, Policy::UPDATE, 'tenant_settings');
        Csrf::check($_POST);

        try {
            Compliance::cancelNotice(
                (int) $tenant['id'],
                (int) ($_POST['notice_id'] ?? 0),
                (int) $user['id'],
                (string) ($_POST['reason'] ?? '')
            );
        } catch (\InvalidArgumentException $e) {
            redirect(url('/firm/retention?error=' . rawurlencode($e->getMessage())));
        }

        redirect(url('/firm/retention'));
    }

    /** Erasure requests (FR-14.3). */
    public function erasure(): string
    {
        [$tenant, $user] = $this->ownerContext();
        Policy::authorize($user, Policy::UPDATE, 'user');

        $tenantId = (int) $tenant['id'];

        return View::render('admin.erasure', [
            'title'    => 'Erasure requests',
            'user'     => $user,
            'tenant'   => $tenant,
            'requests' => Compliance::erasureRequests($tenantId),
            'people'   => $this->erasablePeople($tenantId),
            'error'    => $_GET['error'] ?? null,
        ]);
    }

    public function erasureAct(): string
    {
        [$tenant, $user] = $this->ownerContext();
        Policy::authorize($user, Policy::UPDATE, 'user');
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $action = (string) ($_POST['action'] ?? '');

        try {
            match ($action) {
                'request' => Compliance::requestErasure(
                    $tenantId,
                    (int) ($_POST['subject_user_id'] ?? 0),
                    (int) $user['id'],
                    trim((string) ($_POST['reason'] ?? '')) ?: null
                ),
                'pseudonymise' => Compliance::pseudonymise(
                    $tenantId,
                    (int) ($_POST['request_id'] ?? 0),
                    (int) $user['id'],
                    trim((string) ($_POST['decision'] ?? '')) ?: 'Identifying details removed; the engagement record was kept.'
                ),
                'refuse' => Compliance::refuseErasure(
                    $tenantId,
                    (int) ($_POST['request_id'] ?? 0),
                    (int) $user['id'],
                    (string) ($_POST['decision'] ?? '')
                ),
                default => throw new HttpException(422, 'Unknown action.'),
            };
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            redirect(url('/firm/erasure?error=' . rawurlencode($e->getMessage())));
        }

        redirect(url('/firm/erasure'));
    }

    /** Archive or reopen an engagement (FR-14.4). @param array<string,string> $params */
    public function archive(array $params): string
    {
        [$tenant, $user] = $this->ownerContext();
        Csrf::check($_POST);

        $engagementId = (int) ($params['id'] ?? 0);
        $reopen = ($_POST['action'] ?? '') === 'reopen';

        $ok = $reopen
            ? Compliance::reopen((int) $tenant['id'], $engagementId, (int) $user['id'])
            : Compliance::archive((int) $tenant['id'], $engagementId, (int) $user['id']);

        if (!$ok) {
            throw new HttpException(422, 'That engagement is already in that state.');
        }

        redirect(url('/engagements/' . $engagementId . '/journey'));
    }

    /** Attach or waive the coaching agreement (FR-14.5). @param array<string,string> $params */
    public function agreement(array $params): string
    {
        [$tenant, $user] = $this->ownerContext();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $engagementId = (int) ($params['id'] ?? 0);

        if (($_POST['action'] ?? '') === 'waive') {
            Compliance::waiveAgreement($tenantId, $engagementId, (string) ($_POST['note'] ?? ''));
            Audit::record('agreement.waived', $tenantId, $user, 'engagement', $engagementId,
                ['note' => $_POST['note'] ?? null], ClientIp::resolve($_SERVER));
        } else {
            $documentId = (int) ($_POST['document_id'] ?? 0);

            if (!Compliance::setAgreement($tenantId, $engagementId, $documentId)) {
                throw new HttpException(422, 'That document does not belong to this engagement.');
            }

            Audit::record('agreement.attached', $tenantId, $user, 'engagement', $engagementId,
                ['document_id' => $documentId], ClientIp::resolve($_SERVER));
        }

        redirect(url('/engagements/' . $engagementId . '/documents'));
    }

    // ------------------------------------------------------------- internals

    /** @return array<int,string> */
    private function actionsSeen(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT DISTINCT action FROM pl_audit_log WHERE tenant_id = :tid ORDER BY action ASC'
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * People who could be erased: client-side contacts with no open request.
     *
     * Firm-side staff are excluded. Erasing a colleague is a seat and access
     * decision that belongs on the staff screen, and conflating the two would
     * let "forget this person" quietly remove a coach from their own
     * engagements.
     *
     * @return array<int,array<string,mixed>>
     */
    private function erasablePeople(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT u.id, u.name, u.email, o.name AS org_name
             FROM pl_users u
             JOIN pl_client_orgs o ON o.id = u.client_org_id AND o.tenant_id = u.tenant_id
             WHERE u.tenant_id = :tid AND u.client_org_id IS NOT NULL
               AND NOT EXISTS (
                 SELECT 1 FROM pl_erasure_requests r
                 WHERE r.tenant_id = u.tenant_id AND r.subject_user_id = u.id AND r.status = 'open'
               )
             ORDER BY o.name ASC, u.name ASC"
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }

    /** @return array{0:array,1:array} */
    private function ownerContext(): array
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
