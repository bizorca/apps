<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;
use Dispatch\Core\Request;
use Dispatch\Models\Document;
use Dispatch\Models\Campaign;

class DocumentController extends BaseController
{
    private const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain', 'text/rtf',
        'application/rtf',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    public function index(array $params = []): void
    {
        $userId    = Auth::userId();
        $documents = Document::findForUser($userId);

        $own       = array_filter($documents, fn($d) => (int) $d['user_id'] === $userId);
        $templates = array_filter($documents, fn($d) => $d['is_template'] && (int) $d['user_id'] !== $userId);

        $this->render('documents/index', [
            'title'     => 'Document Library',
            'own'       => array_values($own),
            'templates' => array_values($templates),
        ]);
    }

    public function store(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/documents'); }

        $userId     = Auth::userId();
        $name       = trim(Request::post('name') ?? '');
        $description = trim(Request::post('description') ?? '');
        $content    = trim(Request::post('content') ?? '');
        $isTemplate = (bool) Request::post('is_template');
        $campaignId = Request::post('campaign_id') ? (int) Request::post('campaign_id') : null;

        // The original attached a document to whatever campaign id was posted,
        // so anyone could drop files onto someone else's campaign page.
        if ($campaignId !== null) {
            $campaign = Campaign::findById($campaignId);
            if (!$campaign || (int) $campaign['user_id'] !== $userId) {
                $this->notFound();
            }
        }
        $returnTo   = $campaignId ? '/campaigns/' . $campaignId : '/documents';

        if ($name === '') {
            $this->flash('error', 'Document name is required.');
            $this->redirect($returnTo);
        }

        $filePath = null;
        $fileName = null;
        $fileSize = null;
        $mimeType = null;

        if (Request::hasFile('document_file')) {
            $file = Request::file('document_file');
            $result = $this->handleUpload($file);
            if ($result === null) {
                $this->flash('error', 'File type not allowed or file exceeds 10 MB limit.');
                $this->redirect($returnTo);
            }
            [$filePath, $fileName, $fileSize, $mimeType] = $result;
        }

        if ($filePath === null && $content === '') {
            $this->flash('error', 'Provide either a file or text content.');
            $this->redirect($returnTo);
        }

        Document::create([
            'user_id'     => $userId,
            'campaign_id' => $campaignId,
            'name'        => $name,
            'description' => $description ?: null,
            'content'     => $content ?: null,
            'file_path'   => $filePath,
            'file_name'   => $fileName,
            'file_size'   => $fileSize,
            'mime_type'   => $mimeType,
            'is_template' => $isTemplate,
        ]);

        $this->flash('success', 'Document saved.');
        $this->redirect($returnTo);
    }

    public function download(array $params = []): void
    {
        $doc = $this->resolveDoc((int) ($params['id'] ?? 0));

        if ($doc['file_path'] === null) {
            // Text document — show inline
            $this->redirect('/documents/' . $doc['id'] . '/view');
        }

        $fullPath = DP_UPLOADS . '/' . basename($doc['file_path']);
        if (!is_file($fullPath)) {
            $this->notFound();
        }

        // RFC 6266: an ASCII fallback plus the real name UTF-8 encoded. The
        // original put the percent-encoded name in filename=, so downloads were
        // saved as "Intro%20to%20timebanking.docx".
        $ascii = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) $doc['file_name']);
        header('Content-Type: ' . ($doc['mime_type'] ?? 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode((string) $doc['file_name']));
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, no-cache');
        readfile($fullPath);
        exit;
    }

    public function view(array $params = []): void
    {
        $doc = $this->resolveDoc((int) ($params['id'] ?? 0));

        $this->render('documents/view', [
            'title' => $doc['name'],
            'doc'   => $doc,
        ]);
    }

    public function toggleTemplate(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/documents'); }
        $doc = $this->resolveDoc((int) ($params['id'] ?? 0), ownerOnly: true);
        Document::toggleTemplate((int) $doc['id']);
        $label = $doc['is_template'] ? 'removed from templates' : 'added to template library';
        $this->flash('success', "Document {$label}.");
        $this->redirect('/documents');
    }

    public function destroy(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/documents'); }
        $doc = $this->resolveDoc((int) ($params['id'] ?? 0), ownerOnly: true);

        // Delete file from disk if present
        if ($doc['file_path']) {
            $fullPath = DP_UPLOADS . '/' . basename($doc['file_path']);
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }

        Document::delete((int) $doc['id']);
        $this->flash('success', 'Document deleted.');

        $returnTo = $doc['campaign_id'] ? '/campaigns/' . $doc['campaign_id'] : '/documents';
        $this->redirect($returnTo);
    }

    // -------------------------------------------------------

    private function resolveDoc(int $id, bool $ownerOnly = false): array
    {
        $userId = Auth::userId();
        $doc    = Document::findById($id);

        if (!$doc) $this->notFound();

        // Access: own doc, or template (readable by anyone), or owner-only action
        $isOwner = (int) $doc['user_id'] === $userId;
        if ($ownerOnly && !$isOwner) $this->notFound();
        if (!$ownerOnly && !$isOwner && !$doc['is_template']) $this->notFound();

        return $doc;
    }

    /** @return array{string,string,int,string}|null [filePath, fileName, fileSize, mimeType] */
    private function handleUpload(array $file): ?array
    {
        if ($file['size'] > self::MAX_BYTES) return null;

        // Verify MIME via finfo
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME, true)) return null;

        // The extension came straight from the client's filename; keep it only
        // if it is plain alphanumerics (storage is outside the web root, so this
        // is tidiness, not the only line of defence).
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ext      = preg_match('/^[a-z0-9]{1,8}$/', $ext) ? $ext : 'bin';
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $stored   = time() . '_' . Auth::userId() . '_' . $safeBase . '.' . $ext;
        $destDir  = DP_UPLOADS . '/';

        if (!is_dir($destDir)) mkdir($destDir, 0775, true);

        if (!move_uploaded_file($file['tmp_name'], $destDir . $stored)) return null;

        return [$stored, $file['name'], $file['size'], $mimeType];
    }
}
