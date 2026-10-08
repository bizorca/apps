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
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Services\ScopeControl;
use Bizorca\Pilotage\Services\Timeline;

/** Engagements, and scope/change control on them (M4B). */
final class EngagementController
{
    /** @param array<string,string> $params */
    public function store(array $params): string
    {
        [$tenant, $user] = $this->context();
        $this->requireFirmSide($user);
        Csrf::check($_POST);

        // The organization comes from the PATH, not the body. Taking it from a
        // form field would let a coach attach an engagement to any client in
        // the tenant by editing a hidden input.
        $orgs = new ClientOrgRepository();
        $org = $orgs->find((int) ($params['id'] ?? 0));

        if ($org === null) {
            throw new HttpException(404, 'No such client.');
        }

        Policy::authorize($user, Policy::CREATE, 'engagement', [
            'client_org_id' => (int) $org['id'],
            'owner_user_id' => (int) $user['id'],
        ]);

        $engagements = new EngagementRepository();

        try {
            $id = $engagements->createEngagement([
                'client_org_id' => (int) $org['id'],
                'title'         => (string) ($_POST['title'] ?? ''),
                'summary'       => trim((string) ($_POST['summary'] ?? '')) ?: null,
                'status'        => 'active',
                'coach_user_id' => (int) $user['id'],
                'cadence'       => in_array($_POST['cadence'] ?? '', array_keys(EngagementRepository::CADENCES), true)
                    ? (string) $_POST['cadence'] : 'biweekly',
                'scope_enabled' => empty($_POST['scope_enabled']) ? 0 : 1,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        Timeline::record((int) $tenant['id'], (int) $org['id'], 'engagement.started',
            ($_POST['title'] ?? 'An engagement') . ' started', $user, 'engagement', $id);

        redirect(url('/engagements/' . $id . '/journey'));
    }

    /** @param array<string,string> $params */
    public function scope(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);

        $tenantId = (int) $tenant['id'];
        $engagementId = (int) $engagement['id'];
        $locked = ScopeControl::isLocked($tenantId, $engagementId);
        $clientSide = !$this->isFirmSide($user);

        return View::render('engagements.scope', [
            'title'      => 'Scope',
            'user'       => $user,
            'tenant'     => $tenant,
            'engagement' => $engagement,
            'items'      => ScopeControl::items($tenantId, $engagementId),
            'requests'   => ScopeControl::changeRequests($tenantId, $engagementId),
            'summary'    => ScopeControl::summary($tenantId, $engagementId),
            'locked'     => $locked,
            'accepted'   => $engagement['client_accepted_scope_at'] !== null,
            'clientSide' => $clientSide,
            'canEdit'    => Policy::can($user, Policy::UPDATE, 'scope_item', ['scope_locked' => $locked]),
            'canReview'  => Policy::can($user, 'review', 'change_request'),
            'canAccept'  => Policy::can($user, 'accept', 'scope_item'),
        ]);
    }

    /** @param array<string,string> $params */
    public function scopeAct(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $engagementId = (int) $engagement['id'];
        $locked = ScopeControl::isLocked($tenantId, $engagementId);
        $action = (string) ($_POST['action'] ?? '');

        try {
            switch ($action) {
                case 'enable':
                    $this->requireFirmSide($user);
                    ScopeControl::enable($tenantId, $engagementId);
                    break;

                case 'add_item':
                    Policy::authorize($user, Policy::CREATE, 'scope_item', ['scope_locked' => $locked]);
                    ScopeControl::addItem($tenantId, $engagementId,
                        (string) ($_POST['title'] ?? ''), trim((string) ($_POST['description'] ?? '')) ?: null);
                    break;

                case 'remove_item':
                    Policy::authorize($user, Policy::DELETE, 'scope_item', ['scope_locked' => $locked]);
                    $this->assertScopeItem($tenantId, (int) ($_POST['item_id'] ?? 0), $engagementId);
                    ScopeControl::removeItem($tenantId, (int) $_POST['item_id']);
                    break;

                case 'lock':
                    $this->requireFirmSide($user);
                    Policy::authorize($user, Policy::UPDATE, 'scope_item', ['scope_locked' => $locked]);
                    ScopeControl::lock($tenantId, $engagementId);
                    break;

                case 'accept':
                    Policy::authorize($user, 'accept', 'scope_item');
                    ScopeControl::accept($tenantId, $engagementId);
                    break;

                case 'request_change':
                    Policy::authorize($user, Policy::CREATE, 'change_request');
                    ScopeControl::requestChange($tenantId, $engagementId,
                        (string) ($_POST['title'] ?? ''), (string) ($_POST['description'] ?? ''),
                        $_POST['justification'] ?? null, (int) $user['id']);
                    break;

                case 'approve':
                case 'decline':
                    Policy::authorize($user, 'review', 'change_request');
                    $requestId = (int) ($_POST['request_id'] ?? 0);
                    $request = ScopeControl::changeRequest($tenantId, $requestId);

                    if ($request === null || (int) $request['engagement_id'] !== $engagementId) {
                        throw new HttpException(404, 'No such change request.');
                    }

                    $note = trim((string) ($_POST['review_note'] ?? '')) ?: null;

                    $action === 'approve'
                        ? ScopeControl::approve($tenantId, $requestId, (int) $user['id'], $note)
                        : ScopeControl::decline($tenantId, $requestId, (int) $user['id'], $note);
                    break;

                default:
                    throw new HttpException(422, 'Unknown action.');
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/engagements/' . $engagementId . '/scope'));
    }

    // ------------------------------------------------------------- internals

    private function assertScopeItem(int $tenantId, int $itemId, int $engagementId): void
    {
        $stmt = Database::conn()->prepare(
            'SELECT 1 FROM pl_scope_items WHERE tenant_id = :tid AND id = :id AND engagement_id = :eid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $itemId, 'eid' => $engagementId]);

        if ($stmt->fetch() === false) {
            throw new HttpException(404, 'Not found.');
        }
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
