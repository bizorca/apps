<?php
/**
 * Find Fathom's private files and load its config. First require on every page.
 *
 *   repo       fathom/public/        ->  fathom/includes, fathom/templates
 *   Cloudways  public_html/fathom/   ->  private_html/fathom/includes, .../templates
 *
 * Only this file knows the two layouts apart; everything after uses FM_ROOT.
 */

declare(strict_types=1);

foreach ([__DIR__ . '/..', __DIR__ . '/../../private_html/fathom'] as $dir) {
    if (is_file($dir . '/includes/config.php')) {
        define('FM_ROOT', realpath($dir));
        break;
    }
}

if (!defined('FM_ROOT')) {
    http_response_code(500);
    exit('Fathom configuration not found. Check the deploy.');
}

require_once FM_ROOT . '/includes/config.php';
