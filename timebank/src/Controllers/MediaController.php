<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\Response;
use TimeBank\Core\Uploads;

/**
 * Uploaded images, from outside the web root, to members of the same
 * community: /<slug>/media/<avatars|offers>/<file>.
 */
class MediaController extends BaseController
{
    public function show(string $kind, string $file): void
    {
        $this->requireAuth();

        if (!in_array($kind, Uploads::KINDS, true)) {
            Response::notFound();
        }

        $tenantId = (int) $this->tenantId();
        $path     = Uploads::path('uploads/' . $kind . '/' . $tenantId . '/' . $file, $tenantId);
        if ($path === null || !is_file($path)) {
            Response::notFound();
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            Response::notFound();
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
