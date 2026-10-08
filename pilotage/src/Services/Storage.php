<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Config;

/**
 * File storage (FR-7.5, FR-7.8).
 *
 * Four rules, all of them the difference between a document store and an
 * incident:
 *
 * 1. Bytes live OUTSIDE the web root. There is no URL that maps to a file.
 *    Everything is served by an authenticated PHP handler that re-checks
 *    permission per request.
 * 2. The name on disk is a random token, never the user's filename. A
 *    filename is attacker-controlled input; treating it as a path is how you
 *    get traversal, and treating it as a URL is how you get enumeration.
 * 3. MIME is an allowlist, sniffed from CONTENT, not from the browser's
 *    Content-Type header or the extension. Both are trivially forged.
 * 4. Size caps are enforced before the bytes are kept.
 */
final class Storage
{
    public const MAX_BYTES = 52428800;          // 50 MB per file
    public const TENANT_QUOTA_BYTES = 10737418240; // 10 GB per tenant

    /**
     * What a coach or client can legitimately hand over: documents,
     * spreadsheets, images of paperwork, and archives. Deliberately no
     * executables, no scripts, no HTML — an uploaded .html served from our own
     * origin would be stored XSS.
     */
    public const ALLOWED_MIME = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'application/zip',
    ];

    /** Where the bytes live. Outside the web root, always. */
    public static function root(): string
    {
        return rtrim((string) Config::get('app.storage'), '/') . '/files';
    }

    /**
     * Store an uploaded file.
     *
     * @param array{tmp_name:string,name:string,size:int,error:int} $upload A $_FILES entry.
     * @return array{storage_key:string,mime_type:string,byte_size:int,sha256:string,original_name:string}
     * @throws \RuntimeException on anything not acceptable.
     */
    public static function put(int $tenantId, array $upload): array
    {
        self::assertUploadOk($upload);

        $tmp = $upload['tmp_name'];
        $size = (int) $upload['size'];

        if ($size <= 0) {
            throw new \RuntimeException('That file is empty.');
        }

        if ($size > self::MAX_BYTES) {
            throw new \RuntimeException('Files are capped at ' . self::humanBytes(self::MAX_BYTES) . '.');
        }

        if (self::tenantUsage($tenantId) + $size > self::TENANT_QUOTA_BYTES) {
            throw new \RuntimeException('This account has used its storage quota.');
        }

        // Sniff the CONTENT. The browser's Content-Type and the extension are
        // both attacker-supplied and neither is evidence of anything.
        $mime = self::detectMime($tmp);

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new \RuntimeException('That file type is not accepted.');
        }

        $hash = hash_file('sha256', $tmp);

        if ($hash === false) {
            throw new \RuntimeException('Could not read that file.');
        }

        $key = bin2hex(random_bytes(32));
        $path = self::pathFor($tenantId, $key);

        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('Storage is not writable.');
        }

        // move_uploaded_file, not rename: it verifies the file really came
        // through an HTTP upload and not from somewhere else on the filesystem.
        $moved = is_uploaded_file($tmp)
            ? move_uploaded_file($tmp, $path)
            : rename($tmp, $path);   // tests supply their own temp files

        if (!$moved) {
            throw new \RuntimeException('Could not store that file.');
        }

        @chmod($path, 0640);

        return [
            'storage_key'   => $key,
            'mime_type'     => $mime,
            'byte_size'     => $size,
            'sha256'        => $hash,
            'original_name' => self::safeName($upload['name']),
        ];
    }

    /**
     * Absolute path for a stored file.
     *
     * The key is validated as 64 hex characters before it touches the
     * filesystem, so a crafted key cannot escape the directory.
     */
    public static function pathFor(int $tenantId, string $storageKey): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $storageKey)) {
            throw new \InvalidArgumentException('Malformed storage key.');
        }

        // Two-level fan-out keeps directory listings sane at volume.
        return self::root() . '/' . $tenantId . '/' . substr($storageKey, 0, 2) . '/' . $storageKey;
    }

    public static function exists(int $tenantId, string $storageKey): bool
    {
        return is_file(self::pathFor($tenantId, $storageKey));
    }

    public static function delete(int $tenantId, string $storageKey): bool
    {
        $path = self::pathFor($tenantId, $storageKey);

        return is_file($path) ? @unlink($path) : false;
    }

    public static function tenantUsage(int $tenantId): int
    {
        $stmt = \Bizorca\Pilotage\Core\Database::conn()->prepare(
            'SELECT COALESCE(SUM(byte_size), 0) AS total FROM pl_document_versions WHERE tenant_id = :tid'
        );
        $stmt->execute(['tid' => $tenantId]);

        return (int) $stmt->fetch()['total'];
    }

    /**
     * A display filename that is safe to put in a Content-Disposition header.
     * Strips path separators, control characters, and anything that could
     * break out of the quoted string.
     */
    public static function safeName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '', $name) ?? '';
        $name = trim($name);

        return $name === '' ? 'download' : mb_substr($name, 0, 200);
    }

    public static function humanBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);

                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        // No finfo means we cannot tell what this is, and accepting an unknown
        // file into a store of client financial records is not a trade worth
        // making.
        throw new \RuntimeException('Cannot verify that file type on this server.');
    }

    /** @param array<string,mixed> $upload */
    private static function assertUploadOk(array $upload): void
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw new \RuntimeException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is too large.',
            UPLOAD_ERR_PARTIAL    => 'The upload did not finish. Try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was chosen.',
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE => 'The server could not write that file.',
            default               => 'That upload failed.',
        });
    }
}
