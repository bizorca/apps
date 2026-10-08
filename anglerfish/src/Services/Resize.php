<?php

namespace Anglerfish\Services;

/**
 * Substack-width derivatives of generated assets (SPEC §9.6).
 *
 * Images are generated at 2K — 2752×1536 at 16:9 — because the model renders
 * text more reliably at that size. Substack displays at 1456px, so pasting the
 * original means uploading roughly four times the pixels for no visible gain.
 * `images.substack_width` has been in config since the beginning and nothing
 * ever read it; this is the step that was missing.
 *
 * GD rather than PIL: this runs on the server, where the worker's Python venv
 * does not exist. GD 2.3.3 with JPEG and PNG is present on SiteGround.
 */
final class Resize
{
    /** Derivatives live beside the original so a deploy never sweeps them. */
    private const CACHE = 'assets/web';

    /**
     * Path to a width-limited copy, generating it once and caching it.
     *
     * Returns the original when it is already narrow enough, so a small source
     * is never re-encoded and made worse.
     */
    public static function forWeb(string $absolute, ?int $width = null): string
    {
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        $width ??= (int) ($cfg['images']['substack_width'] ?? 1456);

        if (!is_file($absolute)) {
            throw new \RuntimeException("No such image: $absolute");
        }
        $info = @getimagesize($absolute);
        if (!$info) {
            throw new \RuntimeException('Not a readable image: ' . basename($absolute));
        }
        [$w, $h, $type] = $info;
        if ($w <= $width) {
            return $absolute;
        }

        $dir = AF_STORAGE . '/' . self::CACHE;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Keyed on the source's mtime, so regenerating an asset in place
        // invalidates the derivative instead of serving a stale one.
        $ext = $type === IMAGETYPE_PNG ? 'png' : 'jpg';
        $out = sprintf('%s/%s-%d-%d.%s', $dir,
            pathinfo($absolute, PATHINFO_FILENAME), $width, filemtime($absolute), $ext);
        if (is_file($out)) {
            return $out;
        }

        $src = $type === IMAGETYPE_PNG
            ? @imagecreatefrompng($absolute)
            : @imagecreatefromjpeg($absolute);
        if (!$src) {
            throw new \RuntimeException('Could not decode ' . basename($absolute));
        }

        $height = (int) round($h * ($width / $w));
        $dst = imagecreatetruecolor($width, $height);
        if ($type === IMAGETYPE_PNG) {
            // Without this a transparent PNG downscales onto black.
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $w, $h);

        $type === IMAGETYPE_PNG
            ? imagepng($dst, $out, 6)
            : imagejpeg($dst, $out, (int) ($cfg['images']['jpeg_quality'] ?? 88));

        imagedestroy($src);
        imagedestroy($dst);
        return $out;
    }
}
