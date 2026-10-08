<?php
/**
 * Find Pilotage's private files and load the shared tools core. First require
 * of the front controller.
 *
 *   repo       pilotage/public/        ->  pilotage/{src,config,...}
 *   Cloudways  public_html/pilotage/   ->  private_html/pilotage/{src,config,...}
 *
 * Only this file knows the two layouts apart; everything after uses PL_ROOT.
 */

declare(strict_types=1);

// Included, never requested: asked for directly it is just another 404.
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/pilotage'] as $dir) {
    if (is_file($dir . '/src/Core/autoload.php')) {
        define('PL_ROOT', realpath($dir));
        break;
    }
}

if (!defined('PL_ROOT')) {
    http_response_code(500);
    exit('Pilotage configuration not found. Check the deploy.');
}

// The shared core sits beside this tool's folder on the server
// (private_html/includes) and under private_html/ in the repo.
foreach ([PL_ROOT . '/../includes', PL_ROOT . '/../private_html/includes'] as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        require_once $dir . '/bootstrap.php';
        break;
    }
}

if (!function_exists('tl_user')) {
    http_response_code(500);
    exit('Shared tools core not found. Check the deploy.');
}

require PL_ROOT . '/src/Core/autoload.php';
