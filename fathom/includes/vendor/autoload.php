<?php
/**
 * Vendored libraries (no Composer on the server). Versions and licenses:
 *   bacon/bacon-qr-code 3.1.1 (BSD-2-Clause)  QR codes for public boards/cards
 *   dasprid/enum 1.0.7      (BSD-2-Clause)  required by bacon
 *   erusev/parsedown 1.8.0  (MIT)           comment and description Markdown
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $map = [
        'BaconQrCode\\' => __DIR__ . '/bacon-qr-code/src/',
        'DASPRiD\\Enum\\' => __DIR__ . '/dasprid-enum/src/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

require_once __DIR__ . '/parsedown/Parsedown.php';
