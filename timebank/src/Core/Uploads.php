<?php

declare(strict_types=1);

namespace TimeBank\Core;

/**
 * Avatars and offer images, stored outside the web root under
 * TM_UPLOADS/<kind>/<tenant id>/<file> and served by MediaController.
 *
 * Stored paths keep the original's shape, 'uploads/<kind>/<tenant>/<file>',
 * so rows imported from the old site resolve unchanged. The original wrote
 * into assets/uploads inside its web root and relied on an .htaccess rule to
 * stop PHP running there; this server ignores .htaccess.
 */
final class Uploads
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    public const KINDS = ['avatars', 'offers'];

    /**
     * @return string|null the stored path ('uploads/...'), null if no file was
     *                     sent, or an error message for the user.
     */
    public static function storeImage(?array $file, string $kind, int $tenantId, string $namePrefix, int $maxBytes): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return 'Image upload failed. Please try again.';
        }
        if ($file['size'] > $maxBytes) {
            return 'Image must be ' . (int) round($maxBytes / 1048576) . 'MB or smaller.';
        }

        // Type from the file's content, never from the client.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$mime])) {
            return 'Image must be a JPG, PNG, GIF, or WebP file.';
        }

        $dir = TM_UPLOADS . '/' . $kind . '/' . $tenantId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return 'Failed to save image. Please try again.';
        }

        $name = preg_replace('/[^0-9A-Za-z_]/', '', $namePrefix) . '_' . bin2hex(random_bytes(6)) . '.' . self::TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return 'Failed to save image. Please try again.';
        }

        return 'uploads/' . $kind . '/' . $tenantId . '/' . $name;
    }

    /** Absolute file path for a stored path in this community, or null. */
    public static function path(?string $stored, int $tenantId): ?string
    {
        if (!$stored || !preg_match('#^uploads/(avatars|offers)/(\d+)/([A-Za-z0-9_.-]+)$#', $stored, $m)) {
            return null;
        }
        if ((int) $m[2] !== $tenantId || str_contains($m[3], '..')) {
            return null;
        }
        return TM_UPLOADS . '/' . $m[1] . '/' . $m[2] . '/' . $m[3];
    }

    public static function delete(?string $stored, int $tenantId): void
    {
        $path = self::path($stored, $tenantId);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}
