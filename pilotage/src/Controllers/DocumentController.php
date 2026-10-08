<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Services\DocumentService;
use Bizorca\Pilotage\Services\Storage;
use Bizorca\Pilotage\Services\Timeline;

/**
 * Documents on an engagement, plus the firm's template library.
 *
 * Downloads are NOT handled here — they go through FileController, which
 * re-checks permission per request before a single byte moves.
 */
final class DocumentController
{
    /** @param array<string,string> $params */
    public function forEngagement(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);

        $tenantId = (int) $tenant['id'];
        $clientSide = !$this->isFirmSide($user);

        return View::render('documents.index', [
            'title'        => 'Documents',
            'user'         => $user,
            'tenant'       => $tenant,
            'engagement'   => $engagement,
            'deliverables' => DocumentService::forEngagement($tenantId, (int) $engagement['id'], $clientSide, 'deliverable'),
            'clientFiles'  => DocumentService::forEngagement($tenantId, (int) $engagement['id'], $clientSide, 'client_file'),
            'requests'     => DocumentService::requests($tenantId, (int) $engagement['id']),
            'clientSide'   => $clientSide,
            'maxBytes'     => Storage::MAX_BYTES,
            'maxLabel'     => Storage::humanBytes(Storage::MAX_BYTES),
        ]);
    }

    /** @param array<string,string> $params */
    public function upload(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $clientSide = !$this->isFirmSide($user);

        // A client uploads THEIR files; the firm produces deliverables.
        // The context is decided by who is asking, never by the form.
        $context = $clientSide ? 'client_file' : (string) ($_POST['context'] ?? 'deliverable');

        if (!in_array($context, ['deliverable', 'client_file'], true)) {
            throw new HttpException(422, 'Unknown document type.');
        }

        $objectType = $context === 'client_file' ? 'document_client_file' : 'document_deliverable';
        Policy::authorize($user, Policy::CREATE, $objectType, [
            'client_org_id' => (int) $engagement['client_org_id'],
        ]);

        $file = $_FILES['file'] ?? null;

        if (!is_array($file)) {
            throw new HttpException(422, 'No file was chosen.');
        }

        try {
            $documentId = DocumentService::create($tenantId, [
                'context'       => $context,
                'engagement_id' => (int) $engagement['id'],
                'client_org_id' => (int) $engagement['client_org_id'],
                'title'         => (string) ($_POST['title'] ?? ''),
                'description'   => trim((string) ($_POST['description'] ?? '')) ?: null,
                'folder'        => trim((string) ($_POST['folder'] ?? '')) ?: null,
            ], $file, (int) $user['id']);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        // Fulfilling a request item, if this upload was answering one.
        $itemId = (int) ($_POST['request_item_id'] ?? 0);

        if ($itemId > 0) {
            DocumentService::fulfilRequestItem($tenantId, $itemId, $documentId, (int) $user['id']);
        }

        Timeline::record(
            $tenantId,
            (int) $engagement['client_org_id'],
            $context === 'client_file' ? 'document.uploaded' : 'document.drafted',
            ($_POST['title'] ?? 'A document') . ($context === 'client_file' ? ' uploaded' : ' added'),
            $user,
            'document',
            $documentId,
            $context === 'client_file'
        );

        redirect(url('/engagements/' . $engagement['id'] . '/documents'));
    }

    /** @param array<string,string> $params */
    public function act(array $params): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $document = DocumentService::find($tenantId, (int) ($params['id'] ?? 0));

        if ($document === null || $document['engagement_id'] === null) {
            throw new HttpException(404, 'No such document.');
        }

        $engagements = new EngagementRepository($tenantId);
        $engagement = $engagements->find((int) $document['engagement_id']);

        if ($engagement === null) {
            throw new HttpException(404, 'No such document.');
        }

        $clientSide = !$this->isFirmSide($user);

        if ($clientSide && (int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
            throw new HttpException(404, 'No such document.');
        }

        $action = (string) ($_POST['action'] ?? '');

        try {
            switch ($action) {
                case 'deliver':
                    $this->requireFirmSide($user);
                    Policy::authorize($user, Policy::UPDATE, 'document_deliverable', $engagements->policyContext($engagement));
                    DocumentService::deliver($tenantId, (int) $document['id'], (int) $user['id'], trim((string) ($_POST['note'] ?? '')) ?: null);
                    Timeline::record($tenantId, (int) $engagement['client_org_id'], 'document.delivered',
                        $document['title'] . ' delivered', $user, 'document', (int) $document['id']);
                    break;

                case 'acknowledge':
                    // Only the client acknowledges receipt — a coach confirming
                    // their own delivery would make the record worthless.
                    if (!$clientSide) {
                        throw new HttpException(403, 'Only the client acknowledges receipt.');
                    }
                    DocumentService::acknowledge($tenantId, (int) $document['id'], (int) $user['id'], \Bizorca\Pilotage\Core\ClientIp::resolve($_SERVER));
                    Timeline::record($tenantId, (int) $engagement['client_org_id'], 'document.acknowledged',
                        $document['title'] . ' acknowledged', $user, 'document', (int) $document['id']);
                    break;

                case 'share':
                    $this->requireFirmSide($user);
                    Policy::authorize($user, Policy::UPDATE, 'document_deliverable', $engagements->policyContext($engagement));
                    DocumentService::createShareLink(
                        $tenantId, (int) $document['id'], (int) $user['id'],
                        trim((string) ($_POST['label'] ?? '')) ?: null,
                        (int) ($_POST['days'] ?? DocumentService::SHARE_LINK_DEFAULT_DAYS),
                        ((int) ($_POST['max_views'] ?? 0)) ?: null
                    );
                    break;

                case 'revoke_share':
                    $this->requireFirmSide($user);
                    DocumentService::revokeShareLink($tenantId, (int) ($_POST['link_id'] ?? 0));
                    break;

                default:
                    throw new HttpException(422, 'Unknown action.');
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/documents/' . $document['id']));
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        [$tenant, $user] = $this->context();

        $tenantId = (int) $tenant['id'];
        $document = DocumentService::find($tenantId, (int) ($params['id'] ?? 0));

        if ($document === null) {
            throw new HttpException(404, 'No such document.');
        }

        $clientSide = !$this->isFirmSide($user);
        $engagement = null;

        if ($document['engagement_id'] !== null) {
            $engagements = new EngagementRepository($tenantId);
            $engagement = $engagements->find((int) $document['engagement_id']);

            if ($engagement === null) {
                throw new HttpException(404, 'No such document.');
            }

            if ($clientSide && (int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
                throw new HttpException(404, 'No such document.');
            }
        }

        $delivered = in_array((string) $document['status'], ['delivered', 'acknowledged'], true);

        if ($clientSide && !$delivered) {
            throw new HttpException(404, 'No such document.');
        }

        return View::render('documents.show', [
            'title'      => (string) $document['title'],
            'user'       => $user,
            'tenant'     => $tenant,
            'document'   => $document,
            'engagement' => $engagement,
            'versions'   => DocumentService::versions($tenantId, (int) $document['id'], $clientSide),
            'links'      => $clientSide ? [] : DocumentService::shareLinks($tenantId, (int) $document['id']),
            'clientSide' => $clientSide,
        ]);
    }

    /** @param array<string,string> $params */
    public function createRequest(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        $this->requireFirmSide($user);
        Csrf::check($_POST);
        Policy::authorize($user, Policy::CREATE, 'document_request', $this->orgContext($engagement));

        $labels = $_POST['items'] ?? [];
        $items = [];

        foreach (is_array($labels) ? $labels : [] as $label) {
            $items[] = ['label' => (string) $label];
        }

        try {
            DocumentService::createRequest(
                (int) $tenant['id'],
                (int) $engagement['id'],
                (string) ($_POST['title'] ?? ''),
                $items,
                trim((string) ($_POST['due_on'] ?? '')) ?: null,
                (int) $user['id'],
                trim((string) ($_POST['note'] ?? '')) ?: null
            );
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/engagements/' . $engagement['id'] . '/documents'));
    }

    public function library(): string
    {
        [$tenant, $user] = $this->context();
        $this->requireFirmSide($user);
        Policy::authorize($user, Policy::READ, 'playbook_template');

        return View::render('documents.library', [
            'title'     => 'Document library',
            'user'      => $user,
            'tenant'    => $tenant,
            'documents' => DocumentService::library((int) $tenant['id'], (string) ($_GET['q'] ?? '')),
            'search'    => (string) ($_GET['q'] ?? ''),
            'usage'     => Storage::tenantUsage((int) $tenant['id']),
            'quota'     => Storage::TENANT_QUOTA_BYTES,
        ]);
    }

    // ------------------------------------------------------------- internals

    /** @return array<string,mixed> */
    private function orgContext(array $engagement): array
    {
        return ['client_org_id' => (int) $engagement['client_org_id']];
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
