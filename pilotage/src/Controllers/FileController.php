<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Services\DocumentService;
use Bizorca\Pilotage\Services\Storage;

/**
 * The ONLY path by which a stored byte reaches a browser (FR-7.5).
 *
 * There is no URL that maps to a file on disk. Every download re-resolves the
 * document, re-checks permission for the requesting user, and only then streams
 * bytes. That "re-checks per request" is the requirement — a link that worked
 * yesterday must stop working the moment access is withdrawn.
 *
 * Responses are always an attachment with a sniff-proof content type. Serving
 * user-uploaded content inline from our own origin is how a PDF or an image
 * becomes stored XSS.
 */
final class FileController
{
    /** @param array<string,string> $params */
    public function download(array $params): string
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'Not found.');
        }

        $tenantId = (int) $tenant['id'];
        $user = Session::user();

        if ($user === null) {
            redirect(url('/login?redirect=' . rawurlencode(pl_request_path())));
        }

        $document = DocumentService::find($tenantId, (int) ($params['id'] ?? 0));

        if ($document === null) {
            throw new HttpException(404, 'Not found.');
        }

        $clientSide = ($user['client_org_id'] ?? null) !== null;

        // Which version? Default to current; a specific one is coach-only.
        $versionId = isset($params['version']) ? (int) $params['version'] : null;

        if ($versionId !== null && $clientSide) {
            // A client gets the current version and nothing else. Prior
            // versions are the coach's working history.
            throw new HttpException(404, 'Not found.');
        }

        $versionId = $versionId ?? ($document['current_version_id'] === null ? null : (int) $document['current_version_id']);

        if ($versionId === null) {
            throw new HttpException(404, 'Not found.');
        }

        $version = DocumentService::version($tenantId, $versionId);

        if ($version === null || (int) $version['document_id'] !== (int) $document['id']) {
            throw new HttpException(404, 'Not found.');
        }

        $this->authorize($tenantId, $user, $document, $clientSide);

        DocumentService::logAccess(
            $tenantId,
            (int) $document['id'],
            $versionId,
            (int) $user['id'],
            'portal',
            \Bizorca\Pilotage\Core\ClientIp::resolve($_SERVER)
        );

        $this->stream($tenantId, $version);
    }

    /**
     * A third party opening a share link (FR-7.6). No session, no tenant
     * context — the token is the whole authorisation, which is why it expires,
     * can be capped, and logs every view.
     *
     * @param array<string,string> $params
     */
    public function shared(array $params): string
    {
        $resolved = DocumentService::resolveShareLink(
            (string) ($params['token'] ?? ''),
            \Bizorca\Pilotage\Core\ClientIp::resolve($_SERVER),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        if ($resolved === null) {
            throw new HttpException(404, 'That link has expired or is no longer valid.');
        }

        $this->stream((int) $resolved['document']['tenant_id'], $resolved['version']);
    }

    // ------------------------------------------------------------- internals

    /** @param array<string,mixed> $document */
    private function authorize(int $tenantId, array $user, array $document, bool $clientSide): void
    {
        $context = (string) $document['context'];

        if ($context === 'library') {
            Policy::authorize($user, Policy::READ, 'playbook_template');
            return;
        }

        $engagements = new EngagementRepository($tenantId);
        $engagement = $engagements->find((int) $document['engagement_id']);

        if ($engagement === null) {
            throw new HttpException(404, 'Not found.');
        }

        // A client-side user may only ever reach their own organization's files.
        if ($clientSide && (int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
            throw new HttpException(404, 'Not found.');
        }

        $policyContext = $engagements->policyContext($engagement) + [
            'delivered' => in_array((string) $document['status'], ['delivered', 'acknowledged'], true),
            'shared'    => in_array((string) $document['status'], ['delivered', 'acknowledged'], true),
        ];

        $objectType = $context === 'client_file' ? 'document_client_file' : 'document_deliverable';

        Policy::authorize($user, Policy::READ, $objectType, $policyContext);
    }

    /**
     * Stream the bytes.
     *
     * @param array<string,mixed> $version
     */
    private function stream(int $tenantId, array $version): never
    {
        $path = Storage::pathFor($tenantId, (string) $version['storage_key']);

        if (!is_file($path)) {
            // The row exists but the bytes do not. Do not say so — an attacker
            // learns nothing from a 404, and a user can only report it either way.
            error_log('Missing stored file for version ' . (int) $version['id']);
            http_response_code(404);
            exit;
        }

        $name = Storage::safeName((string) $version['original_name']);

        // Always an attachment, always octet-stream. Serving user-uploaded
        // content inline from our own origin turns an upload into stored XSS.
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        readfile($path);
        exit;
    }
}
