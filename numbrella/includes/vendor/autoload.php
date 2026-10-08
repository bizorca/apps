<?php
/**
 * Vendored libraries (no Composer on the server). Copied from a scratch
 * `composer require dompdf/dompdf:^3.1` on 2026-10-08. Versions and licenses:
 *   dompdf/dompdf 3.1.6              (LGPL-2.1)  HTML -> PDF for the report download
 *   dompdf/php-font-lib 1.0.2        (LGPL-2.1)  required by dompdf
 *   dompdf/php-svg-lib 1.0.2         (LGPL-3.0)  required by dompdf
 *   masterminds/html5 2.11.0         (MIT)       required by dompdf
 *   sabberworm/php-css-parser 9.5.0  (MIT)       required by php-svg-lib (needs ext-iconv)
 * Each folder keeps its LICENSE file. Only src/ (plus dompdf's lib/ fonts and
 * resources) was copied; tests, docs and bin/ were left out.
 *
 * The original composer.json asked for dompdf ^2.0, which Composer now refuses
 * to install (ten security advisories); 3.1.6 is the patched line.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if ($class === 'Dompdf\\Cpdf') {
        require __DIR__ . '/dompdf/lib/Cpdf.php';
        return;
    }
    $map = [
        'Dompdf\\'          => __DIR__ . '/dompdf/src/',
        'FontLib\\'         => __DIR__ . '/php-font-lib/src/FontLib/',
        'Svg\\'             => __DIR__ . '/php-svg-lib/src/Svg/',
        'Masterminds\\'     => __DIR__ . '/html5/src/',
        'Sabberworm\\CSS\\' => __DIR__ . '/php-css-parser/src/',
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

// php-css-parser declares these as Composer "files" (they define functions or
// aliases that the class autoloader would never trigger).
require_once __DIR__ . '/php-css-parser/src/Rule/Rule.php';
require_once __DIR__ . '/php-css-parser/src/RuleSet/RuleContainer.php';
